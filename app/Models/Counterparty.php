<?php

namespace App\Models;

use App\Enums\CounterpartyKind;
use Database\Factories\CounterpartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'kind', 'entity_id'])]
class Counterparty extends Model
{
    /** @use HasFactory<CounterpartyFactory> */
    use HasFactory;

    protected $table = 'counterparties';

    protected function casts(): array
    {
        return [
            'kind' => CounterpartyKind::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return HasMany<PlannedTransaction, $this>
     */
    public function plannedTransactions(): HasMany
    {
        return $this->hasMany(PlannedTransaction::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isInternal(): bool
    {
        return $this->kind === CounterpartyKind::INTERNAL;
    }

    public function displayName(): string
    {
        return $this->isInternal() && $this->entity
            ? $this->entity->name
            : $this->name;
    }
}
