<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeneralSetting extends Model
{
    protected $fillable = [
        'tax_percentage',
        'service_charge_percentage',
        'accurate_tax_account_no',
        'accurate_service_charge_account_no',
        'accurate_bank_account_no',
        'accurate_cash_account_no',
        'accurate_stock_warehouse_name',
        'can_choose_checker',
        'closed_billing_receipt_printer_id',
        'walk_in_receipt_printer_id',
        'end_day_receipt_printer_id',
        'end_day_kitchen_printer_id',
        'end_day_bar_printer_id',
        'area_printer_settings',
        'mail_provider',
        'auth_code_target_email',
        'auth_code_target_whatsapp',
        'fonnte_token',
        'auth_code_delivery_channel',
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
            'area_printer_settings' => 'array',
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
            'accurate_tax_account_no' => '210201',
            'accurate_service_charge_account_no' => '210202',
            'accurate_bank_account_no' => '110102',
            'accurate_cash_account_no' => '110101',
            'accurate_stock_warehouse_name' => 'GD. OUTLET',
            'can_choose_checker' => false,
            'closed_billing_receipt_printer_id' => null,
            'walk_in_receipt_printer_id' => null,
            'end_day_receipt_printer_id' => null,
            'end_day_kitchen_printer_id' => null,
            'end_day_bar_printer_id' => null,
            'mail_provider' => 'smtp',
            'auth_code_target_email' => null,
            'auth_code_target_whatsapp' => null,
            'fonnte_token' => null,
            'auth_code_delivery_channel' => 'both',
            'daily_auth_code_access_emails' => null,
        ]);
    }

    /**
     * Get accurate warehouse name prioritizing GeneralSetting database configuration over config/env fallback.
     */
    public function getAccurateWarehouseName(): string
    {
        if (filled($this->accurate_stock_warehouse_name)) {
            return trim((string) $this->accurate_stock_warehouse_name);
        }

        return (string) (config('accurate.stock_warehouse_name') ?: 'GD. OUTLET');
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

    /**
     * Resolve default printer ID for an area and printer target type.
     * Falls back to global default printer setting if area-specific printer is not set.
     */
    public function getPrinterIdForArea(?int $areaId, string $printerType): ?int
    {
        $areaSettings = $this->area_printer_settings ?? [];

        if ($areaId && ! empty($areaSettings[$areaId][$printerType])) {
            $printerId = (int) $areaSettings[$areaId][$printerType];
            if ($printerId > 0) {
                return $printerId;
            }
        }

        $globalId = match ($printerType) {
            'closed_billing' => $this->closed_billing_receipt_printer_id,
            'walk_in' => $this->walk_in_receipt_printer_id,
            'end_day_receipt' => $this->end_day_receipt_printer_id,
            'end_day_kitchen' => $this->end_day_kitchen_printer_id,
            'end_day_bar' => $this->end_day_bar_printer_id,
            default => null,
        };

        return $globalId ? (int) $globalId : null;
    }
}
