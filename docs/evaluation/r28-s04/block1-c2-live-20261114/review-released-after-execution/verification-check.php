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
    'G1'=>['window-restrictor-policy',[],['2025-03']],
    'G2'=>['laundry-management-procedure',[],['2025-03']],
    'G3'=>['pest-control-procedure',['2025-05-20','2025-07-01'],[]],
    'G4'=>['data-backup-recovery-policy',[],['2025-07']],
    'G5a'=>['staff-supervision-appraisal',[],[]],
    'G5b'=>['appraisal-supervision-schedule',[],[]],
  ];
  $out=['db'=>config('database.connections.pgsql.database'),'corpus'=>$scope->activeCorpusGeneration?->public_id];
  foreach($checks as $id=>[$stem,$dates,$months]){
    $docs=App\Models\Document::where('workspace_id',$scope->workspace->id)->where('source_filename','like',$stem.'%')->with('family')->get();
    $fam=$docs->first()?->family;
    $row=['stem'=>$stem,'families'=>$docs->pluck('family.name')->unique()->values(),'versions'=>[]];
    foreach($docs as $d){
      $chunks=App\Models\DocumentChunk::where('document_id',$d->id)->where('workspace_id',$scope->workspace->id)->orderBy('id')->pluck('text')->implode("\n<<CHUNK>>\n");
      $active=$d->chunks()->whereHas('workspaceCorpusGenerations',fn($q)=>$q->where('workspace_corpus_generations.id',$scope->activeCorpusGeneration->id))->exists();
      $row['versions'][]=['file'=>$d->source_filename,'status'=>$d->status,'gov'=>$d->governance_status,'eff'=>$d->effective_from,'withdrawn'=>$d->withdrawn_at,'start'=>$timeline->authorityStart($d)?->toIso8601String(),'active'=>$active,'sha'=>$d->source_checksum_sha256,'text'=>$chunks];
    }
    foreach($dates as $x){ $row['dates'][$x]=$timeline->resolve($fam,Carbon\CarbonImmutable::createFromFormat('!Y-m-d',$x))?->source_filename; }
    foreach($months as $m){ $r=$periods->resolve($fam,$m); $row['periods'][$m]=[$r->document?->source_filename,$r->reason?->value]; }
    $row['current']=$timeline->resolve($fam,Carbon\CarbonImmutable::now())?->source_filename;
    $out[$id]=$row;
  }
  echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} finally { Illuminate\Support\Facades\DB::rollBack(); }
