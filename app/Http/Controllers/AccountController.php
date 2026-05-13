<?php

namespace App\Http\Controllers;

use App\Actions\CreateAccount;
use App\Actions\DeleteAccount;
use App\Actions\UpdateAccount;
use App\Enums\Currency;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AccountController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    public function store(StoreAccountRequest $request, Entity $entity, CreateAccount $createAccount): RedirectResponse
    {
        Gate::authorize('update', $entity);
        Gate::authorize('create', Account::class);

        $createAccount->execute(
            $entity,
            (string) $request->string('name'),
            Currency::from((string) $request->string('currency')),
            amount: (float) $request->input('amount', 0),
            isMain: filter_var($request->input('is_main'), FILTER_VALIDATE_BOOLEAN),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account added.')]);

        return back();
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccount $updateAccount): RedirectResponse
    {
        Gate::authorize('update', $account);

        $updateAccount->execute(
            $account,
            Currency::from($request->string('currency')),
            (float) $request->input('amount'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return back();
    }

    public function destroy(Account $account, DeleteAccount $deleteAccount): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $deleteAccount->execute($account);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return back();
    }
}
