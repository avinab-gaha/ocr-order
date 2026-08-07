<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR + LLM Order Ingestion Pipeline
    |--------------------------------------------------------------------------
    */

    'ocr' => [
        'base_url' => env('OCR_SERVICE_URL', 'http://localhost:8001'),
        'timeout' => env('OCR_SERVICE_TIMEOUT', 60),
    ],

    'document_validator' => [
        'min_score' => env('DOCUMENT_VALIDATOR_MIN_SCORE', 3),
    ],

    'llm' => [
        // Which provider is used when the request doesn't specify one.
        'default' => env('LLM_PROVIDER', 'openai'), // openai | gemini | ollama

        // Editable extraction rules + few-shot examples sent as the
        // system prompt. Point this at a different file to tune
        // behaviour (e.g. per document type) without touching code.
        'prompt_path' => env('LLM_PROMPT_PATH', resource_path('prompts/order_extraction.md')),

        // Confidence threshold: fields at or below this level are
        // flagged for human review. Values: "high", "medium", "low".
        'review_threshold' => env('LLM_REVIEW_THRESHOLD', 'low'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.1'),
    ],

];
