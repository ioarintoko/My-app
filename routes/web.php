<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/login'));
Route::get('/login', fn () => view('login'))->name('login');
Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');