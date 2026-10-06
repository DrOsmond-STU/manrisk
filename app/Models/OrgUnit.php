<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrgUnit extends Model
{
    use HasFactory, BelongsToOrganization, Auditable, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'type',
        'path',
        'level',
        'head_user_id',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
    /** Semua id turunan (termasuk diri sendiri) berdasarkan path. */
    public function descendantIds(): array
    {
        return static::where('path', 'like', ($this->path ?? '/' . $this->id . '/') . '%')->pluck('id')->push($this->id)->unique()->values()->all();
    }


    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_id');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'unit_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /** Perbarui path/level berdasarkan induk, dan turunkan ke sub-unit. */
    public function refreshPath(): void
    {
        $parent = $this->parent_id ? static::find($this->parent_id) : null;
        $this->path = ($parent?->path ?? '/') . $this->id . '/';
        $this->level = $parent ? $parent->level + 1 : 0;
        $this->saveQuietly();
        foreach ($this->children()->get() as $child) {
            $child->refreshPath();
        }
    }
}
