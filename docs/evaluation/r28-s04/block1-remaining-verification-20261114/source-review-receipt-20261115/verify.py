"""Reproducible provider-free receipt sizing, suite identities and runtime freeze."""
import hashlib
import json
import subprocess
import xml.etree.ElementTree as ET
from pathlib import Path

import tiktoken
from openai.lib._pydantic import to_strict_json_schema
from pydantic import ValidationError

from app.generation.support_review import (
    SUPPORT_REVIEW_OUTPUT_TOKENS, SUPPORT_REVIEW_RECEIPT_TOKENS,
    SUPPORT_REVIEW_REASONING_HEADROOM, SUPPORT_REVIEW_FORMAT_HEADROOM,
    SourceSupportReview,
)
from tests.test_support_receipt_bounds import maximum_receipt
from app.generation.factory import build_needs_generator
from app.settings import get_settings
from pydantic import SecretStr

HERE = Path(__file__).resolve().parent
ROOT = Path('/workspace')
base = json.loads((HERE.parent/'coverage-implementation-20261115/suite-comparison.json').read_text())
comparison = {}
for service in ('ai', 'api'):
    path = HERE / (f'{service}-complete.xml' if service == 'api' else 'ai-release.xml')
    cases = list(ET.fromstring(path.read_bytes()).iter('testcase'))
    failures = sorted({f"{c.get('classname')}::{c.get('name')}" for c in cases
        if c.find('failure') is not None or c.find('error') is not None})
    old = base[service]['failures']
    comparison[service] = {'tests': len(cases), 'failures': failures,
        'skipped': sum(c.find('skipped') is not None for c in cases),
        'baseline_failures': old, 'new': sorted(set(failures)-set(old)),
        'not_reproduced': sorted(set(old)-set(failures)),
        'junit_sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(HERE/'suite-comparison.json').write_text(json.dumps(comparison, indent=2)+'\n')
settings = get_settings()
generator = build_needs_generator(settings.model_copy(update={'generation_openai_api_key': SecretStr('provider-free-not-a-real-key')}))
request_path = {'observation': 'isolated configured factory/client inspection, no request sent',
    'route': '/api/internal/retrieval/generation/answer-needs',
    'same_client_for_generation_and_review': True,
    'sdk_configured_timeout_seconds': generator._client.timeout,
    'provider_timeout_read_seconds': generator._client._client.timeout.read,
    'provider_timeout_connect_seconds': generator._client._client.timeout.connect,
    'sdk_max_retries': generator._client.max_retries,
    'python_max_attempts': generator._max_attempts,
    'generation_max_output_tokens': generator.profile.max_output_tokens,
    'review_max_output_tokens': SUPPORT_REVIEW_OUTPUT_TOKENS,
    'reasoning_effort': generator.profile.reasoning_effort,
    'model': generator.profile.model,
    'api_to_ai_timeout_seconds_from_allowlisted_compose': 480,
    'laravel_validation_attempts_from_source': 2,
    'timeout_changed': False, 'retry_policy_changed': False}
generator._client.close()
(HERE/'request-path.json').write_text(json.dumps(request_path, indent=2)+'\n')
raw = maximum_receipt()
review = SourceSupportReview.model_validate(raw)
encode = tiktoken.get_encoding('o200k_base').encode
tokens = len(encode(review.model_dump_json()))
dense = maximum_receipt()
for part in dense['parts']:
    for claim in part['claims']:
        claim['answer_quote'] = '量🧪'*300
        claim['source_quote'] = '量🧪'*150
try:
    SourceSupportReview.model_validate(dense)
    raise AssertionError('Dense over-cap receipt must be rejected')
except ValidationError:
    pass
sizing = {'measurement': 'provider-free o200k canonical JSON; no live tokens/latency',
    'maximum_fields_fixture': {'parts': 12, 'associations': 30, 'answer_quote_chars': 600,
        'source_anchor_chars': 300, 'identifier_chars': 32, 'missing_condition_chars': 160,
        'canonical_tokens': tokens, 'canonical_bytes': len(review.model_dump_json().encode())},
    'dense_maximum_fields_fixture_rejected': True,
    'dense_canonical_tokens': len(encode(json.dumps(dense, ensure_ascii=False, separators=(',', ':')))),
    'entire_receipt_acceptance_tokens': SUPPORT_REVIEW_RECEIPT_TOKENS,
    'reasoning_headroom_tokens': SUPPORT_REVIEW_REASONING_HEADROOM,
    'format_headroom_tokens': SUPPORT_REVIEW_FORMAT_HEADROOM,
    'output_allowance': SUPPORT_REVIEW_OUTPUT_TOKENS,
    'fits_headroom': tokens + SUPPORT_REVIEW_REASONING_HEADROOM + SUPPORT_REVIEW_FORMAT_HEADROOM <= SUPPORT_REVIEW_OUTPUT_TOKENS,
    'latency_guarantee': False,
    'illustrative_max_fields_emission_seconds_at_40_to_100_tokens_per_second': [tokens/100, tokens/40]}
(HERE/'receipt-sizing.json').write_text(json.dumps(sizing, indent=2)+'\n')
(HERE/'receipt.schema.json').write_text(json.dumps(to_strict_json_schema(SourceSupportReview), indent=2)+'\n')
reservation = {'scope': 'per question output component; not a whole-run authorisation',
    'provider_calls_made': 0, 'generation_attempts_per_python_request': 3,
    'laravel_validation_attempts': 2, 'maximum_generation_calls': 6, 'maximum_review_calls': 6,
    'generation_output_per_call': 4096, 'review_output_per_call': SUPPORT_REVIEW_OUTPUT_TOKENS,
    'normal_two_completed_review_output': 2*SUPPORT_REVIEW_OUTPUT_TOKENS,
    'conservative_generation_and_review_output': 6*(4096+SUPPORT_REVIEW_OUTPUT_TOKENS),
    'previous_same_envelope_output': 6*(4096+32768),
    'selector_output_unchanged_additive': 4096,
    'input_and_other_stages': 'Retain existing conservative reservation, including failed/unknown transport usage; review input is not separately metered by generation preflight.',
    'full_run_usd_reservation': None, 'reason': 'No decision-run dispatch/authorisation in this task; this is the output-component replacement, not a claim that input usage is zero.',
    'runaway_guard_rule_unchanged': 'max(USD 50, twice the complete conservative reservation)'}
(HERE/'decision-run-reservation-update.json').write_text(json.dumps(reservation, indent=2)+'\n')
paths = subprocess.check_output(['git','-c','safe.directory=/workspace','ls-files','--',
    'apps/api/app','apps/api/config','apps/api/routes','apps/ai/app','contracts'], cwd=ROOT).decode().splitlines()
manifest = ''.join(f'{hashlib.sha256((ROOT/p).read_bytes()).hexdigest()}  {p}\n' for p in sorted(paths))
(HERE/'runtime-source.sha256').write_text(manifest)
(HERE/'runtime-identity.json').write_text(json.dumps({'aggregate':hashlib.sha256(manifest.encode()).hexdigest(),
    'files':len(paths), 'execution':'bounded-needs-slice-v23', 'support_contract':'need-source-support-v2',
    'review_output_tokens': SUPPORT_REVIEW_OUTPUT_TOKENS, 'generation_prompt':'grounded-generation-needs-v15',
    'generation_adapter':'openai-responses-needs-v7', 'temporal_profile':'v6', 'live_execution':False}, indent=2)+'\n')
(HERE/'checksums.sha256').write_text(''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}\n'
    for p in sorted(HERE.iterdir()) if p.is_file() and p.name != 'checksums.sha256'))
print(json.dumps({'sizing':sizing, 'suites':{s:{k:v for k,v in r.items() if k in ('tests','new','not_reproduced')} for s,r in comparison.items()}}, indent=2))
