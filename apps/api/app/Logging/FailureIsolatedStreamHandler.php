<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Throwable;

final class FailureIsolatedStreamHandler extends StreamHandler
{
    public function handle(LogRecord $record): bool
    {
        try {
            return parent::handle($record);
        } catch (Throwable) {
            return false;
        }
    }
}
