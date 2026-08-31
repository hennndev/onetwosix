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
        Schema::table('pos_category_settings', function (Blueprint $table) {
            // Null/empty = visible in all areas (backward compatible).
            $table->json('area_ids')->nullable()->after('show_in_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_category_settings', function (Blueprint $table) {
            $table->dropColumn('area_ids');
        });
    }
};
