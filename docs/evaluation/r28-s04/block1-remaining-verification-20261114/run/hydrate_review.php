<?php
// Post-execution read-only hydration. Not an execution input or application change.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
if ($db->getDatabaseName() !== 'dolved_e2e_r28_s03') throw new RuntimeException('Wrong database');
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (count($input['chunk_ids']) > 1000 || count($input['version_ids']) > 100) throw new RuntimeException('Unbounded selection');
$db->beginTransaction();
try {
    $db->statement('SET TRANSACTION READ ONLY');
    $w = App\Models\Workspace::where('public_id', '5323cd6b-8b7a-40f9-98b0-9413c75f643e')->sole();
    $rows = App\Models\DocumentChunk::query()->where('workspace_id', $w->id)
        ->where(function ($q) use ($input) {
            $q->whereIn('public_id', $input['chunk_ids'])->orWhereHas('document', fn ($d) => $d->whereIn('public_id', $input['version_ids']));
        })->with(['document.family'])->orderBy('public_id')->get()->map(function ($c) use ($w) {
            if ($c->document->workspace_id !== $w->id || $c->document->family->workspace_id !== $w->id) throw new RuntimeException('Ownership mismatch');
            return ['chunk_id'=>$c->public_id,'numeric_chunk_id'=>$c->id,'document_id'=>$c->document->public_id,
                'numeric_document_id'=>$c->document->id,'family_id'=>$c->document->family->public_id,
                'family_title'=>$c->document->family->name,'workspace_id'=>$w->public_id,
                'source_checksum_sha256'=>$c->document->source_checksum_sha256,'effective_from'=>$c->document->effective_from,
                'text'=>$c->text];
        })->all();
    if (array_diff($input['chunk_ids'], array_column($rows, 'chunk_id'))) throw new RuntimeException('Missing ranked chunks');
    echo json_encode(['read_only'=>true,'selection'=>$input,'rows'=>$rows], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} finally { $db->rollBack(); }
