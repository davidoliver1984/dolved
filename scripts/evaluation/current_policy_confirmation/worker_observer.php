<?php

declare(strict_types=1);

/** Evaluation-only. No service provider registration and no application logging. */
final class EvaluationWorkerObserver
{
    public const TARGETS = [
        '/api/internal/retrieval/plan-needs' => 'planner',
        '/api/internal/retrieval/generation/current-policy-answer' => 'current_policy_generation',
    ];
    private int $sequence = 0;
    private string $previous = '';
    private ?array $active = null;
    private array $pending = [];
    private array $seen = [];
    private bool $poisoned = false;
    private bool $stopped = false;
    private bool $ready = false;
    private ?string $lastTerminalJob = null;

    public function __construct(private array $config, private Closure $resolve)
    {
        foreach (['journal', 'health'] as $key) {
            $p = $config[$key];
            if (! is_dir($p) || is_link($p) || realpath($p) !== $p || count(scandir($p)) !== 2) {
                throw new RuntimeException('observer destination must be fresh real directory');
            }
        }
        foreach (['execution_id', 'worker_id', 'workspace_id'] as $key) {
            self::uuid($config[$key]);
        }
        $this->safe(fn () => $this->write(['kind' => 'worker_start']));
    }

    public static function workerArguments(): array
    {
        return ['artisan', 'queue:work', 'conversation', '--queue=conversation-generation', '--tries=1', '--timeout=900'];
    }

    public function register(Illuminate\Contracts\Events\Dispatcher $events): void
    {
        $events->listen(Illuminate\Queue\Events\Looping::class, fn ($e) => $this->safe(function () use ($e) {
            if ($this->ready) { return; }
            if ($e->connectionName !== 'conversation' || $e->queue !== 'conversation-generation'
                || ! function_exists('pcntl_signal_get_handler') || ! pcntl_async_signals()
                || ! (pcntl_signal_get_handler(SIGTERM) instanceof Closure)) {
                throw new RuntimeException('worker loop signal boundary');
            }
            $this->write(['kind' => 'worker_loop_ready']);
            $this->ready = true;
        }));
        $events->listen(Illuminate\Queue\Events\JobProcessing::class, fn ($e) => $this->safe(fn () => $this->begin($e)));
        foreach ([Illuminate\Queue\Events\JobProcessed::class => 'processed',
            Illuminate\Queue\Events\JobExceptionOccurred::class => 'exception',
            Illuminate\Queue\Events\JobFailed::class => 'failed'] as $class => $state) {
            $events->listen($class, fn ($e) => $this->safe(fn () => $this->end($e, $state)));
        }
        $events->listen(Illuminate\Http\Client\Events\RequestSending::class, fn ($e) => $this->safe(fn () => $this->sending($e->request)));
        $events->listen(Illuminate\Http\Client\Events\ResponseReceived::class, fn ($e) => $this->safe(fn () => $this->received($e)));
        $events->listen(Illuminate\Http\Client\Events\ConnectionFailed::class, fn ($e) => $this->safe(fn () => $this->transport($e->request)));
        $events->listen(Illuminate\Queue\Events\WorkerStopping::class, fn () => $this->stop());
    }

    private static function uuid(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value)) {
            throw new RuntimeException('invalid identity');
        }
        return $value;
    }

    private function safe(Closure $action): void
    {
        if ($this->poisoned) { return; }
        try { $action(); } catch (Throwable) { $this->fail(); }
    }

    private function fail(): void
    {
        $this->poisoned = true;
        $this->active = null;
        $this->pending = [];
        // Independent file/path, not a claim that a shared disk failure can be logged.
        // Missing journal completion is independently sufficient to halt the runner.
        try {
            if (is_link($this->config['health']) || realpath($this->config['health']) !== $this->config['health']) { return; }
            $f = @fopen($this->config['health'].'/observer-failed', 'x');
            if ($f !== false) { @fwrite($f, "observer_health_failure\n"); @fflush($f); @fsync($f); @fclose($f); }
        } catch (Throwable) { /* Never change the application's result. */ }
    }

    private function write(array $row): void
    {
        if (is_link($this->config['journal']) || realpath($this->config['journal']) !== $this->config['journal']) { throw new RuntimeException('journal path'); }
        if (++$this->sequence > 10000) { throw new RuntimeException('journal bound'); }
        $row = ['schema' => 'worker-observer-v1', 'execution_id' => $this->config['execution_id'],
            'worker_id' => $this->config['worker_id'], 'workspace_id' => $this->config['workspace_id'],
            'sequence' => $this->sequence, 'previous_sha256' => $this->previous] + ($this->active ?? []) + $row;
        $bytes = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        if (strlen($bytes) > 16384) { throw new RuntimeException('journal record bound'); }
        $file = @fopen($this->config['journal'].'/'.sprintf('%06d.json', $this->sequence), 'x');
        if ($file === false) { throw new RuntimeException('journal write'); }
        try {
            if (@fwrite($file, $bytes) !== strlen($bytes) || ! @fflush($file) || ! @fsync($file)) {
                throw new RuntimeException('journal durability');
            }
        } finally { @fclose($file); }
        $this->previous = hash('sha256', $bytes);
    }

    private function begin($event): void
    {
        if ($this->active !== null || $this->pending !== [] || $event->connectionName !== 'conversation'
            || $event->job->getQueue() !== 'conversation-generation') { throw new RuntimeException('job association'); }
        $job = (string) $event->job->getJobId();
        if (! preg_match('/^[a-zA-Z0-9-]{1,100}$/D', $job)) { throw new RuntimeException('job id'); }
        $info = ($this->resolve)($event->job);
        if ($info['workspace_id'] !== $this->config['workspace_id']) { throw new RuntimeException('workspace'); }
        $this->active = ['job_id' => $job, 'run_id' => self::uuid($info['run_id'])];
        $this->write(['kind' => 'job_start']);
    }

    private function end($event, string $state): void
    {
        // Laravel may emit JobFailed and JobExceptionOccurred for the same failure.
        if ($this->active === null) {
            if (in_array($state, ['failed', 'exception'], true) && (string) $event->job->getJobId() === $this->lastTerminalJob) { return; }
            throw new RuntimeException('missing active job');
        }
        if ($event->connectionName !== 'conversation' || $event->job->getQueue() !== 'conversation-generation'
            || (string) $event->job->getJobId() !== $this->active['job_id'] || $this->pending !== []) {
            throw new RuntimeException('job terminal association');
        }
        $info = ($this->resolve)($event->job);
        if ($info['run_id'] !== $this->active['run_id'] || $info['workspace_id'] !== $this->config['workspace_id']) {
            throw new RuntimeException('terminal identity');
        }
        $status = $info['status'];
        if (! in_array($status, ['completed', 'retrieval_no_answer', 'clarification_required', 'failed', 'cancelled', 'running', 'queued'], true)) {
            throw new RuntimeException('run status');
        }
        $this->write(['kind' => 'job_end', 'job_state' => $state, 'run_status' => $status]);
        $this->lastTerminalJob = $this->active['job_id'];
        $this->active = null;
    }

    private function identity($request): ?array
    {
        $url = parse_url($request->url());
        $path = $url['path'] ?? '';
        if (! isset(self::TARGETS[$path])) { return null; }
        $origin = ($url['scheme'] ?? '').'://'.($url['host'] ?? '').(isset($url['port']) ? ':'.$url['port'] : '');
        if ($origin !== $this->config['ai_origin'] || $request->method() !== 'POST' || $this->active === null) {
            throw new RuntimeException('request association');
        }
        // Read-only extraction. Never journal body, headers, signatures or source text.
        $body = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
        if (($body['workspace_id'] ?? null) !== $this->config['workspace_id']) { throw new RuntimeException('request workspace'); }
        return ['request_id' => self::uuid($body['request_id'] ?? null), 'endpoint' => $path, 'stage' => self::TARGETS[$path],
            'purpose' => self::TARGETS[$path] === 'planner' ? 'retrieval.plan-needs' : 'generation.current-policy-answer'];
    }

    private function sending($request): void
    {
        if (($id = $this->identity($request)) === null) { return; }
        if (isset($this->seen[$id['request_id']])) { throw new RuntimeException('request reused'); }
        $this->seen[$id['request_id']] = true;
        $this->pending[$id['request_id']] = $id;
        $this->write(['kind' => 'request_start'] + $id);
    }

    private function received($event): void
    {
        if (($id = $this->identity($event->request)) === null) { return; }
        if (($this->pending[$id['request_id']] ?? null) !== $id) { throw new RuntimeException('unmatched response'); }
        $v = $event->response->json();
        if (! is_array($v)) { $v = []; }
        if (($event->response->successful() && ($v['request_id'] ?? null) !== $id['request_id'])
            || (isset($v['request_id']) && $v['request_id'] !== $id['request_id'])) { throw new RuntimeException('response identity'); }
        $row = ['kind' => 'request_end', 'http_status' => $event->response->status(), 'transport' => 'response'] + $id;
        if ($id['stage'] === 'planner') {
            $receipt = is_array($v['usage'] ?? null) && array_key_exists('validation_attempts', $v['usage']) ? $v['usage'] : (is_array($v['detail'] ?? null) ? $v['detail'] : []);
            $hasAttempts = array_key_exists('validation_attempts', $receipt);
            $attempts = $receipt['validation_attempts'] ?? null;
            $failure = is_array($v['detail'] ?? null) ? $v['detail'] : [];
            $category = $failure['category'] ?? null;
            $row['failure_category'] = in_array($category, ['transport_error', 'provider_authentication', 'provider_quota', 'provider_rate_limit', 'provider_unavailable', 'provider_request_rejected'], true) ? $category : null;
            $row['provider_status'] = is_int($failure['provider_status'] ?? null) && $failure['provider_status'] >= 100 && $failure['provider_status'] <= 599 ? $failure['provider_status'] : null;
            $row['systemic'] = is_bool($failure['systemic'] ?? null) ? $failure['systemic'] : null;
            $row['reported_attempt_count'] = is_int($failure['attempt_count'] ?? null) && $failure['attempt_count'] >= 0 && $failure['attempt_count'] <= 2 ? $failure['attempt_count'] : null;
            $row['attempts'] = null;
            $row['attempt_receipt_state'] = 'missing';
            if ($hasAttempts) {
                if (! is_array($attempts) || ! array_is_list($attempts) || count($attempts) > 2) { throw new RuntimeException('attempt shape'); }
                if ($attempts === [] && ($event->response->status() !== 503 || $row['failure_category'] === null || $row['systemic'] === null)) { throw new RuntimeException('invalid empty attempt boundary'); }
                $row['attempts'] = [];
                $row['attempt_receipt_state'] = $attempts === [] ? 'observed_empty' : 'observed_list';
                foreach ($attempts as $i => $a) {
                    if (($a['attempt'] ?? null) !== $i + 1 || ! in_array($a['status'] ?? null, ['accepted', 'rejected'], true)) { throw new RuntimeException('attempt receipt'); }
                    $row['attempts'][] = ['attempt' => $a['attempt'], 'status' => $a['status']];
                }
                if (count($attempts) === 2 && $attempts[0]['status'] !== 'rejected') { throw new RuntimeException('native correction receipt'); }
            }
        } else {
            $row['generation_status'] = in_array($v['status'] ?? null, ['completed', 'failed', 'capacity'], true) ? $v['status'] : 'unavailable';
            $row['generation_reason'] = in_array($v['reason'] ?? null, ['invalid_output', 'incomplete_output', 'unconfigured', 'provider_authentication', 'provider_authorisation', 'provider_unavailable'], true) ? $v['reason'] : null;
        }
        $this->write($row);
        unset($this->pending[$id['request_id']]);
    }

    private function transport($request): void
    {
        if (($id = $this->identity($request)) === null) { return; }
        if (($this->pending[$id['request_id']] ?? null) !== $id) { throw new RuntimeException('transport association'); }
        $this->write(['kind' => 'request_end', 'transport' => 'connection_failed', 'http_status' => null] + $id);
        unset($this->pending[$id['request_id']]);
    }

    public function stop(): void
    {
        if ($this->stopped) { return; }
        $this->stopped = true;
        $this->safe(function () {
            if ($this->active !== null || $this->pending !== []) { throw new RuntimeException('interrupted job'); }
            $this->write(['kind' => 'worker_stop']);
        });
    }
}
