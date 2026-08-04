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
        Schema::create('pos_discount_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_auth_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('fingerprint', 64)->index();
            $table->json('intent');
            $table->timestamp('approved_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('consumed_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_discount_approvals');
    }
};
