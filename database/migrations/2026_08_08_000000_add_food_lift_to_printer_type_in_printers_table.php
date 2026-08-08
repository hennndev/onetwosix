<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE printers MODIFY COLUMN printer_type ENUM('kitchen','bar','cashier','checker','food_lift') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE printers MODIFY COLUMN printer_type ENUM('kitchen','bar','cashier','checker') NULL DEFAULT NULL");
        }
    }
};
