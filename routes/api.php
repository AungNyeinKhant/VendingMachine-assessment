<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::middleware('auth:api')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('transactions', [TransactionController::class, 'index']);

        Route::middleware('permission:products.purchase')->group(function () {
            Route::post('products/{product}/purchase', [ProductController::class, 'purchase']);
        });

        Route::middleware('permission:products.manage')->group(function () {
            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{product}', [ProductController::class, 'update']);
            Route::delete('products/{product}', [ProductController::class, 'destroy']);
        });
    });
});
