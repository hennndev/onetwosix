<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionsSeeder::class,
            UsersSeeder::class,
            ApiDemoSeeder::class,
            // ClubOperatingHoursSeeder::class,
            // AreaSeeder::class,
            // TabelSeeder::class,
            // CustomerSeeder::class,
            // InventoryCategorySeeder::class,
            // InventoryItemSeeder::class,
            // BomSeeder::class,
            // TableReservationSeeder::class,
            // DisplayMessageSeeder::class,
            // SongRequestSeeder::class,
            // EventSeeder::class,
            // KitchenOrderSeeder::class,
            // BarOrderSeeder::class,
            // PosTestSeeder::class,
        ]);
    }
}
