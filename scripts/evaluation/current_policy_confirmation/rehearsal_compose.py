"""Explicit private extends source for the existing Compose rehearsal service.

Resolved environment values never enter committed files or public evidence.
"""
import hashlib
import json
import os
from pathlib import Path


def literal_values(value):
    # Resolved dollar characters must survive Compose's second interpolation pass.
    if isinstance(value,str):return value.replace('$','$$')
    if isinstance(value,list):return [literal_values(v) for v in value]
    if isinstance(value,dict):return {k:literal_values(v) for k,v in value.items()}
    return value


def private_snapshot(model,path):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'w') as f:
        json.dump(literal_values(model),f);f.flush();os.fsync(f.fileno())
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def dependency_edges(model,service):
    row=model['services'][service];edges=set(row.get('depends_on') or [])
    for item in row.get('links') or []:edges.add(item.split(':',1)[0])
    for item in row.get('volumes_from') or []:
        if item.startswith('container:'):raise ValueError('rehearsal_external_container_dependency')
        edges.add(item.split(':',1)[0])
    for key in ['network_mode','pid','ipc']:
        value=row.get(key,'')
        if isinstance(value,str) and value.startswith('service:'):edges.add(value.split(':',1)[1])
        if isinstance(value,str) and value.startswith('container:'):raise ValueError('rehearsal_external_container_dependency')
    return sorted(edges)


def verify_resolved(model,base,service,command,mounts):
    row=model['services'][service]
    if dependency_edges(model,service):raise ValueError('rehearsal_service_dependencies')
    if row.get('ports'):raise ValueError('rehearsal_published_ports')
    if row['command']!=command:raise ValueError('rehearsal_command_mismatch')
    original=base['services']['api']
    if row['image']!=original['image'] or row['environment']!=original['environment']:raise ValueError('rehearsal_api_identity_mismatch')
    # Only the dedicated container command/identity/health/ports/dependencies and
    # five explicitly bound evaluation mounts differ from the resolved API.
    allowed={'command','container_name','healthcheck','ports','depends_on','labels','volumes'}
    if {k:v for k,v in row.items() if k not in allowed}!={k:v for k,v in original.items() if k not in allowed}:raise ValueError('rehearsal_unexpected_service_change')
    actual={v['target']:v for v in row['volumes']}
    expected={v['target']:v for v in original['volumes']}
    if len(actual)!=len(row['volumes']) or set(actual)!=set(expected)|set(mounts):raise ValueError('rehearsal_mount_set')
    if any(actual[k]!=v for k,v in expected.items()):raise ValueError('rehearsal_base_mount_changed')
    for target,m in mounts.items():
        v=actual[target]
        if v['type']!='bind' or v['source']!=m['source'] or v.get('read_only',False)!=m['read_only']:raise ValueError('rehearsal_observer_mount_changed')
    return {'dependency_graph':{service:[]},'published_ports':[],'image':row['image'],'command':row['command'],'environment_sha256':hashlib.sha256(json.dumps(row['environment'],sort_keys=True).encode()).hexdigest(),'service_sha256':hashlib.sha256(json.dumps(row,sort_keys=True).encode()).hexdigest()}
