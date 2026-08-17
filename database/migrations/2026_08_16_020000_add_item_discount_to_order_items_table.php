<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diskon per-item: penanda is_discount + persentase diskon.
     * discount_amount (rupiah) tetap dihitung = subtotal * discount_pct / 100.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_discount')->default(false)->after('discount_reason');
            $table->decimal('discount_pct', 5, 2)->default(0)->after('is_discount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['is_discount', 'discount_pct']);
        });
    }
};
