<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the food_lift printer type (feature retired).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE printers MODIFY COLUMN printer_type ENUM('kitchen','bar','cashier','checker') NULL DEFAULT NULL");
        }

        DB::table('printers')->where('printer_type', 'food_lift')->delete();

        $settings = DB::table('general_settings')->whereNotNull('area_printer_settings')->get(['id', 'area_printer_settings']);

        foreach ($settings as $setting) {
            $settingsArray = json_decode((string) $setting->area_printer_settings, true);

            if (! is_array($settingsArray)) {
                continue;
            }

            $changed = false;

            foreach ($settingsArray as $areaId => $printerMap) {
                if (array_key_exists('food_lift', $printerMap)) {
                    unset($settingsArray[$areaId]['food_lift']);
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('general_settings')->where('id', $setting->id)->update([
                    'area_printer_settings' => json_encode($settingsArray),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE printers MODIFY COLUMN printer_type ENUM('kitchen','bar','cashier','checker','food_lift') NULL DEFAULT NULL");
        }
    }
};
