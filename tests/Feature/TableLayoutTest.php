<?php

use App\Models\Area;
use App\Models\InternalUser;
use App\Models\Tabel;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function layoutArea(array $attributes = []): Area
{
    return Area::create(array_merge([
        'code' => 'MAIN',
        'name' => 'Main Area',
        'capacity' => 40,
        'is_active' => true,
        'sort_order' => 1,
    ], $attributes));
}

function layoutTable(Area $area, array $attributes = []): Tabel
{
    return Tabel::create(array_merge([
        'area_id' => $area->id,
        'table_number' => 'A01',
        'qr_code' => 'QR-LAYOUT-'.fake()->unique()->numerify('#####'),
        'capacity' => 4,
        'status' => 'available',
        'is_active' => true,
    ], $attributes));
}

it('uploads and replaces an area floor plan safely', function () {
    Storage::fake('public');
    $admin = adminUser();

    actingAs($admin)->post(route('admin.areas.store'), [
        'code' => 'ROOM',
        'name' => 'Room Area',
        'capacity' => 30,
        'sort_order' => 1,
        'is_active' => '1',
        'image' => UploadedFile::fake()->image('room-plan.jpg', 1200, 800),
    ])->assertRedirect(route('admin.areas.index'));

    $area = Area::where('code', 'ROOM')->firstOrFail();
    $oldImage = $area->image;
    expect($oldImage)->not->toBeNull();
    Storage::disk('public')->assertExists($oldImage);

    actingAs($admin)->put(route('admin.areas.update', $area), [
        'code' => 'ROOM',
        'name' => 'Room Area',
        'capacity' => 30,
        'sort_order' => 1,
        'is_active' => '1',
        'image' => UploadedFile::fake()->image('new-room-plan.png', 1200, 800),
    ])->assertRedirect(route('admin.areas.index'));

    $newImage = $area->refresh()->image;
    expect($newImage)->not->toBe($oldImage);
    Storage::disk('public')->assertExists($newImage);
    Storage::disk('public')->assertMissing($oldImage);
});

it('accepts the active checkbox value submitted by the area form', function () {
    Storage::fake('public');
    $admin = adminUser();

    actingAs($admin)->post(route('admin.areas.store'), [
        'code' => 'ACTIVE',
        'name' => 'Active Area',
        'is_active' => '1',
        'image' => UploadedFile::fake()->image('active-floor-plan.jpg'),
    ])->assertRedirect(route('admin.areas.index'))
        ->assertSessionHasNoErrors();

    expect(Area::where('code', 'ACTIVE')->firstOrFail()->is_active)->toBeTrue();
});

it('rejects invalid floor plan uploads', function () {
    Storage::fake('public');
    $admin = adminUser();

    actingAs($admin)->from(route('admin.areas.index'))->post(route('admin.areas.store'), [
        'code' => 'ROOM',
        'name' => 'Room Area',
        'image' => UploadedFile::fake()->create('floor-plan.svg', 20, 'image/svg+xml'),
    ])->assertRedirect(route('admin.areas.index'))
        ->assertSessionHasErrors('image');

    expect(Area::where('code', 'ROOM')->exists())->toBeFalse();
});

it('shows the layout editor in table management and persists normalized positions', function () {
    $admin = adminUser();
    $area = layoutArea();
    $table = layoutTable($area);

    actingAs($admin)->get(route('admin.tables.index'))
        ->assertOk()
        ->assertSee('Atur Denah');

    actingAs($admin)->get(route('admin.tables.layout'))
        ->assertOk()
        ->assertSee('Atur Denah Meja')
        ->assertSee($table->table_number);

    actingAs($admin)->postJson(route('admin.tables.layout.update'), [
        'tables' => [[
            'id' => $table->id,
            'position_x' => 42.125,
            'position_y' => 67.75,
        ]],
    ])->assertOk()
        ->assertExactJson(['message' => 'Posisi meja berhasil disimpan.']);

    $table->refresh();
    expect($table->position_x)->toBe(42.125)
        ->and($table->position_y)->toBe(67.75);
});

it('rejects duplicate and out of range table positions', function () {
    $admin = adminUser();
    $area = layoutArea();
    $table = layoutTable($area);

    actingAs($admin)->postJson(route('admin.tables.layout.update'), [
        'tables' => [
            ['id' => $table->id, 'position_x' => -1, 'position_y' => 50],
            ['id' => $table->id, 'position_x' => 20, 'position_y' => 101],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'tables.0.id',
            'tables.0.position_x',
            'tables.1.id',
            'tables.1.position_y',
        ]);

    expect($table->refresh()->position_x)->toBeNull()
        ->and($table->position_y)->toBeNull();
});

it('prevents an area restricted administrator from moving another areas tables', function () {
    $firstArea = layoutArea();
    $secondArea = layoutArea(['code' => 'VIP', 'name' => 'VIP Area', 'sort_order' => 2]);
    $firstTable = layoutTable($firstArea);
    $secondTable = layoutTable($secondArea, ['table_number' => 'V01']);
    $admin = adminUser();
    $profile = UserProfile::create(['user_id' => $admin->id]);

    InternalUser::create([
        'user_id' => $admin->id,
        'user_profile_id' => $profile->id,
        'area_id' => $firstArea->id,
        'is_active' => true,
    ]);

    actingAs($admin)->get(route('admin.tables.layout'))
        ->assertOk()
        ->assertSee($firstTable->table_number)
        ->assertDontSee($secondTable->table_number);

    actingAs($admin)->postJson(route('admin.tables.layout.update'), [
        'tables' => [[
            'id' => $secondTable->id,
            'position_x' => 10,
            'position_y' => 20,
        ]],
    ])->assertForbidden();

    expect($secondTable->refresh()->position_x)->toBeNull();
});

it('renders a public floor plan preview with only positioned active tables', function () {
    $area = layoutArea(['image' => 'areas/main.jpg']);
    $positionedTable = layoutTable($area, ['position_x' => 30, 'position_y' => 40]);
    $unpositionedTable = layoutTable($area, ['table_number' => 'A02']);
    $inactiveTable = layoutTable($area, ['table_number' => 'A03', 'is_active' => false, 'position_x' => 50, 'position_y' => 50]);

    $tableUrl = route('denah.preview', [
        'area' => $area,
        'status' => $positionedTable->status,
        'table' => $positionedTable,
    ]);

    $this->get(route('denah.preview', $area))
        ->assertOk()
        ->assertDontSee('<header', false)
        ->assertDontSee('Posisi dan status meja terkini')
        ->assertDontSee('Maintenance')
        ->assertSee('floor-fixed', false)
        ->assertSee('width: 960px', false)
        ->assertSee($positionedTable->table_number)
        ->assertSee("handleChipClick('{$positionedTable->status}', {$positionedTable->id})", false)
        ->assertDontSee($unpositionedTable->table_number)
        ->assertDontSee($inactiveTable->table_number);

    $this->get($tableUrl)
        ->assertOk()
        ->assertSee($positionedTable->table_number);
});

it('rejects a preview URL when the table does not belong to the selected area', function () {
    $firstArea = layoutArea();
    $secondArea = layoutArea(['code' => 'SECOND', 'name' => 'Second Area']);
    $table = layoutTable($secondArea);

    $this->get(route('denah.preview', [
        'area' => $firstArea,
        'status' => $table->status,
        'table' => $table,
    ]))->assertNotFound();
});

it('rejects preview URLs with a status that does not match the table', function () {
    $area = layoutArea();
    $table = layoutTable($area, ['status' => 'available']);

    $this->get(route('denah.preview', [
        'area' => $area,
        'status' => 'reserved',
        'table' => $table,
    ]))->assertNotFound();
});

it('rejects direct preview URLs for inactive areas and tables', function () {
    $inactiveArea = layoutArea(['is_active' => false]);
    $activeArea = layoutArea(['code' => 'ACTIVE-AREA', 'name' => 'Active Area']);
    $inactiveTable = layoutTable($activeArea, ['is_active' => false]);

    $this->get(route('denah.preview', ['area' => $inactiveArea]))
        ->assertNotFound();

    $this->get(route('denah.preview', [
        'area' => $activeArea,
        'status' => $inactiveTable->status,
        'table' => $inactiveTable,
    ]))->assertNotFound();
});
