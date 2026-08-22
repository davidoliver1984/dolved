<?php

namespace Tests\Feature;

use App\Actions\Documents\CreateDocument;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\DocumentGovernanceStatus;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class E2eBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'e2e',
            'e2e.resource_marker' => 'dolved-e2e',
            'e2e.database_marker' => ':memory:',
            'e2e.password' => 'Strong-E2E-Password-42!',
        ]);
        $this->app->detectEnvironment(fn (): string => 'e2e');
    }

    public function test_it_provisions_a_verified_owner_through_the_real_workspace_action(): void
    {
        $workspaceAction = Mockery::mock(CreateWorkspace::class)->makePartial();
        $workspaceAction->shouldReceive('handle')->once()->passthru();
        $this->app->instance(CreateWorkspace::class, $workspaceAction);

        $exit = Artisan::call('e2e:bootstrap', ['--run' => 'run-alpha', '--scenario' => 'primary']);
        $payload = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exit);
        $this->assertSame(['email', 'user_public_id', 'workspace_public_id', 'identity'], array_keys($payload));
        $user = User::query()->where('public_id', $payload['user_public_id'])->sole();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->workspaces()->where('workspaces.public_id', $payload['workspace_public_id'])->exists());
    }

    public function test_same_identity_is_repeatable_without_duplicate_user_or_workspace(): void
    {
        $arguments = ['--run' => 'run-repeat', '--scenario' => 'primary'];
        $this->assertSame(0, Artisan::call('e2e:bootstrap', $arguments));
        $first = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, Artisan::call('e2e:bootstrap', $arguments));
        $second = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_memberships', 1);
    }

    public function test_it_refuses_every_non_e2e_environment(): void
    {
        foreach (['local', 'development', 'staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn (): string => $environment);
            $this->assertSame(1, Artisan::call('e2e:bootstrap', ['--run' => 'run-refuse', '--scenario' => $environment]));
            $this->assertStringContainsString('restricted to the isolated dolved-e2e identity', Artisan::output());
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_refuses_missing_resource_and_database_markers(): void
    {
        config(['e2e.resource_marker' => 'developer']);
        $this->assertSame(1, Artisan::call('e2e:bootstrap', ['--run' => 'run-marker', '--scenario' => 'resource']));

        config(['e2e.resource_marker' => 'dolved-e2e', 'e2e.database_marker' => 'not-present']);
        $this->assertSame(1, Artisan::call('e2e:bootstrap', ['--run' => 'run-marker', '--scenario' => 'database']));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_no_public_workspace_creation_route_exists(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());

        $this->assertFalse($routes->contains(fn ($route): bool => in_array('POST', $route->methods(), true)
            && $route->uri() === 'api/workspaces'));
    }

    public function test_it_approves_an_e2e_document_through_the_real_governance_action(): void
    {
        $this->assertSame(0, Artisan::call('e2e:bootstrap', ['--run' => 'run-approve', '--scenario' => 'primary']));
        $identity = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        $user = User::query()->where('public_id', $identity['user_public_id'])->sole();
        $workspace = Workspace::query()->where('public_id', $identity['workspace_public_id'])->sole();
        $document = app(CreateDocument::class)->handle(
            $workspace,
            $user,
            'E2E policy.txt',
            'text/plain',
            42,
            'txt',
        );

        $arguments = [
            '--workspace' => $workspace->public_id,
            '--document' => $document->public_id,
            '--actor' => $user->public_id,
        ];
        $this->assertSame(0, Artisan::call('e2e:approve-document', $arguments));
        $this->assertSame(
            DocumentGovernanceStatus::Approved,
            Document::query()->whereKey($document->id)->sole()->governance_status,
        );
        $this->assertSame(0, Artisan::call('e2e:approve-document', $arguments));
    }

    public function test_document_approval_refuses_non_e2e_environments(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->assertSame(1, Artisan::call('e2e:approve-document', [
            '--workspace' => 'workspace',
            '--document' => 'document',
            '--actor' => 'actor',
        ]));
        $this->assertStringContainsString(
            'restricted to the isolated dolved-e2e identity',
            Artisan::output(),
        );
    }
}
