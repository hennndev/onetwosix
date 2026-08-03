<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('customer_user_id')->constrained('areas')->nullOnDelete();
        });

        Schema::table('bar_orders', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('table_id')->constrained('areas')->nullOnDelete();
        });

        Schema::table('kitchen_orders', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('table_id')->constrained('areas')->nullOnDelete();
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('order_id')->constrained('areas')->nullOnDelete();
        });

        // Backfill existing data based on table_sessions or tables
        DB::statement('
            UPDATE orders
            SET area_id = (
                SELECT tables.area_id
                FROM table_sessions
                JOIN tables ON tables.id = table_sessions.table_id
                WHERE table_sessions.id = orders.table_session_id
                LIMIT 1
            )
            WHERE orders.table_session_id IS NOT NULL AND orders.area_id IS NULL
        ');

        DB::statement('
            UPDATE bar_orders
            SET area_id = (
                SELECT tables.area_id
                FROM tables
                WHERE tables.id = bar_orders.table_id
                LIMIT 1
            )
            WHERE bar_orders.table_id IS NOT NULL AND bar_orders.area_id IS NULL
        ');

        DB::statement('
            UPDATE kitchen_orders
            SET area_id = (
                SELECT tables.area_id
                FROM tables
                WHERE tables.id = kitchen_orders.table_id
                LIMIT 1
            )
            WHERE kitchen_orders.table_id IS NOT NULL AND kitchen_orders.area_id IS NULL
        ');

        DB::statement('
            UPDATE billings
            SET area_id = (
                SELECT orders.area_id
                FROM orders
                WHERE orders.id = billings.order_id
                LIMIT 1
            )
            WHERE billings.order_id IS NOT NULL AND billings.area_id IS NULL
        ');

        DB::statement('
            UPDATE billings
            SET area_id = (
                SELECT tables.area_id
                FROM table_sessions
                JOIN tables ON tables.id = table_sessions.table_id
                WHERE table_sessions.id = billings.table_session_id
                LIMIT 1
            )
            WHERE billings.table_session_id IS NOT NULL AND billings.area_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::table('kitchen_orders', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::table('bar_orders', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};
