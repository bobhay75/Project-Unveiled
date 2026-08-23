#!/usr/bin/env python3
"""Validate Trust-Worthy case records against the public schema and repo invariants."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path
from urllib.parse import urlsplit

from jsonschema import Draft202012Validator, FormatChecker

ROOT = Path(__file__).resolve().parents[1]
CASE_DIR = ROOT / "truth" / "cases"
SCHEMA_PATH = ROOT / "truth" / "truth-case.schema.json"
CASE_ID = re.compile(r"^TW-CLAIM-\d{6}$")


def local_target(url: str) -> Path | None:
    parsed = urlsplit(url)
    if parsed.scheme != "https" or parsed.netloc not in {"bobsome1.com", "www.bobsome1.com"}:
        return None
    return ROOT / parsed.path.lstrip("/")


def main() -> int:
    errors: list[str] = []

    try:
        schema = json.loads(SCHEMA_PATH.read_text(encoding="utf-8"))
        Draft202012Validator.check_schema(schema)
    except (OSError, json.JSONDecodeError, Exception) as exc:
        print(f"Truth-case schema is invalid: {exc}")
        return 1

    validator = Draft202012Validator(schema, format_checker=FormatChecker())
    case_paths = sorted(CASE_DIR.glob("TW-CLAIM-*.json"))
    if not case_paths:
        errors.append("no Trust-Worthy case records found")

    seen_ids: set[str] = set()
    seen_questions: dict[str, str] = {}

    for path in case_paths:
        label = path.relative_to(ROOT)
        try:
            record = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as exc:
            errors.append(f"{label}: invalid JSON: {exc}")
            continue

        for error in sorted(validator.iter_errors(record), key=lambda item: list(item.absolute_path)):
            location = ".".join(str(part) for part in error.absolute_path) or "<root>"
            errors.append(f"{label}:{location}: {error.message}")

        case_id = str(record.get("case_id", ""))
        if not CASE_ID.fullmatch(case_id):
            errors.append(f"{label}: invalid case_id {case_id!r}")
        if path.stem != case_id:
            errors.append(f"{label}: filename and case_id do not match")
        if case_id in seen_ids:
            errors.append(f"{label}: duplicate case_id {case_id}")
        seen_ids.add(case_id)

        normalized_question = re.sub(r"\W+", " ", str(record.get("question", "")).lower()).strip()
        if normalized_question in seen_questions:
            errors.append(f"{label}: duplicates the question in {seen_questions[normalized_question]}")
        seen_questions[normalized_question] = str(label)

        propositions = record.get("propositions", [])
        if len(propositions) != 1:
            errors.append(f"{label}: MVP cases must expose one primary P/not-P proposition")
        elif propositions[0].get("p") == propositions[0].get("not_p"):
            errors.append(f"{label}: P and not-P must differ")

        versions = {item.get("version") for item in record.get("revision_history", [])}
        if record.get("version") not in versions:
            errors.append(f"{label}: current version is missing from revision_history")

        source_ids = [source.get("source_id") for source in record.get("sources", [])]
        if len(source_ids) != len(set(source_ids)):
            errors.append(f"{label}: source IDs must be unique")
        source_id_set = set(source_ids)
        for source in record.get("sources", []):
            parent = source.get("lineage_parent")
            if isinstance(parent, str) and re.fullmatch(r"S\d+", parent) and parent not in source_id_set:
                errors.append(f"{label}: {source.get('source_id')} references missing lineage parent {parent}")

        for field in ("public_url", "challenge_endpoint"):
            target = local_target(str(record.get(field, "")))
            if target is None:
                errors.append(f"{label}: {field} must use the bobsome1.com HTTPS origin")
            elif not target.is_file():
                errors.append(f"{label}: {field} points to missing file {target.relative_to(ROOT)}")

    if errors:
        print("Trust-Worthy case validation failed:")
        print("\n".join(f"- {item}" for item in errors))
        return 1

    print(f"Trust-Worthy case validation passed: {len(case_paths)} records checked.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
