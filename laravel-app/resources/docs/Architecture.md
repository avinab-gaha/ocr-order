# System Architecture

## Overview

OCR Order Ingestion is a two-service system that converts scanned invoices/purchase orders into structured database records. It consists of a **FastAPI OCR microservice** (Python) and a **Laravel 12 API** (PHP) connected via HTTP.

```mermaid
flowchart TB
    subgraph Client
        Browser[" Browser / curl / App "]
    end

    subgraph Laravel[" Laravel 12 Application "]
        Web[" Web Routes<br/>GET /<br/>POST /extract<br/>POST /extract/confirm "]
        API[" API Routes<br/>/api/orders/* "]
        DOC[" DocumentExtractionController "]
        UPLOAD[" OrderUploadController "]
        DETAIL[" OrderDetailController "]
        INGEST[" OrderIngestionService "]
        EXTRACT[" DocumentExtractionService "]
        CONFIRM[" OrderConfirmationService "]
        LLM_FAC[" LlmMapperFactory "]
        LLM_OPENAI[" OpenAiMapper "]
        LLM_GEMINI[" GeminiMapper "]
        LLM_OLLAMA[" OllamaMapper "]
        OCR_CLIENT[" OcrClient "]
        VALIDATOR[" DocumentValidator "]
        MASTER_MODEL[" OrderMaster "]
        DETAIL_MODEL[" OrderDetail "]
    end

    subgraph Python[" Python OCR Microservice "]
        FASTAPI[" FastAPI<br/>GET /health<br/>POST /ocr "]
        PREPROC[" OpenCV Preprocessing<br/>Grayscale, Denoise,<br/>Binarize, Deskew "]
        PADDLE[" PaddleOCR Engine "]
    end

    subgraph External[" External LLM Providers "]
        OPENAI[" OpenAI API<br/>gpt-4o-mini "]
        GEMINI[" Gemini API<br/>gemini-2.5-flash "]
        OLLAMA[" Ollama (Local)<br/>llama3.1 "]
    end

    subgraph Database[" MySQL / SQLite "]
        DB[( order_masters<br/>order_details )]
    end

    %% Client connections
    Browser --> Web
    Browser --> API

    %% Web routes
    Web --> DOC
    DOC --> EXTRACT
    DOC --> CONFIRM

    %% API routes
    API --> UPLOAD
    API --> DETAIL
    UPLOAD --> INGEST
    DETAIL --> MASTER_MODEL
    DETAIL --> DETAIL_MODEL

    %% Service layer
    INGEST --> OCR_CLIENT
    INGEST --> VALIDATOR
    INGEST --> LLM_FAC
    EXTRACT --> OCR_CLIENT
    EXTRACT --> VALIDATOR
    EXTRACT --> LLM_FAC
    CONFIRM --> MASTER_MODEL

    %% LLM Factory
    LLM_FAC --> LLM_OPENAI
    LLM_FAC --> LLM_GEMINI
    LLM_FAC --> LLM_OLLAMA

    %% OCR Client to FastAPI
    OCR_CLIENT -->|HTTP POST /ocr| FASTAPI
    FASTAPI --> PREPROC
    PREPROC --> PADDLE

    %% LLM to external providers
    LLM_OPENAI -->|HTTP POST| OPENAI
    LLM_GEMINI -->|HTTP POST| GEMINI
    LLM_OLLAMA -->|HTTP POST| OLLAMA

    %% Database
    INGEST --> DB
    CONFIRM --> DB
    MASTER_MODEL --> DB
    DETAIL_MODEL --> DB
```

## Request Lifecycle (Upload Flow)

```mermaid
sequenceDiagram
    participant C as Client
    participant LC as LaravelController
    participant OC as OcrClient
    participant FS as FastAPI_OCR
    participant DV as DocValidator
    participant LLM as LlmMapper
    participant DB as Database

    C->>LC: POST /api/orders/upload (file + llm_provider)
    
    LC->>OC: extract(file)
    OC->>FS: HTTP POST /ocr (file)
    Note over FS: OpenCV Preprocess
    Note over FS: PaddleOCR extracts text
    FS-->>OC: { text, lines }
    OC-->>LC: { text, lines }
    
    LC->>DV: validate(ocrText)
    DV-->>LC: void or throws RuntimeException
    
    Note over LC: Create OrderMaster, status=pending
    
    LC->>LLM: map(ocrText)
    LLM->>LLM: Build system prompt from order_extraction.md
    LLM->>External: HTTP POST to provider API
    External-->>LLM: JSON response
    LLM->>LLM: Parse JSON
    Note over LLM: JsonRepairHelper fallback if parse fails
    LLM->>LLM: Unwrap confidence {value, confidence} -> value
    LLM-->>LC: structured data array
    
    Note over LC: DB Transaction
    Note over LC: Update master fields
    Note over LC: Set status flagged if low confidence
    Note over LC: Delete and recreate details
    Note over LC: Recalculate total if empty
    
    LC-->>C: 201 Created { data: OrderMaster }
```

## Authentication Flow

The current implementation has **no authentication layer** — all endpoints are wide open. The system is designed to add auth later.

```mermaid
flowchart LR
    subgraph Current[" Current (No Auth) "]
        C1[" Client "]
        L1[" Laravel API "]
        C1 -->|" Any request "| L1
        L1 -->|" Always 200/201 "| C1
    end

    subgraph Future[" Future (Recommended) "]
        C2[" Client "]
        A[" Auth Middleware<br/>Sanctum / JWT / OAuth "]
        L2[" Laravel API "]
        C2 -->|" Request + Token "| A
        A -->|" Valid "| L2
        A -->|" Invalid "| C2
    end
```

## OCR Pipeline

```mermaid
flowchart TB
    FILE[" Uploaded File<br/>JPG/PNG/PDF "]

    subgraph Preprocessing[" OpenCV Preprocessing "]
        RESIZE[" Resize<br/>max 4000px longest side "]
        GRAY[" Grayscale "]
        DENOISE[" Denoise<br/>fastNlMeansDenoising "]
        BINARIZE[" Binarize<br/>Adaptive Gaussian Threshold "]
        DESKEW[" Deskew<br/>minAreaRect correction "]
    end

    subgraph OCR[" PaddleOCR "]
        DETECTION[" Text Detection "]
        RECOGNITION[" Text Recognition "]
        CLS[" Angle Classification "]
    end

    POSTPROC[" Line Assembly "]
    RESULT[" { text, lines } "]

    FILE --> RESIZE
    RESIZE --> GRAY
    GRAY --> DENOISE
    DENOISE --> BINARIZE
    BINARIZE --> DESKEW
    DESKEW --> DETECTION
    DETECTION --> RECOGNITION
    RECOGNITION --> CLS
    CLS --> POSTPROC
    POSTPROC --> RESULT

    style FILE fill:#e1f5fe
    style RESULT fill:#e8f5e9
```

## Service Interactions

```mermaid
flowchart TB
    subgraph Controllers
        OUC[" OrderUploadController "]
        ODC[" OrderDetailController "]
        DEC[" DocumentExtractionController "]
    end

    subgraph Services
        OIS[" OrderIngestionService "]
        DES[" DocumentExtractionService "]
        OCS[" OrderConfirmationService "]
        DV[" DocumentValidator "]
        OC[" OcrClient "]
    end

    subgraph LLM_Services[" LLM Layer "]
        LMF[" LlmMapperFactory "]
        INTERFACE[" LlmMapperInterface "]
        ALM[" AbstractLlmMapper "]
        OM[" OpenAiMapper "]
        GM[" GeminiMapper "]
        OLM[" OllamaMapper "]
        JRH[" JsonRepairHelper "]
    end

    subgraph Models
        OM_MODEL[" OrderMaster "]
        OD_MODEL[" OrderDetail "]
    end

    OUC --> OIS
    ODC --> OM_MODEL
    ODC --> OD_MODEL
    DEC --> DES
    DEC --> OCS

    OIS --> OC
    OIS --> DV
    OIS --> LMF
    DES --> OC
    DES --> DV
    DES --> LMF
    OCS --> OM_MODEL
    OCS --> OD_MODEL

    LMF --> INTERFACE
    INTERFACE --> ALM
    ALM --> OM
    ALM --> GM
    ALM --> OLM
    ALM --> JRH
    ALM -->|" reads "| PROMPT[" order_extraction.md "]

    OM_MODEL --> OD_MODEL
```

## Key Design Decisions

1. **Stateless OCR Service**: The FastAPI service knows nothing about orders — it only converts pixels to text. This allows reuse by other consumers.

2. **Pluggable LLM Providers**: All three providers (OpenAI, Gemini, Ollama) implement the same `LlmMapperInterface` and share one prompt file, making provider swaps config-free.

3. **Confidence-Aware Extraction**: Each field can include `{value, confidence}` — unwrapped transparently by `AbstractLlmMapper::unwrapConfidence()` so downstream code never sees the wrapper shape.

4. **Editable Prompt**: The extraction schema and few-shot examples live in a Markdown file (`order_extraction.md`), not hardcoded PHP — tune behavior without touching code.

5. **Human-in-the-Loop Workflow**: Upload → preview → edit → confirm. Orders start as `pending` (or `flagged` if low confidence), can be edited, and only become `confirmed` after human review.
