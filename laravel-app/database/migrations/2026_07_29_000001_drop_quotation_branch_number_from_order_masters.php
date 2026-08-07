<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_masters', function (Blueprint $table) {
            $table->dropColumn('quotation_branch_number');
        });
    }

    public function down(): void
    {
        Schema::table('order_masters', function (Blueprint $table) {
            $table->integer('quotation_branch_number')->nullable()->after('quotation_date');
        });
    }
};
