<?php

namespace App\Services\Llm;

use RuntimeException;

abstract class AbstractLlmMapper implements LlmMapperInterface
{
    /**
     * In-process cache so the prompt file is only read from disk once
     * per request lifecycle, even if multiple mappers/retries run.
     */
    protected static ?string $promptCache = null;

    /**
     * System instructions shared by every provider, loaded from an
     * external, editable file so the extraction schema, business rules,
     * and few-shot examples can be tuned without touching PHP code.
     *
     * Override path via config('services.llm.prompt_path') / the
     * LLM_PROMPT_PATH env var if you want to swap prompt files (e.g. per
     * document type) without redeploying code.
     */
    protected function systemPrompt(): string
    {
        if (static::$promptCache !== null) {
            return static::$promptCache;
        }

        $path = config('services.llm.prompt_path', resource_path('prompts/order_extraction.md'));

        if (!is_readable($path)) {
            throw new RuntimeException("LLM extraction prompt file not found or unreadable at [{$path}].");
        }

        return static::$promptCache = file_get_contents($path);
    }

    protected function userPrompt(string $ocrText): string
    {
        return "OCR TEXT:\n---\n{$ocrText}\n---\nReturn the JSON object now.";
    }

    /**
     * Parse a raw LLM text response into the expected array shape,
     * tolerating stray markdown code fences some models still add.
     */
    protected function parseJsonResponse(string $raw): array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(json)?/i', '', $cleaned);
        $cleaned = preg_replace('/```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (!is_array($decoded)) {
            $repaired = JsonRepairHelper::repair($cleaned);
            if ($repaired !== $cleaned) {
                logger()->warning('LLM mapper (' . $this->providerName() . ') JSON repaired.', [
                    'before' => substr($cleaned, 0, 500),
                    'after' => substr($repaired, 0, 500),
                ]);
                $decoded = json_decode($repaired, true);
            }
        }

        if (!is_array($decoded) || !isset($decoded['master']) || !isset($decoded['items'])) {
            throw new RuntimeException(
                'LLM mapper (' . $this->providerName() . ') returned unparsable JSON: ' . substr($cleaned, 0, 500)
            );
        }

        $decoded = $this->unwrapConfidence($decoded);

        $decoded['master'] = array_merge([
            'customer_name' => null,
            'total_amount' => null,
        ], $decoded['master']);

        $decoded['items'] = array_map(function ($item) {
            return array_merge([
                'item_name' => 'Unknown item',
                'item_code' => null,
                'quantity' => 0,
                'unit' => null,
                'unit_price' => 0,
            ], $item);
        }, $decoded['items']);

        return $decoded;
    }

    /**
     * Detect and unwrap the {value, confidence} wrapper shape introduced
     * by the confidence-aware prompt.
     *
     * If the decoded response uses the new shape, this method flattens
     * every leaf back to a plain scalar and populates:
     *   - $decoded['field_confidence']        dot-path => confidence level
     *   - $decoded['low_confidence_fields']   list of dot-paths at/below threshold
     *
     * If the response is already flat (old format), it is returned as-is.
     */
    protected function unwrapConfidence(array $decoded): array
    {
        if (!$this->isConfidenceWrapped($decoded)) {
            return $decoded;
        }

        $threshold = config('services.llm.review_threshold', 'low');
        $levels = ['low' => 0, 'medium' => 1, 'high' => 2];
        $minLevel = $levels[$threshold] ?? 0;

        $fieldConfidence = [];
        $lowConfidenceFields = [];

        $flattened = $this->flattenConfidenceNodes($decoded, '', $fieldConfidence, $lowConfidenceFields, $minLevel, $levels);

        $flattened['field_confidence'] = $fieldConfidence;
        $flattened['low_confidence_fields'] = $lowConfidenceFields;

        return $flattened;
    }

    /**
     * Check if the decoded response uses the {value, confidence} wrapper shape
     * by inspecting the first master field.
     */
    protected function isConfidenceWrapped(array $decoded): bool
    {
        $master = $decoded['master'] ?? [];
        if (empty($master)) {
            return false;
        }
        $first = reset($master);
        return is_array($first) && array_key_exists('value', $first) && array_key_exists('confidence', $first);
    }

    /**
     * Recursively walk the decoded array, unwrapping {value, confidence} leaves
     * and building the confidence metadata.
     */
    protected function flattenConfidenceNodes(
        array $node,
        string $prefix,
        array &$fieldConfidence,
        array &$lowConfidenceFields,
        int $minLevel,
        array $levels
    ): array {
        $result = [];
        foreach ($node as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            if ($this->isConfidenceLeaf($value)) {
                $confidence = $value['confidence'] ?? 'low';
                $fieldConfidence[$path] = $confidence;
                $level = $levels[$confidence] ?? 0;
                if ($level <= $minLevel) {
                    $lowConfidenceFields[] = $path;
                }
                $result[$key] = $value['value'];
            } elseif (is_array($value) && !$this->isIndexedArray($value)) {
                $result[$key] = $this->flattenConfidenceNodes($value, $path, $fieldConfidence, $lowConfidenceFields, $minLevel, $levels);
            } elseif (is_array($value) && $this->isIndexedArray($value)) {
                $result[$key] = array_map(function ($item, $idx) use ($path, &$fieldConfidence, &$lowConfidenceFields, $minLevel, $levels) {
                    if (is_array($item) && !$this->isConfidenceLeaf($item)) {
                        return $this->flattenConfidenceNodes($item, "{$path}.{$idx}", $fieldConfidence, $lowConfidenceFields, $minLevel, $levels);
                    }
                    if ($this->isConfidenceLeaf($item)) {
                        $confidence = $item['confidence'] ?? 'low';
                        $itemPath = "{$path}.{$idx}";
                        $fieldConfidence[$itemPath] = $confidence;
                        $level = $levels[$confidence] ?? 0;
                        if ($level <= $minLevel) {
                            $lowConfidenceFields[] = $itemPath;
                        }
                        return $item['value'];
                    }
                    return $item;
                }, $value, array_keys($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Check if a value is a {value, confidence} leaf node.
     */
    protected function isConfidenceLeaf(mixed $value): bool
    {
        return is_array($value)
            && array_key_exists('value', $value)
            && array_key_exists('confidence', $value)
            && count($value) <= 2;
    }

    /**
     * Check if an array is sequentially indexed (i.e. an array of items).
     */
    protected function isIndexedArray(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
