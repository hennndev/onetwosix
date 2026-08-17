<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan FOC/Compliment — default = perilaku existing (Compliment 100%,
     * FOC 0%, keduanya wajib auth code).
     */
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->boolean('foc_enabled')->default(true)->after('daily_auth_code_access_emails');
            $table->boolean('compliment_enabled')->default(true)->after('foc_enabled');
            $table->boolean('foc_requires_auth_code')->default(true)->after('compliment_enabled');
            $table->boolean('compliment_requires_auth_code')->default(true)->after('foc_requires_auth_code');
            $table->unsignedTinyInteger('foc_discount_percentage')->default(0)->after('compliment_requires_auth_code');
            $table->unsignedTinyInteger('compliment_discount_percentage')->default(100)->after('foc_discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn([
                'foc_enabled',
                'compliment_enabled',
                'foc_requires_auth_code',
                'compliment_requires_auth_code',
                'foc_discount_percentage',
                'compliment_discount_percentage',
            ]);
        });
    }
};
