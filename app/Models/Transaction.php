<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\TransactionKind;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'planned_transaction_id',
    'from_account_id',
    'to_account_id',
    'counterparty_id',
    'amount',
    'currency',
    'kind',
    'occurred_on',
    'note',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'kind' => TransactionKind::class,
            'occurred_on' => 'date',
        ];
    }

    public function plannedTransaction(): BelongsTo
    {
        return $this->belongsTo(PlannedTransaction::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function isAccountToAccount(): bool
    {
        return $this->from_account_id !== null && $this->to_account_id !== null;
    }

    public function isStandalone(): bool
    {
        return $this->planned_transaction_id === null;
    }
}
