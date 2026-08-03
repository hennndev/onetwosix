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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('item_type')->nullable()->after('category_type')->default('INVENTORY');
        });

        // Set initial values based on is_item_group
        DB::table('inventory_items')
            ->where('is_item_group', true)
            ->update(['item_type' => 'GROUP']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
