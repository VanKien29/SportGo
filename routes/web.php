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
    return response()->view('welcome')
        // The HTML contains the current Vite manifest. Keeping an old HTML
        // document after a deploy can point the browser at deleted chunks.
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->where('any', '.*')->name('login');
