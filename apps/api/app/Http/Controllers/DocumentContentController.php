<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionProjectionStatus;
use App\Models\Document;
use App\Models\DocumentExtractionProjectionElement;
use App\Models\DocumentExtractionProjectionWarning;
use App\Models\User;
use App\Queries\Documents\FindDocumentFamilyForWorkspace;
use App\Queries\Documents\FindDocumentForWorkspace;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Services\Documents\DocumentObjectStorage;
use App\Services\Documents\SingleByteRange;
use App\Services\Documents\UnsatisfiableByteRange;
use App\Support\Documents\DocumentAuthorityTimeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class DocumentContentController extends Controller
{
    public function comparison(Request $request, string $workspacePublicId, string $familyPublicId, FindWorkspaceForUser $workspaces, FindDocumentFamilyForWorkspace $families, DocumentAuthorityTimeline $timeline): JsonResponse
    {
        $values = $request->validate(['from' => ['sometimes', 'uuid'], 'to' => ['sometimes', 'uuid']]);
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $family = $families->handle($workspace, $familyPublicId);
        Gate::authorize('viewDocumentMetadata', $workspace);
        $versions = $family->documents()->with(['predecessor', 'activeExtractionProjectionGeneration'])->orderBy('id')->get();
        $find = function (?string $publicId) use ($versions): ?Document {
            if ($publicId === null) {
                return null;
            }
            /** @var Document|null $document */
            $document = $versions->firstWhere('public_id', $publicId);
            abort_if($document === null, 404);

            return $document;
        };
        $from = $find($values['from'] ?? null);
        $to = $find($values['to'] ?? null);
        if ($from === null && $to === null) {
            $to = $timeline->resolve($family, now());
            $from = $to?->predecessor;
        } elseif ($from === null) {
            $from = $to?->predecessor;
        } elseif ($to === null) {
            $to = $versions->first(fn (Document $candidate): bool => $candidate->predecessor_document_id === $from->id);
        }
        abort_if($from?->is($to), 422, 'Choose two distinct versions.');
        if ($from === null || $to === null) {
            return response()->json(['data' => ['available' => false, 'reason' => 'This family has only one comparable version.']]);
        }

        $sides = collect(['from' => $from, 'to' => $to])->map(fn (Document $document): array => $this->comparisonSide($document))->all();
        $left = collect($sides['from']['elements'])->keyBy('ordinal');
        $right = collect($sides['to']['elements'])->keyBy('ordinal');
        $differences = $left->keys()->merge($right->keys())->unique()->sort()->map(function ($ordinal) use ($left, $right): array {
            $before = $left->get($ordinal);
            $after = $right->get($ordinal);

            return ['ordinal' => $ordinal, 'status' => $before === null ? 'added' : ($after === null ? 'removed' : ($before['kind'] === $after['kind'] && $before['text'] === $after['text'] ? 'unchanged' : 'changed')), 'before' => $before, 'after' => $after];
        })->values()->all();

        return response()->json(['data' => ['available' => true, 'family' => ['public_id' => $family->public_id, 'name' => $family->name], 'from' => $sides['from'], 'to' => $sides['to'], 'differences' => $differences]]);
    }

    public function source(
        Request $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
        DocumentObjectStorage $storage,
    ): Response {
        $document = $this->authorisedDocument($request, $workspacePublicId, $documentPublicId, $workspaces, $documents);
        $metadata = $storage->metadata($document);
        abort_if($metadata === null, 404);

        $rangeHeader = $request->header('Range');
        try {
            $range = SingleByteRange::parse($rangeHeader, $metadata['size_bytes']);
        } catch (UnsatisfiableByteRange) {
            $headers = $this->sourceHeaders($document, $metadata['content_type'], 0);
            $headers['Content-Range'] = 'bytes */'.$metadata['size_bytes'];
            $this->logSource($document, 416, $rangeHeader, 0);

            return $request->isMethod('HEAD')
                ? response('', 416, $headers)
                : response()->json(['message' => 'Requested range is not satisfiable.'], 416, $headers);
        }

        $status = $range === null ? 200 : 206;
        $byteCount = $range?->length() ?? $metadata['size_bytes'];
        $headers = $this->sourceHeaders($document, $metadata['content_type'], $byteCount);
        if ($range !== null) {
            $headers['Content-Range'] = "bytes {$range->start}-{$range->end}/{$metadata['size_bytes']}";
        }
        $this->logSource($document, $status, $rangeHeader, $request->isMethod('HEAD') ? 0 : $byteCount);

        if ($request->isMethod('HEAD')) {
            return response('', $status, $headers);
        }

        return response()->stream(function () use ($storage, $document, $range, $byteCount): void {
            $stream = $range === null ? $storage->readStream($document) : $storage->readRange($document, $range->start);
            $remaining = $byteCount;
            try {
                while ($remaining > 0 && ! feof($stream) && connection_aborted() === 0) {
                    $chunk = fread($stream, min(64 * 1024, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                fclose($stream);
            }
        }, $status, $headers);
    }

    public function extractedText(
        Request $request,
        string $workspacePublicId,
        string $documentPublicId,
        FindWorkspaceForUser $workspaces,
        FindDocumentForWorkspace $documents,
    ): JsonResponse {
        $values = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'q' => ['sometimes', 'string', 'max:200'],
        ]);
        $document = $this->authorisedDocument($request, $workspacePublicId, $documentPublicId, $workspaces, $documents);
        $generation = $document->activeExtractionProjectionGeneration()
            ->where('status', ExtractionProjectionStatus::Published)
            ->firstOrFail();
        $query = DocumentExtractionProjectionElement::query()
            ->where('projection_generation_id', $generation->id)
            ->when($values['q'] ?? null, function (Builder $query, string $search): void {
                if (DB::getDriverName() === 'pgsql') {
                    $query->whereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$search]);
                } else {
                    $query->whereRaw('LOWER(text) LIKE ?', ['%'.mb_strtolower($search).'%']);
                }
            })
            ->orderBy('ordinal')->orderBy('id');
        $elements = $query->cursorPaginate((int) ($values['per_page'] ?? 25), ['*'], 'cursor');
        $warnings = DocumentExtractionProjectionWarning::query()
            ->where('projection_generation_id', $generation->id)
            ->orderBy('ordinal')->limit(100)->get();

        return response()->json(['data' => [
            'label' => 'Text Dolved extracted for search',
            'notice' => "Extracted text may not preserve the source's visual layout or table structure exactly.",
            'projection_generation_id' => $generation->public_id,
            'elements' => collect($elements->items())->map(fn (DocumentExtractionProjectionElement $element): array => $this->element($element))->all(),
            'warnings' => $warnings->map(fn (DocumentExtractionProjectionWarning $warning): array => $this->warning($warning))->all(),
            'warnings_truncated' => $generation->expected_warning_count > $warnings->count(),
            'changes' => collect(array_slice($generation->changes ?? [], 0, 100))
                ->map(fn (array $change): array => $this->change($change))->all(),
            'changes_truncated' => count($generation->changes ?? []) > 100,
            'pagination' => [
                'next_cursor' => $elements->nextCursor()?->encode(),
                'previous_cursor' => $elements->previousCursor()?->encode(),
                'per_page' => $elements->perPage(),
            ],
        ]]);
    }

    private function authorisedDocument(Request $request, string $workspacePublicId, string $documentPublicId, FindWorkspaceForUser $workspaces, FindDocumentForWorkspace $documents): Document
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $workspaces->handle($user, $workspacePublicId)->workspace;
        $document = $documents->handle($workspace, $documentPublicId);
        abort_unless($document->status === DocumentStatus::Indexed, 404);
        Gate::authorize('view', $document);

        return $document;
    }

    /** @return array<string, string> */
    private function sourceHeaders(Document $document, string $contentType, int $length): array
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $document->source_filename) ?: 'document';
        $disposition = in_array($contentType, ['application/pdf', 'text/plain', 'text/markdown'], true) ? 'inline' : 'attachment';

        return [
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
            'Content-Type' => $contentType,
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition($disposition, $document->source_filename, $fallback),
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /** @return array<string, mixed> */
    private function element(DocumentExtractionProjectionElement $element): array
    {
        return [
            'id' => $element->element_id,
            'ordinal' => $element->ordinal,
            'kind' => $element->kind,
            'text' => $element->text,
            'source_locations' => $element->payload['source_locations'] ?? [],
            'level' => $element->payload['level'] ?? null,
            'rows' => $element->payload['rows'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function warning(DocumentExtractionProjectionWarning $warning): array
    {
        return [
            'code' => $warning->payload['code'] ?? null,
            'message' => $warning->payload['message'] ?? null,
            'element_id' => $warning->payload['element_id'] ?? null,
            'source_location' => $warning->payload['source_location'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function comparisonSide(Document $document): array
    {
        $generation = $document->activeExtractionProjectionGeneration;
        $available = $document->status === DocumentStatus::Indexed && $generation?->status === ExtractionProjectionStatus::Published;
        $elements = $available ? $generation->elements()->orderBy('ordinal')->orderBy('id')->limit(501)->get() : collect();

        return [
            'document' => ['public_id' => $document->public_id, 'source_filename' => $document->source_filename, 'publisher_label' => $document->publisher_label, 'source_url' => $document->source_url, 'governance_status' => $document->governance_status->value, 'effective_from' => $document->effective_from?->toIso8601String(), 'approved_at' => $document->approved_at?->toIso8601String(), 'withdrawn_at' => $document->withdrawn_at?->toIso8601String()],
            'content_available' => $available,
            'truncated' => $elements->count() > 500,
            'elements' => $elements->take(500)->map(fn (DocumentExtractionProjectionElement $element): array => $this->element($element))->values()->all(),
            'warnings' => $available ? $generation->warnings()->orderBy('ordinal')->limit(100)->get()->map(fn (DocumentExtractionProjectionWarning $warning): array => $this->warning($warning))->values()->all() : [],
        ];
    }

    /** @param array<string, mixed> $change
     * @return array<string, mixed>
     */
    private function change(array $change): array
    {
        return [
            'code' => $change['code'] ?? null,
            'message' => $change['message'] ?? null,
            'source_element_ids' => $change['source_element_ids'] ?? [],
        ];
    }

    private function logSource(Document $document, int $status, ?string $range, int $bytes): void
    {
        Log::info('Document source delivered.', [
            'event_name' => 'document.source.delivered.v1',
            'workspace_id' => $document->workspace->public_id,
            'document_id' => $document->public_id,
            'result_status' => $status,
            'requested_range' => $this->safeRangeForTelemetry($range),
            'byte_count' => $bytes,
        ]);
    }

    private function safeRangeForTelemetry(?string $range): ?string
    {
        if ($range === null) {
            return null;
        }

        return preg_match('/^bytes=(?:\d+-\d*|-\d+)(?:,(?:\d+-\d*|-\d+))*$/D', $range) === 1
            && strlen($range) <= 160
            ? $range
            : 'invalid';
    }
}
