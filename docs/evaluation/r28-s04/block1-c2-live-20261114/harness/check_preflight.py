import json,sys
from pathlib import Path
p=Path('/workspace/docs/evaluation/r28-s04/block1-c2-live-20261114/preflight')
base=json.loads((p/'deployed.json').read_text()); now=json.loads((p/f'runtime-before-{sys.argv[1]}.json').read_text())
assert base['column'] is True and base['migration'] is not None
for key in ('identity','environment','timezone','workspace','actor','migration','column','corpus','needs_profile','candidate_k','retrieval_attempts'):
    assert now[key]==base[key],f'Runtime/configuration/corpus drift: {key}'
print('Runtime/schema/corpus snapshot unchanged')
