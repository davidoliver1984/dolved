<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ConversationStatus;
use App\Enums\GenerationRunStatus;
use App\Exceptions\ConversationException;
use App\Jobs\ExecuteGenerationRun;
use App\Models\Conversation;
use App\Models\GenerationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RetryGenerationRun
{
    public function handle(GenerationRun $run, string $idempotencyKey): GenerationRun
    {
        [$retry, $created] = DB::transaction(function () use ($run, $idempotencyKey): array {
            $original = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            $existing = GenerationRun::query()
                ->where('retry_of_run_id', $original->id)
                ->where('retry_idempotency_key', $idempotencyKey)
                ->first();
            if ($existing instanceof GenerationRun) {
                return [$existing, false];
            }
            if (! $original->status->isRetryEligible()) {
                throw new ConversationException('This generation run is not retry eligible.');
            }
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($original->conversation_id);
            if ($conversation->status !== ConversationStatus::Active
                || $conversation->generationRuns()->whereIn('status', $this->activeStatusValues())->exists()) {
                throw new ConversationException('The conversation cannot start another generation run.');
            }

            return [GenerationRun::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $original->workspace_id,
                'conversation_id' => $original->conversation_id,
                'user_message_id' => $original->user_message_id,
                'assistant_message_id' => null,
                'retry_of_run_id' => $original->id,
                'retry_idempotency_key' => $idempotencyKey,
                'status' => GenerationRunStatus::Queued,
                'correlation_id' => (string) Str::uuid(),
            ]), true];
        });
        if ($created) {
            ExecuteGenerationRun::dispatch($retry->id);
        }

        return $retry;
    }

    /** @return list<string> */
    private function activeStatusValues(): array
    {
        return collect(GenerationRunStatus::cases())->reject->isTerminal()->map->value->all();
    }
}
