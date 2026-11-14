<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$utt = json_decode(file_get_contents('php://stdin') ?: '{}', true);
Illuminate\Support\Facades\DB::beginTransaction();
try {
  Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');
  $user = App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
  $scope = app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($user,'5323cd6b-8b7a-40f9-98b0-9413c75f643e');
  $timeline = app(App\Support\Documents\DocumentAuthorityTimeline::class);
  $periods = app(App\Services\Retrieval\TemporalPeriodResolver::class);
  $stems=['F1'=>'serious-complaint-escalation','F2'=>'admissions-preadmission','F3'=>'self-administration-medication','F4'=>'medication-fridge-breakdown','F5'=>'continence-care-policy'];
  $mig = Illuminate\Support\Facades\DB::table('migrations')->where('migration','like','%temporal_context%')->pluck('migration')->all();
  $out=['temporal_context_migration_applied'=>$mig];
  foreach($utt as $id=>$text){
    $fam=App\Models\Document::where('workspace_id',$scope->workspace->id)->where('source_filename','like',$stems[$id].'-v%')->first()->family;
    $refs=App\Support\Retrieval\ExplicitTemporalReferences::forNeed(['question_span'=>['turn_id'=>'t1','quote'=>$text],'context_spans'=>[]]);
    $rows=[];
    foreach($refs as $r){
      if($r['precision']==='day'){ $d=$timeline->resolve($fam,Carbon\CarbonImmutable::parse($r['at'])); $rows[]=[$r['quote'],$r['precision'],$d?->source_filename]; }
      else { $res=$periods->resolve($fam,$r['value']); $rows[]=[$r['quote'],$r['precision'],$res->document?->source_filename,$res->reason?->value]; }
    }
    $cur=$timeline->resolve($fam,Carbon\CarbonImmutable::parse('2026-11-14'));
    $out[$id]=['references'=>$rows,'current'=>$cur?->source_filename];
  }
  echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
} finally { Illuminate\Support\Facades\DB::rollBack(); }
