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
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->string('accurate_bank_account_no')
                ->nullable()
                ->default('110101')
                ->after('accurate_service_charge_account_no');

            $table->string('accurate_cash_account_no')
                ->nullable()
                ->default('110102')
                ->after('accurate_bank_account_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->dropColumn(['accurate_bank_account_no', 'accurate_cash_account_no']);
        });
    }
};
