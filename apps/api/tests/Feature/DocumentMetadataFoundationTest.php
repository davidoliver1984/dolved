<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Documents\CreateDocument;
use App\Actions\Documents\CreateDocumentVersion;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentGovernanceActorType;
use App\Enums\DocumentGovernanceTargetScope;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFamily;
use App\Models\DocumentTag;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Documents\CreateApplicabilitySnapshot;
use App\Support\Documents\RecordDocumentGovernanceAudit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class DocumentMetadataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_foundation_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('document_families', [
            'description',
            'category_id',
            'owner_user_id',
            'review_due_date',
        ]));
        $this->assertTrue(Schema::hasColumns('documents', [
            'publisher_label',
            'source_url',
            'source_checksum_sha256',
            'checksum_verification_status',
            'checksum_unavailable_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('document_governance_audit_events', [
            'document_family_id',
            'target_scope',
            'actor_type',
            'system_actor_code',
        ]));
        $this->assertTrue(Schema::hasTable('document_categories'));
        $this->assertTrue(Schema::hasTable('document_tags'));
        $this->assertTrue(Schema::hasTable('document_family_tag_assignments'));
    }

    public function test_category_and_tag_names_are_normalised_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $category = DocumentCategory::factory()->for($workspace)->create([
            'name' => "  Safety\u{00A0} Policy  ",
        ]);
        $tag = DocumentTag::factory()->for($workspace)->create([
            'name' => '  CURRENT   Guidance ',
        ]);

        $this->assertSame('safety policy', $category->normalised_name);
        $this->assertSame('current guidance', $tag->normalised_name);
        $this->assertSame(DocumentCategoryStatus::Active, $category->status);

        $this->expectException(QueryException::class);
        DocumentCategory::factory()->for($workspace)->create([
            'name' => 'safety policy',
        ]);
    }

    public function test_the_same_normalised_name_can_exist_in_another_workspace(): void
    {
        DocumentTag::factory()->for(Workspace::factory())->create(['name' => 'Governance']);
        $second = DocumentTag::factory()->for(Workspace::factory())->create(['name' => ' governance ']);

        $this->assertSame('governance', $second->normalised_name);
    }

    public function test_family_metadata_relationships_are_workspace_bound(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $category = DocumentCategory::factory()->for($workspace)->create();
        $tag = DocumentTag::factory()->for($workspace)->create();
        $family = DocumentFamily::factory()->for($workspace)->create([
            'owner_user_id' => $owner->id,
            'category_id' => $category->id,
        ]);

        $family->tags()->attach($tag->id, ['workspace_id' => $workspace->id]);

        $this->assertTrue($family->fresh()->owner->is($owner));
        $this->assertTrue($family->fresh()->category->is($category));
        $this->assertTrue($family->fresh()->tags->contains($tag));

        $otherTag = DocumentTag::factory()->for(Workspace::factory())->create();

        $this->expectException(QueryException::class);
        $family->tags()->attach($otherTag->id, ['workspace_id' => $workspace->id]);
    }

    public function test_new_document_family_is_owned_by_the_creator(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();

        $document = $this->createDocument()->handle(
            $workspace,
            $creator,
            'Medication policy.pdf',
            'application/pdf',
            2_048,
        );
        $document->refresh()->load('family.owner');

        $this->assertTrue($document->family->owner->is($creator));
        $this->assertSame(ChecksumVerificationStatus::Pending, $document->checksum_verification_status);
    }

    public function test_verified_checksum_identity_is_immutable(): void
    {
        $document = Document::factory()->create();
        $document->source_checksum_sha256 = str_repeat('a', 64);
        $document->checksum_verification_status = ChecksumVerificationStatus::Verified;
        $document->save();

        $document->source_checksum_sha256 = str_repeat('b', 64);

        $this->expectException(LogicException::class);
        $document->save();
    }

    public function test_version_scoped_publisher_and_source_url_are_immutable(): void
    {
        $document = Document::factory()->create([
            'publisher_label' => 'Department of Health',
            'source_url' => 'https://example.test/policy',
        ]);
        $document->publisher_label = 'Another publisher';

        $this->expectException(LogicException::class);
        $document->save();
    }

    public function test_new_version_defaults_source_metadata_from_its_immediate_predecessor(): void
    {
        $workspace = Workspace::factory()->create();
        $creator = User::factory()->create();
        $predecessor = Document::factory()->for($workspace)->for($creator, 'createdBy')->create([
            'publisher_label' => 'NHS England',
            'source_url' => 'https://www.england.nhs.uk/policy',
            'effective_from' => now()->subYear(),
        ]);

        $successor = app(CreateDocumentVersion::class)->handle(
            $predecessor,
            $creator,
            'Updated policy.pdf',
            'application/pdf',
            2_048,
            now(),
        );

        $this->assertSame($predecessor->publisher_label, $successor->publisher_label);
        $this->assertSame($predecessor->source_url, $successor->source_url);
    }

    public function test_version_governance_audit_records_family_and_actor_shape(): void
    {
        $document = Document::factory()->create();
        $actor = User::factory()->create();

        (new RecordDocumentGovernanceAudit)->record(
            $document,
            $actor,
            'review_due_date_changed',
            ['review_due_date' => null],
            ['review_due_date' => '2027-01-01'],
        );

        $event = $document->governanceAuditEvents()->sole();

        $this->assertSame($document->document_family_id, $event->document_family_id);
        $this->assertSame(DocumentGovernanceTargetScope::Version, $event->target_scope);
        $this->assertSame(DocumentGovernanceActorType::Human, $event->actor_type);
        $this->assertTrue($event->actor->is($actor));
        $this->assertNull($event->system_actor_code);
    }

    private function createDocument(): CreateDocument
    {
        return new CreateDocument(new CreateApplicabilitySnapshot);
    }
}
