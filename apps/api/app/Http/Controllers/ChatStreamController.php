<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatDeliveryEvent;
use App\Models\Conversation;
use App\Models\GenerationRun;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatStreamController extends Controller
{
    public function show(Request $request, string $workspacePublicId, string $conversationPublicId, string $runPublicId, FindWorkspaceForUser $workspaces): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $conversation = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $conversationPublicId)
            ->firstOrFail();
        $run = GenerationRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversation->id)
            ->where('public_id', $runPublicId)
            ->firstOrFail();
        $after = max(0, (int) $request->header('Last-Event-ID', '0'));

        return response()->stream(function () use ($run, $after): void {
            $cursor = $after;
            $deadline = microtime(true) + (float) config('conversation.sse_connection_seconds');
            do {
                $events = ChatDeliveryEvent::query()
                    ->where('generation_run_id', $run->id)
                    ->where('sequence', '>', $cursor)
                    ->where('expires_at', '>', now())
                    ->orderBy('sequence')
                    ->get();
                foreach ($events as $event) {
                    $data = json_encode([
                        'sequence' => $event->sequence,
                        'type' => $event->event_type->value,
                        'provisional' => $event->provisional,
                        'payload' => $event->safe_payload,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    echo "id: {$event->sequence}\n";
                    echo "event: {$event->event_type->value}\n";
                    echo "data: {$data}\n\n";
                    $cursor = $event->sequence;
                    if ($event->event_type->isTerminal()) {
                        $this->flush();

                        return;
                    }
                }
                $this->flush();
                if (microtime(true) >= $deadline) {
                    echo ": reconnect\n\n";
                    $this->flush();

                    return;
                }
                usleep((int) config('conversation.sse_poll_microseconds'));
            } while (! connection_aborted());
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
