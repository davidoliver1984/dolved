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
    $checks = [
      'maintenance-contractor-schedule'=>['2025-10-14','2025-12-05','2026-11-13'],
      'serious-incident-review'=>['2025-09-01','2025-12-01','2025-12-31','2026-11-13'],
      'email-retention-policy'=>['2025-11-20','2025-12-31','2026-11-13'],
      'staff-reference-request'=>['2025-03-15','2025-09-30','2026-07-02','2026-11-13'],
      'falls-prevention-policy'=>['2026-02-02','2026-11-13'],
      'staff-benefits-guidance'=>['2025-05-01','2025-05-31','2026-11-13'],
      'missing-person-procedure'=>['2024-07-01','2026-06-30','2026-07-01','2026-11-13'],
    ];
    $rows=[];
    foreach ($checks as $stem=>$dates) {
      $docs=App\Models\Document::where('workspace_id',$scope->workspace->id)->where('source_filename','like',$stem.'-v%')->with('family')->get();
      if($docs->count()!==2 || $docs->pluck('document_family_id')->unique()->count()!==1) throw new RuntimeException('Unexpected family selection');
      foreach($dates as $date) {
        // Match EvidenceNeedsPlan::retrievalPlan explicit-date construction.
        $d=$timeline->resolve($docs->first()->family,Carbon\CarbonImmutable::createFromFormat('!Y-m-d',$date));
        $active=$d?->chunks()->whereHas('workspaceCorpusGenerations',fn($q)=>$q->where('workspace_corpus_generations.id',$scope->activeCorpusGeneration->id))->exists();
        $rows[]=['family'=>$stem,'at'=>$date,'document_id'=>$d?->public_id,'source_filename'=>$d?->source_filename,'in_active_authorised_corpus'=>$active];
      }
    }
    echo json_encode(['read_only'=>true,'membership_verified'=>true,'application_timezone'=>config('app.timezone'),'workspace_id'=>$scope->workspace->public_id,'rows'=>$rows],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
} finally {Illuminate\Support\Facades\DB::rollBack();}
