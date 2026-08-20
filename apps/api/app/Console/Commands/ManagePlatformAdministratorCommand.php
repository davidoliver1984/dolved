<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Platform\ManagePlatformAdministrator;
use App\Services\Platform\PlatformAdministrationCredentialRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ManagePlatformAdministratorCommand extends Command
{
    protected $signature = 'platform-administrator:manage
        {operation : bootstrap, grant, revoke or recover}
        {user_public_id : Immutable public ID of the existing user}
        {--replacement-user-public-id= : Replacement required when revoking the final active administrator}
        {--key-id= : Stable deployment credential key identifier}
        {--idempotency-key= : UUID identifying this command}';

    protected $description = 'Manage platform-administrator authority through the deployment credential boundary';

    public function handle(PlatformAdministrationCredentialRegistry $credentials, ManagePlatformAdministrator $manage): int
    {
        $keyId = (string) $this->option('key-id');
        $idempotencyKey = (string) $this->option('idempotency-key');
        if ($keyId === '' || ! Str::isUuid($idempotencyKey) || ! Str::isUuid((string) $this->argument('user_public_id'))) {
            $this->components->error('A key ID and UUID user/idempotency identifiers are required.');

            return self::FAILURE;
        }
        $secret = $this->secret('Platform administration deployment credential');
        if (! is_string($secret) || $secret === '') {
            $this->components->error('The deployment credential is required through protected input.');

            return self::FAILURE;
        }

        try {
            $credential = $credentials->authenticate($keyId, $secret);
            $result = $manage->handle(
                (string) $this->argument('operation'),
                (string) $this->argument('user_public_id'),
                $this->option('replacement-user-public-id') ?: null,
                $idempotencyKey,
                $credential['key_id'].':'.$credential['version'],
                (string) Str::uuid(),
            );
            $this->components->info(sprintf('Platform administration %s completed for %s.', $result['operation'], $result['target_user_public_id']));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
