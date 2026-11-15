<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
if($db->getDatabaseName()!=='dolved_e2e_r28_s03'||$db->getConfig('host')!=='postgres')throw new RuntimeException('Wrong database');
$u=App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
$m=app(App\Queries\Workspaces\FindWorkspaceForUser::class)->handle($u,'5323cd6b-8b7a-40f9-98b0-9413c75f643e');
$scope=app(App\Queries\Retrieval\BuildAuthorisedKnowledgeScope::class)->handle($u,$m->workspace->public_id);
$columns=$db->select("select table_name,column_name from information_schema.columns where table_schema='public' and (table_name in ('users','workspace_memberships') or column_name in ('user_id','organisational_location_id','site_id','location_id')) order by table_name,column_name");
$site=[];foreach($columns as $c){if(in_array($c->table_name,['users','workspace_memberships'])&&preg_match('/site|location/',$c->column_name))$site[]=$c->column_name;}
if($site!==[])throw new RuntimeException('Actor site columns require inspection before continuing');
echo json_encode(['at'=>now()->toIso8601String(),'database'=>$db->getDatabaseName(),'host'=>$db->getConfig('host'),'actor'=>$u->public_id,'membership'=>$m->public_id,'role'=>$m->role->value,
 'workspace'=>$m->workspace->public_id,'generation'=>$scope->activeCorpusGeneration?->public_id,
 'site_assignment'=>null,'site_assignment_basis'=>'No actor/membership site columns; BuildAuthorisedKnowledgeScope carries only user, workspace and active corpus. No site/context supplied by dispatcher.',
 'schema_columns_checked'=>$columns],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
