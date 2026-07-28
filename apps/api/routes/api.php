<?php

use App\Http\Controllers\DocumentIngestionController;
use App\Http\Controllers\DocumentUploadController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [
        AuthenticatedSessionController::class,
        'store',
    ])->middleware(['guest:web', 'throttle:login'])->name('login.store');

    Route::post('/forgot-password', [
        PasswordResetLinkController::class,
        'store',
    ])->middleware(['guest:web', 'throttle:password-reset-link'])->name('password.email');

    Route::post('/reset-password', [
        NewPasswordController::class,
        'store',
    ])->middleware('guest:web')->name('password.update');

    Route::post('/register', [
        RegisteredUserController::class,
        'store',
    ])->middleware([
        'guest:web',
        'registration.open',
        'throttle:registration',
    ])->name('register.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', function (Request $request) {
            return response()->json(['data' => ['user' => $request->user()]]);
        });

        Route::post('/logout', [
            AuthenticatedSessionController::class,
            'destroy',
        ])->name('logout');

        Route::post('/email/verification-notification', [
            EmailVerificationNotificationController::class,
            'store',
        ])->middleware('throttle:6,1')->name('verification.send');
    });
});

Route::get('/platform/status', function () {
    return response()->json(['data' => ['status' => 'available']]);
})->middleware(['auth:sanctum', 'verified']);

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::get('/workspaces/{workspacePublicId}', [WorkspaceController::class, 'show']);
    Route::get(
        '/workspaces/{workspacePublicId}/documents/uploads/configuration',
        [DocumentUploadController::class, 'configuration'],
    );
    Route::post(
        '/workspaces/{workspacePublicId}/documents/uploads',
        [DocumentUploadController::class, 'store'],
    );
    Route::post(
        '/workspaces/{workspacePublicId}/documents/{documentPublicId}/uploads/complete',
        [DocumentUploadController::class, 'complete'],
    );
    Route::post(
        '/workspaces/{workspacePublicId}/documents/{documentPublicId}/ingestion-requests',
        [DocumentIngestionController::class, 'store'],
    );
});
