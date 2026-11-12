from app.settings import get_settings
import hashlib
from pathlib import Path
assert get_settings().retrieval_planner_model == 'gpt-5-mini'
assert hashlib.sha256(Path('/app/app/retrieval/interpretation.py').read_bytes()).hexdigest() == 'ba4d8069e42d52c35fe03037e8742982b30d150ff56df790ab11bbe495634348'
"""Container-side once-only probe of the production check; invoked by governed runner.

Reads question+validated-plan data from stdin. No catalogue, evidence or expected
codes enter the request. No retry. The parent owns authorization/freeze/accounting.
"""
import json
import sys

from app.retrieval.interpretation import InterpretationCheckError, review_interpretation
from app.retrieval.needs import EvidenceNeedsPlan, UserTurn
from app.retrieval.routes import needs_planner_dependency

item = json.load(sys.stdin)
plan = EvidenceNeedsPlan.model_validate(item['plan'])
turns = tuple(UserTurn.model_validate(turn) for turn in item['user_turns'])
plan.validate_grounding(turns)
planner = needs_planner_dependency()
try:
    receipt = review_interpretation(client=planner._client, api_url=planner._api_url,
        api_key=planner._api_key, model=planner._model, provider=planner._provider_name,
        turns=turns, plan=plan)
except InterpretationCheckError as error:
    receipt = error.receipt
print(json.dumps({'probe_id': item['probe_id'], 'receipt': receipt}))
