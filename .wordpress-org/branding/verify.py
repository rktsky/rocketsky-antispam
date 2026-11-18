#!/usr/bin/env python3
"""Validate only the owned directory icon/banner contract, without dependencies.

The screenshot workflow is independent. These checks intentionally select only
icon and banner files so adding or replacing screenshots cannot change its scope.
"""

from pathlib import Path
import hashlib
import json
import re
import struct
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent.parent
ASSETS = ROOT / "assets"
EXPECTED = {"icon-128x128.png": (128, 128), "icon-256x256.png": (256, 256)}
for suffix in ("", "-de", "-fr", "-it", "-es"):
    for width, height in ((772, 250), (1544, 500)):
        EXPECTED[f"banner-{width}x{height}{suffix}.png"] = (width, height)

actual = {p.name for p in ASSETS.glob("*.png") if p.name.startswith(("icon-", "banner-"))}
assert actual == set(EXPECTED), f"Missing or extra directory outputs: {actual ^ set(EXPECTED)}"
records = []
for name, dimensions in EXPECTED.items():
    path = ASSETS / name
    data = path.read_bytes()
    assert data[:8] == b"\x89PNG\r\n\x1a\n", f"Invalid PNG signature: {name}"
    assert struct.unpack(">II", data[16:24]) == dimensions, f"Incorrect native dimensions: {name}"
    # Use the stricter decimal-MB interpretation of WordPress.org's file limits.
    limit = 1_000_000 if name.startswith("icon-") else 4_000_000
    assert len(data) < limit, f"Directory file limit exceeded: {name}"
    records.append({"file": name, "width": dimensions[0], "height": dimensions[1], "bytes": len(data), "sha256": hashlib.sha256(data).hexdigest()})

sources = [ASSETS / "icon.svg", *sorted((ROOT / "branding").glob("banner*.svg"))]
assert len(sources) == 6, "One icon and five localized banner SVGs are required."
for path in sources:
    data = path.read_bytes()
    document = ET.fromstring(data)
    assert not re.search(rb"<(?:text|image|script|foreignObject)\b|(?:href|src)=|@font-face", data), f"Nonportable SVG dependency: {path.name}"
    assert any(node.tag.endswith("path") for node in document.iter()), f"Missing vector outlines: {path.name}"
assert (ASSETS / "icon.svg").stat().st_size < 1_000_000

for width, height in ((772, 250), (1544, 500)):
    outputs = [(ASSETS / f"banner-{width}x{height}{suffix}.png").read_bytes() for suffix in ("", "-de", "-fr", "-it", "-es")]
    assert len(set(outputs)) == 5, "Localized banners must contain actual localized copy."

source_records = []
for path in [*sources, ROOT / "branding" / "build.swift", ROOT / "branding" / "render.sh", Path(__file__).resolve(), ROOT / "branding" / "fonts" / "Roboto-variable.ttf", ROOT / "branding" / "fonts" / "OFL.txt"]:
    data = path.read_bytes()
    source_records.append({"file": str(path.relative_to(ROOT)), "bytes": len(data), "sha256": hashlib.sha256(data).hexdigest()})

report = {"sources": source_records, "contract": "https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/", "checks": "Native filenames, dimensions, file limits, SVG portability, distinct localized content", "files": records}
(ROOT / "branding" / "verification.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
print(f"PASS: {len(EXPECTED)} PNG outputs and 6 outlined SVGs; dimensions, filenames, limits and locales verified.")
