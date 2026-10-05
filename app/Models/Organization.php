<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'code',
        'logo_path',
        'settings',
        'active',
    ];

    protected $casts = [
        'settings' => 'array',
        'active' => 'boolean',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(OrgUnit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
