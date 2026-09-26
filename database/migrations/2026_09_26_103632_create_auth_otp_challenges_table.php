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
        Schema::create('auth_otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('target_phone', 20);
            $table->text('payload')->nullable();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'target_phone', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_otp_challenges');
    }
};
