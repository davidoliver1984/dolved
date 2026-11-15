"""Checksum publication of the stopped run; never dispatches or alters raw evidence."""
import hashlib,json
from pathlib import Path
ROOT=Path('/workspace');HERE=Path(__file__).resolve().parent
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
auth=json.loads((HERE/'authorization.json').read_text())
files=[p for p in HERE.rglob('*') if p.is_file() and p.name not in ('checksums.sha256','publication.json') and '__pycache__' not in p.parts]
linked=[ROOT/'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json',ROOT/'docs/evaluation/r28-s04/decision-run-20261115/reviewer-predispatch-confirmation.md']
text=''.join(f'{sha(p)}  {p.relative_to(ROOT)}\n' for p in sorted(files+linked))
(HERE/'checksums.sha256').write_text(text)
(HERE/'publication.json').write_text(json.dumps({'run_id':auth['run_id'],'execution_commit':auth['commit'],
 'checksums_sha256':sha(HERE/'checksums.sha256'),'files_bound':len(files+linked),'runtime_sha256':auth['runtime'],
 'status':'stopped_no_resume','graded':False,'main_unchanged':True,'block1':'HOLD'},indent=2)+'\n')
print((HERE/'publication.json').read_text())
