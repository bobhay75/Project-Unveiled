#!/usr/bin/env python3
"""Validate the explicit, non-destructive cPanel deployment contract."""

import argparse
import re
import subprocess
import sys
from pathlib import Path
from typing import Iterable, List, Optional, Set


ROOT = Path(__file__).resolve().parents[1]
RETIRED_FILE = ROOT / "deployment" / "retired-public-paths.txt"
PUBLIC_MANIFEST = ROOT / "deployment" / "public-files.txt"

INTERNAL_DIRS = {".git", ".github", "deployment", "docs", "scripts", "tests"}
INTERNAL_ROOT_FILES = {
    ".cpanel.yml",
    ".gitignore",
    "AGENTS.md",
    "CHANGELOG.md",
    "DEPLOYMENT.md",
    "INSTALL.md",
    "README.md",
    "SOURCE_NOTES.md",
    "VALIDATION.md",
}
INTERNAL_SUFFIXES = {".md", ".py", ".yml", ".yaml"}
PRIVATE_SUFFIXES = {".bak", ".key", ".log", ".old", ".p12", ".pem", ".pfx", ".pyc", ".pyo", ".sql", ".zip"}


def error(message: str, errors: List[str]) -> None:
    errors.append(message)


def normalized_relative_path(raw: str) -> bool:
    if not raw or raw.startswith("/") or raw.endswith("/") or "//" in raw or "\\" in raw:
        return False
    if any(char in raw for char in "*?[]"):
        return False
    return all(part not in {"", ".", ".."} for part in raw.split("/"))


def load_retired_paths(errors: List[str]) -> Set[str]:
    if not RETIRED_FILE.is_file() or RETIRED_FILE.is_symlink():
        error("retired-path registry is missing or is a symlink", errors)
        return set()

    retired: Set[str] = set()
    for line_number, raw in enumerate(RETIRED_FILE.read_text(encoding="utf-8").splitlines(), 1):
        path = raw.rstrip("\r")
        if not path or path.startswith("#"):
            continue
        if not normalized_relative_path(path):
            error("invalid retired path on line {}: {}".format(line_number, path), errors)
            continue
        if path in retired:
            error("duplicate retired path on line {}: {}".format(line_number, path), errors)
            continue
        retired.add(path)
        candidate = ROOT / path
        if candidate.exists() or candidate.is_symlink():
            error("retired path still exists in the repository: {}".format(path), errors)
    return retired


def is_public_file(path: str) -> bool:
    parts = Path(path).parts
    if not parts or parts[0].lower() in INTERNAL_DIRS or path in INTERNAL_ROOT_FILES:
        return False
    lower_parts = {part.lower() for part in parts}
    name = parts[-1]
    lower_name = name.lower()
    if "site-private" in lower_parts or "__pycache__" in lower_parts:
        return False
    if lower_name in {
        ".ds_store",
        ".env",
        ".gitattributes",
        ".gitignore",
        ".gitmodules",
        ".cpanel.yml",
        "credentials.json",
        "error_log",
        "id_dsa",
        "id_ed25519",
        "id_rsa",
        "service-account.json",
        "thumbs.db",
    } or lower_name.startswith(".env."):
        return False
    if lower_name.startswith("readme") or Path(lower_name).suffix in INTERNAL_SUFFIXES | PRIVATE_SUFFIXES:
        return False
    if "backup" in lower_name and lower_name.endswith(".html"):
        return False
    return True


def was_public_file(path: str) -> bool:
    """Approximate the former cPanel policy conservatively for deletion checks."""
    parts = Path(path).parts
    if not parts or parts[0].lower() in INTERNAL_DIRS or path in INTERNAL_ROOT_FILES:
        return False
    name = parts[-1]
    if name.startswith("README") or Path(name).suffix.lower() in INTERNAL_SUFFIXES:
        return False
    return True


def expected_public_files() -> List[str]:
    output = subprocess.check_output(
        ["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=str(ROOT)
    )
    paths: List[str] = []
    for item in output.split(b"\0"):
        if not item:
            continue
        path = item.decode("utf-8")
        candidate = ROOT / path
        if is_public_file(path) and candidate.is_file() and not candidate.is_symlink():
            paths.append(path)
    return sorted(set(paths), key=lambda item: item.encode("utf-8"))


def validate_public_manifest(expected: List[str], errors: List[str]) -> Set[str]:
    if not PUBLIC_MANIFEST.is_file() or PUBLIC_MANIFEST.is_symlink():
        error("public-file manifest is missing or is a symlink", errors)
        return set()

    lines = PUBLIC_MANIFEST.read_text(encoding="utf-8").splitlines()
    manifested: Set[str] = set()
    for line_number, path in enumerate(lines, 1):
        if not normalized_relative_path(path):
            error("invalid public-file manifest path on line {}: {}".format(line_number, path), errors)
            continue
        if not is_public_file(path):
            error("internal/private path appears in public-file manifest: {}".format(path), errors)
        if path in manifested:
            error("duplicate public-file manifest path: {}".format(path), errors)
        manifested.add(path)

    if lines != sorted(lines, key=lambda item: item.encode("utf-8")):
        error("public-file manifest is not byte-order sorted", errors)

    expected_set = set(expected)
    for path in sorted(expected_set - manifested)[:20]:
        error("public Git path is missing from manifest: {}".format(path), errors)
    for path in sorted(manifested - expected_set)[:20]:
        error("untracked, missing, or internal path appears in manifest: {}".format(path), errors)
    return manifested


def deleted_paths(base: Optional[str], errors: List[str]) -> Iterable[str]:
    if not base:
        return []
    if not re.fullmatch(r"[0-9a-fA-F]{40}", base) or set(base) == {"0"}:
        if set(base) == {"0"}:
            return []
        error("deployment comparison SHA is not a 40-character Git SHA", errors)
        return []
    try:
        subprocess.check_output(
            ["git", "cat-file", "-e", "{}^{{commit}}".format(base)], cwd=str(ROOT), stderr=subprocess.STDOUT
        )
        output = subprocess.check_output(
            [
                "git",
                "diff",
                "--no-renames",
                "--diff-filter=D",
                "--name-only",
                "-z",
                "{}...HEAD".format(base),
            ],
            cwd=str(ROOT),
        )
    except subprocess.CalledProcessError as exc:
        error("could not compare deployment paths with {}: {}".format(base, exc), errors)
        return []
    return [item.decode("utf-8") for item in output.split(b"\0") if item]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", help="base commit used to detect removed public files")
    parser.add_argument("--print-manifest", action="store_true", help="print the reproducible public-file manifest")
    parser.add_argument("--write-manifest", action="store_true", help="rewrite the reproducible public-file manifest")
    args = parser.parse_args()
    errors: List[str] = []

    expected_public = expected_public_files()
    if args.print_manifest:
        print("\n".join(expected_public))
        return 0
    if args.write_manifest:
        PUBLIC_MANIFEST.parent.mkdir(parents=True, exist_ok=True)
        PUBLIC_MANIFEST.write_text("\n".join(expected_public) + "\n", encoding="utf-8")
        print("Wrote {} public paths to {}.".format(len(expected_public), PUBLIC_MANIFEST.relative_to(ROOT)))
        return 0

    retired = load_retired_paths(errors)
    manifested = validate_public_manifest(expected_public, errors)

    cpanel = (ROOT / ".cpanel.yml").read_text(encoding="utf-8")
    expected_task = '/bin/bash ./scripts/deploy-public.sh "$DEPLOYPATH"'
    if expected_task not in cpanel:
        error(".cpanel.yml does not delegate to the guarded deployment script", errors)
    if "/usr/bin/rsync" in cpanel:
        error(".cpanel.yml bypasses the guarded deployment script with direct rsync", errors)

    deploy_script = (ROOT / "scripts" / "deploy-public.sh").read_text(encoding="utf-8")
    if re.search(r"--delete(?:\s|[-=]|$)", deploy_script):
        error("broad rsync deletion is forbidden; use the retired-path registry", errors)
    if (
        "deployment/public-files.txt" not in deploy_script
        or "--files-from=\"$public_manifest\"" not in deploy_script
        or "--no-implied-dirs" not in deploy_script
        or "--prune-empty-dirs" not in deploy_script
    ):
        error("deployment must copy only the validated public-file manifest", errors)

    for path in deleted_paths(args.base, errors):
        if was_public_file(path) and path not in retired:
            error("removed public file is not registered for retirement: {}".format(path), errors)

    if errors:
        print("Deployment safety validation failed:")
        for item in errors:
            print("- {}".format(item))
        return 1

    print(
        "Deployment safety validation passed: {} public files, {} explicit retired paths; broad deletion disabled.".format(
            len(manifested), len(retired)
        )
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
