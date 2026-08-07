<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiMapper extends AbstractLlmMapper
{
    public function providerName(): string
    {
        return 'gemini';
    }

    public function map(string $ocrText): array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');
        if (!$apiKey) {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(60)->post($url, [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->systemPrompt() . "\n\n" . $this->userPrompt($ocrText)],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Gemini API error ({$response->status()}): {$response->body()}");
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (!$content) {
            throw new RuntimeException('Gemini response did not contain any text.');
        }

        return $this->parseJsonResponse($content);
    }
}