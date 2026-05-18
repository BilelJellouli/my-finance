<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\RecurringPlanStatus;
use Database\Factories\RecurringPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_entity_id',
    'counterparty_id',
    'account_id',
    'direction',
    'currency',
    'label',
    'purpose',
    'is_mandatory',
    'status',
    'starts_on',
    'ends_on',
    'materialized_until',
    'note',
])]
class RecurringPlan extends Model
{
    /** @use HasFactory<RecurringPlanFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'direction' => PlannedTransactionDirection::class,
            'currency' => Currency::class,
            'status' => RecurringPlanStatus::class,
            'is_mandatory' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'materialized_until' => 'date',
        ];
    }

    public function ownerEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'owner_entity_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<RecurringPlanPhase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(RecurringPlanPhase::class);
    }

    /**
     * @return HasMany<PlannedTransaction, $this>
     */
    public function plannedTransactions(): HasMany
    {
        return $this->hasMany(PlannedTransaction::class);
    }

    public function currentPhase(): ?RecurringPlanPhase
    {
        return $this->phases()->whereNull('ends_on')->orderByDesc('starts_on')->first();
    }

    public function isActive(): bool
    {
        return $this->status === RecurringPlanStatus::ACTIVE;
    }
}
