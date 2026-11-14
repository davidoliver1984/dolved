<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='dolved_e2e_r28_s03'||$db->getConfig('host')!=='postgres') throw new RuntimeException('Wrong isolated connection');
$w=App\Models\Workspace::where('public_id','5323cd6b-8b7a-40f9-98b0-9413c75f643e')->sole();
$u=App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
app(App\Queries\Workspaces\FindWorkspaceForUser::class)->handle($u,$w->public_id);
$item=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
if(!in_array($item['id'],['G2','G3'],true)) throw new RuntimeException('Unapproved item');
// Exactly the workspace-scoped catalogue query used by RetrievalClient::planNeeds.
$catalogue=App\Models\DocumentFamily::query()->where('workspace_id',$w->id)->whereNull('tombstoned_at')->orderBy('public_id')->limit(501)->get(['public_id','name'])->map(fn($f)=>['family_id'=>$f->public_id,'title'=>$f->name])->all();
$turns=[['turn_id'=>'user-1','text'=>$item['utterance']]];
if(count($catalogue)>500||strlen(json_encode($catalogue))>40000||strlen(json_encode($turns))>20000) throw new RuntimeException('Bound exceeded');
echo json_encode(['contract_version'=>2,'request_id'=>(string)Illuminate\Support\Str::uuid(),'workspace_id'=>$w->public_id,'evaluated_at'=>now()->toIso8601String(),'user_turns'=>$turns,'catalogue'=>$catalogue],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
