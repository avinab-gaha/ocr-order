<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_master_id',
        'line_no',
        'item_name',
        'item_code',
        'quantity',
        'unit',
        'unit_price',

        'order_id',
        'service_masters_id',
        'quotation_branch_number',
        'line_number',
        'line_processing_type',
        'service_code',
        'service_name1',
        'service_name2',
        'start_time',
        'end_time',
        'duration',
        'minutes',
        'tax_rate',
        'amount',
        'gross_profit',
        'gross_profit_rate',
        'consumption_tax',
        'summary',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'base_unit_cost' => 'decimal:2',
        'base_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'gross_profit_rate' => 'decimal:2',
        'consumption_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'minutes' => 'integer',
        'start_time' => 'string',
        'end_time' => 'string',
        'duration' => 'string',
        'deleted_at' => 'datetime',
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(OrderMaster::class, 'order_master_id');
    }
}
