<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::beginTransaction();
try {
    Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');
    $workspace = App\Models\Workspace::where('public_id', '5323cd6b-8b7a-40f9-98b0-9413c75f643e')->firstOrFail();
    $documents = App\Models\Document::where('workspace_id', $workspace->id)->whereIn('source_filename', ['cleaning-contract-summary-v1.docx', 'cleaning-contract-summary-v2.docx'])->get();
    $rows = $documents->map(fn ($d) => ['document_id'=>$d->public_id, 'source_filename'=>$d->source_filename, 'effective_from'=>$d->effective_from, 'source_checksum_sha256'=>$d->source_checksum_sha256, 'chunks'=>App\Models\DocumentChunk::where('document_id',$d->id)->where('workspace_id',$workspace->id)->get()->map(fn($c)=>['chunk_id'=>$c->public_id,'text'=>$c->text])->all()])->all();
    echo json_encode(['read_only'=>true,'rows'=>$rows], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
} finally {
    Illuminate\Support\Facades\DB::rollBack();
}
