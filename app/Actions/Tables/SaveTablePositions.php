<?php

namespace App\Actions\Tables;

use App\Models\Tabel;
use Illuminate\Support\Facades\DB;

class SaveTablePositions
{
    /**
     * @param  array<int, array{id: int, position_x: int|float|string, position_y: int|float|string}>  $positions
     */
    public function handle(array $positions): void
    {
        DB::transaction(function () use ($positions): void {
            $tables = Tabel::query()
                ->whereIn('id', collect($positions)->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($positions as $position) {
                $tables->get($position['id'])?->update([
                    'position_x' => round((float) $position['position_x'], 4),
                    'position_y' => round((float) $position['position_y'], 4),
                ]);
            }
        });
    }
}
