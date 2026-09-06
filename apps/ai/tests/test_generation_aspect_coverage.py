import json
from pathlib import Path

import pytest

from app.generation.aspect_coverage import AspectLink, validate_aspect_links
from app.generation.models import GenerationResult

FIXTURES = json.loads(
    Path("/contracts/fixtures/generation-aspect-coverage/v1.json").read_text()
)["cases"]


@pytest.mark.parametrize("case", FIXTURES, ids=lambda case: case["name"])
def test_shared_aspect_coverage_contract(case):
    def validate():
        result = GenerationResult.model_validate(case["result"])
        links = tuple(AspectLink.model_validate(link) for link in case["links"])
        validate_aspect_links(result, links, tuple(case["need_ids"]))

    if case["valid"]:
        validate()
    else:
        with pytest.raises(ValueError):
            validate()
