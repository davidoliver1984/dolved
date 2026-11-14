"""Seal completed evidence, not an authorization or execution mechanism."""
import hashlib,json
from pathlib import Path
P=Path(__file__).resolve().parent;ROOT=Path('/workspace')
expected=[str(i) for i in range(17,33)]+['G2','G3']
events=[json.loads(l) for l in (P/'dispatch.ndjson').read_text().splitlines()]
assert [(r['item'],r['event']) for r in events]==[(i,e) for i in expected for e in ('started','recorded')]
assert (P/'metrics-a.json').read_bytes()==(P/'metrics-b.json').read_bytes()
for i in expected:
 raw=json.loads((P/'cases'/f'{i}.json').read_text())
 assert raw['availability']['rejected_planner_output']=='unavailable'
 assert raw['availability']['calendar_normalisation_count']=='unavailable'
 # Exporter retains the DB FK; persisted readback resolves its public identity.
 assert raw['workspace_id']==1
 saved=next(r for r in json.loads((P/'persistence.json').read_text())['items'] if r['item']==i)
 assert saved['workspace_id']=='5323cd6b-8b7a-40f9-98b0-9413c75f643e'
def valid(p):return p.is_file() and not any(x in p.parts for x in ('__pycache__','.pytest_cache')) and p.suffix!='.pyc'
excluded={'checksums.sha256','checksums-verification.txt','aggregate.sha256','publication-files.txt'}
files=sorted(p for p in P.rglob('*') if valid(p) and p.name not in excluded)
inventory=P/'publication-files.txt'
# Exact force-add scope: this package only, never local Journey or unrelated edits.
publish=files+[inventory,P/'checksums.sha256',P/'checksums-verification.txt',P/'aggregate.sha256']
inventory.write_text('\n'.join(str(p.relative_to(ROOT)) for p in sorted(publish))+'\n')
files.append(inventory);files.sort()
lines=[f"{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(P)}" for p in files]
(P/'checksums.sha256').write_text('\n'.join(lines)+'\n')
digest=hashlib.sha256((P/'checksums.sha256').read_bytes()).hexdigest()
(P/'aggregate.sha256').write_text(f'{digest}  checksums.sha256\n')
print(json.dumps({'artifact_count':len(files),'checksums_sha256':digest,'dispatch_once_order_verified':True}))
