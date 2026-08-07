<?php

namespace App\Services\Llm;

class JsonRepairHelper
{
    public static function repair(string $raw): string
    {
        $cleaned = trim($raw);

        $cleaned = preg_replace('/^```(json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $cleaned = preg_replace('/\,\s*([\]}])/', '$1', $cleaned);

        $cleaned = preg_replace('/(?<!\w)\'([^\']*?)\'(?!\w)\s*:/', '"$1":', $cleaned);
        $cleaned = preg_replace('/:\s*\'([^\']*?)\'(?!\w)/', ':"$1"', $cleaned);

        $openBraces = substr_count($cleaned, '{');
        $closeBraces = substr_count($cleaned, '}');
        while ($closeBraces < $openBraces) {
            $cleaned .= '}';
            $closeBraces++;
        }

        $openBrackets = substr_count($cleaned, '[');
        $closeBrackets = substr_count($cleaned, ']');
        while ($closeBrackets < $openBrackets) {
            $cleaned .= ']';
            $closeBrackets++;
        }

        return $cleaned;
    }
}
