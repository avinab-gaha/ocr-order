<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedInteger('service_masters_id')->nullable();
            $table->unsignedBigInteger('quotation_number')->nullable();
            $table->unsignedInteger('quotation_branch_number')->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->string('line_processing_type', 50)->nullable();
            $table->string('service_code', 50)->nullable();
            $table->string('service_name1', 255)->nullable();
            $table->string('service_name2', 255)->nullable();
            $table->string('unit', 20)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('duration')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->decimal('quantity', 15, 2)->nullable();
            $table->string('tax_classification', 20)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('base_unit_cost', 15, 2)->nullable();
            $table->decimal('base_cost', 15, 2)->nullable();
            $table->decimal('gross_profit', 15, 2)->nullable();
            $table->decimal('gross_profit_rate', 5, 2)->nullable();
            $table->decimal('consumption_tax', 15, 2)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreignId('order_master_id')
                ->constrained('order_masters')
                ->cascadeOnDelete();

            $table->unsignedInteger('line_no')->default(1);
            $table->string('item_name')->nullable();
            $table->string('item_code')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
