<?php

namespace App\Support;

class FeatureAccess
{
    /**
     * Daftar feature key yang diblokir (dari config/features.php ← .env).
     *
     * @return array<int, string>
     */
    public static function blocked(): array
    {
        return array_map('strtolower', config('features.blocked', []));
    }

    /**
     * Apakah sebuah feature (mis. "rewards") diblokir.
     */
    public static function isFeatureBlocked(string $feature): bool
    {
        return in_array(strtolower(trim($feature)), self::blocked(), true);
    }

    /**
     * Apakah nama route termasuk feature yang diblokir. Feature key diambil dari
     * segmen setelah "admin.", mis. "admin.rewards.index" -> "rewards".
     */
    public static function isRouteBlocked(?string $routeName): bool
    {
        if ($routeName === null || ! str_starts_with($routeName, 'admin.')) {
            return false;
        }

        $segments = explode('.', $routeName);
        $feature = $segments[1] ?? null;

        return $feature !== null && self::isFeatureBlocked($feature);
    }
}
