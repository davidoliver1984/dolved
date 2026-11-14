<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::beginTransaction();
try {
  Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');
  $user = App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
  $scope = app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($user,'5323cd6b-8b7a-40f9-98b0-9413c75f643e');
  $timeline = app(App\Support\Documents\DocumentAuthorityTimeline::class);
  $periods = app(App\Services\Retrieval\TemporalPeriodResolver::class);
  $checks = [
    'F1'=>['serious-complaint-escalation',['2026-03-10','2026-11-14'],[]],
    'F2'=>['admissions-preadmission',['2026-11-14'],['2025-03']],
    'F3'=>['self-administration-medication',['2026-05-20','2026-06-10','2026-11-14'],[]],
    'F4'=>['medication-fridge-breakdown',['2025-11-01','2025-11-30','2026-11-14'],['2025-11']],
    'F5'=>['continence-care-policy',['2026-01-01','2026-01-31','2026-11-14'],['2026-01']],
  ];
  $out=[];
  foreach($checks as $item=>[$stem,$dates,$months]){
    $docs=App\Models\Document::where('workspace_id',$scope->workspace->id)->where('source_filename','like',$stem.'-v%')->with('family')->get();
    $fam=$docs->first()?->family;
    $row=['item'=>$item,'stem'=>$stem,'doc_count'=>$docs->count(),'family_count'=>$docs->pluck('document_family_id')->unique()->count(),
      'catalogue_title'=>$fam?->name,'family_tombstoned'=>$fam?->tombstoned_at,'versions'=>[],'dates'=>[],'periods'=>[]];
    foreach($docs as $d){
      $chunks=App\Models\DocumentChunk::where('document_id',$d->id)->where('workspace_id',$scope->workspace->id)->orderBy('id')->get();
      $active=$d->chunks()->whereHas('workspaceCorpusGenerations',fn($q)=>$q->where('workspace_corpus_generations.id',$scope->activeCorpusGeneration->id))->exists();
      $row['versions'][]=['source_filename'=>$d->source_filename,'document_id'=>$d->public_id,'attributes'=>collect($d->getAttributes())->only(['status','governance_status','approved_at','effective_from','withdrawn_at','applicability_scope','indexed_at','source_checksum_sha256'])->all(),
        'authority_start'=>$timeline->authorityStart($d)?->toIso8601String(),'in_active_corpus'=>$active,'chunk_count'=>$chunks->count(),'text'=>$chunks->pluck('text')->implode("\n<<CHUNK>>\n")];
    }
    foreach($dates as $date){ $r=$timeline->resolve($fam,Carbon\CarbonImmutable::createFromFormat('!Y-m-d',$date)); $row['dates'][]=['at'=>$date,'selected'=>$r?->source_filename]; }
    foreach($months as $m){ $r=$periods->resolve($fam,$m); $row['periods'][]=['period'=>$m,'selected'=>$r->document?->source_filename,'reason'=>$r->reason?->value]; }
    $out[]=$row;
  }
  echo json_encode(['read_only'=>true,'app_timezone'=>config('app.timezone'),'workspace'=>$scope->workspace->public_id,'active_corpus_generation'=>$scope->activeCorpusGeneration?->public_id ?? $scope->activeCorpusGeneration?->id,'rows'=>$out],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} finally { Illuminate\Support\Facades\DB::rollBack(); }
