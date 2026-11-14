<?php
require '/app/vendor/autoload.php'; $app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=Illuminate\Support\Facades\DB::connection();
if($c->getDatabaseName()!=='dolved_e2e_r28_s03'||$c->getConfig('host')!=='postgres') throw new RuntimeException('Wrong database');
$input=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
$w=App\Models\Workspace::where('public_id','5323cd6b-8b7a-40f9-98b0-9413c75f643e')->sole();
$u=App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
app(App\Queries\Workspaces\FindWorkspaceForUser::class)->handle($u,$w->public_id);
$out=[];$seen=[];
foreach($input['items'] as $item){
  $r=App\Models\GenerationRun::where('workspace_id',$w->id)->where('public_id',$item['run_id'])->with(['conversation','generatedAnswer','assistantMessage'])->sole();
  $conv=$r->conversation;
  if($conv->created_by_user_id!==$u->id || in_array($conv->id,$seen,true)) throw new RuntimeException('Conversation identity mismatch');
  $seen[]=$conv->id;
  $users=$conv->messages()->where('role','user')->get();
  $runs=$conv->generationRuns()->count();
  if($users->count()!==1||$runs!==1) throw new RuntimeException('Unexpected turn/run count');
  $out[]=['item'=>$item['item'],'run_id'=>$r->public_id,'conversation_id'=>$conv->public_id,'workspace_id'=>$w->public_id,'actor_id'=>$u->public_id,'user_turns'=>1,'generation_runs'=>1,'status'=>$r->status->value,'question'=>$users->sole()->display_text,'temporal_context'=>$r->generatedAnswer?->temporal_context,'reloaded_rendered_text'=>$r->generatedAnswer?->renderedText(),'assistant_display_text'=>$r->assistantMessage?->display_text];
}
echo json_encode(['items'=>$out,'verified_at'=>now()->toIso8601String(),'grading'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
