<?php

use Illuminate\Support\Facades\Route;
use Wnikk\LaravelAccessUi\Http\Controllers\InheritController;
use Wnikk\LaravelAccessUi\Http\Controllers\OwnersController;
use Wnikk\LaravelAccessUi\Http\Controllers\PanelController;
use Wnikk\LaravelAccessUi\Http\Controllers\PermissionsController;
use Wnikk\LaravelAccessUi\Http\Controllers\PickerController;
use Wnikk\LaravelAccessUi\Http\Controllers\RulesController;

/*
|--------------------------------------------------------------------------
| Access UI
|--------------------------------------------------------------------------
|
| Loaded by AccessUiServiceProvider, which wraps this file in the prefix,
| middleware, domain and route-name group configured in config/accessUi.php.
| Nothing is registered at all until that configuration names both a prefix
| and a middleware stack — see the notes there.
|
| One HTML route; everything else answers JSON.
|
| `{owner}` is an owner id and nothing else. A host page that wants the
| assignment widget for one of its users gets that id from
| `$user->getOwner()->id` — the trait access-rules asks you to add anyway — so
| this package needs to know nothing about how the application stores people.
|
| The three inheritance routes serve both directions, chosen by a `direction`
| parameter: `children` for the panel's screen (who inherits from this owner)
| and `parents` for the widget (whom this owner inherits from). Same link, same
| table, same writes — asked from either end.
|
*/

Route::get('/', [PanelController::class, 'index'])->name('index');

// Rules — the guard-name tree
Route::get('rules', [RulesController::class, 'index'])->name('rules.index');
Route::post('rules', [RulesController::class, 'store'])->name('rules.store');
Route::put('rules/{id}', [RulesController::class, 'update'])
    ->whereNumber('id')
    ->name('rules.update');
Route::delete('rules/{id}', [RulesController::class, 'destroy'])
    ->whereNumber('id')
    ->name('rules.destroy');
Route::post('rules/{id}/restore', [RulesController::class, 'restore'])
    ->whereNumber('id')
    ->name('rules.restore');

// Owners — the rows that hold permissions
Route::get('owners', [OwnersController::class, 'index'])->name('owners.index');
Route::post('owners', [OwnersController::class, 'store'])->name('owners.store');
Route::put('owners/{owner}', [OwnersController::class, 'update'])
    ->whereNumber('owner')
    ->name('owners.update');
Route::delete('owners/{owner}', [OwnersController::class, 'destroy'])
    ->whereNumber('owner')
    ->name('owners.destroy');

// Permission matrix for one owner
Route::get('owners/{owner}/permissions', [PermissionsController::class, 'index'])
    ->whereNumber('owner')
    ->name('permissions.index');
Route::put('owners/{owner}/permissions/{rule}', [PermissionsController::class, 'update'])
    ->whereNumber(['owner', 'rule'])
    ->name('permissions.update');

// Inheritance — the panel's screen and the widget, from either end
Route::get('owners/{owner}/inherit', [InheritController::class, 'index'])
    ->whereNumber('owner')
    ->name('inherit.index');
Route::post('owners/{owner}/inherit', [InheritController::class, 'store'])
    ->whereNumber('owner')
    ->name('inherit.store');
Route::delete('owners/{owner}/inherit/{link}', [InheritController::class, 'destroy'])
    ->whereNumber(['owner', 'link'])
    ->name('inherit.destroy');

// Searching the owner table when a list is too long to send inline
Route::get('pick', [PickerController::class, 'index'])->name('pick');
