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
        Schema::create('daily_sequences', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('scope');
            $table->unsignedBigInteger('value')->default(0);
            $table->unique(['date', 'scope']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sequences');
    }
};
