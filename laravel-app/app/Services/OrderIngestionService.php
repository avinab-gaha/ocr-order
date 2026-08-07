<?php

namespace App\Services;

use App\Models\OrderMaster;
use App\Services\Llm\LlmMapperFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrderIngestionService
{
    public function __construct(
        protected OcrClient $ocrClient,
        protected DocumentValidator $documentValidator,
    ) {
    }

    /**
     * Full pipeline: store file -> OCR -> document validation -> LLM mapping -> persist
     * order_masters + order_details -> return the created models.
     */
    public function ingest(UploadedFile $file, ?string $llmProvider = null): OrderMaster
    {
        $path = $file->store('order-uploads', 'local');
        $master = null;

        try {
            $ocrResult = $this->ocrClient->extract($file);
            $rawOcrText = $ocrResult['text'];

            $this->documentValidator->validate($rawOcrText);

            $master = OrderMaster::create([
                'source_file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'raw_ocr_text' => $rawOcrText,
                'status' => 'pending',
            ]);

            $mapper = LlmMapperFactory::make($llmProvider);
            $mapped = $mapper->map($rawOcrText);

            DB::transaction(function () use ($master, $mapped, $mapper) {
                $masterData = $mapped['master'] ?? [];
                $items = $mapped['items'] ?? [];
                $fieldConfidence = $mapped['field_confidence'] ?? null;
                $lowConfidenceFields = $mapped['low_confidence_fields'] ?? [];

                $master->fill($masterData);
                $master->llm_raw_response = $mapped;
                $master->field_confidence = $fieldConfidence;
                $master->llm_provider = $mapper->providerName();
                $master->status = !empty($lowConfidenceFields) ? 'flagged' : 'pending';
                $master->save();

                $master->details()->delete();

                foreach (array_values($items) as $i => $item) {
                    $detail = $master->details()->make();
                    $detail->fill($item);
                    $detail->line_no = $i + 1;
                    $detail->save();
                }

                if (empty($master->total_amount)) {
                    $master->recalculateTotal();
                }
            });
        } catch (Throwable $e) {
            if ($master) {
                Log::error('Order ingestion failed', [
                    'order_master_id' => $master->id,
                    'error' => $e->getMessage(),
                ]);
                $master->update([
                    'status' => 'failed',
                    'notes' => $e->getMessage(),
                ]);
                return $master->fresh('details');
            }

            Log::warning('Order ingestion rejected before creation', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $master->fresh('details');
    }
}
