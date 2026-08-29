<?php

use App\Http\Controllers\ChatStreamController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DocumentAdministrationController;
use App\Http\Controllers\DocumentIngestionController;
use App\Http\Controllers\DocumentMetadataController;
use App\Http\Controllers\DocumentUploadController;
use App\Http\Controllers\DocumentVersionGovernanceController;
use App\Http\Controllers\Internal\DocumentDeletionOperationController;
use App\Http\Controllers\Internal\DocumentIngestionClaimController;
use App\Http\Controllers\Internal\IngestionOperationController;
use App\Http\Controllers\Internal\ObservabilityReconciliationController;
use App\Http\Controllers\PlatformOperationsController;
use App\Http\Controllers\RetrievalController;
use App\Http\Controllers\WorkspaceAdministrationController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceUsageController;
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
    ])->middleware(['api.guest', 'throttle:login'])->name('login.store');

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
        'api.guest',
        'registration.open',
        'throttle:registration',
    ])->name('register.store');

    Route::middleware(['auth:sanctum', 'account.enabled'])->group(function (): void {
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
})->middleware(['auth:sanctum', 'account.enabled', 'verified']);

Route::prefix('/platform/operations')->middleware([
    'auth:sanctum',
    'account.enabled',
    'verified',
    'can:access-platform-operations',
])->group(function (): void {
    Route::get('/access', [PlatformOperationsController::class, 'access']);
    Route::get('/health', [PlatformOperationsController::class, 'health']);
    Route::get('/policy', [PlatformOperationsController::class, 'policy']);
    Route::post('/policy', [PlatformOperationsController::class, 'storePolicy']);
});

Route::post('/internal/observability/reconciliation/plan', [ObservabilityReconciliationController::class, 'plan'])
    ->middleware('observability.reconciler:observability.policy.plan.read');
Route::post('/internal/observability/reconciliation/acknowledgements', [ObservabilityReconciliationController::class, 'acknowledge'])
    ->middleware('observability.reconciler:observability.policy.reconcile');

Route::post(
    '/internal/ingestion/events/{eventId}/claim',
    [DocumentIngestionClaimController::class, 'store'],
)->middleware('ingestion.worker:ingestion.claim')->name('ingestion.claim');

Route::prefix('/internal/ingestion/events/{eventId}')->group(function (): void {
    Route::post('/lease/renew', [IngestionOperationController::class, 'renew'])
        ->middleware('ingestion.worker:ingestion.lease.renew')->name('ingestion.lease.renew');
    Route::post('/chunks', [IngestionOperationController::class, 'submit'])
        ->middleware('ingestion.worker:ingestion.chunks.submit')->name('ingestion.chunks.submit');
    Route::post('/chunks/seal', [IngestionOperationController::class, 'seal'])
        ->middleware('ingestion.worker:ingestion.chunks.seal')->name('ingestion.chunks.seal');
    Route::post('/resume', [IngestionOperationController::class, 'resume'])
        ->middleware('ingestion.worker:ingestion.attempt.resume')->name('ingestion.attempt.resume');
    Route::post('/publication/authorise', [IngestionOperationController::class, 'authorise'])
        ->middleware('ingestion.worker:ingestion.publication.authorise')->name('ingestion.publication.authorise');
    Route::post('/complete', [IngestionOperationController::class, 'complete'])
        ->middleware('ingestion.worker:ingestion.complete')->name('ingestion.complete');
    Route::post('/fail', [IngestionOperationController::class, 'fail'])
        ->middleware('ingestion.worker:ingestion.fail')->name('ingestion.fail');
    Route::post('/cancel', [IngestionOperationController::class, 'cancel'])
        ->middleware('ingestion.worker:ingestion.attempt.cancel')->name('ingestion.attempt.cancel');
});

Route::prefix('/internal/document-deletions/{eventId}')->group(function (): void {
    Route::post('/claim', [DocumentDeletionOperationController::class, 'claim'])
        ->middleware('ingestion.worker:document.deletion.claim')->name('document.deletion.claim');
    Route::post('/complete', [DocumentDeletionOperationController::class, 'complete'])
        ->middleware('ingestion.worker:document.deletion.complete')->name('document.deletion.complete');
    Route::post('/fail', [DocumentDeletionOperationController::class, 'fail'])
        ->middleware('ingestion.worker:document.deletion.fail')->name('document.deletion.fail');
});

Route::middleware(['auth:sanctum', 'account.enabled', 'verified'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::get('/workspaces/{workspacePublicId}', [WorkspaceController::class, 'show']);
    Route::get('/workspaces/{workspacePublicId}/members', [WorkspaceAdministrationController::class, 'members']);
    Route::get('/workspaces/{workspacePublicId}/invitations', [WorkspaceAdministrationController::class, 'invitations']);
    Route::get('/workspaces/{workspacePublicId}/usage', [WorkspaceUsageController::class, 'show']);
    Route::post('/workspaces/{workspacePublicId}/invitations', [WorkspaceAdministrationController::class, 'issue']);
    Route::delete('/workspaces/{workspacePublicId}/invitations/{invitationPublicId}', [WorkspaceAdministrationController::class, 'revoke']);
    Route::patch('/workspaces/{workspacePublicId}/memberships/{membershipPublicId}/role', [WorkspaceAdministrationController::class, 'changeRole']);
    Route::delete('/workspaces/{workspacePublicId}/memberships/{membershipPublicId}', [WorkspaceAdministrationController::class, 'remove']);
    Route::post('/workspaces/{workspacePublicId}/memberships/{membershipPublicId}/ownership-transfers', [WorkspaceAdministrationController::class, 'transfer']);
    Route::delete('/workspaces/{workspacePublicId}/membership', [WorkspaceAdministrationController::class, 'leave']);
    Route::post('/workspace-invitations/accept', [WorkspaceAdministrationController::class, 'accept']);
    Route::get('/workspaces/{workspacePublicId}/documents', [DocumentAdministrationController::class, 'index']);
    Route::get('/workspaces/{workspacePublicId}/documents/{documentPublicId}', [DocumentAdministrationController::class, 'show']);
    Route::get('/workspaces/{workspacePublicId}/document-metadata', [DocumentMetadataController::class, 'index']);
    Route::post('/workspaces/{workspacePublicId}/document-categories', [DocumentMetadataController::class, 'storeCategory']);
    Route::patch('/workspaces/{workspacePublicId}/document-categories/{categoryPublicId}/archive', [DocumentMetadataController::class, 'archiveCategory']);
    Route::post('/workspaces/{workspacePublicId}/document-tags', [DocumentMetadataController::class, 'storeTag']);
    Route::get('/workspaces/{workspacePublicId}/document-families/{familyPublicId}/metadata', [DocumentMetadataController::class, 'showFamily']);
    Route::put('/workspaces/{workspacePublicId}/document-families/{familyPublicId}/metadata', [DocumentMetadataController::class, 'updateFamily']);
    Route::put('/workspaces/{workspacePublicId}/document-families/{familyPublicId}/tags', [DocumentMetadataController::class, 'syncTags']);
    Route::get('/workspaces/{workspacePublicId}/document-families/{familyPublicId}/versions', [DocumentVersionGovernanceController::class, 'index']);
    Route::post('/workspaces/{workspacePublicId}/documents/{documentPublicId}/governance/approve', [DocumentVersionGovernanceController::class, 'approve']);
    Route::post('/workspaces/{workspacePublicId}/documents/{documentPublicId}/governance/withdraw', [DocumentVersionGovernanceController::class, 'withdraw']);
    Route::patch('/workspaces/{workspacePublicId}/documents/{documentPublicId}/governance/schedule', [DocumentVersionGovernanceController::class, 'reschedule']);
    Route::patch('/workspaces/{workspacePublicId}/documents/{documentPublicId}/governance/timestamps', [DocumentVersionGovernanceController::class, 'correct']);
    Route::post('/workspaces/{workspacePublicId}/documents/{documentPublicId}/retries', [DocumentAdministrationController::class, 'retry']);
    Route::delete('/workspaces/{workspacePublicId}/documents/{documentPublicId}', [DocumentAdministrationController::class, 'destroy']);
    Route::get('/workspaces/{workspacePublicId}/conversations', [ConversationController::class, 'index']);
    Route::post('/workspaces/{workspacePublicId}/conversations', [ConversationController::class, 'store']);
    Route::get('/workspaces/{workspacePublicId}/conversations/{conversationPublicId}', [ConversationController::class, 'show']);
    Route::post('/workspaces/{workspacePublicId}/conversations/{conversationPublicId}/messages', [ConversationController::class, 'message']);
    Route::post('/workspaces/{workspacePublicId}/conversations/{conversationPublicId}/runs/{runPublicId}/retry', [ConversationController::class, 'retry']);
    Route::post('/workspaces/{workspacePublicId}/conversations/{conversationPublicId}/runs/{runPublicId}/cancel', [ConversationController::class, 'cancel']);
    Route::get('/workspaces/{workspacePublicId}/conversations/{conversationPublicId}/runs/{runPublicId}/events', [ChatStreamController::class, 'show']);
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
    Route::post(
        '/workspaces/{workspacePublicId}/retrieval',
        [RetrievalController::class, 'store'],
    );
});
