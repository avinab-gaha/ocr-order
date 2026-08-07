# Folder Structure

```
ocr-order-project/
│
├── README.md                          # Project overview and setup guide
├── docker-compose.yml                 # Orchestration: ocr-service + mysql + laravel-app
├── implementation_plan.md             # Feature implementation plan (historical)
├── confidence_review_plan.md          # Confidence-aware extraction plan (historical)
│
├── ocr-service/                       # Python FastAPI OCR microservice
│   ├── main.py                        # API endpoints + OpenCV preprocessing + PaddleOCR
│   ├── requirements.txt               # Python dependencies
│   ├── Dockerfile                     # Container build for OCR service
│   ├── .dockerignore
│   └── .venv/                         # Local Python virtual environment
│
├── laravel-app/                       # Laravel 12 PHP application
│   │
│   ├── Dockerfile                     # Container build for Laravel app
│   ├── .env.example                   # Environment configuration template
│   ├── .env                           # Environment configuration
│   ├── composer.json                  # PHP dependencies
│   ├── composer.lock
│   ├── artisan                        # Laravel CLI entry point
│   │
│   ├── app/
│   │   ├── Providers/
│   │   │   └── AppServiceProvider.php # Service container registration
│   │   │
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Controller.php                         # Base controller
│   │   │   │   ├── DocumentExtractionController.php       # Web dashboard endpoints
│   │   │   │   └── Api/
│   │   │   │       ├── OrderUploadController.php           # Upload, list, show, edit, confirm
│   │   │   │       └── OrderDetailController.php           # Line item CRUD
│   │   │   │
│   │   │   ├── Requests/
│   │   │   │   ├── UploadOrderRequest.php                  # Upload validation (20MB, jpg/png/pdf/webp)
│   │   │   │   ├── ExtractDocumentRequest.php              # Extract validation (10MB, jpg/png/pdf)
│   │   │   │   ├── ConfirmExtractionRequest.php            # Confirm payload validation
│   │   │   │   ├── UpdateOrderMasterRequest.php            # Master field update validation
│   │   │   │   ├── StoreOrderDetailRequest.php             # Create detail validation
│   │   │   │   └── UpdateOrderDetailRequest.php            # Update detail validation
│   │   │   │
│   │   │   └── Resources/
│   │   │       ├── OrderMasterResource.php                 # JSON resource for orders
│   │   │       └── OrderDetailResource.php                 # JSON resource for line items
│   │   │
│   │   ├── Models/
│   │   │   ├── OrderMaster.php                             # Order header model (~150 fields)
│   │   │   ├── OrderDetail.php                             # Line item model
│   │   │   └── User.php                                    # Default Laravel user model
│   │   │
│   │   └── Services/
│   │       ├── OcrClient.php                   # HTTP client to FastAPI OCR service
│   │       ├── DocumentValidator.php           # Keyword/pattern scoring for order docs
│   │       ├── DocumentExtractionService.php   # Dashboard extraction pipeline (no DB)
│   │       ├── OrderIngestionService.php       # API ingestion pipeline (persists to DB)
│   │       ├── OrderConfirmationService.php    # Dashboard confirm pipeline (persists to DB)
│   │       │
│   │       └── Llm/
│   │           ├── LlmMapperInterface.php      # Interface for LLM mappers
│   │           ├── LlmMapperFactory.php        # Factory: creates provider-specific mapper
│   │           ├── AbstractLlmMapper.php       # Shared prompt loading + JSON parsing + confidence unwrapping
│   │           ├── OpenAiMapper.php            # OpenAI chat/completions integration
│   │           ├── GeminiMapper.php            # Gemini generateContent integration
│   │           ├── OllamaMapper.php            # Ollama /api/chat integration
│   │           └── JsonRepairHelper.php        # Trailing commas, brace balancing, markdown fence removal
│   │
│   ├── config/
│   │   └── services.php               # OCR + LLM service configuration
│   │
│   ├── database/
│   │   └── migrations/
│   │       ├── 0001_01_01_000000_create_users_table.php
│   │       ├── 0001_01_01_000001_create_cache_table.php
│   │       ├── 0001_01_01_000002_create_jobs_table.php
│   │       ├── 2026_07_09_000001_create_order_masters_table.php     # Main order table
│   │       ├── 2026_07_09_000002_create_order_details_table.php     # Line items table
│   │       ├── 2026_07_20_000001_add_field_confidence_to_order_masters_table.php
│   │       ├── 2026_07_22_062030_remove_column_quotation_number_...
│   │       └── 2026_07_23_044924_add_column_tax_rate_in_order_details_table.php
│   │
│   ├── resources/
│   │   ├── prompts/
│   │   │   └── order_extraction.md     # Editable LLM prompt: schema, rules, few-shot examples
│   │   └── views/
│   │       ├── extraction.blade.php    # Document extraction dashboard UI
│   │       └── welcome.blade.php       # Default Laravel welcome page
│   │
│   ├── routes/
│   │   ├── api.php                     # API routes (prefix: /api/orders/*)
│   │   ├── web.php                     # Web routes (/, /extract, /extract/confirm)
│   │   └── console.php                 # Artisan console commands
│   │
│   └── tests/
│       └── Feature/
│           ├── DocumentExtractionTest.php       # Dashboard extraction tests
│           ├── ExtractionConfirmationTest.php   # Confirm endpoint tests
│           ├── ConfidenceExtractionTest.php     # Confidence-aware extraction tests
│           ├── OrderUploadTest.php              # API upload pipeline tests
│           └── OrderEditingTest.php             # Edit/recalculate/confirm tests
│
└── docs/                               # Generated documentation (this folder)
    ├── Architecture.md
    ├── FolderStructure.md
    ├── API.md
    ├── Database.md
    ├── Services.md
    ├── Deployment.md
    └── DevelopmentGuide.md
```
