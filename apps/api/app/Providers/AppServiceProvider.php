<?php

namespace App\Providers;

use App\Contracts\Documents\DocumentGovernanceEmailTransport;
use App\Contracts\Documents\ExportSourceHold;
use App\Contracts\Documents\ResolveDocumentGovernanceEmailBranding;
use App\Contracts\Ingestion\ContentCloneVectorGateway;
use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Contracts\Platform\OperationalMetricsReader;
use App\Models\User;
use App\Observers\UserAccessObserver;
use App\Services\Documents\DolvedGovernanceEmailBrandingResolver;
use App\Services\Documents\LaravelDocumentGovernanceEmailTransport;
use App\Services\Documents\NoopExportSourceHold;
use App\Services\Ingestion\AiContentCloneVectorGateway;
use App\Services\Ingestion\SqsIngestionEventPublisher;
use App\Services\Platform\PrometheusOperationalMetricsReader;
use App\Support\CanonicalEmail;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentGovernanceEmailTransport::class, LaravelDocumentGovernanceEmailTransport::class);
        $this->app->bind(ResolveDocumentGovernanceEmailBranding::class, DolvedGovernanceEmailBrandingResolver::class);
        $this->app->bind(ExportSourceHold::class, NoopExportSourceHold::class);
        $this->app->bind(
            IngestionEventPublisher::class,
            SqsIngestionEventPublisher::class,
        );
        $this->app->bind(
            OperationalMetricsReader::class,
            PrometheusOperationalMetricsReader::class,
        );
        $this->app->bind(
            ContentCloneVectorGateway::class,
            AiContentCloneVectorGateway::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserAccessObserver::class);
        Gate::define(
            'access-platform-operations',
            fn (User $user): Response => $user->hasPlatformAdministratorAccess()
                ? Response::allow()
                : Response::denyAsNotFound('Not Found'),
        );

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
