<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Public: auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Public: lihat data movie (master data)
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{movie}', [MovieController::class, 'show']);
Route::get('/health', [HealthController::class, 'check']);


// Protected
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::delete('/movies/{movie}', [MovieController::class, 'destroy']);

    Route::apiResource('watchlists', WatchlistController::class)->except(['index', 'store', 'show', 'update', 'destroy']);
    // apiResource di atas sengaja di-except semua karena kita daftarkan manual di bawah biar eksplisit:
    Route::get('/watchlists', [WatchlistController::class, 'index']);
    Route::post('/watchlists', [WatchlistController::class, 'store']);
    Route::get('/watchlists/{watchlist}', [WatchlistController::class, 'show']);
    Route::put('/watchlists/{watchlist}', [WatchlistController::class, 'update']);
    Route::patch('/watchlists/{watchlist}', [WatchlistController::class, 'update']);
    Route::delete('/watchlists/{watchlist}', [WatchlistController::class, 'destroy']);
});