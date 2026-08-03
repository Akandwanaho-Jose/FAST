#!/usr/bin/env python3
"""Normalize handbook curriculum tables into reviewable course placements."""

from __future__ import annotations

import argparse
import csv
import json
import re
from collections import defaultdict
from pathlib import Path

SECTIONS = {
    "bachelor-of-biomedical-engineering": (483, 500),
    "bachelor-of-electrical-and-electronics-engineering": (694, 943),
    "bachelor-of-mechanical-and-industrial-engineering": (1191, 1227),
    "bachelor-of-science-in-civil-and-building-services-engineering": (1393, 1422),
}
CODE = re.compile(r"^[A-Z]{2,5}\s*\d{4}$", re.I)

# Corrections supported by other content in the same handbook. These are kept
# here (and reported) so the imported data remains reproducible and auditable.
HANDBOOK_CORRECTIONS = {
    ("MIE1105", "production engineering i"): (
        "MIE1106",
        "Corrected duplicated code using the handbook course-description heading.",
    ),
}


def compact(row: list[str]) -> list[str]:
    values: list[str] = []
    for raw in row:
        value = " ".join(str(raw).replace("\u00a0", " ").split())
        if value and (not values or value != values[-1]):
            values.append(value)
    return values


def placement(code: str) -> tuple[int, int]:
    digits = re.search(r"(\d{4})$", code)
    if digits is None:
        raise ValueError(f"Course code has no placement digits: {code}")
    return int(digits.group(1)[0]), int(digits.group(1)[1])


def parse_rows(programme: str, block_index: str, rows: list[list[str]]) -> list[dict[str, str]]:
    records: list[dict[str, str]] = []
    elective = False
    order_by_period: defaultdict[tuple[int, int], int] = defaultdict(int)
    for raw in rows:
        row = compact(raw)
        joined = " ".join(row).lower()
        if "semester load" in joined or joined.startswith("year "):
            elective = False
        if "elective" in joined and not any(CODE.fullmatch(item) for item in row):
            elective = True
            continue
        code_index = next((i for i, item in enumerate(row) if CODE.fullmatch(item)), None)
        if code_index is None or code_index + 1 >= len(row):
            continue
        code = re.sub(r"\s+", "", row[code_index]).upper()
        title = row[code_index + 1]
        if title.lower() in {"course title", "course name"}:
            continue
        correction = HANDBOOK_CORRECTIONS.get((code, title.strip().lower()))
        source_note = ""
        if correction is not None:
            code, source_note = correction
        year, semester = placement(code)
        # The Year 4 Semester I EEE table labels the design stage EEE4207 even
        # though its code suggests Semester II. Table position is authoritative.
        if programme == "bachelor-of-electrical-and-electronics-engineering" and block_index == "751" and code == "EEE4207":
            semester = 1
            source_note = "; ".join(filter(None, [source_note, "Semester taken from the Year 4 Semester I table."]))
        numerics = [item for item in row[code_index + 2:] if re.fullmatch(r"\d+(?:\.\d+)?", item)]
        credits = numerics[-1] if numerics else ""
        contact = numerics[-2] if len(numerics) >= 2 else ""
        order_by_period[(year, semester)] += 1
        records.append({
            "programme_slug": programme,
            "version_name": "Undergraduate Handbook 2025/2026",
            "course_code": code,
            "course_title": title,
            "study_year": str(year),
            "semester": str(semester),
            "requirement_type": "elective" if elective else "core",
            "contact_hours": contact,
            "credit_units": credits,
            "display_order": str(order_by_period[(year, semester)]),
            "source_block": block_index,
            "source_note": source_note,
        })
    return records


def parse_nested_peem(tables: list[list[list[str]]]) -> list[dict[str, str]]:
    records: list[dict[str, str]] = []
    orders: defaultdict[tuple[int, int], int] = defaultdict(int)
    for table_index, rows in enumerate(tables, start=1):
        elective = [False, False]
        for raw in rows:
            row = [" ".join(str(item).split()) for item in raw]
            for side, offset in enumerate((0, 4)):
                cells = row[offset:offset + 4]
                joined = " ".join(cells).lower()
                if "semester load" in joined or "no elective" in joined:
                    elective[side] = False
                if "elective" in joined and not any(CODE.fullmatch(item) for item in cells):
                    elective[side] = True
                    continue
                code = re.sub(r"\s+", "", cells[0]).upper() if cells else ""
                if not CODE.fullmatch(code) or len(cells) < 2:
                    continue
                year, semester = placement(code)
                orders[(year, semester)] += 1
                records.append({
                    "programme_slug": "bachelor-of-petroleum-engineering-and-environmental-management",
                    "version_name": "Undergraduate Handbook 2025/2026",
                    "course_code": code,
                    "course_title": cells[1],
                    "study_year": str(year),
                    "semester": str(semester),
                    "requirement_type": "elective" if elective[side] else "core",
                    "contact_hours": cells[2],
                    "credit_units": cells[3],
                    "display_order": str(orders[(year, semester)]),
                    "source_block": f"nested-{table_index}",
                    "source_note": "Nested Word table",
                })
    return records


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("extraction", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("report", type=Path)
    args = parser.parse_args()
    data = json.loads(args.extraction.read_text(encoding="utf-8"))
    records: list[dict[str, str]] = []
    for programme, (start, end) in SECTIONS.items():
        for block in data["blocks"]:
            if block["type"] == "table" and start <= block["index"] < end:
                records.extend(parse_rows(programme, str(block["index"]), block["rows"]))
    records.extend(parse_nested_peem(data.get("nested_tables", [])))

    definitions: defaultdict[str, set[tuple[str, str]]] = defaultdict(set)
    for record in records:
        definitions[record["course_code"]].add((record["course_title"], record["credit_units"]))
    conflicts = {code: sorted(values) for code, values in definitions.items() if len(values) > 1}
    for record in records:
        if record["course_code"] in conflicts:
            extra = "Conflicting handbook definitions: " + " | ".join(
                f"{title} ({credits} CU)" for title, credits in conflicts[record["course_code"]]
            )
            record["source_note"] = "; ".join(filter(None, [record["source_note"], extra]))

    fields = list(records[0]) if records else []
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(records)
    by_programme: defaultdict[str, int] = defaultdict(int)
    for record in records:
        by_programme[record["programme_slug"]] += 1
    report = {
        "placements": len(records),
        "unique_course_codes": len(definitions),
        "placements_by_programme": dict(sorted(by_programme.items())),
        "definition_conflicts": conflicts,
        "applied_corrections": [
            {
                "original_code": original_code,
                "title": title,
                "corrected_code": corrected_code,
                "reason": reason,
            }
            for (original_code, title), (corrected_code, reason) in HANDBOOK_CORRECTIONS.items()
        ] + [{
            "course_code": "EEE4207",
            "programme_slug": "bachelor-of-electrical-and-electronics-engineering",
            "source_block": "751",
            "corrected_semester": 1,
            "reason": "Semester taken from the Year 4 Semester I table.",
        }],
        "missing_credit_units": sorted({r["course_code"] for r in records if not r["credit_units"]}),
    }
    args.report.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    print(json.dumps(report, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
