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
            if (preg_match('~/((search|rerank|compare-family))$~', $path)) {
                $this->event('retrieval_service_request', ['key'=>$key,'path'=>$path,'body'=>$body]);
            }
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
                'service_status'=>$body['status']??null,'error_category'=>$body['error']['category']??null,
                'comparison_review_failure'=>$e->response->header('X-Comparison-Review-Failure') ?: null,
                'error'=>$body['error']??null,
                'review_metadata'=>array_intersect_key(is_array($body)?$body:[],array_flip(['selection','original_decision','validation_rejection_code','candidate_identities'])),
                'provider_receipt'=>array_intersect_key(is_array($body)?$body:[],array_flip(['provider_input_tokens','provider_attempt_count','provider_retry_count','rate_limit_event_count','retry_delays','first_provider_attempt_at','final_provider_success_at','provider_retry_elapsed_seconds']))];
            $this->calls[$key]=$row; $this->event('service_call_received',$row);
            if (str_ends_with($row['path'],'/answer-needs') && is_array($body)) {
                $this->trace['generation_response']=$body;
                $this->event('generation_response',$body);
            }
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
        $routes=[];
        $bindings=array_column($this->trace['needs_plan']['bindings']??[],null,'need_id');
        foreach($needs as $need) {
            $id=$need['need_id'];
            $selection=$this->trace['need.'.$id.'.topical_family_selection']??null;
            $guard=$this->trace['need.'.$id.'.temporal_guard']??null;
            $route=$need['operation']==='compare'?'compare':(($bindings[$id]['subject_status']??null)==='matched'?'planner-bound/C1':'other');
            if($need['operation']==='retrieve' && $selection!==null) {
                if(($selection['decision']??null)==='selected' && ($selection['family_id']??null)!==null) $route='C2-resolved';
                elseif(($guard['fallback']??null)==='original_unbound') $route='C2-fallback (guard not run)';
                else $route='other'; // Failed discovery is not a successful C2 route.
            }
            $routes[$id]=['route'=>$route,'c2_discovery_ran'=>$need['operation']==='retrieve' && $selection!==null,
                'selection'=>$selection,'temporal_guard'=>$guard,
                'discovery_work'=>$run->retrievalOutcomeSnapshot?->lineage['topical_discovery_work'][$id]??null];
        }
        $admitted=[]; $used=[];
        foreach($targets as $rows) foreach($rows as $target) if($target['purpose']==='precautionary' && $target['status']==='searched') $admitted[]=$target['target_id'];
        foreach($context['uses']??[] as $use) $used[]=$use['target_id']??$use['target']['target_id']??null;
        $lanes=[];
        foreach($this->trace as $stage=>$value) if(str_ends_with($stage,'.search')) $lanes[]=['stage'=>$stage,'lineage'=>$value['lineage']??[],'usage'=>$value['usage']??[]];
        return ['run_id'=>$run->public_id,'conversation_id'=>$run->conversation_id,'workspace_id'=>$run->workspace_id,'status'=>$run->status->value,'failure_code'=>$run->failure_code?->value,
            'evaluated_at'=>$run->retrievalOutcomeSnapshot?->evaluated_at,'displayed_text'=>$run->assistantMessage?->display_text,
            'production_trace'=>$this->trace,'inventoried_references'=>$inventory,'observed_temporal_targets'=>$targets,
            'routes_by_need'=>$needs===[]?null:$routes,
            'availability'=>['accepted_plan'=>$needs!==[], 'temporal_inventory'=>$needs===[]?'unavailable_no_accepted_plan':'recorded',
                'generation_request'=>isset($this->trace['assembled_generation_request'])?'recorded':'unavailable_not_reached',
                'validated_temporal_use'=>$answer===null?'unavailable_no_answer_record':'recorded',
                'empty_derived_arrays'=>'No observation is not proof of an executed negative result; consult availability and raw trace.'],
            'generation_request'=>$this->trace['assembled_generation_request']??null,
            'entailment_checks'=>$this->trace['generation_response']['result']['entailment_checks']??null,
            'aspect_results'=>$answer?->aspect_results,
            'unsupported_aspects'=>$answer?->unsupported_aspects,
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
