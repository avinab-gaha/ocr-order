<?php

use App\Http\Controllers\DocumentExtractionController;
use App\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentExtractionController::class, 'index']);
Route::post('/extract', [DocumentExtractionController::class, 'extract']);
Route::post('/extract/confirm', [DocumentExtractionController::class, 'confirm']);

Route::prefix('docs')->name('docs.')->group(function () {
    Route::get('/', [DocsController::class, 'index'])->name('index');
    Route::get('/{file}', [DocsController::class, 'show'])->name('show');
});
