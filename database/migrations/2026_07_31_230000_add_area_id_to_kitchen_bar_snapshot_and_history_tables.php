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
        Schema::table('daily_kitchen_snapshots', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('end_day')->constrained('areas')->nullOnDelete();
        });

        Schema::table('daily_bar_snapshots', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('end_day')->constrained('areas')->nullOnDelete();
        });

        Schema::table('recap_history_kitchen', function (Blueprint $table) {
            $table->dropUnique(['end_day']);
            $table->foreignId('area_id')->nullable()->after('end_day')->constrained('areas')->nullOnDelete();
            $table->unique(['end_day', 'area_id']);
        });

        Schema::table('recap_history_bar', function (Blueprint $table) {
            $table->dropUnique(['end_day']);
            $table->foreignId('area_id')->nullable()->after('end_day')->constrained('areas')->nullOnDelete();
            $table->unique(['end_day', 'area_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recap_history_bar', function (Blueprint $table) {
            $table->dropUnique(['end_day', 'area_id']);
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
            $table->unique('end_day');
        });

        Schema::table('recap_history_kitchen', function (Blueprint $table) {
            $table->dropUnique(['end_day', 'area_id']);
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
            $table->unique('end_day');
        });

        Schema::table('daily_bar_snapshots', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::table('daily_kitchen_snapshots', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};
