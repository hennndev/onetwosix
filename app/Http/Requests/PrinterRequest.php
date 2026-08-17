<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $serviceLocations = ['kitchen', 'bar', 'cashier', 'checker'];
        $areaCodes = \App\Models\Area::where('is_active', true)->pluck('code')->filter()->map(fn ($c) => (string) $c)->toArray();
        $areaCodesLower = array_map('strtolower', $areaCodes);
        $areaCodesUpper = array_map('strtoupper', $areaCodes);
        $validLocations = array_unique(array_merge($serviceLocations, $areaCodes, $areaCodesLower, $areaCodesUpper));

        $ownId = $this->route('printer')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'in:'.implode(',', $validLocations)],
            'area_id' => ['nullable', 'exists:areas,id'],
            'printer_type' => ['nullable', 'string', 'in:kitchen,bar,cashier,checker'],
            'connection_type' => ['required', 'in:network,file,windows,log'],
            'ip' => ['required_if:connection_type,network', 'nullable', 'ip'],
            'port' => ['required_if:connection_type,network', 'nullable', 'integer', 'min:1', 'max:65535'],
            'path' => ['required_if:connection_type,file,windows', 'nullable', 'string', 'max:255'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'header' => ['nullable', 'string', 'max:255'],
            'footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048', 'dimensions:max_width=300,max_height=200'],
            'show_qr_code' => ['boolean'],
            'width' => ['nullable', 'integer', 'min:24', 'max:48'],
            'receiver_printer_id' => [
                'nullable', 'integer', 'exists:printers,id',
                Rule::notIn($ownId ? [$ownId] : []),
            ],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Printer name is required.',
            'location.in' => 'Please select a valid location from the list.',
            'connection_type.required' => 'Connection type is required.',
            'ip.required_if' => 'IP address is required for network connection.',
            'path.required_if' => 'Path is required for file/windows connection.',
            'logo.image' => 'Logo must be an image file.',
            'logo.max' => 'Logo file size must not exceed 2MB.',
            'logo.dimensions' => 'Logo dimensions should not exceed 300x200 pixels.',
        ];
    }
}
