<?php
// Read-only, authorised-workspace inventory. No retrieval or service adapter.
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='dolved_e2e_r28_s03'||$db->getConfig('host')!=='postgres') throw new RuntimeException('Wrong database');
$w=App\Models\Workspace::where('public_id','5323cd6b-8b7a-40f9-98b0-9413c75f643e')->sole();
$g=$w->activeCorpusGeneration()->with(['embeddingSpaceGeneration','sparseSpaceGeneration'])->sole();
echo json_encode(['corpus'=>$g->only(['public_id','point_manifest_digest','status']),
 'dense'=>$g->embeddingSpaceGeneration->only(['public_id','collection_name','vector_name','dimensions','distance','status']),
 'sparse'=>$g->sparseSpaceGeneration->only(['public_id','collection_name','vector_name','status']),
 'assigned_chunk_ids'=>$db->table('workspace_corpus_generation_chunks as a')->join('document_chunks as c','c.id','=','a.document_chunk_id')->where('a.workspace_corpus_generation_id',$g->id)->orderBy('c.public_id')->pluck('c.public_id')->all()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
