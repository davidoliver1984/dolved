<?php

namespace App\Providers;

use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Services\Ingestion\SqsIngestionEventPublisher;
use App\Support\CanonicalEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            IngestionEventPublisher::class,
            SqsIngestionEventPublisher::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols());

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => CanonicalEmail::from($notifiable->getEmailForPasswordReset()),
            ]);

            return rtrim(config('app.frontend_url'), '/').'/reset-password?'.$query;
        });
    }
}
