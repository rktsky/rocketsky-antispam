#!/usr/bin/env python3
"""Build reproducible installable releases from committed owned source. Since 0.5.2."""

from __future__ import annotations

import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import time
import zipfile


class ReleaseBuilder:
	"""Keep directory and GitHub packages auditable without editing source. Since 0.5.2."""

	SLUG = "rocketsky-antispam"
	ROOT_FILES = frozenset({"rocketsky-antispam.php", "uninstall.php", "readme.txt", "LICENSE"})
	DIRECTORIES = frozenset({"src", "templates", "assets", "languages"})
	UPDATE_HEADER = b" * Update URI: https://github.com/rktsky/rocketsky-antispam\n"

	def __init__(self, root: Path, channel: str, output: Path) -> None:
		"""Bind one clean checkout and an external artifact directory. Since 0.5.2."""
		self.root = root.resolve()
		self.channel = channel
		self.output = output.resolve()
		if self.output == self.root or self.root in self.output.parents:
			raise ValueError("Artifacts must be outside the source checkout.")
		if self.git("status", "--porcelain").strip():
			raise ValueError("Commit the reviewed source before building a release.")
		self.commit = self.git("rev-parse", "HEAD").decode().strip()
		self.production_commit = self.git("log", "-1", "--format=%H", self.commit, "--", *sorted(self.ROOT_FILES), *sorted(self.DIRECTORIES)).decode().strip()

	def git(self, *arguments: str) -> bytes:
		"""Read Git using structured arguments without a shell. Since 0.5.2."""
		return subprocess.check_output(["git", "-C", str(self.root), *arguments])

	def files(self) -> dict[str, bytes]:
		"""Read only tracked regular production blobs from the exact commit. Since 0.5.2."""
		result = {}
		entries = self.git("ls-tree", "-r", "-z", self.commit).split(b"\0")
		for entry in filter(None, entries):
			metadata, raw_name = entry.split(b"\t", 1)
			mode, kind, _digest = metadata.split()
			name = raw_name.decode("utf-8")
			path = PurePosixPath(name)
			if path.parts[0] not in self.DIRECTORIES and name not in self.ROOT_FILES:
				continue
			if kind != b"blob" or mode != b"100644" or path.is_absolute() or ".." in path.parts or "\\" in name:
				raise ValueError("Production entries must be ordinary tracked files: " + name)
			result[name] = self.git("show", self.commit + ":" + name)
		if not self.ROOT_FILES.issubset(result):
			raise ValueError("The plugin entrypoint, uninstall, readme and licence are required.")
		for locale in ("de_CH", "de_DE", "fr_FR", "it_IT", "es_ES"):
			for extension in ("po", "mo"):
				if f"languages/{self.SLUG}-{locale}.{extension}" not in result:
					raise ValueError("A bundled language source or compiled catalogue is missing.")
		return dict(sorted(result.items()))

	def archive(self, files: dict[str, bytes]) -> bytes:
		"""Create deterministic ZIP bytes and verify their complete readback. Since 0.5.2."""
		epoch = max(315532800, int(self.git("show", "-s", "--format=%ct", self.production_commit)))
		stamp = time.gmtime(epoch)[:6]
		stream = io.BytesIO()
		with zipfile.ZipFile(stream, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
			for name, content in files.items():
				entry = zipfile.ZipInfo(self.SLUG + "/" + name, stamp)
				entry.create_system = 3
				entry.external_attr = 0o100644 << 16
				entry.compress_type = zipfile.ZIP_DEFLATED
				archive.writestr(entry, content, compresslevel=9)
		payload = stream.getvalue()
		with zipfile.ZipFile(io.BytesIO(payload)) as archive:
			if archive.testzip() is not None or len(archive.namelist()) != len(files):
				raise ValueError("The built archive did not pass CRC and inventory verification.")
			for name, content in files.items():
				if archive.read(self.SLUG + "/" + name) != content:
					raise ValueError("Archive readback differs from the intended source: " + name)
		return payload

	def build(self) -> dict:
		"""Validate metadata and write one immutable ZIP, manifest and checksum. Since 0.5.2."""
		files = self.files()
		main = files["rocketsky-antispam.php"]
		version_match = re.search(rb"^ \* Version: (\d+\.\d+\.\d+)\s*$", main, re.M)
		if not version_match:
			raise ValueError("A numeric plugin release version is required.")
		version = version_match.group(1).decode()
		if ("Stable tag: " + version + "\n").encode() not in files["readme.txt"]:
			raise ValueError("Readme stable tag and plugin version must match.")
		if ("define( 'RKTSKY_ANTISPAM_VERSION', '" + version + "' );").encode() not in main:
			raise ValueError("Runtime version and plugin header must match.")
		if b" * Text Domain: rocketsky-antispam\n" not in main or main.count(self.UPDATE_HEADER) != 1:
			raise ValueError("The reviewed text domain and GitHub source update identity are required.")
		if self.channel == "wordpress-org":
			files["rocketsky-antispam.php"] = main.replace(self.UPDATE_HEADER, b"", 1)
		payload = self.archive(files)
		if payload != self.archive(files):
			raise ValueError("Repeated archive generation was not reproducible.")
		digest = hashlib.sha256(payload).hexdigest()
		name = f"{self.SLUG}-{version}-{self.channel}"
		manifest = {
			"plugin": "Rocketsky Antispam", "slug": self.SLUG, "version": version,
			"channel": self.channel, "source_commit": self.commit, "production_commit": self.production_commit,
			"archive": name + ".zip", "bytes": len(payload), "sha256": digest,
			"transformations": ["Remove GitHub-specific Update URI header"] if self.channel == "wordpress-org" else [],
			"files": {self.SLUG + "/" + path: hashlib.sha256(content).hexdigest() for path, content in files.items()},
		}
		outputs = {
			name + ".zip": payload,
			name + ".manifest.json": (json.dumps(manifest, indent=2, ensure_ascii=False) + "\n").encode(),
			name + ".sha256": (digest + "  " + name + ".zip\n").encode(),
		}
		self.output.mkdir(parents=True, exist_ok=True)
		for filename, content in outputs.items():
			path = self.output / filename
			if path.is_symlink() or (path.exists() and path.read_bytes() != content):
				raise ValueError("Refusing to overwrite a different existing release artifact: " + filename)
		for filename, content in outputs.items():
			path = self.output / filename
			if not path.exists():
				with path.open("xb") as target:
					target.write(content)
		return {"archive": str(self.output / (name + ".zip")), "sha256": digest, "files": len(files), "source_commit": self.commit}


def main() -> None:
	"""Parse explicit release-channel and output choices; never publish. Since 0.5.2."""
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("--channel", required=True, choices=("wordpress-org", "github"))
	parser.add_argument("--output", required=True, type=Path)
	arguments = parser.parse_args()
	builder = ReleaseBuilder(Path(__file__).resolve().parents[1], arguments.channel, arguments.output)
	print(json.dumps(builder.build(), indent=2))


if __name__ == "__main__":
	main()
