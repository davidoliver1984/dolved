"""Exact retained failure identities, including non-reproducing failures."""
import gzip, hashlib, json, xml.etree.ElementTree as ET
from pathlib import Path
P=Path(__file__).resolve().parent
ROOT=Path('/workspace')
BASE=ROOT/'docs/evaluation/r28-s04/block1-extractive-20261112/verification'
result={}
for suite in ('ai','api'):
    records=[]
    for path in (BASE/f'migrated-ready-{suite}.xml', P/'provider-free'/f'{suite}-full-final.xml'):
        if not path.exists(): path=path.with_suffix(path.suffix+'.gz')
        raw=path.read_bytes(); xml=gzip.decompress(raw) if path.suffix=='.gz' else raw
        cases=list(ET.fromstring(xml).iter('testcase'))
        failed=sorted(f"{c.get('classname')}::{c.get('name')}" for c in cases if c.find('failure') is not None or c.find('error') is not None)
        records.append({'path':str(path.relative_to(ROOT)),'sha256':hashlib.sha256(raw).hexdigest(),'tests':len(cases),'failures':failed})
    result[suite]={'baseline':records[0],'current':records[1],
                   'new':sorted(set(records[1]['failures'])-set(records[0]['failures'])),
                   'not_reproduced':sorted(set(records[0]['failures'])-set(records[1]['failures']))}
(P/'provider-free/suite-comparison.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({k:{'tests':v['current']['tests'],'failures':len(v['current']['failures']),'new':v['new'],'not_reproduced':v['not_reproduced']} for k,v in result.items()},indent=2))
assert not any(v['new'] for v in result.values())
