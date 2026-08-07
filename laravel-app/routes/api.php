<?php

use App\Http\Controllers\Api\OrderDetailController;
use App\Http\Controllers\Api\OrderUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->group(function () {
    Route::get('/', [OrderUploadController::class, 'index']);
    Route::post('/upload', [OrderUploadController::class, 'upload']);
    Route::get('/{order}', [OrderUploadController::class, 'show']);
    Route::patch('/{order}', [OrderUploadController::class, 'update']);
    Route::post('/{order}/confirm', [OrderUploadController::class, 'confirm']);
    Route::post('/{order}/reopen', [OrderUploadController::class, 'reopen']);
    Route::post('/{order}/recalculate-total', [OrderUploadController::class, 'recalculateTotal']);

    Route::post('/{order}/items', [OrderDetailController::class, 'store']);
    Route::patch('/{order}/items/{item}', [OrderDetailController::class, 'update']);
    Route::delete('/{order}/items/{item}', [OrderDetailController::class, 'destroy']);
});
