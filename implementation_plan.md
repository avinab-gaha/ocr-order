# Document Extraction Feature Implementation Plan

> **Note**: Image preprocessing (sections marked with 🖼️) was added directly
> to the **ocr-service** FastAPI microservice, not the Laravel app. This keeps
> the entire image processing pipeline in Python where OpenCV is native.

## 🖼️ OpenCV Image Preprocessing (ocr-service)

### [MODIFY] [ocr-service/main.py](file:///f:/my_works/ocr-order-project/ocr-service/main.py)
Added `preprocess_image()` function that runs before PaddleOCR:
1. **Resize** if longest side > `PREPROCESS_MAX_SIZE` (default 4000).
2. **Grayscale** — reduces noise, improves text detection.
3. **Denoise** (`fastNlMeansDenoising`, h=30) — removes scan artifacts.
4. **Binarize** (adaptive Gaussian threshold, 31-block, 2-subtract) — clear black-on-white.
5. **Deskew** (`minAreaRect` angle detection, up to ~5°) — corrects rotation.

Each step can be toggled via env vars:

| Variable | Default | Description |
|---|---|---|
| `PREPROCESS_ENABLED` | `true` | Set to `false` to disable entirely |
| `PREPROCESS_GRAYSCALE` | `true` | Grayscale conversion |
| `PREPROCESS_DENOISE` | `true` | Denoising |
| `PREPROCESS_BINARIZE` | `true` | Adaptive thresholding |
| `PREPROCESS_DESKEW` | `true` | Rotation correction |
| `PREPROCESS_MAX_SIZE` | `4000` | Max longest side in pixels |

### [MODIFY] [ocr-service/requirements.txt](file:///f:/my_works/ocr-order-project/ocr-service/requirements.txt)
Added `opencv-python-headless==4.10.0.84` as an explicit dependency.

---

# Document Extraction Feature Implementation Plan

We will implement a clean, database-independent document extraction dashboard. The user will upload a document (JPG, JPEG, PNG, or PDF), the application will call the PaddleOCR microservice to get raw text, send that text to the chosen LLM provider (Gemini/OpenAI/Ollama), repair any invalid JSON returned by the AI, and display the preview, extracted text, pretty JSON, and any missing fields.

## User Review Required

> [!IMPORTANT]
> - **Database Bypass**: In accordance with the requirements ("Do not save to the database yet"), this feature runs the OCR + LLM pipeline and returns the extracted payload directly in the response without creating `OrderMaster` or `OrderDetail` rows.
> - **Temporary File Preview**: To preview the document after upload, the backend will temporarily save files in `public/storage/temp/` and return a public asset URL. Alternatively, client-side JS can preview the selected file using `URL.createObjectURL` immediately. We will use both for optimal user experience.

## Open Questions

- *Are there any additional fields that should be flagged as required?*
  By default, we will consider the following required:
  - `master.vendor_name`
  - `master.quotation_date`
  - `master.currency`
  - `master.total_amount`
  - `items.*.item_name`
  - `items.*.quantity`
  - `items.*.unit_price`

> **Removed fields**: `order_date` (master) and `subtotal` (items) were removed from the schema. Use `quotation_date` for dates and `amount` for line-item totals going forward.

---

## Proposed Changes

### Configuration & Routing

#### [MODIFY] [routes/web.php](file:///f:/my_works/ocr-order-project/laravel-app/routes/web.php)
Register the new routes for the extraction dashboard:
- `GET /`: Renders the document extraction page (`DocumentExtractionController@index`).
- `POST /extract`: Handles the file upload, runs OCR + LLM, repairs JSON, checks missing fields, and returns the response (`DocumentExtractionController@extract`).

---

### Request & Controller Layer

#### [NEW] [ExtractDocumentRequest.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Http/Requests/ExtractDocumentRequest.php)
Validate the input parameters:
- `file`: Required, must be a file of type `jpg, jpeg, png, pdf`, max size 10MB.
- `llm_provider`: Optional string, must be one of: `openai`, `gemini`, `ollama`.

#### [NEW] [DocumentExtractionController.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Http/Controllers/DocumentExtractionController.php)
Define the controller:
- `index()`: Return the dashboard blade view.
- `extract(ExtractDocumentRequest $request)`: Call the `DocumentExtractionService` to process the document and return a JSON response.

---

### Service Layer & AI Logic

#### [NEW] [JsonRepairHelper.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Services/Llm/JsonRepairHelper.php)
Provide robust JSON healing capabilities:
- Clean markdown fences.
- Clean trailing commas in objects and arrays.
- Balance braces/brackets at the end of truncated responses.
- Replace single quotes around keys/values with double quotes.

#### [MODIFY] [AbstractLlmMapper.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Services/Llm/AbstractLlmMapper.php)
Update `parseJsonResponse()` to attempt automatic JSON repair using `JsonRepairHelper::repair()` if the initial `json_decode()` fails, and log the event.

#### [MODIFY] [DocumentExtractionService.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Services/DocumentExtractionService.php)
Orchestrate the extraction pipeline:
1. Store file in a temporary folder (`public/storage/temp/`) to generate a public preview URL.
2. Call `OcrClient` to extract raw OCR text.
3. Call `DocumentValidator` to validate the OCR text is order-related; if not, throw RuntimeException.
4. Call the `LlmMapper` (retrieved via `LlmMapperFactory`) to map the text to structured JSON.
5. If JSON decoding fails, apply `JsonRepairHelper::repair()`.
6. Audit the resulting array for missing required fields (e.g. `vendor_name`, `total_amount`, and item fields).
7. Return a payload containing:
   - `preview_url`: URL of the uploaded document.
   - `raw_ocr_text`: Extracted text.
   - `extracted_data`: The repaired, valid JSON object.
   - `missing_fields`: Array of human-readable warnings for empty required fields.

#### [MODIFY] [OrderIngestionService.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Services/OrderIngestionService.php)
Orchestrate the API ingestion pipeline:
1. Store file in private storage (`storage/app/private/order-uploads/`).
2. Call `OcrClient` to extract raw OCR text.
3. Call `DocumentValidator` to validate OCR text is order-related; if not, throw before creating DB record.
4. Create `OrderMaster`, call `LlmMapper`, persist results in DB transaction.
5. On failure: if order record exists, set status to `failed`; otherwise re-throw.

#### [NEW] [DocumentValidator.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Services/DocumentValidator.php)
Rule-based validator that scores OCR text against order-related keywords and patterns:

- **Keywords** (English): invoice, purchase order, receipt, bill, quotation, total, customer, vendor, amount, price, quantity, unit price, tax, subtotal, etc.
- **Keywords** (Japanese): 請求書, 納品書, 見積書, 注文書, 領収書, 合計金額, 消費税, 伝票番号, etc.
- **Unit keywords** (Japanese): 時間, 個, 回, 人, 枚, セット, 式 (time, pieces, times, people, sheets, set, lot)
- **Order code pattern**: must start with `ORD` followed by digits (e.g. `ORD-001`, `ORD12345`) — separate dedicated pattern in addition to the combined invoice/PO/ORD pattern
- **Patterns**: currency amounts (`$100`, `¥1,000`), invoice/PO/order numbers (`INV-001`, `ORD-001`), dates, quantity patterns (`2 x 10`), total/subtotal lines
- **Scoring**: each keyword match = +1, each pattern match = +2. Minimum threshold: 3 (configurable via `DOCUMENT_VALIDATOR_MIN_SCORE` env var).
- **On failure**: throws `RuntimeException` with message *"The uploaded image doesn't appear to be a supported order image/document"*

```php
// Example: passes validation (score 7)
"INV-2001 | Acme Supplies | $20.00 | 2026-07-09"
→ inv (+1) + INV-2001 pattern (+2) + $20.00 pattern (+2) + date pattern (+2) = 7 >= 3 ✓

// Example: fails validation (score 0)
"A beautiful sunset over the mountains"
→ no keywords or patterns match = 0 < 3 ✗ → 422 error
```

#### [MODIFY] [OrderUploadController.php](file:///f:/my_works/ocr-order-project/laravel-app/app/Http/Controllers/Api/OrderUploadController.php)
Add `RuntimeException` catch around `upload()`: if `DocumentValidator` rejects the image, return 422 with the validation error message (consistent with the web dashboard behavior).

---

### Frontend Presentation

#### [NEW] [extraction.blade.php](file:///f:/my_works/ocr-order-project/laravel-app/resources/views/extraction.blade.php)
Create a modern, premium dashboard with Bootstrap 5, custom CSS styling, and jQuery:
- **Rich Aesthetics**: HSL color themes, subtle gradients, soft shadows, hover transitions, and dark-accented UI card layouts.
- **Split-Pane Layout**:
  - *Left Pane*: Upload area (drag-and-drop zone) and original document preview (handling images and PDF embed).
  - *Right Pane*: Tabbed view for extraction results:
    1. **Overview / Alerts**: Display status badge, processing stats, and missing required fields warnings.
    2. **Pretty JSON**: Interactive JSON viewer with syntax highlighting and copy-to-clipboard button.
    3. **Extracted OCR Text**: Plaintext representation of raw OCR lines.
- **Interactive UI**: Real-time validation, progress bar/spinner animation while processing, and error handling alert banners.

---

## Verification Plan

### Automated Tests

#### [NEW] [DocumentExtractionTest.php](file:///f:/my_works/ocr-order-project/laravel-app/tests/Feature/DocumentExtractionTest.php)
Write feature tests to verify:
- Dashboard route returns HTTP 200.
- Upload validation rejects invalid mime-types or large files.
- Successful extraction returns correct JSON structure, preview URL, and raw text (mocking OCR and LLM).
- Truncated/broken LLM JSON response is automatically fixed and returned successfully.
- Missing required fields are correctly identified and returned in the warnings list.
- Non-order document (random text without keywords) returns 422 with validation error message.
- Empty OCR text returns 422 with validation error message.
- Japanese order document (請求書, 合計金額) passes validation and returns correct extraction.

#### [MODIFY] [OrderUploadTest.php](file:///f:/my_works/ocr-order-project/laravel-app/tests/Feature/OrderUploadTest.php)
Add test:
- Non-order document upload returns 422 with validation error message.

### Manual Verification
- Deploy local dev servers and perform manual file uploads using sample documents.
- Upload a non-order image (e.g. a photo, random text) and confirm 422 error is returned.
- Review CSS styling responsiveness and animations in the browser.
