<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeneralSetting extends Model
{
    protected $fillable = [
        'tax_percentage',
        'service_charge_percentage',
        'can_choose_checker',
        'closed_billing_receipt_printer_id',
        'walk_in_receipt_printer_id',
        'end_day_receipt_printer_id',
        'end_day_kitchen_printer_id',
        'end_day_bar_printer_id',
        'mail_provider',
        'auth_code_target_email',
        'daily_auth_code_access_emails',
    ];

    protected function casts(): array
    {
        return [
            'tax_percentage' => 'integer',
            'service_charge_percentage' => 'integer',
            'can_choose_checker' => 'boolean',
            'closed_billing_receipt_printer_id' => 'integer',
            'walk_in_receipt_printer_id' => 'integer',
            'end_day_receipt_printer_id' => 'integer',
            'end_day_kitchen_printer_id' => 'integer',
            'end_day_bar_printer_id' => 'integer',
        ];
    }

    /**
     * Always return the single settings row, creating it if missing.
     */
    public static function instance(): self
    {
        return self::firstOrCreate([], [
            'tax_percentage' => 0,
            'service_charge_percentage' => 0,
            'can_choose_checker' => false,
            'closed_billing_receipt_printer_id' => null,
            'walk_in_receipt_printer_id' => null,
            'end_day_receipt_printer_id' => null,
            'end_day_kitchen_printer_id' => null,
            'end_day_bar_printer_id' => null,
            'mail_provider' => 'smtp',
            'auth_code_target_email' => null,
            'daily_auth_code_access_emails' => null,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function dailyAuthCodeAccessEmails(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $this->daily_auth_code_access_emails, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): string => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function allowsDailyAuthCodeAccess(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return in_array(Str::lower(trim($email)), $this->dailyAuthCodeAccessEmails(), true);
    }
}
