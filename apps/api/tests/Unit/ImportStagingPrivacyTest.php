<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ImportItem;
use App\Models\Workspace;
use App\Services\Documents\ImportStagingStorage;
use App\Support\Documents\ImportStagingObjectKey;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use LogicException;
use Mockery;
use Tests\TestCase;

final class ImportStagingPrivacyTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_staging_key_is_exact_workspace_item_scoped_and_contains_no_filename(): void
    {
        config()->set('imports.staging_prefix', 'imports/workspaces');
        $workspace = $this->workspace(10, '41b20555-55d4-474f-a3df-87a64f0318aa');
        $item = $this->item(20, 10, '754615ff-5590-4f80-b485-534970045ddd');

        $key = (new ImportStagingObjectKey)->for($workspace, $item);

        $this->assertSame(
            'imports/workspaces/41b20555-55d4-474f-a3df-87a64f0318aa/items/754615ff-5590-4f80-b485-534970045ddd/source',
            $key,
        );
        $this->assertStringNotContainsString('.pdf', $key);
    }

    public function test_cross_workspace_and_substituted_keys_fail_before_storage_access(): void
    {
        $workspace = $this->workspace(10, '41b20555-55d4-474f-a3df-87a64f0318aa');
        $item = $this->item(20, 11, '754615ff-5590-4f80-b485-534970045ddd');

        $this->expectException(LogicException::class);
        (new ImportStagingObjectKey)->for($workspace, $item);
    }

    public function test_presigned_operation_is_content_type_time_and_exact_key_bounded(): void
    {
        CarbonImmutable::setTestNow('2026-08-31T12:00:00Z');
        config()->set('imports.staging_disk', 'staging');
        config()->set('imports.presigned_url_lifetime_seconds', 600);
        $workspace = $this->workspace(10, '41b20555-55d4-474f-a3df-87a64f0318aa');
        $item = $this->item(20, 10, '754615ff-5590-4f80-b485-534970045ddd');
        $item->staged_object_key = (new ImportStagingObjectKey)->for($workspace, $item);

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('temporaryUploadUrl')->once()->with(
            $item->staged_object_key,
            Mockery::on(fn (CarbonImmutable $expires): bool => $expires->equalTo(CarbonImmutable::now()->addMinutes(10))),
            ['ContentType' => 'application/pdf'],
        )->andReturn([
            'url' => 'https://storage.example/exact-signed-operation',
            'headers' => ['Content-Type' => 'application/pdf', 'Host' => 'storage.example'],
        ]);
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->once()->with('staging')->andReturn($disk);

        $request = (new ImportStagingStorage($filesystems, new ImportStagingObjectKey))
            ->createUploadRequest($workspace, $item, 'application/pdf');

        $this->assertSame('PUT', $request['method']);
        $this->assertSame(['Content-Type' => 'application/pdf'], $request['headers']);
        $this->assertSame('2026-08-31T12:10:00+00:00', $request['expires_at']);
    }

    public function test_configuration_reuses_private_upload_disk_without_public_visibility(): void
    {
        $this->assertSame('s3_uploads', config('imports.staging_disk'));
        $this->assertSame('s3', config('imports.storage_disk'));
        $this->assertArrayNotHasKey('visibility', config('filesystems.disks.s3_uploads'));
        $this->assertTrue((bool) config('filesystems.disks.s3_uploads.throw'));
        $this->assertSame(7, config('imports.retention_days'));
    }

    public function test_preflight_read_is_exact_key_and_lease_time_bounded(): void
    {
        CarbonImmutable::setTestNow('2026-08-31T12:00:00Z');
        config()->set('imports.storage_disk', 'private-storage');
        config()->set('imports.preflight.lease_seconds', 600);
        $workspace = $this->workspace(10, '41b20555-55d4-474f-a3df-87a64f0318aa');
        $item = $this->item(20, 10, '754615ff-5590-4f80-b485-534970045ddd');
        $item->staged_object_key = (new ImportStagingObjectKey)->for($workspace, $item);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('temporaryUrl')->once()->with(
            $item->staged_object_key,
            Mockery::on(fn (CarbonImmutable $expires): bool => $expires->equalTo(CarbonImmutable::now()->addMinutes(10))),
        )->andReturn('https://storage.example/exact-read');
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->once()->with('private-storage')->andReturn($disk);

        $request = (new ImportStagingStorage($filesystems, new ImportStagingObjectKey))
            ->createPreflightReadRequest($workspace, $item);

        $this->assertSame($item->staged_object_key, $request['key']);
        $this->assertSame('https://storage.example/exact-read', $request['read_url']);
        $this->assertSame('2026-08-31T12:10:00+00:00', $request['expires_at']);
    }

    public function test_cleanup_addresses_only_the_bound_exact_key(): void
    {
        config()->set('imports.storage_disk', 'private-storage');
        $workspace = $this->workspace(10, '41b20555-55d4-474f-a3df-87a64f0318aa');
        $item = $this->item(20, 10, '754615ff-5590-4f80-b485-534970045ddd');
        $item->staged_object_key = (new ImportStagingObjectKey)->for($workspace, $item);

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($item->staged_object_key)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($item->staged_object_key)->andReturnTrue();
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->once()->with('private-storage')->andReturn($disk);

        (new ImportStagingStorage($filesystems, new ImportStagingObjectKey))
            ->deleteExact($workspace, $item);

        $this->assertTrue(true);
    }

    private function workspace(int $id, string $publicId): Workspace
    {
        $workspace = new Workspace;
        $workspace->id = $id;
        $workspace->public_id = $publicId;
        $workspace->exists = true;

        return $workspace;
    }

    private function item(int $id, int $workspaceId, string $publicId): ImportItem
    {
        $item = new ImportItem;
        $item->id = $id;
        $item->workspace_id = $workspaceId;
        $item->public_id = $publicId;
        $item->exists = true;

        return $item;
    }
}
