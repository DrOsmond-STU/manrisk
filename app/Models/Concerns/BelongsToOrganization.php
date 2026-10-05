<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Semua data bisnis terikat pada satu organisasi (tenant). Global scope memastikan
 * query hanya mengembalikan data organisasi pengguna yang sedang masuk. Saat membuat
 * data, organization_id SELALU dipaksa mengikuti organisasi pengguna yang masuk sehingga
 * nilai dari input apa pun tidak dapat memindahkan data ke tenant lain.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());
        static::creating(function ($model) {
            if ($user = auth()->user()) {
                $model->organization_id = $user->organization_id;
            }
        });
    }

    public function initializeBelongsToOrganization(): void
    {
        if (!in_array('organization_id', $this->fillable, true)) {
            $this->fillable[] = 'organization_id';
        }
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
