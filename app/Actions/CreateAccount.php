<?php

namespace App\Actions;

use App\Enums\Currency;
use App\Events\AccountCreated;
use App\Models\Account;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;

class CreateAccount
{
    public function execute(
        Entity $entity,
        string $name,
        Currency $currency,
        float $amount = 0.0,
        bool $isMain = false,
    ): Account {
        $shouldBeMain = $isMain || ! $entity->accounts()->exists();

        $account = DB::transaction(function () use ($entity, $name, $currency, $amount, $shouldBeMain) {
            if ($shouldBeMain) {
                $entity->accounts()->where('is_main', true)->update(['is_main' => false]);
            }

            return $entity->accounts()->create([
                'name' => $name,
                'currency' => $currency,
                'amount' => $amount,
                'is_main' => $shouldBeMain,
            ]);
        });

        AccountCreated::dispatch($account);

        return $account;
    }
}
