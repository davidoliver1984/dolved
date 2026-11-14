<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__.'/C1Capture.php';
$payload=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);
if(array_keys($payload)!==['id','utterance'] || !is_string($payload['id']) || !is_string($payload['utterance'])) throw new RuntimeException('Invalid question-only input');
$c=Illuminate\Support\Facades\DB::connection();
if($c->getDatabaseName()!=='dolved_e2e_r28_s03'||$c->getConfig('host')!=='postgres'||!Illuminate\Support\Facades\Schema::hasColumn('generated_answers','temporal_context')) throw new RuntimeException('Wrong database or schema');
$workspaceId='5323cd6b-8b7a-40f9-98b0-9413c75f643e';
if(config('conversation.needs_workspace_ids')!==[$workspaceId]) throw new RuntimeException('Wrong needs opt-in');
$user=App\Models\User::where('public_id','ae454ce6-4d91-4c7e-9a40-b4750b696a6b')->sole();
$workspace=app(App\Queries\Workspaces\FindWorkspaceForUser::class)->handle($user,$workspaceId)->workspace;
Illuminate\Support\Facades\Auth::setUser($user);
Illuminate\Support\Facades\Queue::fake(); // Same synchronous diagnostic boundary; prevents duplicate queued execution.
$ledger=__DIR__.'/ledger-'.$payload['id'].'.ndjson';
if(file_exists($ledger)) throw new RuntimeException('Item ledger already exists; refuse redispatch');
$cap=new C1Capture($ledger); $cap->attachHttp();
$start=microtime(true);
$cap->event('item_started',['id'=>$payload['id'],'at_london'=>Carbon\CarbonImmutable::now('Europe/London')->toIso8601String()]);
$conversation=app(App\Actions\Conversation\CreateConversation::class)->handle($workspace,$user);
$run=app(App\Actions\Conversation\SubmitConversationMessage::class)->handle($conversation,$user,$payload['utterance'],(string)Illuminate\Support\Str::uuid());
$cap->event('conversation_created',['conversation_id'=>$conversation->public_id,'run_id'=>$run->public_id]);
app(App\Actions\Conversation\OrchestrateConversationRun::class)->handle($run->id,$cap->observe(...));
$record=['input'=>$payload,'started_at_london'=>Carbon\CarbonImmutable::createFromTimestamp($start,'Europe/London')->toIso8601String(),'finished_at_london'=>Carbon\CarbonImmutable::now('Europe/London')->toIso8601String(),'elapsed_ms'=>(microtime(true)-$start)*1000,...$cap->snapshot($run)];
$cap->event('item_finished',['id'=>$payload['id'],'run_id'=>$run->public_id,'status'=>$record['status']]);
echo json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
