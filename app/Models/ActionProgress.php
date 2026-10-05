<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionProgress extends Model
{
    use HasFactory;

    protected $table = 'action_progress';

    protected $fillable = [
        'action_plan_id',
        'user_id',
        'from_pct',
        'to_pct',
        'note',
    ];

    public function actionPlan(): BelongsTo
    {
        return $this->belongsTo(ActionPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
