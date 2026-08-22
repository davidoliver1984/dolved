<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class BootstrapE2eWorkspaceCommand extends Command
{
    protected $signature = 'e2e:bootstrap
        {--run= : Unique E2E run identity}
        {--scenario= : Unique E2E scenario identity}';

    protected $description = 'Provision a verified synthetic user and workspace only inside the isolated E2E environment';

    public function handle(CreateNewUser $createUser, CreateWorkspace $createWorkspace): int
    {
        try {
            $this->assertE2eIdentity();
            $run = $this->validatedIdentity((string) $this->option('run'), 'run');
            $scenario = $this->validatedIdentity((string) $this->option('scenario'), 'scenario');
            $password = (string) config('e2e.password');
            if ($password === '') {
                throw new RuntimeException('The protected E2E account password is required.');
            }

            $identity = substr(hash('sha256', $run.'|'.$scenario), 0, 24);
            $email = "e2e+{$identity}@example.test";
            $name = "Dolved E2E {$scenario}";
            $workspaceName = "Dolved E2E {$run} {$scenario}";

            $result = DB::transaction(function () use ($createUser, $createWorkspace, $email, $identity, $name, $password, $workspaceName): array {
                $user = User::query()->where('email', $email)->first();
                if ($user === null) {
                    $user = $createUser->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => $password,
                        'password_confirmation' => $password,
                    ]);
                    $user->markEmailAsVerified();
                    $workspace = $createWorkspace->handle($user, $workspaceName);
                } else {
                    $workspace = Workspace::query()
                        ->where('created_by_user_id', $user->id)
                        ->where('name', $workspaceName)
                        ->first();
                    if ($workspace === null || $user->email_verified_at === null) {
                        throw new RuntimeException('Existing E2E identity is incomplete and cannot be reused.');
                    }
                    $owner = $workspace->memberships()
                        ->where('user_id', $user->id)
                        ->where('role', WorkspaceRole::Owner->value)
                        ->exists();
                    if (! $owner) {
                        throw new RuntimeException('Existing E2E workspace ownership is invalid.');
                    }
                }

                return [
                    'email' => $email,
                    'user_public_id' => $user->public_id,
                    'workspace_public_id' => $workspace->public_id,
                    'identity' => $identity,
                ];
            });

            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function assertE2eIdentity(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $databaseMarker = (string) config('e2e.database_marker');
        if (! app()->environment('e2e')
            || config('e2e.resource_marker') !== 'dolved-e2e'
            || $databaseMarker === ''
            || ! str_contains($database, $databaseMarker)) {
            throw new RuntimeException('E2E bootstrap is restricted to the isolated dolved-e2e identity.');
        }
    }

    private function validatedIdentity(string $value, string $label): string
    {
        $value = trim($value);
        if (! preg_match('/\A[a-z0-9][a-z0-9-]{2,63}\z/', $value)) {
            throw new RuntimeException("The E2E {$label} identity is invalid.");
        }

        return $value;
    }
}
