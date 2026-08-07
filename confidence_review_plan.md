# Confidence-Aware Extraction, Review UI & Confirm-to-Database

We will extend the OCR + LLM pipeline so the LLM reports a **confidence
level for every field it extracts**, surface low-confidence fields in
the extraction dashboard for a human to check/correct, and add a
**"Confirm & Save"** action that persists the reviewed result into
`order_masters` / `order_details`. This bridges the existing
preview-only dashboard (`/`, `/extract` — no DB writes) with the
already-persisted ingestion flow (`/api/orders/upload`), without
re-running OCR/LLM a second time.

## User Review Required

> [!IMPORTANT]
> - **New DB writes from the dashboard**: today `/extract` never touches
>   the database. This plan adds `POST /extract/confirm`, which *does*
>   create `order_masters`/`order_details` rows — the first time the
>   preview dashboard persists anything.
> - **Confidence threshold**: fields below **"medium"** confidence
>   (i.e. only `"low"`) are flagged for review by default. Configurable
>   via `config('services.llm.review_threshold')`.
> - **No second LLM call on confirm**: `/extract/confirm` accepts the
>   (possibly human-edited) payload the dashboard already has in memory
>   and saves it directly — it does not re-run OCR or the LLM.
> - **New order status**: `flagged` is added alongside
>   `pending`/`confirmed`/`failed`, set automatically when any field is
>   below the confidence threshold.

## Open Questions

- *Should OCR-level confidence (PaddleOCR's per-line scores, currently
  discarded) also factor into flagging, or is LLM self-reported
  confidence enough for v1?*
  Default: **LLM confidence only for v1** — OCR confidence is a
  reasonable fast-follow once this ships, cross-referencing both would
  reduce false positives/negatives further.
- *Can a human edit a low-confidence field inline before confirming, or
  only view + approve as-is?*
  Default: **inline edit** — the same input the field is displayed in
  doubles as the correction field, no separate edit mode.
- *Three confidence buckets (`high`/`medium`/`low`) or a numeric score?*
  Default: **three buckets**. LLMs self-assess coarse categories more
  reliably than calibrated 0–1 probabilities.

---

## Proposed Changes

### Prompt & Schema

#### [MODIFY] `resources/prompts/order_extraction.md`
Change every leaf field in the JSON schema from a bare scalar to an
object: `{"value": ..., "confidence": "high"|"medium"|"low"}`. Add a
rule: *"Mark confidence 'low' whenever the OCR text was ambiguous,
contradictory, or you had to infer rather than directly read a value."*
Update both existing few-shot examples to the new shape so the model
sees the pattern demonstrated, not just described.

---

### Service Layer

#### [MODIFY] `app/Services/Llm/AbstractLlmMapper.php`
After JSON decode (+ `JsonRepairHelper` fallback if needed), add an
`unwrapConfidence()` step that:
- flattens `{value, confidence}` pairs back into plain values (so
  downstream code — `OrderIngestionService`, `DocumentExtractionService`
  — doesn't need to know about the wrapper shape)
- builds a parallel `field_confidence` map (dot-path → confidence, e.g.
  `"master.quotation_date" => "low"`)
- builds a `low_confidence_fields` list (dot-paths at/below the
  configured threshold)

Both get attached to the returned array (`$decoded['field_confidence']`,
`$decoded['low_confidence_fields']`), same pattern as the existing
`raw_response` key.

#### [MODIFY] `app/Services/OrderIngestionService.php`
- Persist `field_confidence` into a new `order_masters.field_confidence`
  JSON column.
- If `low_confidence_fields` is non-empty, set `status = 'flagged'`
  instead of `'pending'`.

#### [MODIFY] `app/Services/DocumentExtractionService.php`
Include `field_confidence` and `low_confidence_fields` in the preview
payload returned to the dashboard, alongside the existing
`missing_fields`.

#### [NEW] `app/Services/OrderConfirmationService.php`
Takes the payload the dashboard already has (extracted data, possibly
edited by the reviewer) and persists it as `order_masters` +
`order_details` in a DB transaction — reusing the same field-mapping
logic `OrderIngestionService` uses, but skipping OCR/LLM entirely since
that already ran. Sets `status = 'confirmed'` directly (the human just
reviewed it).

---

### Database

#### [NEW migration] `add_field_confidence_and_flagged_status_to_order_masters_table.php`
- Add `field_confidence` (`json`, nullable) after `llm_raw_response`.
- Expand the `status` enum to include `flagged`.

*(Following the project's existing convention of additive migrations —
see `2026_07_16_000001_add_llm_raw_text_...` — rather than editing the
original `order_masters` migration.)*

---

### Request / Controller / Routes

#### [NEW] `app/Http/Requests/ConfirmExtractionRequest.php`
Validates the payload sent back from the dashboard: `master.*` fields
(nullable, typed per field) and `items` (array of objects with
`item_name` required, numeric quantity/unit_price).

#### [MODIFY] `app/Http/Controllers/DocumentExtractionController.php`
Add `confirm(ConfirmExtractionRequest $request)`: calls
`OrderConfirmationService::confirm(...)`, returns the created
`OrderMaster` via the existing `OrderMasterResource` (so the response
shape matches `/api/orders/{id}` for consistency).

#### [MODIFY] `routes/web.php`
Add `POST /extract/confirm`.

---

### Frontend

#### [MODIFY] `resources/views/extraction.blade.php`
- **Overview tab**: render `master` fields and each item's fields as
  labeled inputs (not just static text) — pre-filled with the extracted
  value. Fields in `low_confidence_fields` get an amber outline
  (reusing the existing `--mark` token already used for warnings) and a
  small "AI wasn't sure about this" note, so the reviewer's eye goes
  straight to what needs checking rather than re-reading everything.
- **Confirm & Save button**: appears once extraction succeeds. On click,
  collects current input values (including any edits) into the
  `master`/`items` shape and `POST`s to `/extract/confirm`.
- **Success state**: on confirm, show a confirmation badge with a link
  to `/api/orders/{id}` (or a future `/orders/{id}` view) so the saved
  record is easy to find, and disable further edits on that result
  (matches the "confirmed = locked" rule already enforced by
  `OrderMaster::assertEditable()` in the API).

---

## Verification Plan

### Automated Tests

#### [NEW] `tests/Feature/ConfidenceExtractionTest.php`
- A mocked LLM response with mixed confidence levels → `field_confidence`
  and `low_confidence_fields` are computed correctly.
- `OrderIngestionService` sets `status = 'flagged'` when any field is
  low confidence, `'pending'` when none are.
- `unwrapConfidence()` correctly flattens `{value, confidence}` back to
  plain values regardless of confidence level (i.e. the rest of the
  pipeline never sees the wrapper shape).

#### [NEW] `tests/Feature/ExtractionConfirmationTest.php`
- `POST /extract/confirm` with a valid (possibly edited) payload creates
  exactly one `order_masters` row and the matching `order_details` rows,
  with `status = 'confirmed'`.
- Malformed/missing-required-field payloads are rejected with 422.
- Confirms field_confidence from the original extraction is preserved
  on the saved record (for later audit), even if the reviewer corrected
  the value.

### Manual Verification
- Upload a document with a genuinely ambiguous field (smudged date,
  handwritten total) and confirm the LLM marks it `low` confidence.
- Confirm the dashboard visually flags it, allows inline correction, and
  that **Confirm & Save** produces a real, queryable `order_masters` row
  matching what was shown on screen.
- Upload a clean, unambiguous document and confirm nothing gets flagged
  and the save still works end-to-end.
