<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

Route::get('/api/auth/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:web', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/', function () {
    return view('welcome');
});
