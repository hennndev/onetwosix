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
        Schema::table('display_message_requests', function (Blueprint $table): void {
            $table->foreignId('table_session_id')->nullable()->after('customer_id')->constrained('table_sessions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('display_message_requests', function (Blueprint $table): void {
            $table->dropForeign(['table_session_id']);
            $table->dropColumn('table_session_id');
        });
    }
};
