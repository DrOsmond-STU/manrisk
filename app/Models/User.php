<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, BelongsToOrganization, Auditable;

    public const ROLES = [
        'super_admin' => 'Super Admin',
        'risk_admin' => 'Risk Administrator',
        'risk_manager' => 'Risk Manager',
        'risk_officer' => 'Risk Officer',
        'risk_owner' => 'Risk Owner',
        'management' => 'Management',
        'auditor' => 'Auditor',
    ];

    /** Peran yang cakupannya dibatasi pada unit kerja sendiri (dan sub-unit). */
    public const UNIT_SCOPED = ['risk_officer', 'risk_owner'];

    protected $fillable = ['organization_id', 'unit_id', 'name', 'email', 'password', 'role', 'position', 'scope_units', 'active', 'must_change_password', 'password_changed_at', 'last_login_at', 'last_login_ip', 'preferences'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['active' => true, 'must_change_password' => false, 'role' => 'risk_officer'];

    protected $auditExclude = ['last_login_at', 'last_login_ip', 'preferences', 'password_changed_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'scope_units' => 'array',
            'preferences' => 'array',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'unit_id');
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isReadOnly(): bool
    {
        return $this->hasRole('management', 'auditor');
    }

    public function isUnitScoped(): bool
    {
        return in_array($this->role, self::UNIT_SCOPED, true);
    }

    /** Id unit yang boleh diakses pengguna bercakupan unit; null = seluruh organisasi. */
    public function accessibleUnitIds(): ?array
    {
        if (!$this->isUnitScoped()) {
            return null;
        }
        $ids = collect($this->scope_units ?? []);
        if ($this->unit) {
            $ids = $ids->merge($this->unit->descendantIds());
        }
        return $ids->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function canAccessUnit(?int $unitId): bool
    {
        $ids = $this->accessibleUnitIds();
        return $ids === null || ($unitId !== null && in_array($unitId, $ids, true));
    }
}
