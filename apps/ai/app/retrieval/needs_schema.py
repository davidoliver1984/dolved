"""Provider-visible structural restrictions; application authority stays independent."""

from copy import deepcopy
from typing import Any

from app.retrieval.needs import CatalogueFamily, EvidenceNeedsPlan
from app.retrieval.planner import RetrievalPlanningError, _strict_response_schema


def provider_needs_schema(catalogue: tuple[CatalogueFamily, ...]) -> dict[str, Any]:
    """Keep the wire representation, excluding structurally impossible alternatives.

    No title matching, answerability decision, ID rewriting or validator bypass.
    Shared enum definitions avoid multiplying the provider's total enum allowance.
    """
    ids = [str(f.family_id) for f in catalogue]
    if len(ids) > 500 or len(set(ids)) != len(ids):
        raise RetrievalPlanningError(
            "invalid bounded catalogue", category="catalogue_bound_exceeded"
        )
    schema = _strict_response_schema(EvidenceNeedsPlan.model_json_schema())
    defs = schema["$defs"]
    original_authority = defs["NeedAuthority"]

    def authority(kinds: list[str], precision: str) -> dict[str, Any]:
        branch = deepcopy(original_authority)
        props = branch["properties"]
        props["kind"] = {"type": "string", "enum": kinds}
        props["precision"] = {"type": "string", "enum": [precision]}
        props["source"] = (
            {"type": "null"}
            if precision == "none"
            else {"$ref": "#/$defs/QuestionSpan"}
        )
        patterns = {
            "day": r"^[0-9]{4}-[0-9]{2}-[0-9]{2}$",
            "month": r"^[0-9]{4}-[0-9]{2}$",
            "year": r"^[0-9]{4}$",
            "interval": r"^[0-9]{4}-[0-9]{2}-[0-9]{2}/[0-9]{4}-[0-9]{2}-[0-9]{2}$",
            "named": r"\S",
        }
        props["value"] = (
            {"type": "null"}
            if precision == "none"
            else {"type": "string", "minLength": 1, "pattern": patterns[precision]}
        )
        return branch

    current = authority(["current"], "none")
    past = [
        authority(["previous", "historical_reference"], "named"),
        authority(["date"], "day"),
    ]
    past += [authority(["period"], p) for p in ("month", "year", "interval")]
    transitions = [authority(["transition_boundary"], "day")]
    transitions += [
        authority(["transition_period"], p) for p in ("month", "year", "interval")
    ]
    authority_refs = []
    for index, branch in enumerate([current, *past, *transitions]):
        name = f"AuthorityAlternative{index}"
        defs[name] = branch
        authority_refs.append({"$ref": f"#/$defs/{name}"})
    defs["NeedAuthority"] = {"anyOf": authority_refs}
    defs["RetrieveNeedAuthority"] = {"anyOf": authority_refs[: 1 + len(past)]}
    defs["CompareNeedAuthority"] = {"anyOf": authority_refs[1:]}
    need = defs["EvidenceNeed"]
    alternatives = []
    for operation, ref in [
        ("retrieve", "RetrieveNeedAuthority"),
        ("compare", "CompareNeedAuthority"),
    ]:
        branch = deepcopy(need)
        branch["properties"]["operation"] = {"type": "string", "enum": [operation]}
        branch["properties"]["authority"] = {"$ref": f"#/$defs/{ref}"}
        alternatives.append(branch)
    defs["EvidenceNeed"] = {"anyOf": alternatives}

    # >250 UUID enum values exceed the per-enum string-size bound; use shared groups.
    if ids:
        defs["CatalogueFamilyId"] = {
            "anyOf": [
                {"type": "string", "enum": ids[i : i + 250]}
                for i in range(0, len(ids), 250)
            ]
        }
    binding = defs["NeedBinding"]
    alternatives = []
    statuses = (
        ["topic", "unavailable"]
        + (["matched"] if ids else [])
        + (["ambiguous"] if len(ids) > 1 else [])
    )
    for subject_status in statuses:
        for ownership in ["authorised_workspace", "external_tenant"]:
            branch = deepcopy(binding)
            props = branch["properties"]
            props["subject_status"] = {"type": "string", "enum": [subject_status]}
            matched = subject_status in {"matched", "ambiguous"}
            props["family_ids"] = {
                "type": "array",
                "items": {"$ref": "#/$defs/CatalogueFamilyId"}
                if ids
                else {"type": "string"},
                "minItems": (2 if subject_status == "ambiguous" else 1)
                if matched
                else 0,
                "maxItems": 8 if matched else 0,
            }
            props["ownership"] = {"type": "string", "enum": [ownership]}
            props["ownership_source"] = (
                {"type": "null"}
                if ownership == "authorised_workspace"
                else {"$ref": "#/$defs/QuestionSpan"}
            )
            alternatives.append(branch)
    defs["NeedBinding"] = {"anyOf": alternatives}
    return schema
