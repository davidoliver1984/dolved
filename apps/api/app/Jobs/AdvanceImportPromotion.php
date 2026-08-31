<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Imports\ClaimImportPromotion;
use App\Actions\Imports\FinalizeImportPromotion;
use App\Actions\Imports\RecordImportPromotionFailure;
use App\Actions\Imports\VerifyImportPromotionSource;
use App\Models\PromotionAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class AdvanceImportPromotion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 5];

    public function __construct(public readonly int $attemptId)
    {
        $this->onConnection('conversation');
        $this->onQueue((string) config('documents.administration_queue'));
        $this->afterCommit();
    }

    public function handle(
        ClaimImportPromotion $claim,
        VerifyImportPromotionSource $verify,
        FinalizeImportPromotion $finalize,
        RecordImportPromotionFailure $failures,
    ): void {
        $attempt = PromotionAttempt::query()->findOrFail($this->attemptId);
        $lease = $claim->handle($attempt);
        try {
            $verified = $verify->handle(
                $lease['attempt'],
                $lease['lease_token'],
                $lease['lease_generation'],
            );
            $finalize->handle(
                $verified,
                $lease['lease_token'],
                $lease['lease_generation'],
            );
        } catch (Throwable $exception) {
            $failures->handle(
                $lease['attempt'],
                $lease['lease_token'],
                $lease['lease_generation'],
                'promotion_execution_failed',
            );
            throw $exception;
        }
    }
}
