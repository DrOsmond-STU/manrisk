<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/** Parameter penilaian Risk Maturity Index (disusun per dimensi; bobot relatif dalam dimensinya). */
class RmiParameter extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = ['dimension', 'code', 'name', 'guidance', 'weight', 'sort', 'active'];

    protected $casts = ['weight' => 'float', 'active' => 'boolean'];
}
