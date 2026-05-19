<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['entity_id', 'name', 'currency', 'amount', 'is_main'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'is_main' => 'boolean',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactionsFrom(): HasMany
    {
        return $this->hasMany(Transaction::class, 'from_account_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactionsTo(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    public function isMain(): bool
    {
        return $this->is_main;
    }

    /**
     * Current balance = opening (column `amount`) + Σ transactions arriving − Σ transactions leaving.
     * Uses pre-loaded `incoming_sum` / `outgoing_sum` aggregates if present (via withSum), otherwise queries.
     */
    public function currentBalance(?int $excludeTransactionId = null): float
    {
        $opening = (float) $this->amount;

        if (
            $excludeTransactionId === null
            && array_key_exists('incoming_sum', $this->attributes)
            && array_key_exists('outgoing_sum', $this->attributes)
        ) {
            return $opening + (float) $this->attributes['incoming_sum'] - (float) $this->attributes['outgoing_sum'];
        }

        $in = $this->transactionsTo()
            ->when($excludeTransactionId !== null, fn ($q) => $q->where('id', '!=', $excludeTransactionId))
            ->sum('amount');
        $out = $this->transactionsFrom()
            ->when($excludeTransactionId !== null, fn ($q) => $q->where('id', '!=', $excludeTransactionId))
            ->sum('amount');

        return $opening + (float) $in - (float) $out;
    }
}
