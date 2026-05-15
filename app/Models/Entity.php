<?php

namespace App\Models;

use App\Enums\EntityColor;
use App\Enums\EntityType;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'type', 'color', 'user_id'])]
class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => EntityType::class,
            'color' => EntityColor::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasOne<Account, $this>
     */
    public function mainAccount(): HasOne
    {
        return $this->hasOne(Account::class)->where('is_main', true);
    }

    /**
     * @return HasOne<Counterparty, $this>
     */
    public function counterpartyMirror(): HasOne
    {
        return $this->hasOne(Counterparty::class);
    }

    /**
     * @return HasMany<PlannedTransaction, $this>
     */
    public function plannedTransactions(): HasMany
    {
        return $this->hasMany(PlannedTransaction::class, 'owner_entity_id');
    }

    public function isPersonal(): bool
    {
        return $this->type === EntityType::PERSONAL;
    }
}
