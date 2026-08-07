<?php

namespace App\Services;

use App\Models\OrderMaster;
use Illuminate\Support\Facades\DB;

class OrderConfirmationService
{
    /**
     * Persist the (possibly human-edited) extraction payload as
     * order_masters + order_details in a single transaction.
     *
     * This does NOT re-run OCR or the LLM — it saves the data the
     * dashboard already has in memory after human review.
     *
     * @param array $data Shape: ['master' => [...], 'items' => [[...], ...]]
     * @param array $originalConfidence The field_confidence map from the original extraction.
     * @return OrderMaster
     */
    public function confirm(array $data, array $originalConfidence = []): OrderMaster
    {
        $masterData = $data['master'] ?? [];
        $items = $data['items'] ?? [];

        return DB::transaction(function () use ($masterData, $items, $originalConfidence) {
            $master = new OrderMaster();
            $master->fill(array_filter($masterData, fn($v) => $v !== null));
            $master->field_confidence = !empty($originalConfidence) ? $originalConfidence : null;
            $master->status = 'confirmed';
            $master->save();

            foreach (array_values($items) as $i => $item) {
                $detail = $master->details()->make();
                $detail->fill(array_filter($item, fn($v) => $v !== null));
                $detail->line_no = $i + 1;
                $detail->save();
            }

            if (empty($master->total_amount)) {
                $master->recalculateTotal();
            }

            return $master->fresh('details');
        });
    }
}
