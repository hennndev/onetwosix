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
        Schema::create('song_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained('customer_users')->onDelete('cascade');
            $table->string('song_title');
            $table->string('artist');
            $table->decimal('tip', 10, 2)->nullable();
            $table->enum('status', ['pending', 'played', 'completed', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_requests');
    }
};
