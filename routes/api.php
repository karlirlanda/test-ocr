<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('image')->group(function () {
    Route::post('/google-orc', [ImageController::class, 'googleOcr']);
    Route::post('/aws-ocr', [ImageController::class, 'awsOcr']);
    Route::post('/mindee-store', [ImageController::class, 'mindeeStore']);
    Route::post('/mindee-show', [ImageController::class, 'mindeeShow']);
});
