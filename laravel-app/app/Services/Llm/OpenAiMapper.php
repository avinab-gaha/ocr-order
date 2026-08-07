<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiMapper extends AbstractLlmMapper
{
    public function providerName(): string
    {
        return 'openai';
    }

    public function map(string $ocrText): array
    {
        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model', 'gpt-4o-mini');

        if (!$apiKey) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($ocrText)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("OpenAI API error ({$response->status()}): {$response->body()}");
        }

        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new RuntimeException('OpenAI response did not contain message content.');
        }

        return $this->parseJsonResponse($content);
    }
}
