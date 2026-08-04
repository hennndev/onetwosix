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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('discount_reason', 500)->nullable()->after('discount_amount');
            $table->foreignId('discount_approval_id')->nullable()->after('discount_reason')->constrained('pos_discount_approvals')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_approval_id');
            $table->dropColumn('discount_reason');
        });
    }
};
