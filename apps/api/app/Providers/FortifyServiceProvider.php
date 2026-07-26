<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\ApiLoginResponse;
use App\Http\Responses\ApiRegisterResponse;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Http\Responses\VerifyEmailRedirectResponse;
use App\Support\CanonicalEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->bind(LoginResponse::class, ApiLoginResponse::class);
        $this->app->bind(RegisterResponse::class, ApiRegisterResponse::class);
        $this->app->bind(
            SuccessfulPasswordResetLinkRequestResponse::class,
            GenericPasswordResetLinkResponse::class
        );
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            GenericPasswordResetLinkResponse::class
        );
        $this->app->bind(VerifyEmailResponse::class, VerifyEmailRedirectResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = CanonicalEmail::from(
                (string) $request->input(Fortify::username())
            ).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('password-reset-link', function (Request $request) {
            return Limit::perMinute(3)->by(
                CanonicalEmail::from((string) $request->input('email')).'|'.$request->ip()
            );
        });
    }
}
