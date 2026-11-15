<?php
// Read-only reconciliation of the three previously materialised evaluation scopes.
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='dolved_e2e_r28_s03'||$db->getConfig('host')!=='postgres')throw new RuntimeException('Wrong connection');
$scopes=['primary'=>['5323cd6b-8b7a-40f9-98b0-9413c75f643e','795fa5fa-2216-4dbf-9a98-94f6a3325765'],
'foreign_tenant'=>['be22830b-ddf1-4de5-b852-f7f4c111cf8e','f69ee65c-8318-4a41-aefe-bf133e9a25dd'],
'prompt_injection_pack'=>['4db6ffe2-6974-415d-8fd3-002b8fb05d05','f5a32899-4c78-4f2d-940c-96340464a5a4']];
$out=[];
foreach($scopes as $name=>[$workspace,$old]){
 $w=App\Models\Workspace::where('public_id',$workspace)->sole();
 $g=$w->activeCorpusGeneration()->with(['embeddingSpaceGeneration','sparseSpaceGeneration'])->sole();
 $previous=App\Models\WorkspaceCorpusGeneration::where('workspace_id',$w->id)->where('public_id',$old)->sole();
 $chunks=fn($generation)=>$db->table('workspace_corpus_generation_chunks as a')->join('document_chunks as c','c.id','=','a.document_chunk_id')->where('a.workspace_corpus_generation_id',$generation->id)->get(['c.id','c.public_id','c.workspace_id','c.document_id']);
 $now=$chunks($g);$then=$chunks($previous);
 $added=$now->whereNotIn('public_id',$then->pluck('public_id'))->values();
 $removed=$then->whereNotIn('public_id',$now->pluck('public_id'))->values();
 $docs=App\Models\Document::where('workspace_id',$w->id)->whereIn('id',$added->pluck('document_id')->merge($removed->pluck('document_id')))->get();
 $out[$name]=['workspace'=>$workspace,'generation'=>$g->public_id,'digest'=>$g->point_manifest_digest,'active_count'=>$now->count(),'previous_generation'=>$old,'previous_count'=>$then->count(),
 'active_document_count'=>$now->pluck('document_id')->unique()->count(),
 'previous_document_public_ids'=>App\Models\Document::where('workspace_id',$w->id)->whereIn('id',$then->pluck('document_id'))->orderBy('public_id')->pluck('public_id')->all(),
 'foreign_assignments'=>$now->where('workspace_id','!=',$w->id)->count(),'added'=>$added,'removed'=>$removed,
 'changed_documents'=>$docs->map(fn($d)=>$d->only(['id','public_id','source_filename','created_at','effective_from','approved_at','status'])),
 'dense'=>$g->embeddingSpaceGeneration->only(['public_id','collection_name','vector_name','dimensions','status']),
 'sparse'=>$g->sparseSpaceGeneration->only(['public_id','collection_name','vector_name','status'])];
}
echo json_encode(['checked_at'=>now()->toIso8601String(),'database'=>$db->getDatabaseName(),'scopes'=>$out],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
