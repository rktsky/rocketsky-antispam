#!/usr/bin/env python3
"""Check reviewed PHPCS exceptions against native PHP tokens. Since 0.5.6.

This is an inventory and scope check, not a security audit. It never updates an
inventory or executes inspected plugin code. Tests and development helpers are
outside the production PHP roots inspected by this project-specific policy.
"""

from __future__ import annotations

import argparse
from collections import Counter
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import xml.etree.ElementTree as ET


ROOT_FILES = ("rocketsky-antispam.php", "uninstall.php")
DIRECTORIES = ("src", "templates", "assets", "languages")
INVENTORY = "tools/quality-exceptions.json"
RULESET = "phpcs.xml.dist"
IGNORED_TOKENS = {"T_WHITESPACE", "T_COMMENT", "T_DOC_COMMENT"}
IDENTITY_KEYS = ("path", "action", "sniffs", "scope", "code_sha256", "context_sha256", "reason")
DIRECTIVE = re.compile(r"^[\t /*#]*@?phpcs:([A-Za-z]+)\b([^\r\n]*)", re.IGNORECASE | re.MULTILINE)
SNIFF = re.compile(r"[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*){2,3}\Z")


class QualityError(ValueError):
    """Report an unreviewed, stale or prohibited exception. Since 0.5.6."""


def php_command(explicit: str | None = None) -> str:
    """Resolve one executable, including an explicit isolated PHP wrapper. Since 0.5.6."""
    command = explicit or os.environ.get("RKTSKY_ANTISPAM_PHP") or shutil.which("php")
    if not command:
        raise QualityError("Set --php or RKTSKY_ANTISPAM_PHP to PHP with native Tokenizer support.")
    return command


def production_sources(root: Path) -> dict[str, str]:
    """Discover every owned production PHP file, including newly added consumers. Since 0.5.6."""
    paths = [root / name for name in ROOT_FILES]
    for directory in DIRECTORIES:
        paths.extend((root / directory).rglob("*.php"))
    result = {}
    for path in sorted(set(paths)):
        if path.is_symlink() or not path.is_file() or root not in path.resolve().parents:
            raise QualityError("Production PHP must be a regular local file: " + str(path))
        result[path.relative_to(root).as_posix()] = path.read_text(encoding="utf-8")
    return result


def native_tokens(sources: dict[str, str], php: str) -> dict[str, list]:
    """Send source through stdin to a fixed native tokenizer, never to eval/include. Since 0.5.6."""
    helper = Path(__file__).with_name("php-quality-tokens.php").read_text(encoding="utf-8")
    if not helper.startswith("<?php\n"):
        raise QualityError("The owned tokenizer helper has an unexpected entrypoint.")
    completed = subprocess.run(
        [php, "-r", helper[len("<?php\n"):]], input=json.dumps(sources),
        text=True, capture_output=True, check=False,
    )
    if completed.returncode or completed.stderr:
        raise QualityError("Native PHP tokenization failed: " + completed.stderr.strip())
    try:
        result = json.loads(completed.stdout)
    except json.JSONDecodeError as error:
        raise QualityError("The native tokenizer did not return its JSON token stream.") from error
    if not isinstance(result, dict) or set(result.get("files", {})) != set(sources) or not isinstance(result.get("php_version"), str):
        raise QualityError("Native tokenization did not cover the complete production PHP inventory.")
    return result


def significant(tokens: list) -> list:
    """Ignore explanatory comments and spacing, retaining exact code/string tokens. Since 0.5.6."""
    return [[kind, text.strip() if kind == "T_OPEN_TAG" else text]
            for kind, text, _start, _end in tokens if kind not in IGNORED_TOKENS]


def code_hash(tokens: list) -> str:
    """Hash a lexical code contract without binding harmless comment edits. Since 0.5.6."""
    return hashlib.sha256(json.dumps(significant(tokens), ensure_ascii=False, separators=(",", ":")).encode()).hexdigest()


def callable_scopes(tokens: list) -> list[tuple[int, int, str]]:
    """Find native function body spans without interpreting PHP expressions. Since 0.5.6."""
    scopes = []
    for index, token in enumerate(tokens):
        if token[0] != "T_FUNCTION":
            continue
        following = index + 1
        while following < len(tokens) and (tokens[following][0] in IGNORED_TOKENS or "AMPERSAND" in tokens[following][0] or tokens[following][1] == "&"):
            following += 1
        name = tokens[following][1] if following < len(tokens) and tokens[following][0] == "T_STRING" else "<closure>"
        opening = following
        while opening < len(tokens) and not (tokens[opening][0] == "character" and tokens[opening][1] in ("{", ";")):
            opening += 1
        if opening == len(tokens) or tokens[opening][1] == ";":
            continue
        depth = 1
        closing = opening + 1
        while closing < len(tokens) and depth:
            kind, text, _line, _end = tokens[closing]
            if (kind == "character" and text == "{") or kind in ("T_CURLY_OPEN", "T_DOLLAR_OPEN_CURLY_BRACES"):
                depth += 1
            elif kind == "character" and text == "}":
                depth -= 1
            closing += 1
        if depth:
            raise QualityError("Native function tokens have no matching closing brace.")
        scopes.append((index, closing, "function:" + name))
    return scopes


def context(tokens: list, scopes: list, index: int) -> tuple[str, str]:
    """Bind a suppression to its containing callable, or to a top-level template. Since 0.5.6."""
    containing = [scope for scope in scopes if scope[0] <= index < scope[1]]
    if not containing:
        return "file", code_hash(tokens)
    start, end, name = min(containing, key=lambda scope: scope[1] - scope[0])
    return name, code_hash(tokens[start:end])


def parse_directive(comment: str, location: str) -> tuple[str, list[str], str] | None:
    """Accept only named narrow directives with reasons, rejecting bypass syntax. Since 0.5.6."""
    if re.search(r"@codingStandardsIgnore", comment, re.IGNORECASE):
        raise QualityError(location + ": legacy checker bypass annotations are prohibited.")
    matches = list(DIRECTIVE.finditer(comment))
    if not matches:
        return None
    if len(matches) != 1:
        raise QualityError(location + ": use one explicit PHPCS directive per comment.")
    action, tail = matches[0].groups()
    action = action.lower()
    if action not in ("ignore", "disable", "enable"):
        raise QualityError(location + ": unsupported or file-wide checker bypass: " + action)
    code_text, _separator, reason = tail.partition("--")
    code_text = code_text.strip().removesuffix("*/").strip()
    sniffs = [item.strip() for item in code_text.split(",")]
    if not sniffs or any(not SNIFF.fullmatch(item) for item in sniffs) or len(set(sniffs)) != len(sniffs):
        raise QualityError(location + ": unqualified, wildcard or duplicate PHPCS exclusions are prohibited.")
    if any(item.startswith("WordPress.Security.EscapeOutput") for item in sniffs):
        raise QualityError(location + ": production EscapeOutput suppressions are prohibited; escape at the output boundary.")
    reason = reason.strip().removesuffix("*/").strip()
    if action != "enable" and not reason:
        raise QualityError(location + ": a concrete inline exception reason is required.")
    return action, sorted(sniffs), reason


def annotations(path: str, tokens: list) -> list[dict]:
    """Resolve actual comment directives and pair every bounded disabled region. Since 0.5.6."""
    scopes = callable_scopes(tokens)
    result = []
    active = {}
    for index, token in enumerate(tokens):
        kind, text, line, end = token
        if kind not in ("T_COMMENT", "T_DOC_COMMENT"):
            continue
        location = f"{path}:{line}"
        directive = parse_directive(text, location)
        if not directive:
            continue
        action, sniffs, reason = directive
        name, contract_hash = context(tokens, scopes, index)
        if action == "ignore":
            before = [item for item in tokens[:index] if item[2] <= line <= item[3] and item[0] not in IGNORED_TOKENS]
            target = line if before else end + 1
            covered = [item for item in tokens if item[2] <= target <= item[3]]
            if not significant(covered):
                raise QualityError(location + ": ignore does not address a nonempty PHP code line.")
            result.append({"path": path, "action": "ignore", "sniffs": sniffs, "scope": name,
                           "code_sha256": code_hash(covered), "context_sha256": contract_hash,
                           "line": line, "reason": reason})
        elif action == "disable":
            if any(sniff in active for sniff in sniffs):
                raise QualityError(location + ": an already-disabled sniff cannot be widened or nested.")
            for sniff in sniffs:
                active[sniff] = (index, sniffs, reason, name, contract_hash)
        else:
            starts = [active.get(sniff) for sniff in sniffs]
            if not starts or any(start is None for start in starts) or len({start[0] for start in starts}) != 1 or starts[0][1] != sniffs:
                raise QualityError(location + ": enable must close the exact matching named disabled region.")
            opening, _codes, original_reason, original_name, original_hash = starts[0]
            if name != original_name or contract_hash != original_hash:
                raise QualityError(location + ": disabled regions cannot cross callable boundaries.")
            result.append({"path": path, "action": "disable-region", "sniffs": sniffs, "scope": name,
                           "code_sha256": code_hash(tokens[opening + 1:index]), "context_sha256": original_hash,
                           "line": tokens[opening][2], "reason": original_reason})
            for sniff in sniffs:
                del active[sniff]
    if active:
        raise QualityError(path + ": every disabled region needs its explicit matching enable.")
    return result


def xml_contract(root: Path, production: dict[str, str]) -> dict:
    """Inspect the effective explicit PHPCS ruleset, including its entire configuration. Since 0.5.6."""
    path = root / RULESET
    if path.is_symlink() or not path.is_file():
        raise QualityError("The explicit PHPCS ruleset must be a regular file.")
    tree = ET.fromstring(path.read_text(encoding="utf-8"))
    if not any(node.get("ref") == "WordPress" for node in tree.iter("rule")):
        raise QualityError("The complete WordPress ruleset must remain enabled.")
    checked_paths = []
    for node in tree.iter("file"):
        relative = (node.text or "").strip()
        target = (root / relative).resolve()
        if not relative or any(char in relative for char in "*?[") or root not in target.parents or not target.exists():
            raise QualityError("PHPCS file scopes must be explicit existing project files or directories: " + relative)
        checked_paths.append(target)
    for relative in production:
        target = (root / relative).resolve()
        if not any(target == checked or checked in target.parents for checked in checked_paths):
            raise QualityError("Production PHP is outside PHPCS's actual file scope: " + relative)
    exclusions = []

    def canonical(node: ET.Element, parent_rule: str = "") -> list:
        """Retain semantic XML, ignoring descriptive text, comments and indentation. Since 0.5.6."""
        rule = node.get("ref", parent_rule) if node.tag == "rule" else parent_rule
        if node.tag == "arg" and node.get("name") in ("exclude", "sniffs", "ignore"):
            raise QualityError("Ruleset command arguments cannot silently narrow checks or production scope.")
        if node.tag == "exclude":
            name = node.get("name", "")
            if not SNIFF.fullmatch(name) or name.startswith("WordPress.Security"):
                raise QualityError("Ruleset security, broad or wildcard exclusions are prohibited: " + name)
            exclusions.append({"rule": rule, "name": name})
        if node.tag == "exclude-pattern":
            raise QualityError("Ruleset path exclusions can silently remove production coverage and are prohibited.")
        if node.tag == "severity" and (node.text or "").strip() == "0":
            raise QualityError("Ruleset severity zero cannot hide findings.")
        children = [canonical(child, rule) for child in node if child.tag != "description"]
        text = (node.text or "").strip() if node.tag != "description" else ""
        return [node.tag, sorted(node.attrib.items()), text, children]

    semantic = canonical(tree)
    return {"path": RULESET, "sha256": hashlib.sha256(json.dumps(semantic, separators=(",", ":")).encode()).hexdigest(),
            "exclusions": sorted(exclusions, key=lambda item: (item["rule"], item["name"]))}


def observe(root: Path, php: str) -> dict:
    """Return current lexical exceptions without treating discovery as review. Since 0.5.6."""
    root = root.resolve()
    sources = production_sources(root)
    native = native_tokens(sources, php)
    tokenized = native["files"]
    found = [entry for path, tokens in tokenized.items() for entry in annotations(path, tokens)]
    return {"status": "unreviewed-observation", "production_php_files": len(sources), "php_version": native["php_version"],
            "annotations": found, "ruleset": xml_contract(root, sources)}


def identity(entry: dict) -> str:
    """Compare actual scope/code identities rather than editable line numbers. Since 0.5.6."""
    if set(entry) != set(IDENTITY_KEYS):
        raise QualityError("Inventory exceptions must describe exactly path, action, sniffs, scope, code/context hashes and reason.")
    return json.dumps(entry, sort_keys=True, separators=(",", ":"))


def nonempty(value: object) -> bool:
    """Validate only a nonempty text shape; the reviewer judges its substance. Since 0.5.6."""
    return isinstance(value, str) and bool(value.strip())


def evidence_paths(inventory: dict) -> set[str]:
    """Collect the exact referenced checks without treating their presence as proof. Since 0.5.6."""
    paths = set()
    for group in inventory.get("groups", {}).values():
        for proof in group.get("evidence", []):
            if not nonempty(proof.get("path")):
                raise QualityError("A referenced check needs its explicit project path.")
            paths.add(proof["path"])
    return paths


def evidence_contracts(root: Path, paths: set[str], php: str) -> dict:
    """Fingerprint real check contents, lexing PHP as data without running tests. Since 0.5.6."""
    root = root.resolve()
    contracts = {}
    php_sources = {}
    for relative in sorted(paths):
        candidate = root / relative
        if root not in candidate.resolve().parents or candidate.is_symlink() or not candidate.is_file():
            raise QualityError("Missing or nonlocal referenced check: " + relative)
        if candidate.suffix == ".php":
            php_sources[relative] = candidate.read_text(encoding="utf-8")
        else:
            contracts[relative] = {"method": "file-sha256", "sha256": hashlib.sha256(candidate.read_bytes()).hexdigest()}
    if php_sources:
        native = native_tokens(php_sources, php)
        for relative, tokens in native["files"].items():
            contracts[relative] = {"method": "php-code-sha256-v1", "sha256": code_hash(tokens)}
    return dict(sorted(contracts.items()))


def check_quality(root: Path, php: str | None = None) -> dict:
    """Reject unreviewed, stale, changed and prohibited exceptions; do not mutate. Since 0.5.6."""
    root = root.resolve()
    observed = observe(root, php_command(php))
    inventory_path = root / INVENTORY
    if inventory_path.is_symlink() or not inventory_path.is_file():
        raise QualityError("The reviewed exception inventory is missing or not a regular file.")
    inventory = json.loads(inventory_path.read_text(encoding="utf-8"))
    if inventory.get("version") != 1 or inventory.get("ruleset") != {key: observed["ruleset"][key] for key in ("path", "sha256")}:
        raise QualityError("The effective PHPCS ruleset changed or has no matching reviewed configuration.")
    expected = []
    expected_xml = []
    groups = inventory.get("groups")
    if not isinstance(groups, dict) or not groups:
        raise QualityError("A nonempty reviewed exception-group inventory is required.")
    for name, group in groups.items():
        if group.get("review_status") != "reviewed" or not nonempty(group.get("reason")):
            raise QualityError("Exception group needs its reason and a completed review: " + name)
        contracts = group.get("contracts", [])
        if not contracts or any(not isinstance(url, str) or not url.startswith("https://") for url in contracts):
            raise QualityError("Exception group needs its official contract reference: " + name)
        evidence = group.get("evidence", [])
        if not evidence:
            raise QualityError("Exception group needs specific compensating test coverage: " + name)
        for proof in evidence:
            candidate = root / proof.get("path", "")
            if root not in candidate.resolve().parents or candidate.is_symlink() or not candidate.is_file() or not nonempty(proof.get("covers")):
                raise QualityError("Missing or underspecified exception coverage in group: " + name)
        sites = group.get("annotations", [])
        xml_sites = group.get("ruleset_exclusions", [])
        if not sites and not xml_sites:
            raise QualityError("Stale exception group has no actual sites: " + name)
        expected.extend(identity(entry) for entry in sites)
        expected_xml.extend(json.dumps(entry, sort_keys=True) for entry in xml_sites)
    actual = Counter(identity({key: entry[key] for key in IDENTITY_KEYS}) for entry in observed["annotations"])
    reviewed = Counter(expected)
    if actual != reviewed:
        added, stale = actual - reviewed, reviewed - actual
        raise QualityError(f"PHPCS exception review changed: {sum(added.values())} new/changed scope(s), {sum(stale.values())} removed/stale scope(s). Use --report for current unreviewed locations.")
    actual_xml = Counter(json.dumps(entry, sort_keys=True) for entry in observed["ruleset"]["exclusions"])
    if actual_xml != Counter(expected_xml):
        raise QualityError("Ruleset exclusions differ from their exact reviewed inventory.")
    paths = evidence_paths(inventory)
    bound = inventory.get("evidence_hashes")
    if not isinstance(bound, dict) or set(bound) != paths:
        raise QualityError("Referenced checks need an exact content-bound inventory; missing or stale evidence hashes require review.")
    for relative, expected_contract in bound.items():
        if not isinstance(expected_contract, dict) or set(expected_contract) != {"method", "sha256"} or not isinstance(expected_contract.get("sha256"), str) or not re.fullmatch(r"[0-9a-f]{64}", expected_contract["sha256"]):
            raise QualityError("Referenced check has an unreviewed or unfilled content hash: " + relative)
    current_contracts = evidence_contracts(root, paths, php_command(php))
    changed = [path for path in sorted(paths) if bound[path] != current_contracts[path]]
    if changed:
        raise QualityError("Referenced check contents changed and need review: " + ", ".join(changed))
    return {"status": "pass", "production_php_files": observed["production_php_files"],
            "php_version": observed["php_version"], "inventory_sha256": hashlib.sha256(inventory_path.read_bytes()).hexdigest(),
            "ruleset_sha256": observed["ruleset"]["sha256"],
            "reviewed_annotations": len(observed["annotations"]), "reviewed_ruleset_exclusions": sum(actual_xml.values()),
            "content_bound_evidence_files": len(paths),
            "escape_output_suppressions": 0,
            "scope": "Configured PHPCS exception boundaries and content hashes of referenced checks; their behavior is not executed here."}


def main() -> int:
    """Expose checking and explicitly unreviewed discovery; offer no auto-baseline. Since 0.5.6."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--php", help="Native PHP executable or isolated wrapper; defaults to RKTSKY_ANTISPAM_PHP or PATH.")
    parser.add_argument("--report", action="store_true", help="Print current unreviewed observations without modifying the inventory.")
    arguments = parser.parse_args()
    try:
        if arguments.report:
            result = observe(arguments.root.resolve(), php_command(arguments.php))
            inventory_path = arguments.root / INVENTORY
            if inventory_path.is_file() and not inventory_path.is_symlink():
                inventory = json.loads(inventory_path.read_text(encoding="utf-8"))
                result["evidence_hashes"] = evidence_contracts(arguments.root, evidence_paths(inventory), php_command(arguments.php))
        else:
            result = check_quality(arguments.root, arguments.php)
        print(json.dumps(result, indent=2))
        return 0
    except (QualityError, OSError, ET.ParseError, json.JSONDecodeError) as error:
        print("Quality exceptions: " + str(error), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
