import json
from pathlib import Path
P=Path(__file__).resolve().parent
root=Path('/workspace/docs/evaluation/r28-s04')
rows=json.loads((P/'review-inputs.json').read_text())
ids={r['case_id'] for r in rows if r['cohort']!='known_regression_ungraded'}
corpus=json.loads((root/'r07-evidence-units-v4-20261111/evidence-units-corpus.json').read_text())
versions=sorted({u['document_version_id'] for c in corpus['cases'] if c['case_id'] in ids for u in c['evidence_units']})
(P/'hydration-input.json').write_text(json.dumps({'chunk_ids':json.loads((P/'ranked-chunk-ids.json').read_text()),'version_ids':versions},indent=2)+'\n')
