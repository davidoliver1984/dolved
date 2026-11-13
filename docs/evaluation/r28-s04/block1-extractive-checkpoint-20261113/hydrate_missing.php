<?php

declare(strict_types=1);

// Analysis-only hydration of observed candidate IDs, never an execution input.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ids = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! is_array($ids) || count($ids) > 1000 || count($ids) !== count(array_unique($ids))) {
    throw new RuntimeException('Invalid bounded chunk selection');
}
Illuminate\Support\Facades\DB::beginTransaction();
try {
    Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');
    $workspaceIds = App\Models\Workspace::query()->whereIn('public_id', [
        '5323cd6b-8b7a-40f9-98b0-9413c75f643e',
        '4db6ffe2-6974-415d-8fd3-002b8fb05d05',
    ])->pluck('id');
    $rows = App\Models\DocumentChunk::query()->whereIn('public_id', $ids)
        ->whereIn('workspace_id', $workspaceIds)->with(['document.family', 'workspace'])
        ->orderBy('public_id')->get()->map(function ($chunk) {
            if ($chunk->document->workspace_id !== $chunk->workspace_id || $chunk->document->family->workspace_id !== $chunk->workspace_id) {
                throw new RuntimeException('Chunk ownership mismatch');
            }
            return [
                'chunk_id' => $chunk->public_id, 'document_id' => $chunk->document->public_id,
                'family_id' => $chunk->document->family->public_id,
                'family_title' => $chunk->document->family->title,
                'source_filename' => $chunk->document->source_filename,
                'source_checksum_sha256' => $chunk->document->source_checksum_sha256,
                'workspace_id' => $chunk->workspace->public_id, 'text' => $chunk->text,
            ];
        })->all();
    if (count($rows) !== count($ids)) {
        throw new RuntimeException('Observed chunks missing or outside allowed workspaces');
    }
    echo json_encode(['read_only' => true, 'requested_chunk_ids' => $ids, 'rows' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
} finally {
    Illuminate\Support\Facades\DB::rollBack();
}
