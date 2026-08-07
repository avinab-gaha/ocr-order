# OCR Order Ingestion — Laravel 12 + FastAPI/PaddleOCR + LLM

Upload a scanned invoice/purchase order → the OCR service preprocesses the
image with OpenCV (grayscale, denoise, binarize, deskew) → PaddleOCR
extracts the text → an LLM (OpenAI, Gemini, or a local Ollama model) maps
it into structured data → Laravel automatically creates `order_masters` +
`order_details` rows and returns a JSON preview.

```
┌────────────┐   file    ┌──────────────────────────┐
│   Client   │ ────────► │  FastAPI + PaddleOCR     │
│ (curl/app) │           │  (with OpenCV preproc)   │
└────────────┘           └────────┬─────────────────┘
                                  │   text
                                  ▼
                         ┌──────────────────┐
                         │  Laravel 12 API  │
                         │ /api/orders/...  │
                         └────────┬─────────┘
                                  │   JSON text
                                  ▼
                         ┌──────────────────┐
                         │ LLM mapper        │  OpenAI / Gemini / Ollama
                         │ (pluggable)        │  -> structured JSON
                         └────────┬───────────┘
                                  ▼
                     order_masters + order_details
                        (auto-saved, status=pending)
```

## Project layout

```
ocr-order-project/
├── docker-compose.yml       # orchestrates ocr-service + mysql + laravel-app
├── ocr-service/              # FastAPI + PaddleOCR + OpenCV preprocessing
│   ├── main.py               # API + preprocessing + OCR logic
│   ├── requirements.txt
│   └── Dockerfile
└── laravel-app/               # Laravel 12 application
    ├── app/Http/Controllers/Api/OrderUploadController.php  # upload, list, show, edit master, confirm, reopen
    ├── app/Http/Controllers/Api/OrderDetailController.php  # add/edit/delete line items
    ├── app/Http/Requests/                                  # Upload + Update*Request validation
    ├── app/Http/Resources/{OrderMasterResource,OrderDetailResource}.php
    ├── app/Models/{OrderMaster,OrderDetail}.php
    ├── app/Services/OcrClient.php               # talks to FastAPI
    ├── app/Services/OrderIngestionService.php   # orchestrates the pipeline
    ├── app/Services/Llm/                        # OpenAI/Gemini/Ollama mappers
    ├── resources/prompts/order_extraction.md    # editable rules + few-shot examples
    ├── database/migrations/..._create_order_masters_table.php
    ├── database/migrations/..._create_order_details_table.php
    ├── routes/api.php
    ├── config/services.php   # OCR + LLM config
    ├── tests/Feature/{OrderUploadTest,OrderEditingTest}.php
    └── Dockerfile
```

## What was already verified in this sandbox

- All PHP files pass `php -l` (syntax-checked, no errors).
- The FastAPI service was smoke-tested end-to-end (health check, rejected
  file types, and the full image → OCR-lines → text pipeline using a
  stubbed OCR engine so it didn't need to download PaddleOCR's model
  weights here).
- **Not run here:** `composer install` and PaddleOCR's real model
  inference, because this sandbox's network egress doesn't include
  `packagist.org` or PaddleOCR's model-weight CDN. Both work normally
  on a machine with full internet access — see setup steps below.

## 0. Image preprocessing with OpenCV (built into the OCR service)

Before PaddleOCR runs, every image is preprocessed by OpenCV directly inside
the `ocr-service` (`main.py:preprocess_image()`):

1. **Resizes** if the longest side exceeds `PREPROCESS_MAX_SIZE` (default 4000px).
2. **Converts to grayscale** (reduces noise, better text detection).
3. **Denoises** via `fastNlMeansDenoising` (removes scan artifacts).
4. **Binarizes** via adaptive Gaussian thresholding (clear black-on-white text).
5. **Deskews** via `minAreaRect` (corrects rotation up to ~5°).

Each step can be toggled via environment variables on the OCR service:

| Variable | Default | Description |
|---|---|---|
| `PREPROCESS_ENABLED` | `true` | Set to `false` to disable entirely |
| `PREPROCESS_GRAYSCALE` | `true` | Grayscale conversion |
| `PREPROCESS_DENOISE` | `true` | Denoising |
| `PREPROCESS_BINARIZE` | `true` | Adaptive thresholding |
| `PREPROCESS_DESKEW` | `true` | Rotation correction |
| `PREPROCESS_MAX_SIZE` | `4000` | Max longest side in pixels |

To disable preprocessing:

```bash
PREPROCESS_ENABLED=false uvicorn main:app --reload --port 8000
```

## 1. Run the OCR service (FastAPI + PaddleOCR + OpenCV)

```bash
cd ocr-service
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8000
```

Or with Docker:

```bash
docker build -t ocr-service ./ocr-service
docker run -p 8000:8000 ocr-service
```

First request will download PaddleOCR's detection/recognition/angle-cls
model weights (a few hundred MB) — this needs outbound internet access
once, then they're cached.

Test it directly:

```bash
curl -X POST http://localhost:8000/ocr -F "file=@/path/to/invoice.jpg"
```

## 2. Set up the Laravel 12 app

The `laravel-app/` folder here contains only the **custom application
code** for this feature (models, controllers, services, migrations,
config) layered on top of a standard `laravel/laravel` skeleton — the
`vendor/` folder is intentionally not included (never ship that in
source control).

```bash
cd laravel-app
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```ini
DB_CONNECTION=sqlite   # or mysql, see docker-compose.yml for mysql config

OCR_SERVICE_URL=http://localhost:8000

LLM_PROVIDER=openai        # openai | gemini | ollama
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini

# Only needed if you switch LLM_PROVIDER
GEMINI_API_KEY=
OLLAMA_BASE_URL=http://localhost:11434
```

If using SQLite, create the file first: `touch database/database.sqlite`

```bash
php artisan migrate
php artisan serve
```

## 3. Upload a document and get the auto-generated preview

```bash
curl -X POST http://localhost:8000/api/orders/upload \
  -F "file=@/path/to/invoice.jpg" \
  -F "llm_provider=openai"
```

Response (`201 Created`):

```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "order_number": "INV-2001",
    "vendor_name": "Acme Supplies",
    "customer_name": null,
    "currency": "USD",
    "total_amount": 20.0,
    "llm_provider": "openai",
    "original_filename": "invoice.jpg",
    "notes": null,
    "items": [
      {
        "id": 1,
        "line_no": 1,
        "item_name": "Widget",
        "item_code": null,
        "quantity": 2,
        "unit": "pcs",
        "unit_price": 10.0
      }
    ],
    "created_at": "2026-07-09T12:00:00+00:00"
  }
}
```

Other endpoints:

| Method | Path                                | Purpose                                              |
|--------|-------------------------------------|--------------------------------------------------------|
| GET    | `/api/orders`                       | Paginated list of ingested orders                     |
| GET    | `/api/orders/{id}`                  | Fetch one order + its line items                      |
| PATCH  | `/api/orders/{id}`                  | Correct misread header fields (vendor, date, etc.)    |
| POST   | `/api/orders/{id}/items`            | Add a line item OCR/LLM missed                        |
| PATCH  | `/api/orders/{id}/items/{item}`     | Correct a misread line item                           |
| DELETE | `/api/orders/{id}/items/{item}`     | Remove a hallucinated/duplicate line item              |
| POST   | `/api/orders/{id}/recalculate-total`| Re-sum `total_amount` from current line items          |
| POST   | `/api/orders/{id}/confirm`          | Mark a previewed order as `confirmed`                  |
| POST   | `/api/orders/{id}/reopen`           | Move a `confirmed` order back to `pending` for editing |

`status` starts as `pending` (auto-created preview) so a human can
review/edit the parsed data in your frontend before calling `confirm`;
it becomes `failed` if OCR or the LLM step throws, with the error saved
in `notes` and the raw OCR text (if captured) kept for debugging.

### Correcting OCR/LLM mistakes

The PATCH/POST/DELETE endpoints above exist specifically for the case in
the prompt: sometimes text gets misread. Nothing in an OCR+LLM pipeline
guarantees perfect extraction, so the workflow is: upload → review the
JSON preview → fix anything wrong via these endpoints → `confirm`.

```bash
# Fix a misread vendor name / order number
curl -X PATCH http://localhost:8000/api/orders/1 \
  -H "Content-Type: application/json" \
  -d '{"vendor_name": "Acme Supplies", "order_number": "INV-2001"}'

# Fix a misread line item (qty, unit_price, etc.)
curl -X PATCH http://localhost:8000/api/orders/1/items/3 \
  -H "Content-Type: application/json" \
  -d '{"quantity": 3}'

# Add an item OCR missed entirely
curl -X POST http://localhost:8000/api/orders/1/items \
  -H "Content-Type: application/json" \
  -d '{"item_name": "Gadget", "quantity": 1, "unit_price": 5.00}'

# Once the total looks right relative to corrected items:
curl -X POST http://localhost:8000/api/orders/1/recalculate-total
curl -X POST http://localhost:8000/api/orders/1/confirm
```

Editing is blocked (`409 Conflict`) once an order is `confirmed` — call
`POST /api/orders/{id}/reopen` first if you need to fix something after
the fact.

## 4. Run everything with Docker Compose

```bash
docker compose up --build
```

This starts `ocr-service` (port 8000), `mysql` (port 3306), and
`laravel-app` (port 8080). The Laravel container's `Dockerfile` runs
`composer install` at build time and `php artisan migrate` at startup —
both need real internet access from wherever you run `docker compose`.

## 5. Switching LLM providers

Set `LLM_PROVIDER` in `.env`, or pass `llm_provider` per-request in the
upload form (`openai`, `gemini`, or `ollama`) to override the default.
All three implement the same `LlmMapperInterface` and share one prompt
(`app/Services/Llm/AbstractLlmMapper.php`) so the extraction schema
stays identical regardless of provider — swap providers without
touching any downstream code.

For Ollama, pull a model first: `ollama pull llama3.1`

### Tuning extraction behaviour

The extraction rules, JSON schema, and few-shot examples sent as the
system prompt live in `resources/prompts/order_extraction.md` — a plain
Markdown file, not hardcoded PHP. Edit it directly to:

- add more few-shot examples (this is usually the highest-leverage way
  to fix systematic misreads for your specific document layouts)
- tighten/loosen field rules (date formats, currency inference, how to
  handle multiple totals, etc.)
- add rules for a new document type

All three providers (`AbstractLlmMapper::systemPrompt()`) load this same
file, so changes apply everywhere at once. Point `LLM_PROMPT_PATH` in
`.env` at a different file if you want to swap prompts per environment
or document type without touching code.

## 6. Running the Laravel test suite

```bash
cd laravel-app
php artisan test
```

`tests/Feature/OrderUploadTest.php` fakes both the OCR service and the
OpenAI HTTP calls, so it runs fully offline and verifies the whole
upload → OCR → LLM-map → `order_masters`/`order_details` pipeline.

`tests/Feature/OrderEditingTest.php` covers the correction workflow:
patching master fields, adding/editing/deleting line items, the
recalculate-total action, and the confirmed→edit guard (409 until you
`reopen`).

## Notes & things to double-check before production

- The LLM API request shapes (OpenAI `chat/completions`, Gemini
  `generateContent`, Ollama `/api/chat`) reflect each provider's
  documented format as of early 2026 — check current docs if a call
  starts failing, since these APIs do evolve.
- Add authentication/rate-limiting to the upload endpoint before
  exposing it publicly — it's wide open in this scaffold.
- `total_amount` is used from the LLM output when present, otherwise
  recalculated as the sum of line-item amounts.
- PaddleOCR defaults to English (`OCR_LANG=en` env var on the OCR
  service); pass e.g. `ch` for Chinese or another supported PaddleOCR
  language code as needed.
