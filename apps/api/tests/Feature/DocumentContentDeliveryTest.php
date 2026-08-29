<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ExtractionProjectionStatus;
use App\Enums\ExtractionUploadStatus;
use App\Models\Document;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionProjectionElement;
use App\Models\DocumentExtractionProjectionGeneration;
use App\Models\DocumentExtractionProjectionWarning;
use App\Models\DocumentExtractionUploadAuthorisation;
use App\Models\IngestionEventClaim;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DocumentContentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_delivery_supports_full_range_head_and_unsatisfiable_requests(): void
    {
        Storage::fake('local');
        config()->set('documents.storage_disk', 'local');
        [$user, $workspace, $document] = $this->memberDocument('policy.pdf', 'application/pdf', '0123456789');
        $path = "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}/source";

        $full = $this->actingAs($user)->get($path)
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('0123456789', $full->streamedContent());
        $partial = $this->actingAs($user)->withHeader('Range', 'bytes=2-5')->get($path)
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Content-Length', '4');
        $this->assertSame('2345', $partial->streamedContent());
        $this->actingAs($user)->withHeader('Range', 'bytes=-3')->head($path)
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 7-9/10')
            ->assertHeader('Content-Length', '3')
            ->assertContent('');
        $this->actingAs($user)->withHeader('Range', 'bytes=20-')->getJson($path)
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */10')
            ->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_source_delivery_conceals_other_tenants_before_storage_metadata_is_disclosed(): void
    {
        Storage::fake('local');
        config()->set('documents.storage_disk', 'local');
        [$user, $workspace] = $this->memberDocument('own.pdf', 'application/pdf', 'own');
        [, $otherWorkspace, $otherDocument] = $this->memberDocument('secret.pdf', 'application/pdf', 'secret');

        $this->actingAs($user)
            ->withHeader('Range', 'bytes=999-')
            ->getJson("/api/workspaces/{$workspace->public_id}/documents/{$otherDocument->public_id}/source")
            ->assertNotFound()
            ->assertHeaderMissing('Content-Range');
        $this->assertNotSame($workspace->id, $otherWorkspace->id);
    }

    public function test_extracted_text_is_active_generation_only_bounded_searchable_and_projection_safe(): void
    {
        [$user, $workspace, $document] = $this->memberDocument('policy.pdf', 'application/pdf', 'source');
        $generation = $this->publishedGeneration($document);
        DocumentExtractionProjectionElement::query()->create([
            'projection_generation_id' => $generation->id,
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'element_id' => (string) Str::uuid(),
            'ordinal' => 1,
            'kind' => 'paragraph',
            'text' => 'Record an omitted dose immediately.',
            'payload' => ['text' => 'Record an omitted dose immediately.', 'source_locations' => [['kind' => 'pdf', 'page_number' => 2]], 'provider_reasoning' => 'must-not-leak'],
        ]);
        DocumentExtractionProjectionElement::query()->create([
            'projection_generation_id' => $generation->id,
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'element_id' => (string) Str::uuid(),
            'ordinal' => 2,
            'kind' => 'heading',
            'text' => 'Unrelated heading',
            'payload' => ['text' => 'Unrelated heading'],
        ]);
        DocumentExtractionProjectionWarning::query()->create([
            'projection_generation_id' => $generation->id,
            'ordinal' => 0,
            'payload' => ['code' => 'layout_changed', 'message' => 'Layout was normalised.', 'provider_reasoning' => 'must-not-leak'],
        ]);
        $document->forceFill(['active_extraction_projection_generation_id' => $generation->id])->save();

        $response = $this->actingAs($user)->getJson(
            "/api/workspaces/{$workspace->public_id}/documents/{$document->public_id}/extracted-text?q=omitted&per_page=1",
        );

        $response->assertOk()
            ->assertJsonPath('data.label', 'Text Dolved extracted for search')
            ->assertJsonCount(1, 'data.elements')
            ->assertJsonPath('data.elements.0.ordinal', 1)
            ->assertJsonPath('data.elements.0.source_locations.0.page_number', 2)
            ->assertJsonMissing(['provider_reasoning' => 'must-not-leak'])
            ->assertJsonPath('data.warnings.0.code', 'layout_changed');
    }

    /** @return array{User, Workspace, Document} */
    private function memberDocument(string $filename, string $mediaType, string $bytes): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->member()->create();
        $document = Document::factory()->for($workspace)->indexed()->create([
            'source_filename' => $filename,
            'media_type' => $mediaType,
            'size_bytes' => strlen($bytes),
        ]);
        Storage::disk((string) config('documents.storage_disk'))->put($document->storage_key, $bytes);

        return [$user, $workspace, $document];
    }

    private function publishedGeneration(Document $document): DocumentExtractionProjectionGeneration
    {
        $attempt = IngestionEventClaim::factory()->for($document)->create();
        $upload = DocumentExtractionUploadAuthorisation::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $document->workspace_id,
            'document_id' => $document->id, 'ingestion_event_claim_id' => $attempt->id,
            'object_key' => $document->storage_key.'.extraction.json', 'lease_generation' => 1,
            'expires_at' => now()->addMinute(), 'status' => ExtractionUploadStatus::Verified,
            'artifact_sha256' => str_repeat('a', 64), 'size_bytes' => 1,
            'projection_manifest_digest' => str_repeat('b', 64), 'warning_manifest_digest' => str_repeat('c', 64),
            'element_count' => 2, 'warning_count' => 1, 'verified_at' => now(),
        ]);
        $artifact = DocumentExtractionArtifact::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $document->workspace_id,
            'document_id' => $document->id, 'upload_authorisation_id' => $upload->id,
            'object_key' => $upload->object_key, 'contract_version' => 'document-extraction-artifact-v1',
            'artifact_sha256' => str_repeat('a', 64), 'size_bytes' => 1,
            'projection_manifest_digest' => str_repeat('b', 64), 'warning_manifest_digest' => str_repeat('c', 64),
            'element_count' => 2, 'warning_count' => 1, 'verified_at' => now(), 'published_at' => now(),
        ]);

        return DocumentExtractionProjectionGeneration::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $document->workspace_id,
            'document_id' => $document->id, 'document_extraction_artifact_id' => $artifact->id,
            'status' => ExtractionProjectionStatus::Published, 'expected_element_count' => 2,
            'expected_warning_count' => 1, 'expected_projection_manifest_digest' => str_repeat('b', 64),
            'expected_warning_manifest_digest' => str_repeat('c', 64),
            'verified_projection_manifest_digest' => str_repeat('b', 64),
            'verified_warning_manifest_digest' => str_repeat('c', 64),
            'verified_at' => now(), 'published_at' => now(), 'changes' => [],
        ]);
    }
}
