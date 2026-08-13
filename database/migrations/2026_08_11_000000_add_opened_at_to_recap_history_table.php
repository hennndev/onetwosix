<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recap_history', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('end_day');
        });

        // Backfill: opened_at hari X = created_at recap sebelumnya (saat window X dibuka),
        // per-area (area_id null = global). Row pertama tiap area = anchor operational_start_time (fallback 09:00).
        $startTime = \App\Models\GeneralSetting::query()->value('operational_anchor_time') ?? '09:00';
        [$hour, $minute] = array_map('intval', explode(':', $startTime));

        $areaKeys = DB::table('recap_history')
            ->distinct()
            ->pluck('area_id')
            ->map(fn ($id) => $id === null ? '' : (string) $id)
            ->values();

        foreach ($areaKeys as $areaKey) {
            $prevCreatedAt = null;

            $rows = DB::table('recap_history')
                ->when($areaKey === '', fn ($q) => $q->whereNull('area_id'), fn ($q) => $q->where('area_id', (int) $areaKey))
                ->orderBy('end_day')
                ->get();

            foreach ($rows as $row) {
                $openedAt = $prevCreatedAt;

                if ($openedAt === null) {
                    $openedAt = \Illuminate\Support\Carbon::parse($row->end_day, 'Asia/Jakarta')
                        ->setTime($hour, $minute, 0)
                        ->toDateTimeString();
                }

                DB::table('recap_history')
                    ->where('id', $row->id)
                    ->update(['opened_at' => $openedAt]);

                $prevCreatedAt = $row->created_at;
            }
        }
    }

    public function down(): void
    {
        Schema::table('recap_history', function (Blueprint $table) {
            $table->dropColumn('opened_at');
        });
    }
};
