<?php

namespace App\Actions;

use App\Events\AccountDeleted;
use App\Models\Account;

class DeleteAccount
{
    public function execute(Account $account): void
    {
        $accountId = $account->id;
        $entityId = $account->entity_id;

        $account->delete();

        AccountDeleted::dispatch($accountId, $entityId);
    }
}
