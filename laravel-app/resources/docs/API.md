# API Reference

All API endpoints are prefixed with `/api`. Web routes live under `/` and `/extract`.

---

## Web Routes (Dashboard)

### `GET /`
Returns the document extraction dashboard HTML page.

### `POST /extract`
Upload a document for OCR + LLM extraction (no database write).

**Request**: `multipart/form-data`
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | yes | JPG, JPEG, PNG, or PDF (max 10MB) |
| `llm_provider` | string | no | `openai`, `gemini`, or `ollama` (overrides default) |

**Response 200**:
```json
{
    "preview_url": "http://localhost/storage/temp/abc123.jpg",
    "raw_ocr_text": "INV-2001 | Acme Supplies | $20.00",
    "extracted_data": {
        "master": { "order_code": "INV-2001", ... },
        "items": [ { "service_name1": "Widget", ... } ],
        "field_confidence": { "master.order_code": "high", ... },
        "low_confidence_fields": [ "master.customer_name" ]
    },
    "missing_fields": ["Missing required field: Total Amount..."],
    "field_confidence": { ... },
    "low_confidence_fields": [ ... ]
}
```

**Response 422** (validation error or non-order document):
```json
{ "error": "The uploaded image doesn't appear to be a supported order image/document" }
```

### `POST /extract/confirm`
Persist a (possibly human-edited) extraction result as an order in the database.

**Request**: `application/json`
```json
{
    "master": {
        "order_code": "INV-2001",
        "customer_name": "Acme Supplies",
        "total_amount": 100.00
    },
    "items": [
        { "service_name1": "Widget", "quantity": 2, "unit_price": 50.00, "amount": 100.00 }
    ],
    "field_confidence": {
        "master.order_code": "high",
        "master.customer_name": "high"
    }
}
```

**Response 201**: Returns `OrderMasterResource` (same shape as GET `/api/orders/{id}`).

---

## API Routes

### `POST /api/orders/upload`
Full pipeline: upload → OCR → LLM → persist to database → return preview.

**Request**: `multipart/form-data`
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | file | yes | JPG, JPEG, PNG, PDF, WebP (max 20MB) |
| `llm_provider` | string | no | `openai`, `gemini`, `ollama` |

**Response 201** (success):
```json
{
    "data": {
        "id": 1,
        "order_code": "INV-2001",
        "status": "pending",
        "customer_name": "Acme Supplies",
        "total_amount": "20.00",
        "original_filename": "invoice.jpg",
        "llm_raw_response": { ... },
        "field_confidence": { ... },
        "items": [
            {
                "id": 1,
                "line_no": 1,
                "service_name1": "Widget",
                "quantity": "2.000",
                "unit": "pcs",
                "unit_price": "10.00",
                "amount": "20.00"
            }
        ],
        "created_at": "2026-07-28T12:00:00.000000Z"
    }
}
```

**Response 422** (validation or non-order doc): `{ "error": "..." }`

### `GET /api/orders`
Paginated list of all orders.

**Query Parameters**:
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | integer | 15 | Items per page |

### `GET /api/orders/{id}`
Get a single order with all line items.

### `PATCH /api/orders/{id}`
Update master fields. Only sends changed fields.

**Request**: `application/json`
```json
{
    "order_code": "INV-CORRECTED",
    "customer_name": "Actual Customer Name"
}
```

**Response**: Updated `OrderMasterResource`.

**409** if order is `confirmed` (must `reopen` first).

### `POST /api/orders/{id}/confirm`
Mark as confirmed. Locks against further edits.

**Response**: Updated `OrderMasterResource`.

### `POST /api/orders/{id}/reopen`
Move from `confirmed` back to `pending` for editing.

### `POST /api/orders/{id}/recalculate-total`
Re-sum `total_amount` from current line items.

### `POST /api/orders/{id}/items`
Add a new line item.

**Request**: `application/json`
```json
{
    "service_name1": "Gadget",
    "quantity": 1,
    "unit_price": 5.00
}
```

**Response 201**: `OrderDetailResource`.

### `PATCH /api/orders/{order}/items/{item}`
Update a line item. Only sends changed fields.

### `DELETE /api/orders/{order}/items/{item}`
Delete a line item.

**Response**: `{ "deleted": true }`

---

## OCR Service Endpoints

### `GET /health`
Liveness check. Returns `{ "status": "ok" }`.

### `POST /ocr`
Send an image or PDF for text extraction.

**Request**: `multipart/form-data`, field name `file`.

**Response 200**:
```json
{
    "text": "INV-2001 | Acme Supplies | $20.00\n...",
    "lines": [
        {
            "text": "INV-2001",
            "confidence": 0.98,
            "box": [[x1,y1],[x2,y2],[x3,y3],[x4,y4]],
            "page": 1
        }
    ],
    "pages": 1
}
```

**Response 415**: Unsupported content type (only JPG, JPEG, PNG, WebP, PDF).
**Response 400**: Empty file or corrupt file.
**Response 500**: OCR processing failure.
