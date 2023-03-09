<?php

use Illuminate\Support\Facades\Route;
use Wnikk\LaravelAccessUi\Http\Controllers\AccessUiController;
use Wnikk\LaravelAccessUi\Http\Controllers\RulesController;
use Wnikk\LaravelAccessUi\Http\Controllers\OwnersController;

Route::get('/rules', [AccessUiController::class, 'rules']);
Route::get('/owners/{type}', [AccessUiController::class, 'owners']);

Route::apiResource('/rules-data', RulesController::class, ['as' => 'accessUi'])
    ->parameters(['rules-data' => 'id'])
    ->except(['show']);

Route::apiResource('/owners-data', OwnersController::class, ['as' => 'accessUi'])
    ->parameters(['owners-data' => 'id'])
    ->except(['show']);

