<?php

use Illuminate\Support\Facades\Route;

// Auth Routes (Login, Register, etc.)
Route::prefix('auth')->group(base_path('routes/api/auth.php'));

// Admin Routes (Policies, etc.)
Route::prefix('admin')->group(base_path('routes/api/admin.php'));
