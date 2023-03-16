<?php

use Illuminate\Support\Facades\Route;
use Wnikk\LaravelAccessUi\Http\Controllers\AccessUiController;
use Wnikk\LaravelAccessUi\Http\Controllers\RulesController;
use Wnikk\LaravelAccessUi\Http\Controllers\OwnersController;
use Wnikk\LaravelAccessUi\Http\Controllers\InheritController;
use Wnikk\LaravelAccessUi\Http\Controllers\PermissionController;

Route::name('accessUi.')->group(static function () {
    Route::get('/rules', [AccessUiController::class, 'rules']);
    Route::get('/management', [AccessUiController::class, 'management']);
    Route::get('/owners/{type}', [AccessUiController::class, 'owners']);
    Route::get('/inherit/{owner}', [AccessUiController::class, 'inherit'])->whereNumber('owner')->name('inherit');
    Route::get('/permission/{owner}', [AccessUiController::class, 'permission'])->whereNumber('owner')->name('permission');

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
