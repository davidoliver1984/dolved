import hashlib,json
from pathlib import Path
r=Path('/workspace/docs/evaluation/r28-s04/block1-c1-live-20261114')
p=r/'released-input/questions-only.json'
assert hashlib.sha256(p.read_bytes()).hexdigest()=='e1a54d05d0b688988a083596390ff9a9062b7a32c9352ea716a3a156145a9659'
d=json.loads(p.read_text()); assert [x['id'] for x in d['items']]==['F1','F2','F3','F4','F5']
here=r/'continuation'; (here/'inputs').mkdir(exist_ok=True)
for item in d['items']:
    assert list(item)==['id','utterance']
    target=here/'inputs'/f"{item['id']}.json"
    if target.exists(): raise RuntimeError('Prepared input already exists')
    target.write_text(json.dumps(item,ensure_ascii=False)+'\n')
print('Five question-only transport inputs prepared; original input untouched.')
