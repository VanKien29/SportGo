<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;

Route::get('/receipts/{receipt}', [ReceiptController::class, 'show'])
    ->name('receipts.show')
    ->middleware('signed');

Route::get('/invoices/{receipt}', [ReceiptController::class, 'show'])
    ->name('invoices.show')
    ->middleware('signed');

Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');
