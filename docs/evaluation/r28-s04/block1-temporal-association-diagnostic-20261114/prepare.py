"""Create this bounded diagnostic's copies from retained committed harness only."""
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path('/workspace')
P = Path(__file__).resolve().parent
OLD = 'docs/evaluation/r28-s04/block1-remaining-verification-20261114/run'
BASE = '02470b24a23ed3001faebe41d9cf94c9c4818180'
def read(path):
    return subprocess.check_output(['git', '-c', 'safe.directory=/workspace', 'show', BASE+':'+path], cwd=ROOT)

for directory in ('harness', 'inputs', 'cases', 'preflight', 'capture'):
    (P/directory).mkdir(exist_ok=True)
for name in ('C1Capture.php','dispatch.php','preflight.php','verify_persistence.php','accounting.py','check_record.py','check_preflight.py','test_stop_logic.py'):
    text = read(OLD+'/harness/'+name).decode().replace(OLD, str(P.relative_to(ROOT)))
    if name == 'C1Capture.php':
        text = text.replace("'rejected_planner_output'=>'unavailable','calendar_normalisation_count'=>'unavailable'",
                            "'rejected_planner_output'=>'evaluation_sidecar','calendar_normalisation_count'=>'evaluation_sidecar'")
    (P/'harness'/name).write_text(text)
(P/'budget.php').write_bytes(read(OLD+'/budget.php'))
selection = []
for item in ('G3','17','25'):
    raw = read(OLD+'/inputs/'+item+'.json')
    (P/'inputs'/f'{item}.json').write_bytes(raw)
    selection.append({**json.loads(raw), 'source': OLD+'/inputs/'+item+'.json', 'sha256':hashlib.sha256(raw).hexdigest()})
(P/'selection.json').write_text(json.dumps(selection,indent=2)+'\n')
print('Prepared exactly G3, 17, 25. No providers; prior records unchanged.')
