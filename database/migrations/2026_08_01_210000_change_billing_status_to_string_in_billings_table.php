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
            DB::statement("ALTER TABLE billings MODIFY COLUMN billing_status VARCHAR(50) NOT NULL DEFAULT 'draft'");
        } catch (\Throwable $e) {
            Schema::table('billings', function (Blueprint $table) {
                $table->string('billing_status', 50)->default('draft')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE billings MODIFY COLUMN billing_status ENUM('draft','finalized','paid','partially_paid','force_closed') NOT NULL DEFAULT 'draft'");
        } catch (\Throwable $e) {
            // Ignore rollback errors
        }
    }
};
