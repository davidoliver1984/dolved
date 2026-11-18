"""One-use, zero-question idle lifecycle rehearsal. Not a question-run authority."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import uuid
from observer_commands import environment_map
from mount_guard import build_mount_spec, mount_mismatches, verify_mounts
import native_capture as nc
from runner import durable

def sha(p): return hashlib.sha256(Path(p).read_bytes()).hexdigest()
def inspect(n): return json.loads(subprocess.check_output(['docker','inspect',n]))[0]
def load_adapter(b):
    spec=importlib.util.spec_from_file_location('frozen_preparation_adapter',b['base_adapter'])
    m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
def snapshot(b):
    return json.loads(subprocess.check_output(['docker','exec','-i','dolved-r28-s03-api-1','php'],input=Path(b['queue_snapshot_script']).read_bytes()))

def identity_mismatches(b,c,created=False):
    errors=[]
    if c['Image']!=b['image']:errors.append('worker_image_mismatch')
    if c['Config']['Cmd']!=b['command']:errors.append('worker_command_mismatch')
    if c['Config']['Entrypoint']!=b['entrypoint']:errors.append('worker_entrypoint_mismatch')
    if c['Config']['Labels'].get(b.get('ownership_label','dolved.rehearsal-owner'))!=b['owner_id']:errors.append('worker_ownership_mismatch')
    try:
        value=hashlib.sha256(json.dumps(environment_map(c['Config']['Env']),sort_keys=True).encode()).hexdigest()
        if value!=b['environment_sha256']:errors.append('environment_mapping_mismatch')
    except ValueError as e:errors.append(str(e))
    errors+=mount_mismatches(b['mount_spec'],c['Mounts'])
    if created and (c['State']['Status']!='created' or c['State']['Running'] or not c['State']['StartedAt'].startswith('0001-')):errors.append('container_previously_started')
    return sorted(set(errors))

def start_verified(b,c,start):
    errors=identity_mismatches(b,c,created=True)
    if errors:raise ValueError(','.join(errors))
    start(c['Id'])

def content_check(b,container_id):
    code='$m=json_decode(stream_get_contents(STDIN),true);foreach($m as $p=>$h){if(!is_file($p)||hash_file("sha256",$p)!==$h)exit(1);}'
    subprocess.run(['docker','exec','-i',container_id,'php','-r',code],input=json.dumps(b['mounted_files']),text=True,capture_output=True,check=True)
def verify(b):
    if b['purpose']!='empty_queue_lifecycle_only' or b['provider_call_allowance']!=0:raise ValueError('rehearsal_authority_boundary')
    if subprocess.check_output(['git','rev-parse','HEAD'],cwd=b['worktree'],text=True).strip()!=b['commit']:raise ValueError('harness_drift')
    for p,h in b['files'].items():
        if sha(p)!=h:raise ValueError('bound_file_drift')
    m=load_adapter(b);m.mounted()
    api=inspect('dolved-r28-s03-api-1')
    if api['Id']!=b['api_id'] or api['Image']!=b['image']:raise ValueError('api_identity_drift')
    config=json.loads(subprocess.check_output(m.compose()+['config','--format','json'],cwd=b['worktree'],env=m.env()))
    from mount_guard import verify_api_mounts
    verify_api_mounts(config,api,b['base_mount_binding'],m.mounted)
    assert build_mount_spec(config,b['base_mount_binding'],b['mounts'])==b['mount_spec']
    resolved=json.loads(subprocess.check_output(m.compose()+['-f',b['override'],'config','--format','json'],cwd=b['worktree'],env=m.env()))
    from rehearsal_compose import verify_resolved
    verify_resolved(resolved,config,b['service'],b['command'],b['mounts'])
    observed=resolved['services'][b['service']]
    if hashlib.sha256(json.dumps(observed,sort_keys=True).encode()).hexdigest()!=b['service_sha256']:raise ValueError('worker_configuration_drift')
    if snapshot(b)!=b['queue_snapshot']:raise ValueError('queue_drift')
    names=subprocess.check_output(['docker','ps','-a','--format','{{.Names}}'],text=True).splitlines()
    if b['worker'] in names:raise ValueError('prior_worker_exists')
    for n in names:
        if n.startswith('dolved-r28-s03-'):
            c=inspect(n);cmd=' '.join(c['Config'].get('Cmd') or [])
            if c['State']['Running'] and any(s in cmd for s in ['queue:work','observed_worker','rehearsal_worker','schedule:']):raise ValueError('consumer_running')
    return m
def execute(path):
    b=json.loads(Path(path).read_text());m=verify(b);root=Path(b['destination'])
    if any(any(Path(b['native_capture'][n]).iterdir()) for n in ['journal','health','capture']) or any((root/'lifecycle').iterdir()):raise ValueError('prior_rehearsal_state')
    a=json.loads(Path(b['authority']).read_text())
    if a!={'purpose':'empty_queue_lifecycle_only','binding_sha256':sha(path),'approved_by':'David','approved':True,'provider_call_allowance':0}:raise ValueError('rehearsal_authority_mismatch')
    Path(b['authority']+'.consumed').mkdir(exist_ok=False)
    start=time.time();deadline=start+300
    durable(root/'started.json',{'at':start,'deadline':deadline,'authority_sha256':sha(b['authority'])},True)
    lifecycle=root/'lifecycle';primary=None;exit_code=None
    def event(name,**v):durable(lifecycle/(name+'.json'),{'phase':name,'at':time.time(),**v},True)
    try:
        # Dedicated service has an explicitly empty dependency set; this installed
        # Compose create command has no --no-deps option. It never starts services.
        argv=m.compose()+['-f',b['override'],'create','--no-recreate','--no-build','--pull','never',b['service']]
        subprocess.run(argv,cwd=b['worktree'],env=m.env(),capture_output=True,check=True,timeout=max(1,deadline-time.time()))
        c=inspect(b['worker']);event('process_created',container_id=c['Id'])
        errors=identity_mismatches(b,c,created=True);event('static_identity',mismatch_codes=errors,container_id=c['Id'],content_check='host source hashes verified; running content check pending')
        def start_id(cid):
            event('process_start_requested',container_id=cid)
            subprocess.run(['docker','start',cid],capture_output=True,check=True,timeout=max(1,deadline-time.time()))
        start_verified(b,c,start_id)
        running=inspect(c['Id']);errors=identity_mismatches(b,running)
        try:content_check(b,c['Id'])
        except Exception:errors.append('mounted_content_mismatch')
        event('post_start_identity',mismatch_codes=errors,container_id=running['Id'])
        if errors:raise ValueError(','.join(errors))
        # Docker's actual StartedAt anchors readiness, not the earlier create.
        from datetime import datetime
        process_start=datetime.fromisoformat(running['State']['StartedAt'].replace('Z','+00:00')).timestamp()
        nc.verify_startup(b['native_capture'],min(deadline,process_start+60),root,durable)
        event('observer_started_and_loop_ready_verified')
        # Proof of the pre-pop veto, not a sleep used as evidence of readiness.
        while time.time()<min(deadline,process_start+60):
            if (lifecycle/'poll_prevented.json').is_file():break
            time.sleep(.05)
        if not (lifecycle/'poll_prevented.json').is_file() or not (lifecycle/'no_job_no_http_guard_installed.json').is_file():raise ValueError('isolation_proof_missing')
        rows,jobs,requests,active,stopped=nc.journal(b['native_capture'])
        if jobs or requests or active or stopped or snapshot(b)!=b['queue_snapshot']:raise ValueError('unexpected_job_activity')
        event('idle_verified',job_reservations=0,observed_requests=0)
    except BaseException as error:
        primary=error;event('primary_halt',code=getattr(error,'code','lifecycle_failure'),error_class=type(error).__name__)
    finally:
        names=subprocess.check_output(['docker','ps','-a','--format','{{.Names}}'],text=True).splitlines()
        if b['worker'] in names:
            c=inspect(b['worker'])
            if c['Config']['Labels'].get('dolved.rehearsal-owner')!=b['owner_id']:raise ValueError('cleanup_ownership_mismatch')
            event('stop_requested',ready=(root/'observer-startup.json').exists())
            stop=b.get('automatic_stop_command') or ['docker','stop','--timeout','30',c['Id']]
            # Optional release-bound command exercises the same copied standalone
            # adapter as the question run; no fallback disguises its failure.
            try:subprocess.run(stop,cwd=b.get('stop_cwd'),env=b.get('stop_environment'),capture_output=True,check=True,timeout=min(30,max(1,deadline-time.time())))
            except subprocess.TimeoutExpired:
                subprocess.run(['docker','kill',b['worker']],capture_output=True,check=True);event('forced_termination')
            c=inspect(b['worker']);exit_code=c['State']['ExitCode'];event('process_exited',exit_code=exit_code,running=c['State']['Running'])
            try:
                nc.verify_shutdown(b['native_capture'],root,durable)
                if exit_code!=0 or c['State']['Running']:raise ValueError('non_graceful_exit')
                event('observer_stopped_verified')
            except Exception as error:event('cleanup_halt',code=getattr(error,'code','shutdown_failure'),error_class=type(error).__name__,primary_preserved=primary is not None)
        else:event('startup_incomplete_no_process')
    ok=primary is None and exit_code==0 and (root/'observer-shutdown.json').exists() and not (lifecycle/'cleanup_halt.json').exists() and time.time()<=deadline and snapshot(b)==b['queue_snapshot']
    durable(root/'result.json',{'passed':ok,'started':start,'ended':time.time(),'exit_code':exit_code,'provider_calls':0,'question_submissions':0,'no_item_reservation':True,'rehearsals':1},True)
    if not ok:raise RuntimeError('rehearsal_not_passed')
if __name__=='__main__':execute(sys.argv[1])
