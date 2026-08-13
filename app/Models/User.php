<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'token_firebase',
    ];

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function internalUser()
    {
        return $this->hasOne(InternalUser::class);
    }

    public function customerUser()
    {
        return $this->hasOne(CustomerUser::class);
    }

    public function tableSessions()
    {
        return $this->hasMany(TableSession::class, 'customer_id');
    }

    /**
     * Determine if the user can switch between multiple areas (e.g. Super Admin / Admin with no assigned area).
     */
    public function hasMultiAreaAccess(): bool
    {
        return $this->internalUser?->area_id === null;
    }

    /**
     * Get the user's primary assigned area if restricted.
     */
    public function getAssignedArea(): ?Area
    {
        return $this->internalUser?->area;
    }

    /**
     * Get accessible areas collection for the user.
     *
     * @return \Illuminate\Support\Collection<int, Area>
     */
    public function getAccessibleAreas(): \Illuminate\Support\Collection
    {
        if (! $this->hasMultiAreaAccess() && $this->getAssignedArea()) {
            return collect([$this->getAssignedArea()]);
        }

        return Area::where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * Resolve the active area ID for the user given an optional requested area ID.
     */
    public function resolveActiveAreaId(mixed $requestedAreaId = null, bool $hasRequestKey = false): ?int
    {
        if (! $this->hasMultiAreaAccess() && $this->getAssignedArea()) {
            return $this->getAssignedArea()->id;
        }

        if ($hasRequestKey) {
            return ($requestedAreaId !== null && $requestedAreaId !== '' && $requestedAreaId !== 'all')
                ? (int) $requestedAreaId
                : null;
        }

        if ($requestedAreaId !== null && $requestedAreaId !== '' && $requestedAreaId !== 'all') {
            return (int) $requestedAreaId;
        }

        $sessionAreaId = session('active_area_id');

        if ($sessionAreaId && $sessionAreaId !== 'all') {
            return (int) $sessionAreaId;
        }

        return null;
    }

    /**
     * Resolve the active area model for the current user session.
     */
    public function resolveActiveArea(): ?Area
    {
        if (! $this->hasMultiAreaAccess() && $this->getAssignedArea()) {
            return $this->getAssignedArea();
        }

        $sessionAreaId = session('active_area_id');

        if ($sessionAreaId && $sessionAreaId !== 'all') {
            $area = Area::find($sessionAreaId);
            if ($area) {
                return $area;
            }
        }

        // 'all' (or no selection) means no specific area context.
        return null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
