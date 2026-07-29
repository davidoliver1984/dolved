<?php

use App\Http\Middleware\AuthenticateIngestionWorker;
use App\Http\Middleware\CanonicalizeEmail;
use App\Http\Middleware\RequireOpenRegistration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(CanonicalizeEmail::class);
        $middleware->redirectGuestsTo(
            fn () => rtrim(config('app.frontend_url'), '/').'/login'
        );
        $middleware->alias([
            'ingestion.worker' => AuthenticateIngestionWorker::class,
            'registration.open' => RequireOpenRegistration::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
