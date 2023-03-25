<?php

use Illuminate\Support\Facades\Route;
use Wnikk\LaravelAccessUi\Http\Controllers\AccessUiController;
use Wnikk\LaravelAccessUi\Http\Controllers\RulesController;
use Wnikk\LaravelAccessUi\Http\Controllers\OwnersController;
use Wnikk\LaravelAccessUi\Http\Controllers\InheritController;
use Wnikk\LaravelAccessUi\Http\Controllers\PermissionController;

Route::name('accessUi.')->group(static function () {
    Route::get('/', [AccessUiController::class, 'main']);

    Route::apiResource('/rules-data', RulesController::class)
        ->parameters(['rules-data' => 'id'])
        ->except(['show']);

    Route::apiResource('/owners-data', OwnersController::class)
        ->parameters(['owners-data' => 'id'])
        ->except(['show']);

    Route::apiResource('/owner.inherit-data', InheritController::class)
        ->parameters(['inherit-data' => 'id'])
        ->except(['show', 'update']);

    Route::apiResource('/owner.permission-data', PermissionController::class)
        ->parameters(['permission-data' => 'id'])
        ->except(['show', 'store', 'destroy']);
});
