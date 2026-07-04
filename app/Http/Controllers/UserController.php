<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\InternalUser;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AccurateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected $accurateService;

    public function __construct(AccurateService $accurateService)
    {
        $this->accurateService = $accurateService;
    }

    // HALAMAN USER MANAGEMENT
    public function index(Request $request)
    {
        $query = User::with(['profile', 'internalUser.area', 'roles'])
            ->whereHas('internalUser');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('roles', function ($roleQuery) use ($search) {
                        $roleQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $users = $query->latest()->get();
        $totalUsers = User::whereHas('internalUser')->count();
        $activeUsers = User::whereHas('internalUser', function ($q) {
            $q->where('is_active', true);
        })->count();
        $inactiveUsers = $totalUsers - $activeUsers;

        $roles = Role::all();
        $areas = Area::where('is_active', true)->orderBy('sort_order')->get();

        return view('users.index', compact('users', 'totalUsers', 'activeUsers', 'inactiveUsers', 'roles', 'areas'));
    }

    // CREATE USER/STAF INTERNAL
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'role_id' => 'required|exists:roles,id',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
        ]);
        $accurateId = null;
        try {
            DB::beginTransaction();
            $response = $this->accurateService->saveEmployee([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'position' => Role::findById($validated['role_id'])->name,
            ]);
            $accurateId = $response['r']['id'];

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $profile = UserProfile::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
            ]);
            InternalUser::create([
                'accurate_id' => $accurateId,
                'user_id' => $user->id,
                'user_profile_id' => $profile->id,
                'area_id' => $validated['area_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);
            $role = Role::findById($validated['role_id']);
            $user->assignRole($role);
            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', 'User berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($accurateId !== null) {
                $this->accurateService->deleteEmployee((int) $accurateId);
            }

            return back()->withErrors(['error' => 'Gagal menambahkan user: '.$e->getMessage()]);
        }
    }

    // UPDATE USER/STAF INTERNAL
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'role_id' => 'required|exists:roles,id',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];
            if (! empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            $accurateId = $user->internalUser?->accurate_id;

            if ($accurateId !== null) {
                $this->accurateService->saveEmployee([
                    'id' => (int) $accurateId,
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'position' => Role::findById($validated['role_id'])->name,
                ]);
            }
            $user->update($userData);
            $user->profile->update([
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
            ]);

            // Update internal user
            $user->internalUser->update([
                'area_id' => $validated['area_id'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Update role
            $user->syncRoles([]);
            $role = Role::findById($validated['role_id']);
            $user->assignRole($role);

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', 'User berhasil diupdate');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal mengupdate user: '.$e->getMessage()]);
        }
    }

    public function syncAccurateEmployees(Request $request)
    {
        try {
            $employees = $this->accurateService->getEmployees($request);

            if ($employees->isEmpty()) {
                return $this->respondSyncAccurateEmployees(
                    $request,
                    true,
                    'Tidak ada data employee dari Accurate untuk disinkronkan.'
                );
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;

            DB::transaction(function () use ($employees, &$created, &$updated, &$skipped): void {
                foreach ($employees as $employee) {
                    $employeeData = is_array($employee) ? $employee : (array) $employee;
                    $accurateId = (int) ($employeeData['id'] ?? 0);

                    if ($accurateId <= 0) {
                        $skipped++;

                        continue;
                    }

                    $name = trim((string) ($employeeData['name'] ?? $employeeData['fullName'] ?? "Employee {$accurateId}"));
                    $rawEmail = trim((string) ($employeeData['email'] ?? ''));
                    $baseEmail = $rawEmail !== '' ? $rawEmail : "accurate.employee.{$accurateId}@local.invalid";

                    $internalUser = InternalUser::query()->where('accurate_id', $accurateId)->first();

                    if ($internalUser) {
                        $user = $internalUser->user;

                        if (! $user) {
                            $skipped++;

                            continue;
                        }

                        $resolvedEmail = $this->resolveUniqueEmail($baseEmail, $user->id);
                        $user->update([
                            'name' => $name,
                            'email' => $resolvedEmail,
                        ]);

                        if ($user->profile) {
                            $phone = $employeeData['mobilePhone'] ?? $employeeData['phone'] ?? null;

                            if (filled($phone)) {
                                $user->profile->update(['phone' => (string) $phone]);
                            }
                        }

                        $role = $this->resolveRoleFromEmployee($employeeData);
                        if ($role) {
                            $user->syncRoles([$role->name]);
                        }

                        $updated++;

                        continue;
                    }

                    $resolvedEmail = $this->resolveUniqueEmail($baseEmail);
                    $user = User::query()->where('email', $resolvedEmail)->first();

                    if (! $user) {
                        $user = User::create([
                            'name' => $name,
                            'email' => $resolvedEmail,
                            'password' => Hash::make('AccurateSync#'.bin2hex(random_bytes(8))),
                        ]);
                        $created++;
                    } else {
                        $user->update([
                            'name' => $name,
                            'email' => $resolvedEmail,
                        ]);
                        $updated++;
                    }

                    $phone = $employeeData['mobilePhone'] ?? $employeeData['phone'] ?? null;
                    $profile = $user->profile;

                    if (! $profile) {
                        $profile = UserProfile::create([
                            'user_id' => $user->id,
                            'phone' => filled($phone) ? (string) $phone : null,
                        ]);
                    } elseif (filled($phone)) {
                        $profile->update(['phone' => (string) $phone]);
                    }

                    InternalUser::query()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'accurate_id' => $accurateId,
                            'user_profile_id' => $profile->id,
                            'is_active' => true,
                        ]
                    );

                    $role = $this->resolveRoleFromEmployee($employeeData);
                    if ($role) {
                        $user->syncRoles([$role->name]);
                    }
                }
            });

            $message = "Sync employee Accurate berhasil. Baru: {$created}, Update: {$updated}, Lewati: {$skipped}.";
            $output = "Baru: {$created}\nUpdate: {$updated}\nLewati: {$skipped}";

            return $this->respondSyncAccurateEmployees($request, true, $message, $output);
        } catch (\Exception $e) {
            return $this->respondSyncAccurateEmployees(
                $request,
                false,
                'Gagal sync employee Accurate: '.$e->getMessage(),
                null,
                500
            );
        }
    }

    private function respondSyncAccurateEmployees(Request $request, bool $success, string $message, ?string $output = null, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'output' => $output,
            ], $status);
        }

        if ($success) {
            return redirect()->route('admin.users.index')->with('success', $message);
        }

        return back()->withErrors(['error' => $message]);
    }

    private function resolveRoleFromEmployee(array $employeeData): ?Role
    {
        $position = trim((string) ($employeeData['position'] ?? $employeeData['positionName'] ?? $employeeData['jobTitle'] ?? ''));

        if ($position === '') {
            return Role::query()->where('name', 'Cashier')->first()
                ?? Role::query()->first();
        }

        $normalizedPosition = strtolower($position);

        $exactRole = Role::query()->get()->first(function (Role $role) use ($normalizedPosition) {
            return strtolower((string) $role->name) === $normalizedPosition;
        });

        return $exactRole
            ?? Role::query()->where('name', 'Cashier')->first()
            ?? Role::query()->first();
    }

    private function resolveUniqueEmail(string $email, ?int $ignoreUserId = null): string
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! str_contains($normalized, '@')) {
            $normalized = 'accurate.sync@local.invalid';
        }

        [$localPart, $domainPart] = explode('@', $normalized, 2);
        $candidate = $localPart.'@'.$domainPart;
        $counter = 1;

        while (true) {
            $query = User::query()->where('email', $candidate);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            if (! $query->exists()) {
                return $candidate;
            }

            $candidate = $localPart.'+'.$counter.'@'.$domainPart;
            $counter++;

            if ($counter > 100) {
                return 'accurate.sync.'.uniqid().'@local.invalid';
            }
        }
    }

    // HAPUS USER/STAF INTERNAL
    public function destroy(Request $request, User $user)
    {
        try {
            $accurateId = $user->internalUser?->accurate_id;

            if ($accurateId !== null && !$request->has('force')) {
                $this->accurateService->deleteEmployee((int) $accurateId);
            }
            $user->delete();

            return redirect()->route('admin.users.index')
                ->with('success', 'User berhasil dihapus');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '23000') {
                return back()->withErrors(['error' => 'Gagal menghapus user: User ini sudah terhubung dengan data transaksi (seperti order). Silakan ubah status user menjadi "Inactive" melalui menu Edit daripada menghapusnya.']);
            }
            return back()->withErrors(['error' => 'Gagal menghapus user karena ada data terkait: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Check if error is related to Accurate
            $isAccurateError = str_contains(strtolower($errorMessage), 'accurate') || 
                               str_contains(strtolower($errorMessage), 'tidak ditemukan') || 
                               str_contains(strtolower($errorMessage), 'sudah dihapus') || 
                               str_contains(strtolower($errorMessage), 'host database');
                               
            if ($isAccurateError) {
                return back()
                    ->with('accurate_error_id', $user->id)
                    ->with('accurate_error_message', $errorMessage);
            }
            
            return back()->withErrors(['error' => 'Gagal menghapus user: ' . $errorMessage]);
        }
    }
}
