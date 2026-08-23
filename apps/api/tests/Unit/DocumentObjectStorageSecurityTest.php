<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\DocumentUploadException;
use App\Models\Document;
use App\Services\Documents\DocumentObjectStorage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DocumentObjectStorageSecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_presigned_upload_is_content_type_bound_and_expires_at_the_configured_deadline(): void
    {
        CarbonImmutable::setTestNow('2026-08-23T12:00:00Z');
        config()->set('documents.presigned_url_lifetime_seconds', 600);
        config()->set('documents.upload_disk', 'uploads');
        $document = new Document([
            'media_type' => 'application/pdf',
        ]);
        $document->storage_key = 'workspaces/workspace/documents/document/source.pdf';

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('temporaryUploadUrl')
            ->once()
            ->withArgs(function (string $key, CarbonImmutable $expiresAt, array $options) use ($document): bool {
                return $key === $document->storage_key
                    && $expiresAt->equalTo(CarbonImmutable::now()->addMinutes(10))
                    && $options === ['ContentType' => 'application/pdf'];
            })
            ->andReturn([
                'url' => 'https://object-storage.example/signed',
                'headers' => [
                    'Content-Type' => 'application/pdf',
                    'Host' => 'object-storage.example',
                ],
            ]);
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->once()->with('uploads')->andReturn($disk);

        $request = (new DocumentObjectStorage($filesystems))->createUploadRequest($document);

        $this->assertSame('2026-08-23T12:10:00+00:00', $request['expires_at']);
        $this->assertSame(['Content-Type' => 'application/pdf'], $request['headers']);
    }

    public function test_storage_provider_failure_is_replaced_with_a_safe_typed_error(): void
    {
        config()->set('documents.upload_disk', 'uploads');
        $document = new Document(['media_type' => 'application/pdf']);
        $document->storage_key = 'workspaces/workspace/documents/document/source.pdf';
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('temporaryUploadUrl')
            ->once()
            ->andThrow(new RuntimeException('secret endpoint and credential details'));
        $filesystems = Mockery::mock(FilesystemFactory::class);
        $filesystems->shouldReceive('disk')->once()->andReturn($disk);

        try {
            (new DocumentObjectStorage($filesystems))->createUploadRequest($document);
            $this->fail('Expected a typed upload failure.');
        } catch (DocumentUploadException $exception) {
            $this->assertSame('Object storage is temporarily unavailable.', $exception->getMessage());
            $this->assertStringNotContainsString('secret', $exception->getMessage());
            $this->assertStringNotContainsString('credential', $exception->getMessage());
        }
    }
}
