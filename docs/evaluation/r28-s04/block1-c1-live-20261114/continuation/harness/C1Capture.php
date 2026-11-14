<?php
/** Checkpoint-only observer; never alters a plan, request, result or production decision. */
final class C1Capture
{
    public array $trace = [];
    public array $calls = [];
    private array $started = [];
    private int $sequence = 0;
    private string $previous = '';
    public function __construct(private string $ledger) {}
    public function event(string $type, array $data): void
    {
        $entry=['sequence'=>++$this->sequence,'at'=>gmdate('c'),'event'=>$type,'data'=>$data,'previous_hash'=>$this->previous];
        $this->previous=hash('sha256',json_encode($entry,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $entry['hash']=$this->previous;
        $h=fopen($this->ledger,'ab');
        if(!$h || !flock($h,LOCK_EX)) throw new RuntimeException('Checkpoint ledger unavailable');
        fwrite($h,json_encode($entry,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n"); fflush($h); fsync($h); flock($h,LOCK_UN); fclose($h);
    }
    public function observe(string $stage, mixed $value): void
    {
        if(!is_array($value)) return;
        // Production hooks expose explicit arrays; no Eloquent object expansion.
        $safe=json_decode(json_encode($value,JSON_THROW_ON_ERROR),true,flags:JSON_THROW_ON_ERROR);
        $this->trace[$stage]=$safe;
        $this->event('production_observation',['stage'=>$stage,'value'=>$safe]);
    }
    public function attachHttp(): void
    {
        Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\RequestSending::class,function($e){
            $path=parse_url($e->request->url(),PHP_URL_PATH);
            if(!str_starts_with($path??'','/api/internal/')) return;
            $body=$e->request->data(); $id=$body['request_id']??('call-'.count($this->calls));
            $key=$id.':'.count($this->calls); $this->started[spl_object_id($e->request)]=[$key,microtime(true)];
            // No headers, signatures, keys or cookies are persisted.
            $row=['key'=>$key,'request_id'=>$id,'path'=>$path,'status'=>'started'];
            $this->calls[$key]=$row; $this->event('service_call_started',$row);
            if(str_ends_with($path,'/answer-needs')) {
                $this->trace['assembled_generation_request']=$body;
                $this->event('assembled_generation_request',$body);
            }
        });
        Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ResponseReceived::class,function($e){
            $start=$this->started[spl_object_id($e->request)]??null; if($start===null) return;
            [$key,$at]=$start; $body=$e->response->json();
            $row=[...$this->calls[$key],'status'=>'received','http_status'=>$e->response->status(),'latency_ms'=>(microtime(true)-$at)*1000,
                'usage'=>$body['usage']??$body['result']['usage']??$body['error']['usage']??null,
                'service_status'=>$body['status']??null,'error_category'=>$body['error']['category']??null];
            $this->calls[$key]=$row; $this->event('service_call_received',$row);
        });
        Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ConnectionFailed::class,function($e){
            $start=$this->started[spl_object_id($e->request)]??null; if($start===null) return;
            [$key,$at]=$start; $row=[...$this->calls[$key],'status'=>'connection_failed','latency_ms'=>(microtime(true)-$at)*1000];
            $this->calls[$key]=$row; $this->event('service_call_failed',$row);
        });
    }
    public function snapshot(App\Models\GenerationRun $run): array
    {
        $run=$run->fresh(['generatedAnswer.answerParts.evidenceSnapshots','assistantMessage','retrievalOutcomeSnapshot','contextualisationResult']);
        $answer=$run->generatedAnswer;
        $needs=$this->trace['needs_plan']['needs']??[];
        $inventory=[];
        foreach($needs as $need) $inventory[$need['need_id']]=App\Support\Retrieval\ExplicitTemporalReferences::forNeed($need);
        $targets=[];
        foreach($this->trace as $stage=>$value) if(str_ends_with($stage,'.temporal_targets')) $targets[$stage]=$value;
        $context=$answer?->temporal_context??[];
        $admitted=[]; $used=[];
        foreach($targets as $rows) foreach($rows as $target) if($target['purpose']==='precautionary' && $target['status']==='searched') $admitted[]=$target['target_id'];
        foreach($context['uses']??[] as $use) $used[]=$use['target_id']??$use['target']['target_id']??null;
        $lanes=[];
        foreach($this->trace as $stage=>$value) if(str_ends_with($stage,'.search')) $lanes[]=['stage'=>$stage,'lineage'=>$value['lineage']??[],'usage'=>$value['usage']??[]];
        return ['run_id'=>$run->public_id,'conversation_id'=>$run->conversation_id,'workspace_id'=>$run->workspace_id,'status'=>$run->status->value,'failure_code'=>$run->failure_code?->value,
            'evaluated_at'=>$run->retrievalOutcomeSnapshot?->evaluated_at,'displayed_text'=>$run->assistantMessage?->display_text,
            'production_trace'=>$this->trace,'inventoried_references'=>$inventory,'observed_temporal_targets'=>$targets,
            'lane_events'=>$lanes,'base_logical_lanes'=>App\Support\Retrieval\NeedsEvidenceSelection::searchLanes($needs),
            'additional_search_lanes'=>$run->retrievalOutcomeSnapshot?->lineage['temporal_alternatives']['additional_search_lanes']??0,
            'final_allocated_evidence'=>$this->trace['needs_final_evidence']??[],
            'precautionary_target_admitted'=>$admitted!==[],'admitted_precautionary_target_ids'=>$admitted,
            'answer_used_precautionary_target'=>array_intersect($admitted,$used)!==[],
            'validated_used_target_ids'=>$used,'answer_used_requested_target'=>count(array_filter($used,fn($id)=>is_string($id)&&str_ends_with($id,':requested')))>0,
            'temporal_context'=>$context,'reloaded_temporal_context'=>$answer?->fresh()->temporal_context,
            'reloaded_rendered_text'=>$answer?->fresh()->renderedText(),
            'parts'=>$answer?->answerParts->map(fn($p)=>['text'=>$p->text,'evidence'=>$p->evidenceSnapshots->map(fn($s)=>$s->only(['evidence_handle','document_chunk_id','document_id','side','temporal_authority']))->all()])->all(),
            'retrieval'=>$run->retrievalOutcomeSnapshot?->only(['outcome','clarification_source','clarification_reason','lineage','resolved_temporal_authority']),
            'usage'=>$run->usage,'generation_usage'=>$answer?->usage,'service_calls'=>array_values($this->calls),
            'workspace_usage'=>App\Models\WorkspaceUsageEvent::where('workspace_id',$run->workspace_id)->where('scope_public_id',$run->public_id)->orderBy('id')->get()->toArray()];
    }
}
