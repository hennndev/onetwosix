<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor akun (COA) Accurate untuk expense FOC & Compliment. Nullable tanpa
     * default — nomor akun berbeda tiap bisnis, diisi manual di Pengaturan Umum.
     */
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->string('accurate_foc_account_no')
                ->nullable()
                ->after('accurate_cash_account_no');

            $table->string('accurate_compliment_account_no')
                ->nullable()
                ->after('accurate_foc_account_no');
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->dropColumn(['accurate_foc_account_no', 'accurate_compliment_account_no']);
        });
    }
};
