"""Freeze this checkpoint's explicit published artifacts; never scan sealed material."""
import hashlib,json,subprocess
from pathlib import Path
root=Path('/workspace'); p=root/'docs/evaluation/r28-s04/block1-c2-live-20261114'
def git(*args): return subprocess.check_output(['git','-c','safe.directory=/workspace',*args],cwd=root).decode().strip()
head=git('rev-parse','HEAD'); assert head=='829bbf132429ab8d4b408e4df2654e1b76fd2070'
inventory=(p/'preflight/runtime-source.sha256').read_bytes()
assert hashlib.sha256(inventory).hexdigest()=='7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af'
for line in inventory.decode().splitlines():
    h,name=line.split('  ',1); assert hashlib.sha256((root/name).read_bytes()).hexdigest()==h
assert not git('diff','--name-only','--','apps/api','apps/ai','contracts')
assert git('rev-parse','main')=='348f09e053c8ad73676921c5c616cced812111d7'
(p/'final-freeze.json').write_text(json.dumps({'execution_head_before_and_after':head,'implementation':'84f22f862e5001be998e6a8fe027f56a17db6941','runtime_aggregate_before_and_after':hashlib.sha256(inventory).hexdigest(),'main':git('rev-parse','main'),'application_changes':False,'block_1':'HOLD','grading_performed':False,'released_input_sha256':hashlib.sha256((p/'released-input/questions-only.json').read_bytes()).hexdigest()},indent=2)+'\n')
files=[]
for d in ('harness','continuation','preflight','cases','inputs','authorization-consumed'):
    files += [x for x in (p/d).rglob('*') if x.is_file() and '__pycache__' not in x.parts]
root_files=['authorization.json','governed-inputs.sha256','continuation-freeze.sha256','dispatch.ndjson','execution.log','continuation-execution.log','accounting.json','accounting-reconciled.json','route-summary.json','persistence-input.json','persistence-verification.json','verification.json','postprocess.py','verify_results.py','finalize.py','report.md','final-freeze.json']
files += [p/x for x in root_files]+[p/'released-input/questions-only.json']
publication=sorted({str(x.relative_to(p)) for x in files}|{'files-for-publication.txt','checksums.sha256','checksums.sha256.digest'})
(p/'files-for-publication.txt').write_text('\n'.join(publication)+'\n')
files.append(p/'files-for-publication.txt')
checks=''.join(f'{hashlib.sha256(x.read_bytes()).hexdigest()}  {x.relative_to(p)}\n' for x in sorted(set(files)))
(p/'checksums.sha256').write_text(checks)
digest=hashlib.sha256(checks.encode()).hexdigest()
(p/'checksums.sha256.digest').write_text(digest+'  checksums.sha256\n')
print(json.dumps({'published_files':len(publication),'checksummed_files':len(set(files)),'aggregate':digest}))
