"""Questions-only release adapter; no expectations, grading or dispatch."""
import hashlib
import json
import sys
from pathlib import Path

RELEASE_SHA256 = '4c6ad785d33fe032b57541fb6fcf9b06f81a48391bd4bf9e7b43bcc852769048'

def parse_release(raw, expected_sha256=RELEASE_SHA256):
    if hashlib.sha256(raw).hexdigest() != expected_sha256:
        raise ValueError('released_input_checksum_mismatch')
    package = json.loads(raw)
    if package.get('schema_version') != 'decision-gate-questions-v1':
        raise ValueError('unknown_release_schema')
    section_names = ('fresh_answerable', 'controls', 'known_regressions')
    if tuple(k for k in package if k in section_names) != section_names:
        raise ValueError('unexpected_section_order')
    result=[]
    for section, group, count in zip(section_names, ('fresh','control','regression'), (24,4,6), strict=True):
        rows=package.get(section)
        if not isinstance(rows,list) or len(rows)!=count:
            raise ValueError('selection_count_mismatch')
        for row in rows:
            if not isinstance(row.get('id'),str) or not isinstance(row.get('utterance'),str) or not row['utterance']:
                raise ValueError('invalid_question_only_item')
            # No trimming, paraphrase, new date or alteration of question bytes.
            result.append({'id':row['id'],'utterance':row['utterance'],'group':group})
    if len({r['id'] for r in result})!=34:
        raise ValueError('duplicate_item_id')
    return result

def inventory(raw):
    items=parse_release(raw)
    return {'release_sha256':hashlib.sha256(raw).hexdigest(),'counts':{'fresh':24,'control':4,'regression':6},
        'items':[{'ordinal':i,'id':r['id'],'group':r['group'],'utterance_sha256':hashlib.sha256(r['utterance'].encode()).hexdigest()} for i,r in enumerate(items,1)],
        'normalised_execution_sha256':hashlib.sha256(json.dumps(items,ensure_ascii=False,separators=(',',':')).encode()).hexdigest(),
        'workspace_context_supplied_in_release':False,'expectations_accessed':False,'dispatches':0}

def execute_released(raw, authority, destination, verify, dispatch, budget_check):
    """Phase B only: both the original release and derived execution bytes are bound.

    This is not called during Phase A. No authority is constructed here.
    The existing checkpoint's exclusive consumption and all stop controls remain.
    """
    if authority.get('released_input_sha256') != RELEASE_SHA256:
        raise ValueError('unapproved_release_identity')
    if authority.get('execution_context_confirmed') is not True:
        raise ValueError('reviewer_context_unconfirmed')
    rows=parse_release(raw)
    normalised=json.dumps(rows,ensure_ascii=False,separators=(',',':')).encode()
    sys.path.insert(0,str(Path(__file__).resolve().parent.parent/'harness'))
    from checkpoint import execute
    return execute(normalised,authority,destination,verify,dispatch,budget_check)
