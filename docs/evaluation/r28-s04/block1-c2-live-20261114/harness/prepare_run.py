import hashlib,json
from pathlib import Path
r=Path('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114')
p=r/'released-input/questions-only.json'
assert hashlib.sha256(p.read_bytes()).hexdigest()=='7603fc82fc2f95f9852f584f28cc6a3d4949a2a30e17b8ab3b56a52ce4d2899f'
d=json.loads(p.read_text()); items=d['fresh_items']+d['known_regression_items']
assert [x['id'] for x in items]==['G1','G2','G3','G4','G5','R16']
for directory in ('inputs','cases','preflight'): (r/directory).mkdir(exist_ok=True)
for item in items:
    assert list(item)==['id','utterance']
    with (r/'inputs'/f"{item['id']}.json").open('x') as f: f.write(json.dumps(item,ensure_ascii=False)+'\n')
print('Six question-only inputs prepared; original bytes unchanged.')
