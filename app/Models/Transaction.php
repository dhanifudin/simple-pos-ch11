<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'invoice_no', 'total', 'discount', 'tax', 'payment_method', 'amount_paid', 'change_due',
        'status', 'voided_by', 'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'amount_paid' => 'integer',
            'change_due' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'selesai');
    }

    public function isVoided(): bool
    {
        return $this->status === 'dibatalkan';
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'qris' => 'QRIS',
            'kartu' => 'Kartu Debit/Kredit',
            default => 'Tunai',
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }
}
