<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use Aws\Exception\AwsException;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class EnsureDocumentStorage
{
    public function handle(): void
    {
        $diskName = (string) config('documents.storage_disk');
        $disk = Storage::disk($diskName);
        if (! $disk instanceof AwsS3V3Adapter) {
            throw new RuntimeException('The production document storage disk must be S3-compatible.');
        }
        $client = $disk->getClient();
        $bucket = (string) config("filesystems.disks.{$diskName}.bucket");
        if ($bucket === '') {
            throw new RuntimeException('The document storage bucket is not configured.');
        }

        try {
            $client->headBucket(['Bucket' => $bucket]);
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }
            $client->createBucket(['Bucket' => $bucket]);
            $client->waitUntil('BucketExists', ['Bucket' => $bucket]);
        }

        $client->putBucketVersioning([
            'Bucket' => $bucket,
            'VersioningConfiguration' => ['Status' => 'Enabled'],
        ]);
        $origin = rtrim((string) config('app.frontend_url'), '/');
        if ($origin !== '') {
            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => ['CORSRules' => [[
                    'AllowedHeaders' => ['*'],
                    'AllowedMethods' => ['GET', 'HEAD', 'PUT'],
                    'AllowedOrigins' => [$origin],
                    'ExposeHeaders' => ['ETag', 'x-amz-version-id'],
                    'MaxAgeSeconds' => 3600,
                ]]],
            ]);
        }

        $status = $client->getBucketVersioning(['Bucket' => $bucket])->get('Status');
        if ($status !== 'Enabled') {
            throw new RuntimeException('Document object versioning could not be verified.');
        }
    }
}
