<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill area_id for billings associated with a table_session
        DB::statement('
            UPDATE billings
            SET area_id = (
                SELECT tables.area_id
                FROM table_sessions
                JOIN tables ON tables.id = table_sessions.table_id
                WHERE table_sessions.id = billings.table_session_id
                LIMIT 1
            )
            WHERE billings.area_id IS NULL AND billings.table_session_id IS NOT NULL
        ');

        // 2. Backfill area_id for billings associated directly with an order
        DB::statement('
            UPDATE billings
            SET area_id = (
                SELECT orders.area_id
                FROM orders
                WHERE orders.id = billings.order_id
                LIMIT 1
            )
            WHERE billings.area_id IS NULL AND billings.order_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
