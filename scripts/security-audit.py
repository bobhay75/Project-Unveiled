#!/usr/bin/env python3
"""Fail closed on high-confidence secrets and unsafe workflow privileges."""

import argparse
import os
import re
import subprocess
import sys
from pathlib import Path
from typing import Dict, Iterable, List, Set, Tuple


ROOT = Path(__file__).resolve().parents[1]
MAX_BLOB_BYTES = 5 * 1024 * 1024
SECRET_PATTERNS = (
    ("private key", re.compile(br"-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----")),
    ("OpenAI API key", re.compile(br"\bsk-(?:proj-|svcacct-)?[A-Za-z0-9_-]{20,}\b")),
    ("GitHub token", re.compile(br"\bgh[pousr]_[A-Za-z0-9_]{30,}\b")),
    ("AWS access key", re.compile(br"\b(?:AKIA|ASIA)[A-Z0-9]{16}\b")),
    ("Google API key", re.compile(br"\bAIza[0-9A-Za-z_-]{35}\b")),
    ("Slack token", re.compile(br"\bxox[baprs]-[A-Za-z0-9-]{10,}\b")),
    ("Stripe live secret", re.compile(br"\b(?:sk|rk)_live_[A-Za-z0-9]{16,}\b")),
)
FORBIDDEN_NAMES = {
    ".env",
    "credentials.json",
    "error_log",
    "id_dsa",
    "id_ed25519",
    "id_rsa",
    "service-account.json",
}
FORBIDDEN_SUFFIXES = {".bak", ".key", ".log", ".old", ".p12", ".pem", ".pfx", ".pyc", ".pyo", ".sql", ".zip"}
FORBIDDEN_DIRS = {"__pycache__", "site-private"}


def git_output(arguments: List[str]) -> bytes:
    return subprocess.check_output(["git"] + arguments, cwd=str(ROOT), stderr=subprocess.STDOUT)


def working_files() -> Iterable[Tuple[str, bytes]]:
    output = git_output(["ls-files", "--cached", "--others", "--exclude-standard", "-z"])
    for encoded in output.split(b"\0"):
        if not encoded:
            continue
        relative = encoded.decode("utf-8")
        path = ROOT / relative
        if path.is_symlink() or not path.is_file() or path.stat().st_size > MAX_BLOB_BYTES:
            continue
        yield relative, path.read_bytes()


def history_blobs() -> Iterable[Tuple[str, str, bytes]]:
    objects: Dict[str, str] = {}
    for line in git_output(["rev-list", "--objects", "--all"]).decode("utf-8", "replace").splitlines():
        object_id, _, path = line.partition(" ")
        objects.setdefault(object_id, path or "unknown path")

    process = subprocess.Popen(
        ["git", "cat-file", "--batch-check=%(objectname) %(objecttype) %(objectsize)"],
        cwd=str(ROOT),
        stdin=subprocess.PIPE,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
    )
    request = "".join("{}\n".format(object_id) for object_id in objects).encode("ascii")
    stdout, stderr = process.communicate(request)
    if process.returncode != 0:
        raise RuntimeError("git cat-file metadata scan failed: {}".format(stderr.decode("utf-8", "replace")))

    for line in stdout.decode("ascii", "replace").splitlines():
        object_id, object_type, size_text = line.split(" ", 2)
        size = int(size_text)
        if object_type != "blob" or size > MAX_BLOB_BYTES:
            continue
        data = git_output(["cat-file", "blob", object_id])
        yield object_id, objects.get(object_id, "unknown path"), data


def matched_rules(data: bytes) -> Iterable[str]:
    if b"\0" in data[:8192]:
        return []
    return [name for name, pattern in SECRET_PATTERNS if pattern.search(data)]


def audit_paths(errors: List[str]) -> int:
    output = git_output(["ls-files", "--cached", "--others", "--exclude-standard", "-z"])
    count = 0
    for encoded in output.split(b"\0"):
        if not encoded:
            continue
        count += 1
        relative = encoded.decode("utf-8")
        path = ROOT / relative
        lower_parts = {part.lower() for part in Path(relative).parts}
        lower_name = path.name.lower()
        if lower_parts & FORBIDDEN_DIRS:
            errors.append("forbidden private directory is tracked or pending: {}".format(relative))
        if lower_name in FORBIDDEN_NAMES or path.suffix.lower() in FORBIDDEN_SUFFIXES:
            errors.append("forbidden secret/deployment artifact is tracked or pending: {}".format(relative))
        if path.is_symlink():
            errors.append("symbolic links are not allowed in the public repository: {}".format(relative))
    return count


def audit_workflows(errors: List[str]) -> int:
    workflow_dir = ROOT / ".github" / "workflows"
    count = 0
    for path in sorted(list(workflow_dir.glob("*.yml")) + list(workflow_dir.glob("*.yaml"))):
        count += 1
        text = path.read_text(encoding="utf-8")
        relative = str(path.relative_to(ROOT))
        if re.search(r"(?m)^\s*pull_request_target\s*:", text):
            errors.append("pull_request_target is forbidden in {}".format(relative))
        if re.search(r"(?m)^\s*permissions\s*:\s*write-all\s*$", text):
            errors.append("write-all workflow permission is forbidden in {}".format(relative))
        if re.search(r"(?m)^\s+[A-Za-z-]+\s*:\s*write\s*$", text):
            errors.append("write workflow permission requires explicit review in {}".format(relative))
        if re.search(r"(?m)^\s*run\s*:.*\$\{\{\s*github\.event\.", text):
            errors.append("untrusted event data is interpolated directly into a shell command in {}".format(relative))
        for line_number, match in enumerate(text.splitlines(), 1):
            action = re.search(r"\buses:\s*([^\s#]+)", match)
            if not action or action.group(1).startswith("./"):
                continue
            reference = action.group(1).rsplit("@", 1)[-1]
            if not re.fullmatch(r"[0-9a-fA-F]{40}", reference):
                errors.append(
                    "third-party action is not pinned to a full commit SHA in {}:{}".format(relative, line_number)
                )
        if "actions/checkout@" in text and "persist-credentials: false" not in text:
            errors.append("checkout credentials are persisted in {}".format(relative))
    return count


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--history", action="store_true", help="also scan every reachable Git blob")
    args = parser.parse_args()
    errors: List[str] = []
    files_checked = audit_paths(errors)
    workflows_checked = audit_workflows(errors)

    for relative, data in working_files():
        for rule in matched_rules(data):
            errors.append("{} detected in working file {} (value suppressed)".format(rule, relative))

    history_checked = 0
    if args.history:
        try:
            for object_id, path, data in history_blobs():
                history_checked += 1
                for rule in matched_rules(data):
                    errors.append(
                        "{} detected in history blob {} at {} (value suppressed)".format(
                            rule, object_id[:12], path
                        )
                    )
        except (OSError, subprocess.CalledProcessError, RuntimeError) as exc:
            errors.append("history secret scan could not run: {}".format(exc))

    if errors:
        print("Security audit failed:")
        for item in sorted(set(errors)):
            print("- {}".format(item))
        return 1

    print(
        "Security audit passed: {} files, {} workflows, {} history blobs; secret values never printed.".format(
            files_checked, workflows_checked, history_checked
        )
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
