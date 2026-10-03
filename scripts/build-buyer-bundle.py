#!/usr/bin/env python3
"""Build a deterministic PRIVATE four-component delivery ZIP. Never approves it."""
from __future__ import annotations
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import zipfile

PRODUCT = 'project-unveiled-digital-edition'
ENTRYPOINTS = {
    'illustrated-ebook': 'Project_Unveiled_Illustrated_Ebook.pdf',
    'timeline': 'index.html',
    'deep-study-guide': 'Deep_Study_Guide.pdf',
    'no-more-milk': 'No_More_Milk.pdf',
}
EXTENSIONS = {'.pdf', '.epub', '.html', '.css', '.js', '.json', '.jpg', '.jpeg', '.png', '.svg', '.webp', '.gif', '.woff2', '.txt', '.md'}
REPO = Path(__file__).resolve().parents[1]
MAX_FILE = 50 * 1024 * 1024
MAX_TOTAL = 100 * 1024 * 1024

def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

def canonical(data: object) -> bytes:
    return (json.dumps(data, sort_keys=True, indent=2, ensure_ascii=True) + '\n').encode()

def build(source: Path, destination: Path, release_id: str) -> dict:
    source, destination = source.resolve(), destination.resolve()
    if source.is_relative_to(REPO) or destination.is_relative_to(REPO):
        raise ValueError('Paid input and output must stay outside the Git repository/public deployment tree')
    if destination.is_relative_to(source):
        raise ValueError('Output must be outside the component source directory')
    if not re.fullmatch(r'[a-z0-9][a-z0-9._-]{0,79}', release_id):
        raise ValueError('Invalid release_id')
    if destination.suffix != '.zip':
        raise ValueError('Destination must be .zip')
    if not source.is_dir() or {p.name for p in source.iterdir()} != set(ENTRYPOINTS):
        raise ValueError('Source must contain exactly the four component directories')
    manifest = {'schema': 1, 'product': PRODUCT, 'release_id': release_id, 'components': []}
    payload = {}
    total = 0
    folded = set()
    for component, entry in ENTRYPOINTS.items():
        directory = source / component
        if directory.is_symlink() or not directory.is_dir():
            raise ValueError('Component must be a real directory')
        primary = directory / entry
        if not primary.is_file() or primary.is_symlink():
            raise ValueError('Missing component entrypoint: ' + component + '/' + entry)
        files = []
        for path in sorted(directory.rglob('*')):
            if path.is_symlink():
                raise ValueError('Symlinks are forbidden')
            if path.is_dir():
                continue
            if not path.is_file():
                raise ValueError('Only regular files are allowed')
            name = path.relative_to(source).as_posix()
            if (not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._/-]*', name)
                or len(name) > 200 or len(Path(name).parts) > 8
                or any(p in {'.', '..'} or p.startswith('.') or p.endswith('.')
                       or re.match(r'^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)', p, re.I)
                       for p in Path(name).parts)
                or name.lower() in folded
                or path.suffix.lower() not in EXTENSIONS):
                raise ValueError('Unsafe or unsupported member: ' + name)
            folded.add(name.lower())
            size = path.stat().st_size
            if path == primary and size < 100:
                raise ValueError('Entrypoint must contain at least 100 bytes: ' + name)
            if not 0 < size <= MAX_FILE:
                raise ValueError('Empty or oversized member: ' + name)
            data = path.read_bytes()
            if path.suffix.lower() == '.pdf' and not data.startswith(b'%PDF-'):
                raise ValueError('Invalid PDF signature: ' + name)
            if path.suffix.lower() == '.epub':
                with zipfile.ZipFile(path) as epub:
                    if epub.read('mimetype') != b'application/epub+zip':
                        raise ValueError('Invalid EPUB mimetype')
            total += size
            payload[name] = data
            files.append({'path': name, 'bytes': len(data), 'sha256': digest(data)})
        manifest['components'].append({'id': component, 'entrypoint': component + '/' + entry, 'files': files})
    if total > MAX_TOTAL or len(payload) + 1 > 512:
        raise ValueError('Bundle exceeds bounded checkout limits')
    for name in folded:
        parts = name.split('/')
        if any('/'.join(parts[:n]) in folded for n in range(1, len(parts))):
            raise ValueError('Case-insensitive file/ancestor collision')
    encoded = canonical(manifest)
    if total + len(encoded) > MAX_TOTAL:
        raise ValueError('Expanded bundle including manifest exceeds checkout limit')
    if len(encoded) > 64 * 1024:
        raise ValueError('Manifest exceeds checkout limit')
    payload['bundle-manifest.json'] = encoded
    destination.parent.mkdir(parents=True, exist_ok=True)
    # Exclusive creation protects an already reviewed candidate from accidental overwrite.
    descriptor = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(descriptor, 'wb') as stream:
        with zipfile.ZipFile(stream, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for name, data in sorted(payload.items()):
                info = zipfile.ZipInfo(name, date_time=(2026, 10, 3, 0, 0, 0))
                info.create_system = 3
                info.external_attr = (stat.S_IFREG | 0o600) << 16
                info.compress_type = zipfile.ZIP_DEFLATED
                archive.writestr(info, data)
    destination.chmod(0o600)
    if destination.stat().st_size > MAX_FILE:
        destination.unlink()
        raise ValueError('Archive exceeds 50 MiB checkout limit')
    sha = digest(destination.read_bytes())
    destination.with_suffix('.manifest.json').write_bytes(encoded)
    destination.with_suffix('.sha256').write_text(sha + '  ' + destination.name + '\n')
    record = {'schema': 1, 'product': PRODUCT, 'release_id': release_id,
              'asset_sha256': sha, 'asset_bytes': destination.stat().st_size,
              'manifest_sha256': digest(encoded), 'enabled': False,
              'owner_review_status': 'pending', 'owner_approved_sha256': '', 'owner_approved_at': ''}
    destination.with_suffix('.review.json').write_bytes(canonical(record))
    return record

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--release-id', required=True)
    args = parser.parse_args()
    try:
        print(json.dumps(build(args.source, args.output, args.release_id), indent=2))
    except (OSError, ValueError, KeyError, zipfile.BadZipFile) as error:
        parser.exit(1, str(error) + '\n')
