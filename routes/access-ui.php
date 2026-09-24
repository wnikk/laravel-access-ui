<?php

use Illuminate\Support\Facades\Route;
use Wnikk\LaravelAccessUi\Http\Controllers\ConditionsController;
use Wnikk\LaravelAccessUi\Http\Controllers\ExplainController;
use Wnikk\LaravelAccessUi\Http\Controllers\HealthController;
use Wnikk\LaravelAccessUi\Http\Controllers\InheritController;
use Wnikk\LaravelAccessUi\Http\Controllers\OwnersController;
use Wnikk\LaravelAccessUi\Http\Controllers\PanelController;
use Wnikk\LaravelAccessUi\Http\Controllers\PermissionsController;
use Wnikk\LaravelAccessUi\Http\Controllers\PickerController;
use Wnikk\LaravelAccessUi\Http\Controllers\RulesController;
use Wnikk\LaravelAccessUi\Http\Controllers\XacmlController;

/*
| Loaded by AccessUiServiceProvider inside the prefix, middleware, domain and name group of
| config/accessUi.php. Nothing is registered until that configuration names both a prefix and
| a middleware stack.
|
| One HTML route; everything else answers JSON. {owner} is the id of a row in the owner table of
| the core and nothing else: a host page gets it from $user->getOwner()->id.
*/

Route::get('/', [PanelController::class, 'index'])->name('index');

// Rules: the names the application checks against
Route::get('rules', [RulesController::class, 'index'])->name('rules.index');
Route::post('rules', [RulesController::class, 'store'])->name('rules.store');
Route::put('rules/{id}', [RulesController::class, 'update'])->whereNumber('id')->name('rules.update');
Route::delete('rules/{id}', [RulesController::class, 'destroy'])->whereNumber('id')->name('rules.destroy');
Route::get('rules/{id}/holders', [RulesController::class, 'holders'])->whereNumber('id')->name('rules.holders');

// Owners: the rows that hold permissions
Route::get('owners', [OwnersController::class, 'index'])->name('owners.index');
Route::post('owners', [OwnersController::class, 'store'])->name('owners.store');
Route::put('owners/{owner}', [OwnersController::class, 'update'])->whereNumber('owner')->name('owners.update');
Route::delete('owners/{owner}', [OwnersController::class, 'destroy'])->whereNumber('owner')->name('owners.destroy');
Route::get('owners/{owner}/heirs', [OwnersController::class, 'heirs'])->whereNumber('owner')->name('owners.heirs');

// Permissions of one owner. A permit and a prohibition of one rule are two rows and are written apart.
Route::get('owners/{owner}/permissions', [PermissionsController::class, 'index'])->whereNumber('owner')->name('permissions.index');
Route::post('owners/{owner}/permissions', [PermissionsController::class, 'store'])->whereNumber('owner')->name('permissions.store');
Route::delete('owners/{owner}/permissions', [PermissionsController::class, 'destroy'])->whereNumber('owner')->name('permissions.destroy');

// Inheritance, from either end: direction=children for the panel, direction=parents for the widget
Route::get('owners/{owner}/inherit', [InheritController::class, 'index'])->whereNumber('owner')->name('inherit.index');
Route::post('owners/{owner}/inherit', [InheritController::class, 'store'])->whereNumber('owner')->name('inherit.store');
Route::delete('owners/{owner}/inherit/{link}', [InheritController::class, 'destroy'])->whereNumber(['owner', 'link'])->name('inherit.destroy');

// Searching the owner table when a list is too long to send inline
Route::get('pick', [PickerController::class, 'index'])->name('pick');

// The editor of conditions: what it may name, and whether what was typed compiles
Route::get('conditions/vocabulary', [ConditionsController::class, 'vocabulary'])->name('conditions.vocabulary');
Route::post('conditions/check', [ConditionsController::class, 'check'])->name('conditions.check');

// "Why?": the explanation of one check
Route::get('explain', [ExplainController::class, 'index'])->name('explain');

// Health: what acr:lint finds, as data; and the cache
Route::get('health', [HealthController::class, 'index'])->name('health.index');
Route::post('health/fix', [HealthController::class, 'fix'])->name('health.fix');
Route::post('cache/flush', [HealthController::class, 'flush'])->name('cache.flush');

// XACML: download, look, import
Route::get('xacml/export', [XacmlController::class, 'export'])->name('xacml.export');
Route::post('xacml/check', [XacmlController::class, 'check'])->name('xacml.check');
Route::post('xacml/import', [XacmlController::class, 'import'])->name('xacml.import');
