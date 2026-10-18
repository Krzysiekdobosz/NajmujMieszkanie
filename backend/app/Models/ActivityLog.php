<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    // Tabela ma tylko kolumnę created_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'description',
        'subject_type', 'subject_id',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
