<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_masters', function (Blueprint $table) {
            $table->dropColumn(['quotation_number','staff_code','planned_service_time']);
        });
        
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['quotation_number','tax_classification','tax_rate','base_unit_cost','base_cost']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_masters', function (Blueprint $table) {
            $table->string('quotation_number')->nullable();
            $table->string('staff_code')->nullable();
            $table->time('planned_service_time')->nullable();
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('quotation_number')->nullable();
            $table->string('tax_classification')->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('base_unit_cost', 15, 2)->nullable();
            $table->decimal('base_cost', 15, 2)->nullable();
        });
    }
};
