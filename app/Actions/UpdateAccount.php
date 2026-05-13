<?php

namespace App\Actions;

use App\Enums\Currency;
use App\Events\AccountUpdated;
use App\Models\Account;

class UpdateAccount
{
    public function execute(Account $account, Currency $currency, float $amount): Account
    {
        $account->update([
            'currency' => $currency,
            'amount' => $amount,
        ]);

        AccountUpdated::dispatch($account);

        return $account;
    }
}
