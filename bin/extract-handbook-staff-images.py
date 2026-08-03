#!/usr/bin/env python3
"""Extract mapped staff portraits from the FAST handbook as web-ready WebP files."""

from __future__ import annotations

import csv
import hashlib
import json
import sys
import zipfile
from pathlib import Path, PurePosixPath
from xml.etree import ElementTree as ET

from PIL import Image, ImageOps

NS = {
    "a": "http://schemas.openxmlformats.org/drawingml/2006/main",
    "r": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
    "pr": "http://schemas.openxmlformats.org/package/2006/relationships",
}
ROOT = Path(__file__).resolve().parent.parent
MAP_PATH = ROOT / "database" / "seeds" / "staff-handbook-2025-2026-images.csv"
OUTPUT_ROOT = ROOT / "public" / "uploads" / "staff" / "handbook-2025-2026"


def transformations(archive: zipfile.ZipFile) -> dict[str, dict[str, object]]:
    document = ET.fromstring(archive.read("word/document.xml"))
    rels = ET.fromstring(archive.read("word/_rels/document.xml.rels"))
    relationships = {
        rel.attrib["Id"]: rel.attrib["Target"]
        for rel in rels.findall("pr:Relationship", NS)
    }
    embed = f"{{{NS['r']}}}embed"
    result: dict[str, dict[str, object]] = {}
    for drawing in document.findall(".//{http://schemas.openxmlformats.org/wordprocessingml/2006/main}drawing"):
        blip = drawing.find(".//a:blip", NS)
        if blip is None or embed not in blip.attrib:
            continue
        target = relationships.get(blip.attrib[embed], "")
        filename = PurePosixPath(target).name
        if filename in result:
            continue
        transform = drawing.find(".//a:xfrm", NS)
        source_rect = drawing.find(".//a:srcRect", NS)
        result[filename] = {
            "rotation": int(transform.attrib.get("rot", "0")) / 60000 if transform is not None else 0,
            "flip_h": transform is not None and transform.attrib.get("flipH") == "1",
            "flip_v": transform is not None and transform.attrib.get("flipV") == "1",
            "crop": dict(source_rect.attrib) if source_rect is not None else {},
        }
    return result


def apply_word_transform(image: Image.Image, transform: dict[str, object]) -> Image.Image:
    image = ImageOps.exif_transpose(image)
    crop = transform.get("crop", {})
    if isinstance(crop, dict) and crop:
        width, height = image.size
        left = max(0, round(width * int(crop.get("l", 0)) / 100000))
        top = max(0, round(height * int(crop.get("t", 0)) / 100000))
        right = max(0, round(width * int(crop.get("r", 0)) / 100000))
        bottom = max(0, round(height * int(crop.get("b", 0)) / 100000))
        if left + right < width and top + bottom < height:
            image = image.crop((left, top, width - right, height - bottom))
    if transform.get("flip_h"):
        image = ImageOps.mirror(image)
    if transform.get("flip_v"):
        image = ImageOps.flip(image)
    clockwise = float(transform.get("rotation", 0)) % 360
    if clockwise:
        image = image.rotate((360 - clockwise) % 360, expand=True)
    image.thumbnail((1600, 1600), Image.Resampling.LANCZOS)
    return image


def main() -> int:
    if len(sys.argv) != 2:
        raise SystemExit("Usage: extract-handbook-staff-images.py handbook.docx")
    source = Path(sys.argv[1]).resolve()
    with MAP_PATH.open(newline="", encoding="utf-8-sig") as handle:
        mappings = list(csv.DictReader(handle))
    OUTPUT_ROOT.mkdir(parents=True, exist_ok=True)
    extracted: list[dict[str, object]] = []
    errors: list[str] = []
    with zipfile.ZipFile(source) as archive:
        transform_map = transformations(archive)
        members = set(archive.namelist())
        for mapping in mappings:
            source_media = mapping["source_media"]
            member = "word/media/" + source_media
            if member not in members:
                errors.append(f"Missing embedded media: {source_media}")
                continue
            expected_path = "public/uploads/staff/handbook-2025-2026/" + mapping["staff_slug"] + ".webp"
            if mapping["public_path"] != expected_path:
                errors.append(f"Unsafe or unexpected output path for {mapping['staff_slug']}")
                continue
            from io import BytesIO

            with Image.open(BytesIO(archive.read(member))) as original:
                image = apply_word_transform(original.copy(), transform_map.get(source_media, {}))
            output = ROOT / Path(*PurePosixPath(expected_path).parts)
            temporary = output.with_suffix(".tmp.webp")
            image.save(temporary, "WEBP", quality=90, method=6)
            temporary.replace(output)
            extracted.append({
                "staff_slug": mapping["staff_slug"],
                "source_media": source_media,
                "public_path": expected_path,
                "width": image.width,
                "height": image.height,
                "bytes": output.stat().st_size,
                "sha256": hashlib.sha256(output.read_bytes()).hexdigest(),
            })
    print(json.dumps({
        "source": str(source),
        "mapping_count": len(mappings),
        "extracted_count": len(extracted),
        "output_directory": str(OUTPUT_ROOT),
        "total_bytes": sum(int(item["bytes"]) for item in extracted),
        "errors": errors,
        "images": extracted,
    }, indent=2))
    return 1 if errors else 0


if __name__ == "__main__":
    raise SystemExit(main())
