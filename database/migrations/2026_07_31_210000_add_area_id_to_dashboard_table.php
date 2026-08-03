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
        Schema::table('dashboard', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('id')->constrained('areas')->nullOnDelete();
            $table->unique(['area_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropUnique(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};
