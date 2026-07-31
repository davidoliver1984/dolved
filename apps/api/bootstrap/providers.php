<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelemetryServiceProvider;

return [
    AppServiceProvider::class,
    TelemetryServiceProvider::class,
    FortifyServiceProvider::class,
];
