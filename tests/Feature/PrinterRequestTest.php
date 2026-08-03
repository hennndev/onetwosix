<?php

use App\Http\Requests\PrinterRequest;
use App\Models\Area;
use Illuminate\Support\Facades\Validator;

test('printer request accepts checker, kitchen, bar, cashier and area codes as valid locations', function () {
    Area::create([
        'code' => 'ROOM',
        'name' => 'Room',
        'capacity' => 10,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $request = new PrinterRequest;
    $rules = $request->rules()['location'];

    foreach (['checker', 'kitchen', 'bar', 'cashier', 'ROOM', 'room'] as $loc) {
        $validator = Validator::make(['location' => $loc], ['location' => $rules]);
        expect($validator->passes())->toBeTrue("Failed validating location: {$loc}");
    }
});
