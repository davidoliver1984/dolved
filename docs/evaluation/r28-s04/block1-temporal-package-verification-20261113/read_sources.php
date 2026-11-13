<?php
// Bounded, read-only verification of the reviewer-selected engineering families.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::beginTransaction();
try {
    Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');
    $workspace = App\Models\Workspace::where('public_id', '5323cd6b-8b7a-40f9-98b0-9413c75f643e')->firstOrFail();
    $names = ['maintenance-contractor-schedule', 'serious-incident-review-policy', 'email-retention-policy', 'staff-reference-request-procedure', 'falls-prevention-policy', 'staff-benefits-guidance', 'missing-person-procedure'];
    $documents = App\Models\Document::where('workspace_id', $workspace->id)->where(function ($q) use ($names) {
        foreach ($names as $name) { $q->orWhere('source_filename', 'like', $name.'-v%'); }
        $q->orWhere('source_filename', 'like', 'serious-incident-review-v%');
        $q->orWhere('source_filename', 'like', 'staff-reference-request-v%');
    })->with('family')->orderBy('source_filename')->get();
    if ($documents->count() > 21) { throw new RuntimeException('Unexpected selection size'); }
    $rows = $documents->map(fn ($d) => ['document_id'=>$d->public_id, 'family_id'=>$d->family->public_id, 'source_filename'=>$d->source_filename, 'effective_from'=>$d->effective_from, 'approved_at'=>$d->approved_at, 'withdrawn_at'=>$d->withdrawn_at, 'status'=>$d->status, 'governance_status'=>$d->governance_status, 'source_checksum_sha256'=>$d->source_checksum_sha256, 'chunks'=>App\Models\DocumentChunk::where('document_id',$d->id)->where('workspace_id',$workspace->id)->get()->map(fn($c)=>['chunk_id'=>$c->public_id,'text'=>$c->text])->all()])->all();
    echo json_encode(['read_only'=>true,'workspace_id'=>$workspace->public_id,'selected_filename_prefixes'=>$names,'rows'=>$rows], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
} finally { Illuminate\Support\Facades\DB::rollBack(); }
