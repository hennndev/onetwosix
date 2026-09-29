<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked Features
    |--------------------------------------------------------------------------
    |
    | Daftar "feature key" yang diblokir, dipisah koma di .env. Feature key =
    | segmen tengah nama route admin, mis. route `admin.rewards.*` -> key
    | `rewards`, `admin.transaction-history.*` -> `transaction-history`.
    |
    | Efek saat sebuah feature diblokir:
    |   1. Semua route `admin.<key>.*` mengembalikan 403 (berlaku untuk semua
    |      role, termasuk Administrator).
    |   2. Item sidebar untuk feature tersebut disembunyikan.
    |
    | Contoh .env:  BLOCKED_FEATURES=rewards,promos
    |
    */

    'blocked' => array_values(array_filter(array_map(
        fn (string $feature): string => trim($feature),
        explode(',', (string) env('BLOCKED_FEATURES', ''))
    ), fn (string $feature): bool => $feature !== '')),

];
