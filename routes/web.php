<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\PlannedTransactionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('entities', EntityController::class)->except(['show']);
    Route::post('entities/{entity}/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    Route::get('planned-transactions', [PlannedTransactionController::class, 'index'])
        ->name('planned-transactions.index');
    Route::post('planned-transactions', [PlannedTransactionController::class, 'store'])
        ->name('planned-transactions.store');
    Route::put('planned-transactions/{plannedTransaction}', [PlannedTransactionController::class, 'update'])
        ->name('planned-transactions.update');
    Route::delete('planned-transactions/{plannedTransaction}', [PlannedTransactionController::class, 'destroy'])
        ->name('planned-transactions.destroy');

    Route::post('planned-transactions/{plannedTransaction}/transactions', [TransactionController::class, 'store'])
        ->name('planned-transactions.transactions.store');
});

require __DIR__.'/settings.php';
