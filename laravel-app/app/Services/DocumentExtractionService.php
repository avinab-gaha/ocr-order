<?php

namespace App\Services;

use App\Services\Llm\LlmMapperFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentExtractionService
{
    protected OcrClient $ocrClient;
    protected DocumentValidator $documentValidator;

    public function __construct(OcrClient $ocrClient, DocumentValidator $documentValidator)
    {
        $this->ocrClient = $ocrClient;
        $this->documentValidator = $documentValidator;
    }

    public function extract(UploadedFile $file, ?string $llmProvider = null): array
    {
        $path = $file->store('temp', 'public');
        $previewUrl = asset('storage/' . $path);

        $ocrResult = $this->ocrClient->extract($file);
        $rawOcrText = $ocrResult['text'];

        $this->documentValidator->validate($rawOcrText);

        $extractedData = null;
        $missingFields = [];
        $llmError = null;

        try {
            $mapper = LlmMapperFactory::make($llmProvider);
            $extractedData = $mapper->map($rawOcrText);
            $missingFields = $this->auditMissingFields($extractedData);
        } catch (RuntimeException $e) {
            $llmError = $e->getMessage();
            Log::warning('Document extraction LLM failed, falling back to OCR-only.', [
                'llm_provider' => $llmProvider,
                'error' => $llmError,
            ]);
        }

        $payload = [
            'preview_url' => $previewUrl,
            'raw_ocr_text' => $rawOcrText,
            'extracted_data' => $extractedData,
            'missing_fields' => $missingFields,
            'field_confidence' => $extractedData['field_confidence'] ?? null,
            'low_confidence_fields' => $extractedData['low_confidence_fields'] ?? [],
        ];

        if ($llmError) {
            $payload['llm_error'] = $llmError;
        }

        return $payload;
    }

    protected function auditMissingFields(array $data): array
    {
        $warnings = [];

        $requiredMaster = [
            'total_amount' => 'Total Amount (master.total_amount)',
        ];

        foreach ($requiredMaster as $key => $label) {
            $value = $data['master'][$key] ?? null;
            if ($value === null || $value === '' || $value === 0) {
                $warnings[] = "Missing required field: {$label}";
            }
        }

        $items = $data['items'] ?? [];
        foreach ($items as $i => $item) {
            $prefix = "items.{$i}.";
            $requiredItem = [
                'item_name' => 'Item Name',
                'quantity' => 'Quantity',
                'unit_price' => 'Unit Price',
            ];
            foreach ($requiredItem as $key => $label) {
                $value = $item[$key] ?? null;
                if ($value === null || $value === '' || $value === 0) {
                    $warnings[] = "Missing required field: {$prefix}{$key} ({$label}) at item #" . ($i + 1);
                }
            }
        }

        return $warnings;
    }
}
