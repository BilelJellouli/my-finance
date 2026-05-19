<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\PlannedTransactionController;
use App\Http\Controllers\RecurringPlanController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    Route::get('recurring-plans', [RecurringPlanController::class, 'index'])
        ->name('recurring-plans.index');
    Route::get('recurring-plans/{recurringPlan}', [RecurringPlanController::class, 'show'])
        ->name('recurring-plans.show');
    Route::post('recurring-plans', [RecurringPlanController::class, 'store'])
        ->name('recurring-plans.store');
    Route::put('recurring-plans/{recurringPlan}', [RecurringPlanController::class, 'update'])
        ->name('recurring-plans.update');
    Route::post('recurring-plans/{recurringPlan}/phases', [RecurringPlanController::class, 'addPhase'])
        ->name('recurring-plans.phases.store');
    Route::post('recurring-plans/{recurringPlan}/pause', [RecurringPlanController::class, 'pause'])
        ->name('recurring-plans.pause');
    Route::post('recurring-plans/{recurringPlan}/resume', [RecurringPlanController::class, 'resume'])
        ->name('recurring-plans.resume');
    Route::post('recurring-plans/{recurringPlan}/end', [RecurringPlanController::class, 'end'])
        ->name('recurring-plans.end');
    Route::delete('recurring-plans/{recurringPlan}', [RecurringPlanController::class, 'destroy'])
        ->name('recurring-plans.destroy');
});

require __DIR__.'/settings.php';
