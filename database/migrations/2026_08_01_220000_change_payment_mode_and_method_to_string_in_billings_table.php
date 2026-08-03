<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE billings MODIFY COLUMN payment_mode VARCHAR(50) NULL');
            DB::statement('ALTER TABLE billings MODIFY COLUMN payment_method VARCHAR(50) NULL');
        } catch (\Throwable $e) {
            Schema::table('billings', function (Blueprint $table) {
                $table->string('payment_mode', 50)->nullable()->change();
                $table->string('payment_method', 50)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE billings MODIFY COLUMN payment_mode ENUM('normal','split') NULL");
        } catch (\Throwable $e) {
            // Ignore rollback errors
        }
    }
};
