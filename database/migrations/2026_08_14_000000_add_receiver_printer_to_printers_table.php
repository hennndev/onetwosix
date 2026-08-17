<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->unsignedBigInteger('receiver_printer_id')->nullable()->after('copies');
            $table->foreign('receiver_printer_id')->references('id')->on('printers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropForeign(['receiver_printer_id']);
            $table->dropColumn('receiver_printer_id');
        });
    }
};
