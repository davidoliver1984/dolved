"""Freeze mechanical preflight only; never creates execution authority."""
import hashlib,json,sys
from pathlib import Path
ROOT=Path('/workspace'); HERE=Path(__file__).resolve().parent
sys.path.insert(0,str(HERE.parent/'completion'))
from released_input import parse_release
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def manifest(paths):return ''.join(f'{sha(p)}  {p.relative_to(ROOT)}\n' for p in sorted(set(paths)))
runtime=HERE.parent/'runtime-source.sha256'
assert sha(runtime)=='b73d054d9492e909cfaeadb642544950cfe940de10bfcbe12b388b089997b901'
for line in runtime.read_text().splitlines():
    digest,path=line.split('  ',1);assert sha(ROOT/path)==digest,path
release=ROOT/'docs/evaluation/r28-s04/decision-run-20261115/released-input/questions-only.json'
items=parse_release(release.read_bytes()); context=json.loads((HERE/'context.json').read_text())
assert context['site_assignment'] is None
bindings={'release_sha256':sha(release),'normalised_inputs_sha256':hashlib.sha256(json.dumps(items,ensure_ascii=False,separators=(',',':')).encode()).hexdigest(),
 'items':[{'id':i['id'],'group':i['group'],'workspace':context['workspace'],'generation':context['generation'],'actor':context['actor'],'membership':context['membership'],'site_assignment':None,'fresh_persisted_conversation':True,'turns':1,'utterance_sha256':hashlib.sha256(i['utterance'].encode()).hexdigest()} for i in items],
 'context_rule':'All34 primary, including K1/K2/K3/K4; exact released wording; no injected context or bindings',
 'authority':'NOT_AN_AUTHORISATION'}
(HERE/'execution-context.json').write_text(json.dumps(bindings,indent=2)+'\n')
paths=[ROOT/l.split('  ',1)[1] for l in (HERE.parent/'completion/harness.sha256').read_text().splitlines()]
(HERE/'harness.sha256').write_text(manifest(paths))
deps=[ROOT/l.split('  ',1)[1] for l in (HERE.parent/'dependencies.sha256').read_text().splitlines()]
deps += [HERE/'execution-context.json',HERE/'recovery.json',HERE/'config-verification.json',HERE/'running-verification.json',HERE/'reservation.json']
deps += list(HERE.glob('*.py'))+list(HERE.glob('*.php'))
(HERE/'dependencies.sha256').write_text(manifest(deps))
identity={'runtime_sha256':sha(runtime),'runtime_files':len(runtime.read_text().splitlines()),'input_sha256':sha(release),'harness_sha256':sha(HERE/'harness.sha256'),'dependencies_sha256':sha(HERE/'dependencies.sha256'),'context_sha256':sha(HERE/'execution-context.json'),'provider_calls':0,'authorisations_created_or_consumed':0,'status':'mechanical_preflight_complete_pending_phase_b_approval'}
(HERE/'identity.json').write_text(json.dumps(identity,indent=2)+'\n')
capture=ROOT/'docs/evaluation/r28-s04/decision-run-20261115/backend-run/capture'
for path in capture.glob('00000000-0000-4000-8000-000000000001-*.json'):
    (HERE/'destination-smoke-record.json').write_bytes(path.read_bytes())
(HERE/'checksums.sha256').write_text(manifest(p for p in HERE.iterdir() if p.is_file() and p.name!='checksums.sha256'))
print(json.dumps(identity,indent=2))
