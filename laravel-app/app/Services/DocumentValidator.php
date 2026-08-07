<?php

namespace App\Services;

use RuntimeException;

class DocumentValidator
{
    protected int $minScore;

    protected array $orderKeywords = [
        'invoice', 'invoic', 'inv', 'purchase order', 'po ', 'p.o.',
        'receipt', 'bill', 'quotation', 'quote', 'estimate',
        'order number', 'order no', 'invoice number', 'reference',
        'customer', 'vendor', 'supplier', 'client',
        'total', 'subtotal', 'amount', 'price', 'quantity', 'qty',
        'unit price', 'payment', 'billing', 'shipping',
        'tax', 'sub total', 'grand total',
        'order date', 'invoice date',
        'ship to', 'bill to', 'pay to',
        'description', 'item', 'product', 'service',
        'discount', 'balance due', 'terms',
        '請求書', '納品書', '見積書', '注文書',
        '領収書', '合計金額', '消費税', 'お客様',
        '発行日', '商品名', '数量', '単価', '金額',
        '小計', '税抜', '税込', '請求先', '得意先',
        'サービス区分', 'サービス名', '事業所',
        '支払条件', '締日', '予定日', '見積番号',
        '伝票番号', '発注書', '購買注文',
        '時間', '個', '回', '人', '枚', 'セット', '式',
        '単位', '時限',
    ];

    protected array $orderPatterns = [
        '/[¥$€]\s*[\d,]+\.?\d*/',
        '/[\d,]+\.?\d*\s*(?:円|yen|usd|eur)/i',
        '/\b(?:INV|PO|ORD|SO|SQ|EST|RFQ)[-_]?\d{3,}\b/i',
        '/\bORD[-_]?\d{3,}\b/i',
        '/#\d{4,}/',
        '/\d{1,3}\s*(?:x|×|×)\s*\d{1,3}/',
        '/\b\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\b/',
        '/\b(?:小計|subtotal|sub total)\s*:?\s*[\d,]+/i',
        '/\b(?:合計|total|grand total)\s*:?\s*[\d,]+/i',
    ];

    public function __construct()
    {
        $this->minScore = (int) config('services.document_validator.min_score', 3);
    }

    public function validate(string $ocrText): void
    {
        $trimmed = trim($ocrText);

        if ($trimmed === '') {
            throw new RuntimeException('The uploaded image doesn\'t appear to be a supported order image/document');
        }

        $score = 0;
        $textLower = mb_strtolower($trimmed);

        foreach ($this->orderKeywords as $keyword) {
            if (mb_strpos($textLower, mb_strtolower($keyword)) !== false) {
                $score++;
            }
        }

        foreach ($this->orderPatterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                $score += 2;
            }
        }

        if ($score < $this->minScore) {
            throw new RuntimeException('The uploaded image doesn\'t appear to be a supported order image/document');
        }
    }
}
