<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaMapper extends AbstractLlmMapper
{
    public function providerName(): string
    {
        return 'ollama';
    }

    public function map(string $ocrText): array
    {
        $baseUrl = rtrim(config('services.ollama.base_url', 'http://localhost:11434'), '/');
        $model = config('services.ollama.model', 'llama3.1');

        $response = Http::timeout(120)->post("{$baseUrl}/api/chat", [
            'model' => $model,
            'stream' => false,
            'format' => 'json',
            'options' => ['temperature' => 0],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($ocrText)],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Ollama API error ({$response->status()}): {$response->body()}");
        }

        $content = $response->json('message.content');

        if (!$content) {
            throw new RuntimeException('Ollama response did not contain message content.');
        }

        return $this->parseJsonResponse($content);
    }
}
