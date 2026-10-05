<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    use BelongsToOrganization, Auditable;

    protected $fillable = ['user_id', 'type', 'format', 'frequency', 'day', 'recipients', 'params', 'active', 'last_sent_at', 'last_error'];

    protected $casts = ['recipients' => 'array', 'params' => 'array', 'active' => 'boolean', 'last_sent_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDue(\Carbon\CarbonInterface $now): bool
    {
        if (!$this->active || ($this->last_sent_at && $this->last_sent_at->isSameDay($now))) {
            return false;
        }
        return $this->frequency === 'weekly' ? $now->dayOfWeekIso === max(1, min(7, $this->day)) : $now->day === $this->day;
    }
}
