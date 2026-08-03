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
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billings')->cascadeOnDelete();
            $table->decimal('amount_paid', 12, 2);
            $table->string('payment_method');
            $table->string('payment_reference_number')->nullable();
            $table->string('payment_type')->default('full_payment'); // initial_partial, debt_settlement, full_payment
            $table->string('accurate_sales_receipt_number')->nullable();
            $table->string('accurate_sync_status')->default('pending'); // pending, synced, failed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
