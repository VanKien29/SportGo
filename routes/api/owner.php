<?php

use App\Http\Controllers\Api\Owner\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index']);
// Các API khác của owner sẽ thêm vào đây...
