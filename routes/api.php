<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('image')->group(function () {
    Route::post('/', [ImageController::class, 'store']);
    Route::get('recognize', [ImageController::class, 'show']);
});
