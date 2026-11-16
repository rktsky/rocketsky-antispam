#!/usr/bin/env python3
"""Exercise exception drift and bypass rejection in disposable source copies. Since 0.5.6."""

from __future__ import annotations

import argparse
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

from quality_exceptions import check_quality, observe, php_command, QualityError


SOURCE = Path(__file__).resolve().parents[1]
PHP = None


class QualityGuardTest(unittest.TestCase):
    """Change real exception boundaries without touching the working checkout. Since 0.5.6."""

    def setUp(self) -> None:
        """Copy production sources and test references into one isolated project. Since 0.5.6."""
        self.temporary = tempfile.TemporaryDirectory(prefix="rocketsky-quality-guard-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name) / "source"
        self.root.mkdir()
        for directory in ("src", "templates", "assets", "languages"):
            shutil.copytree(SOURCE / directory, self.root / directory)
        for filename in ("rocketsky-antispam.php", "uninstall.php", "readme.txt", "LICENSE", "phpcs.xml.dist"):
            shutil.copy2(SOURCE / filename, self.root / filename)
        self.inventory_path = self.root / "tools/quality-exceptions.json"
        self.inventory_path.parent.mkdir()
        self.inventory = json.loads((SOURCE / "tools/quality-exceptions.json").read_text())
        # Review flags here are synthetic fixture state, never an approval of the real inventory.
        for group in self.inventory["groups"].values():
            group["review_status"] = "reviewed"
            for evidence in group["evidence"]:
                destination = self.root / evidence["path"]
                destination.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(SOURCE / evidence["path"], destination)
        self.write_inventory()

    def write_inventory(self) -> None:
        """Persist only the disposable fixture's inventory. Since 0.5.6."""
        self.inventory_path.write_text(json.dumps(self.inventory), encoding="utf-8")

    def replace(self, path: str, before: str, after: str) -> None:
        """Apply an asserted single mutation so a stale fixture cannot pass. Since 0.5.6."""
        target = self.root / path
        source = target.read_text(encoding="utf-8")
        self.assertIn(before, source)
        target.write_text(source.replace(before, after, 1), encoding="utf-8")

    def rejects(self, message: str) -> None:
        """Require a specific guard failure rather than any incidental exception. Since 0.5.6."""
        with self.assertRaisesRegex(QualityError, message):
            check_quality(self.root, PHP)

    def test_matching_inventory(self) -> None:
        """An unchanged reviewed fixture reports actual inspected scope and zero escaping exclusions. Since 0.5.6."""
        result = check_quality(self.root, PHP)
        self.assertEqual("pass", result["status"])
        self.assertEqual(0, result["escape_output_suppressions"])
        self.assertGreater(result["reviewed_annotations"], 0)
        self.assertIn("structurally", result["scope"])

    def test_discovery_does_not_approve_or_write(self) -> None:
        """Discovery identifies itself as unreviewed and leaves its input bytes untouched. Since 0.5.6."""
        before = self.inventory_path.read_bytes()
        self.assertEqual("unreviewed-observation", observe(self.root, PHP)["status"])
        self.assertEqual(before, self.inventory_path.read_bytes())

    def test_pending_review_blocks(self) -> None:
        """A discovered baseline cannot become a release gate without its substantive review. Since 0.5.6."""
        next(iter(self.inventory["groups"].values()))["review_status"] = "pending"
        self.write_inventory()
        self.rejects("completed review")

    def test_new_annotation(self) -> None:
        """An additional named exception is found through recursive production discovery. Since 0.5.6."""
        (self.root / "src/QualityFixture.php").write_text("<?php\n$value = $_GET['value']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic mutation.\n")
        self.rejects("new/changed scope")

    def test_removed_annotation_leaves_stale_review(self) -> None:
        """Deleting an obsolete exception requires removing its now-stale inventory entry too. Since 0.5.6."""
        self.replace("src/Admin.php", "// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted navigation.", "// Ordinary explanation.")
        self.rejects("removed/stale scope")

    def test_changed_code_contract(self) -> None:
        """Changing a bounded native query invalidates its exact reviewed contract. Since 0.5.6."""
        self.replace("src/Store.php", "LIMIT 4096", "LIMIT 4095")
        self.rejects("new/changed scope")

    def test_widened_disabled_region(self) -> None:
        """Moving an enable past another real statement invalidates the region hash. Since 0.5.6."""
        enable = "\t\t// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase"
        statement = "\t\tadd_action( 'phpmailer_init', $configure, PHP_INT_MAX );"
        self.replace("src/Digest.php", enable + "\n" + statement, statement + "\n" + enable)
        self.rejects("new/changed scope")

    def test_changed_exception_reason(self) -> None:
        """Changing a directive's justification also requires review, unlike ordinary comments. Since 0.5.6."""
        self.replace("src/Admin.php", "-- Read-only allowlisted navigation.", "-- A changed claimed exception contract.")
        self.rejects("new/changed scope")

    def test_benign_comments_and_formatting(self) -> None:
        """Ordinary comments and PHP spacing neither change code hashes nor depend on line numbers. Since 0.5.6."""
        self.replace("src/Admin.php", "namespace Rktsky\\Antispam;", "// Extra explanation before the same code.\nnamespace   Rktsky\\Antispam;")
        self.replace("phpcs.xml.dist", "<ruleset name=", "<!-- Additional non-operative explanation. -->\n<ruleset name=")
        self.assertEqual("pass", check_quality(self.root, PHP)["status"])

    def test_string_literals_are_not_directives(self) -> None:
        """Native tokenization does not treat PHPCS-looking text inside a string as a bypass. Since 0.5.6."""
        (self.root / "src/QualityFixture.php").write_text("<?php\n$value = 'phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped';\n")
        self.assertEqual("pass", check_quality(self.root, PHP)["status"])

    def test_documentary_comment_is_not_a_directive(self) -> None:
        """A descriptive mention outside the PHPCS directive position does not disable checks. Since 0.5.6."""
        (self.root / "src/QualityFixture.php").write_text("<?php\n// Documentation mentions phpcs:ignore without applying it.\n$value = 1;\n")
        self.assertEqual("pass", check_quality(self.root, PHP)["status"])

    def test_ignore_all_wildcard_and_escaping_are_prohibited(self) -> None:
        """Dangerous bypass forms cannot be approved by adding inventory records. Since 0.5.6."""
        for directive in ("ignore -- All checks", "disable -- All checks", "ignore WordPress.Security.* -- Wildcard", "ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Hidden sink"):
            with self.subTest(directive=directive):
                (self.root / "src/QualityFixture.php").write_text("<?php\n$value = 1; // phpcs:" + directive + "\n")
                self.rejects("prohibited")

    def test_unclosed_disabled_region(self) -> None:
        """An open-ended suppression cannot expand through the rest of a file. Since 0.5.6."""
        self.replace("src/Digest.php", "// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase", "// Closing annotation was removed.")
        self.rejects("already-disabled|matching enable")

    def test_new_ruleset_exception(self) -> None:
        """A newly excluded formatting rule still changes the reviewed effective configuration. Since 0.5.6."""
        self.replace("phpcs.xml.dist", "</ruleset>", '<rule ref="Generic.WhiteSpace.DisallowTabIndent"/>\n</ruleset>')
        self.rejects("ruleset changed")

    def test_security_ruleset_bypass(self) -> None:
        """Security exclusions and command-argument filters fail before inventory matching. Since 0.5.6."""
        original = (self.root / "phpcs.xml.dist").read_text()
        for bypass in ('<rule ref="WordPress"><exclude name="WordPress.Security.EscapeOutput.OutputNotEscaped"/></rule>', '<arg name="exclude" value="WordPress.Security.EscapeOutput"/>'):
            with self.subTest(bypass=bypass):
                (self.root / "phpcs.xml.dist").write_text(original.replace("</ruleset>", bypass + "\n</ruleset>"))
                self.rejects("prohibited|silently narrow")

    def test_discovered_php_must_have_actual_phpcs_coverage(self) -> None:
        """A new language/asset PHP consumer names the missing lint scope rather than escaping it. Since 0.5.6."""
        (self.root / "assets/uncovered.php").write_text("<?php\nreturn 'synthetic';\n")
        self.rejects("outside PHPCS.*assets/uncovered.php")

    def test_missing_compensating_test(self) -> None:
        """An exception cannot retain a reference to a deleted compensating check. Since 0.5.6."""
        next(iter(self.inventory["groups"].values()))["evidence"][0]["path"] = "tests/nonexistent.php"
        self.write_inventory()
        self.rejects("Missing or underspecified")

    def test_builder_rejects_clean_but_unreviewed_candidate(self) -> None:
        """The immutable builder blocks exception drift before it writes any archive. Since 0.5.6."""
        (self.root / "src/QualityFixture.php").write_text("<?php\n$value = $_GET['value']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic mutation.\n")
        subprocess.run(["git", "init", "-q", str(self.root)], check=True, capture_output=True)
        subprocess.run(["git", "-C", str(self.root), "add", "."], check=True, capture_output=True)
        subprocess.run(["git", "-C", str(self.root), "-c", "user.name=Synthetic Guard Fixture", "-c", "user.email=fixture@example.invalid", "commit", "-qm", "Synthetic source candidate"], check=True, capture_output=True)
        spec = importlib.util.spec_from_file_location("quality_fixture_builder", SOURCE / "tools/build-release.py")
        builder = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(builder)
        output = Path(self.temporary.name) / "artifacts"
        with self.assertRaisesRegex(QualityError, "new/changed scope"):
            builder.ReleaseBuilder(self.root, "wordpress-org", output, PHP)
        self.assertFalse(output.exists())


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__, add_help=False)
    parser.add_argument("--php")
    arguments, remaining = parser.parse_known_args()
    PHP = php_command(arguments.php)
    unittest.main(argv=[__file__, *remaining], verbosity=2)
