<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PlannedTransactionDirection;
use App\Enums\PlannedTransactionStatus;
use Database\Factories\PlannedTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_entity_id',
    'counterparty_id',
    'direction',
    'amount',
    'currency',
    'due_date',
    'purpose',
    'status',
    'is_mandatory',
    'note',
    'transfer_group_id',
    'deletion_reason',
])]
class PlannedTransaction extends Model
{
    /** @use HasFactory<PlannedTransactionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'direction' => PlannedTransactionDirection::class,
            'status' => PlannedTransactionStatus::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'is_mandatory' => 'boolean',
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

    public function isIncoming(): bool
    {
        return $this->direction === PlannedTransactionDirection::INCOMING;
    }

    public function isOutgoing(): bool
    {
        return $this->direction === PlannedTransactionDirection::OUTGOING;
    }

    public function isPartOfTransfer(): bool
    {
        return $this->transfer_group_id !== null;
    }
}
