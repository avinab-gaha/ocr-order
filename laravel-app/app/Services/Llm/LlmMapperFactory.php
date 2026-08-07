<?php

namespace App\Services\Llm;

use InvalidArgumentException;

class LlmMapperFactory
{
    public static function make(?string $provider = null): LlmMapperInterface
    {
        $provider = $provider ?? config('services.llm.default', 'openai');

        return match (strtolower($provider)) {
            'openai' => new OpenAiMapper(),
            'gemini' => new GeminiMapper(),
            'ollama' => new OllamaMapper(),
            default => throw new InvalidArgumentException("Unknown LLM provider [{$provider}]."),
        };
    }
}
