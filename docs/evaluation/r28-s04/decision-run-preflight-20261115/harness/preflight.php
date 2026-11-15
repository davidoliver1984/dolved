<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=Illuminate\Support\Facades\DB::connection();
$identity=['driver'=>$c->getDriverName(),'host'=>$c->getConfig('host'),'database'=>$c->getDatabaseName()];
if($identity!==['driver'=>'pgsql','host'=>'postgres','database'=>'dolved_e2e_r28_s03']) throw new RuntimeException('Wrong connection');
$workspace=App\Models\Workspace::where('public_id','5323cd6b-8b7a-40f9-98b0-9413c75f643e')->sole();
$user=App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
app(App\Queries\Workspaces\FindWorkspaceForUser::class)->handle($user,$workspace->public_id);
$generation=$workspace->activeCorpusGeneration()->with(['embeddingSpaceGeneration.embeddingProfile','sparseSpaceGeneration.sparseEmbeddingProfile'])->sole();
$assigned=Illuminate\Support\Facades\DB::table('workspace_corpus_generation_chunks')->where('workspace_corpus_generation_id',$generation->id)->count();
$foreign=Illuminate\Support\Facades\DB::table('workspace_corpus_generation_chunks as a')->join('document_chunks as c','c.id','=','a.document_chunk_id')->where('a.workspace_corpus_generation_id',$generation->id)->where('c.workspace_id','<>',$workspace->id)->count();
if($assigned!==986 || $foreign!==0 || $generation->status->value!=='active') throw new RuntimeException('Corpus identity mismatch');
echo json_encode(['at'=>now()->toIso8601String(),'identity'=>$identity,'environment'=>app()->environment(),'timezone'=>config('app.timezone'),'workspace'=>$workspace->public_id,'actor'=>$user->public_id,'migration'=>Illuminate\Support\Facades\DB::table('migrations')->where('migration','2026_11_14_000001_add_temporal_context_to_generated_answers')->first(),'column'=>Illuminate\Support\Facades\Schema::hasColumn('generated_answers','temporal_context'),'corpus'=>['id'=>$generation->public_id,'digest'=>$generation->point_manifest_digest,'assigned'=>$assigned,'foreign'=>$foreign,'dense'=>$generation->embeddingSpaceGeneration->embeddingProfile->only(['provider','model','dimensions']),'sparse'=>$generation->sparseSpaceGeneration->sparseEmbeddingProfile->only(['provider','model'])],'needs_profile'=>app(App\Services\Generation\GenerationProfileFactory::class)->configuredNeeds(),'candidate_k'=>config('conversation.candidate_k'),'retrieval_attempts'=>config('retrieval.max_attempts')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
