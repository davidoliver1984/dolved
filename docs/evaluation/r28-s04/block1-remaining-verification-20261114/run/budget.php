<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='dolved_e2e_r28_s03') throw new RuntimeException('Wrong database');
$w=App\Models\Workspace::where('public_id','5323cd6b-8b7a-40f9-98b0-9413c75f643e')->sole();
$max=$db->table('document_chunks')->where('workspace_id',$w->id)->selectRaw('max(octet_length(text)) as max_text_bytes, max(token_count) as max_recorded_tokens')->first();
echo json_encode(['corpus_bounds'=>$max,'max_context_characters'=>config('generation.max_context_characters'),'reranker_config'=>config('retrieval.timeout_budget')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
