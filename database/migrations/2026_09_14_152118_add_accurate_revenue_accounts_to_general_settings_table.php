<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom nomor akun Accurate untuk pendapatan/diskon kategori.
     * Nullable tanpa default — nomor akun berbeda tiap bisnis, diisi manual di Pengaturan Umum.
     */
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->string('accurate_extra_charge_account_no')->nullable()->after('accurate_compliment_account_no');
            $table->string('accurate_food_sales_account_no')->nullable()->after('accurate_extra_charge_account_no');
            $table->string('accurate_beverage_sales_account_no')->nullable()->after('accurate_food_sales_account_no');
            $table->string('accurate_cigarette_sales_account_no')->nullable()->after('accurate_beverage_sales_account_no');
            $table->string('accurate_sales_discount_account_no')->nullable()->after('accurate_cigarette_sales_account_no');
            $table->string('accurate_breakage_account_no')->nullable()->after('accurate_sales_discount_account_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'accurate_extra_charge_account_no',
                'accurate_food_sales_account_no',
                'accurate_beverage_sales_account_no',
                'accurate_cigarette_sales_account_no',
                'accurate_sales_discount_account_no',
                'accurate_breakage_account_no',
            ]);
        });
    }
};
