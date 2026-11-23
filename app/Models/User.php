<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const AVAILABLE_ROLES = [
        'Superuser', 'Dinas', 'Dinas Kesehatan', 'Perencanaan', 'Kepala Puskesmas', 'Kepala Labkes', 'Kasubbag TU',
        'Pejabat Keuangan', 'Bendahara Pengeluaran', 'Bendahara Penerimaan', 'PPTK',
        'Klaster 1', 'Klaster 2', 'Klaster 3', 'Klaster 4', 'Lintas Klaster',
    ];

    public const PUSKESMAS_ROLES = [
        'Kasubbag TU', 'Pejabat Keuangan', 'Bendahara Pengeluaran',
        'Bendahara Penerimaan', 'PPTK', 'Klaster 1', 'Klaster 2', 'Klaster 3', 'Klaster 4', 'Lintas Klaster',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'upt',
        'nip',
        'role',
        'roles',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'roles' => 'array',
    ];

    public function roleNames(): array
    {
        $roles = $this->roles;
        if (!is_array($roles) || $roles === []) $roles = [$this->role];
        $names = [];
        foreach ($roles as $role) {
            if (is_string($role) && trim($role) !== '') $names[strtolower(trim($role))] = trim($role);
        }

        return array_values($names);
    }

    public function hasRole(string $role): bool
    {
        return $this->hasAnyRole([$role]);
    }

    public function hasAnyRole(array $roles): bool
    {
        $normalize = fn ($role) => strtolower(trim((string) $role));

        return array_intersect(array_map($normalize, $this->roleNames()), array_map($normalize, $roles)) !== [];
    }

    public function isDinasKesehatan(): bool
    {
        return $this->hasAnyRole(['Dinas', 'Dinas Kesehatan', 'Perencanaan'])
            || strcasecmp(trim((string) $this->upt), 'Dinas Kesehatan') === 0;
    }

    public function scopeWithRole(Builder $query, string $role): Builder
    {
        $canonical = collect(self::AVAILABLE_ROLES)->first(fn ($name) => strcasecmp($name, trim($role)) === 0) ?? trim($role);

        return $query->where(function ($query) use ($role, $canonical) {
            $query->whereJsonContains('roles', $canonical)->orWhere(function ($query) use ($role) {
                $query->where(fn ($query) => $query->whereNull('roles')->orWhereJsonLength('roles', 0))
                    ->whereRaw('LOWER(TRIM(role)) = ?', [strtolower(trim($role))]);
            });
        });
    }
}
