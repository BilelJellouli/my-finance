<?php

namespace App\Models;

use App\Enums\EntityColor;
use App\Enums\EntityType;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function isPersonal(): bool
    {
        return $this->type === EntityType::Personal;
    }
}
