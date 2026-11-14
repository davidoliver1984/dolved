import hashlib,json
from pathlib import Path
p=Path(__file__).resolve().parent
root=Path('/workspace')
inventory=(p/'runtime-source.sha256').read_bytes()
assert hashlib.sha256(inventory).hexdigest()=='7f20f123cb9e4af9567e0ea0240ebf715d54755ae2611dc66ecd6719756773af'
for line in inventory.decode().splitlines():
    digest,name=line.split('  ',1)
    assert hashlib.sha256((root/name).read_bytes()).hexdigest()==digest
files=[]
for d in ('harness','proof','inputs','requests','cases','preflight','authorization-consumed'):
    files += [x for x in (p/d).rglob('*') if x.is_file() and '__pycache__' not in x.parts]
files += [p/x for x in ('authorization.json','governed-inputs.sha256','runtime-source.sha256','dispatch.ndjson','execution.log','verify.py','verification.json','report.md','finalize.py')]
publication=sorted({str(x.relative_to(p)) for x in files}|{'files-for-publication.txt','checksums.sha256','checksums.sha256.digest'})
(p/'files-for-publication.txt').write_text('\n'.join(publication)+'\n')
files.append(p/'files-for-publication.txt')
checks=''.join(f'{hashlib.sha256(x.read_bytes()).hexdigest()}  {x.relative_to(p)}\n' for x in sorted(set(files)))
(p/'checksums.sha256').write_text(checks)
digest=hashlib.sha256(checks.encode()).hexdigest()
(p/'checksums.sha256.digest').write_text(digest+'  checksums.sha256\n')
print(json.dumps({'files':len(publication),'checksummed_files':len(set(files)),'aggregate':digest}))
