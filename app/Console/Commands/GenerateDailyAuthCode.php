<?php

namespace App\Console\Commands;

use App\Models\DailyAuthCode;
use Illuminate\Console\Command;

class GenerateDailyAuthCode extends Command
{
    protected $signature = 'auth:generate-daily-code';

    protected $description = 'Generate a new random daily auth code for today';

    public function handle(): int
    {
        $today = now()->format('Y-m-d');

        $record = DailyAuthCode::firstOrNew(['date' => $today]);

        $newCode = DailyAuthCode::generateRandom();

        $record->fill([
            'code' => $newCode,
            'override_code' => null,
            'generated_at' => now(),
        ])->save();

        $this->info("Daily auth code for {$today}: {$newCode}");

        return self::SUCCESS;
    }
}
