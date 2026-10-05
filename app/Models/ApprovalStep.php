<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_id',
        'step_no',
        'role',
        'approver_id',
        'action',
        'note',
        'acted_at',
        'due_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
