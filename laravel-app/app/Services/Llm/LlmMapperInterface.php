<?php

namespace App\Services\Llm;

interface LlmMapperInterface
{
    /**
     * Take raw OCR text and return structured order data.
     *
     * Expected return shape:
     * [
     *   'master' => [
     *       'customer_name' => ?string,
      *       'total_amount' => ?float,
     *   ],
     *   'items' => [
     *       ['item_name' => string, 'item_code' => ?string, 'quantity' => float,
      *        'unit' => ?string, 'unit_price' => float],
     *       ...
     *   ],
     * ]
     */
    public function map(string $ocrText): array;

    public function providerName(): string;
}
