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
        Schema::table('recap_history', function (Blueprint $table): void {
            $table->foreignId('area_id')->nullable()->after('id')->constrained('areas')->nullOnDelete();
            $table->dropUnique('recap_history_end_day_unique');
            $table->unique(['end_day', 'area_id'], 'recap_history_end_day_area_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recap_history', function (Blueprint $table): void {
            $table->dropUnique('recap_history_end_day_area_id_unique');
            $table->unique('end_day', 'recap_history_end_day_unique');
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};
