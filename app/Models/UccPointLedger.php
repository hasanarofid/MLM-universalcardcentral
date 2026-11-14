<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UccPointLedger extends Model
{
    protected $fillable = [
        'user_id',
        'source_user_id',
        'event_type',
        'gross_amount',
        'points_added',
        'saldo_tunai_added',
        'saldo_belanja_added',
        'saldo_upgrade_added',
        'saldo_reward_added',
        'saldo_induk_added',
        'description',
        'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'saldo_tunai_added' => 'decimal:2',
            'saldo_belanja_added' => 'decimal:2',
            'saldo_upgrade_added' => 'decimal:2',
            'saldo_reward_added' => 'decimal:2',
            'saldo_induk_added' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }
}

