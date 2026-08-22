<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            if (Schema::hasColumn('printers', 'receiver_printer_id')) {
                $table->dropForeign(['receiver_printer_id']);
                $table->dropColumn('receiver_printer_id');
            }

            if (! Schema::hasColumn('printers', 'enable_receiver')) {
                $table->boolean('enable_receiver')->default(false)->after('width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            if (Schema::hasColumn('printers', 'enable_receiver')) {
                $table->dropColumn('enable_receiver');
            }

            if (! Schema::hasColumn('printers', 'receiver_printer_id')) {
                $table->unsignedBigInteger('receiver_printer_id')->nullable()->after('width');
                $table->foreign('receiver_printer_id')->references('id')->on('printers')->nullOnDelete();
            }
        });
    }
};
