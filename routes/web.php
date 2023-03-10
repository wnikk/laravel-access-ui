<?php

use Illuminate\Support\Facades\Route;
use Wnikk\LaravelAccessUi\Http\Controllers\AccessUiController;
use Wnikk\LaravelAccessUi\Http\Controllers\RulesController;
use Wnikk\LaravelAccessUi\Http\Controllers\OwnersController;
use Wnikk\LaravelAccessUi\Http\Controllers\InheritController;

Route::get('/rules', [AccessUiController::class, 'rules']);
Route::get('/owners/{type}', [AccessUiController::class, 'owners']);
Route::get('/inherit/{owner}', [AccessUiController::class, 'inherit'])->whereNumber('owner');

Route::apiResource('/rules-data', RulesController::class, ['as' => 'accessUi'])
    ->parameters(['rules-data' => 'id'])
    ->except(['show']);

Route::apiResource('/owners-data', OwnersController::class, ['as' => 'accessUi'])
    ->parameters(['owners-data' => 'id'])
    ->except(['show']);

Route::apiResource('/owner.inherit-data', InheritController::class, ['as' => 'accessUi'])
    ->parameters(['inherit-data' => 'id'])
    ->except(['show', 'update']);

