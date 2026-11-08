#!/usr/bin/env python3
"""Compare native WP-CLI extraction with every shipped MO catalogue. Since 0.5.2."""

import argparse
import ast
import gettext
from pathlib import Path
import re


def source_keys(path: Path) -> set:
	"""Read singular, contextual and plural keys from native gettext source. Since 0.5.2."""
	keys = set()
	entry = {}
	field = None
	for line in path.read_text().splitlines() + [""]:
		if not line.strip():
			if entry.get("msgid"):
				key = (entry.get("msgctxt", "") + "\x04" if entry.get("msgctxt") else "") + entry["msgid"]
				keys.add((key, 0) if "msgid_plural" in entry else key)
			entry = {}
			field = None
		elif line.startswith("#"):
			continue
		elif line.startswith('"') and field is not None:
			entry[field] += ast.literal_eval(line)
		else:
			match = re.match(r"^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?) (.*)$", line)
			if match:
				field = match[1]
				entry[field] = ast.literal_eval(match[2])
	return keys


def main() -> None:
	"""Fail on untranslated source messages or stale active catalogue keys. Since 0.5.2."""
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("--pot", required=True, type=Path)
	parser.add_argument("--languages", required=True, type=Path)
	arguments = parser.parse_args()
	source = source_keys(arguments.pot)
	# Native extraction includes fixed brand/URL headers. Their original spelling
	# is intentional, while the human-readable description must be translated.
	identity = {"Rocketsky", "https://github.com/rktsky/rocketsky-antispam", "https://www.rocketsky.ch/"}
	required = source - identity
	for locale in ("de_CH", "de_DE", "fr_FR", "it_IT", "es_ES"):
		path = arguments.languages / ("rocketsky-antispam-" + locale + ".mo")
		with path.open("rb") as handle:
			catalogue = gettext.GNUTranslations(handle)
		actual = {key for key in catalogue._catalog if key != "" and (not isinstance(key, tuple) or key[1] == 0)}
		missing = required - actual
		stale = actual - source
		if missing or stale:
			raise SystemExit(f"{locale}: missing={sorted(map(str, missing))!r}; stale={sorted(map(str, stale))!r}")
		print(f"PASS: {locale}: {len(required)} native source messages covered; no stale keys.")


if __name__ == "__main__":
	main()
