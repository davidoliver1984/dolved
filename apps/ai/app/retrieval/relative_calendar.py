"""Application-owned London calendar periods; never infer an event date."""

import re
from datetime import datetime, timedelta
from zoneinfo import ZoneInfo

PATTERN = re.compile(r"\b(last|this)\s+(month|quarter|year)\b", re.IGNORECASE)


def relative_period(quote: str, evaluated_at: str | None) -> dict[str, str] | None:
    matches = {(m[0].lower(), m[1].lower()) for m in PATTERN.findall(quote)}
    if not matches:
        return None
    if len(matches) != 1 or evaluated_at is None:
        raise ValueError("relative calendar reference requires one anchored period")
    anchor = datetime.fromisoformat(evaluated_at)
    if anchor.tzinfo is None:
        raise ValueError("relative calendar anchor requires a timezone")
    local = anchor.astimezone(ZoneInfo("Europe/London"))
    relation, unit = next(iter(matches))
    width = {"month": 1, "quarter": 3, "year": 12}[unit]
    start_month = ((local.month - 1) // width) * width + 1
    serial = local.year * 12 + start_month - 1 - (width if relation == "last" else 0)
    year, month = divmod(serial, 12)
    end_year, end_month = divmod(serial + width, 12)
    start = datetime(year, month + 1, 1, tzinfo=ZoneInfo("Europe/London"))
    end = datetime(end_year, end_month + 1, 1, tzinfo=ZoneInfo("Europe/London"))
    precision = {"month": "month", "year": "year", "quarter": "interval"}[unit]
    value = start.strftime("%Y-%m" if unit == "month" else "%Y")
    if unit == "quarter":
        value = (
            f"{start.date().isoformat()}/{(end.date() - timedelta(days=1)).isoformat()}"
        )
    return {
        "precision": precision,
        "value": value,
        "start_inclusive": start.isoformat(),
        "end_exclusive": end.isoformat(),
        "timezone": "Europe/London",
    }


def grounded_periods(turns, evaluated_at: str) -> list[dict[str, object]]:
    return [
        {
            "turn_id": turn.turn_id,
            "quote": match.group(0),
            **(relative_period(match.group(0), evaluated_at) or {}),
        }
        for turn in turns
        for match in PATTERN.finditer(turn.text)
    ]
