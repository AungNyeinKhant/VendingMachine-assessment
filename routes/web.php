<?php

use App\Http\Controllers\ProductsController;
use App\Http\Controllers\TransactionsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/products')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('transactions', [TransactionsController::class, 'index'])
        ->name('transactions.index');

    Route::get('products', [ProductsController::class, 'index'])
        ->name('products.index');

    Route::get('products/create', [ProductsController::class, 'create'])
        ->middleware('permission:products.manage')
        ->name('products.create');

    Route::post('products', [ProductsController::class, 'store'])
        ->middleware('permission:products.manage')
        ->name('products.store');

    Route::get('products/{product:slug}', [ProductsController::class, 'show'])
        ->name('products.show');

    Route::get('products/{product:slug}/edit', [ProductsController::class, 'edit'])
        ->middleware('permission:products.manage')
        ->name('products.edit');

    Route::put('products/{product:slug}', [ProductsController::class, 'update'])
        ->middleware('permission:products.manage')
        ->name('products.update');

    Route::delete('products/{product:slug}', [ProductsController::class, 'destroy'])
        ->middleware('permission:products.manage')
        ->name('products.destroy');
});

require __DIR__.'/settings.php';
