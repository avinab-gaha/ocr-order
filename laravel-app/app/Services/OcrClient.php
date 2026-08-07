<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OcrClient
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ocr.base_url'), '/');
        $this->timeout = (int) config('services.ocr.timeout', 60);
    }

    /**
     * Send a file to the PaddleOCR FastAPI service and get back
     * the extracted plain text plus per-line detections.
     *
     * @return array{text: string, lines: array}
     */
    public function extract(UploadedFile $file): array
    {
        $response = Http::timeout($this->timeout)
            ->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )
            ->post("{$this->baseUrl}/ocr");

        if ($response->failed()) {
            throw new RuntimeException(
                "OCR service error ({$response->status()}): {$response->body()}"
            );
        }

        $data = $response->json();

        return [
            'text' => $data['text'] ?? '',
            'lines' => $data['lines'] ?? [],
        ];
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
