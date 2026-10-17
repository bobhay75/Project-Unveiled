#!/usr/bin/env python3
"""Offline packaging regression: deterministic bytes, all parts, no approval."""
import importlib.util
import json
import os
import shutil
import subprocess
from pathlib import Path
import tempfile
import unittest
import zipfile

script = Path(__file__).resolve().parents[2] / 'scripts/build-buyer-bundle.py'
spec = importlib.util.spec_from_file_location('builder', script)
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)

class BundleTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.source = self.root / 'source'
        self.output = self.root / 'candidate.zip'
        for component, entry in builder.ENTRYPOINTS.items():
            p = self.source / component / entry
            p.parent.mkdir(parents=True)
            p.write_bytes((b'%PDF-1.4\nsynthetic fixture\n%%EOF' if p.suffix == '.pdf' else b'<!doctype html><title>Timeline</title>') + b' ' * 100)
    def tearDown(self):
        self.tmp.cleanup()
    def build(self):
        return builder.build(self.source, self.output, 'fixture-v1')
    def test_deterministic_and_pending(self):
        first = self.build()
        second = builder.build(self.source, self.root/'second.zip', 'fixture-v1')
        self.assertEqual(first['asset_sha256'], second['asset_sha256'])
        self.assertFalse(first['enabled'])
        self.assertEqual(first['owner_review_status'], 'pending')
        self.assertEqual(first['owner_approved_sha256'], '')
        self.assertEqual(first['owner_approved_at'], '')
        with zipfile.ZipFile(self.output) as z:
            m = json.loads(z.read('bundle-manifest.json'))
            self.assertEqual({x['id'] for x in m['components']}, set(builder.ENTRYPOINTS))
            for component in m['components']:
                for f in component['files']:
                    self.assertEqual(builder.digest(z.read(f['path'])), f['sha256'])
                    self.assertEqual(len(z.read(f['path'])), f['bytes'])
    def test_php_validator_accepts_builder_output(self):
        php = shutil.which('php')
        if not php: self.skipTest('PHP unavailable; required in Site safety CI')
        record = self.build()
        # Synthetic approval exists only in this offline subprocess memory.
        code = """require $argv[1]; $c=['asset_path'=>$argv[2], 'asset_sha256'=>$argv[3],
          'owner_approved_sha256'=>$argv[3], 'owner_approved_at'=>'2026-01-01T00:00:00Z'];
          $r=bc_validate_bundle($c); echo $r['release_id'];"""
        result = subprocess.run([php, '-r', code, str(script.parents[1]/'store/checkout/lib.php'),
                                 str(self.output), record['asset_sha256']], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(result.stdout, 'fixture-v1')
    def test_runtime_path_restrictions(self):
        for name in ['CON.txt', 'trailing./file.txt', 'A.txt', 'a.txt']:
            p = self.source/'timeline'/name
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text('fixture')
        with self.assertRaises(ValueError): self.build()
    def test_uppercase_release_rejected(self):
        with self.assertRaises(ValueError): builder.build(self.source,self.output,'Fixture-v1')
    def test_short_entrypoint_rejected(self):
        (self.source/'timeline/index.html').write_text('<html></html>')
        with self.assertRaises(ValueError): self.build()
    def test_archive_private_from_creation(self):
        self.build()
        self.assertEqual(self.output.stat().st_mode & 0o777, 0o600)
    def test_missing_component(self):
        (self.source/'no-more-milk/No_More_Milk.pdf').unlink()
        with self.assertRaises(ValueError): self.build()
    def test_extra_root(self):
        (self.source/'private-config.php').write_text('fixture')
        with self.assertRaises(ValueError): self.build()
    def test_executable(self):
        (self.source/'timeline/hidden.php').write_text('fixture')
        with self.assertRaises(ValueError): self.build()
    def test_symlink(self):
        (self.source/'timeline/leak.txt').symlink_to(self.source/'no-more-milk/No_More_Milk.pdf')
        with self.assertRaises(ValueError): self.build()
    def test_invalid_pdf(self):
        (self.source/'no-more-milk/No_More_Milk.pdf').write_text('not a PDF')
        with self.assertRaises(ValueError): self.build()
    def test_empty_file(self):
        (self.source/'timeline/empty.txt').touch()
        with self.assertRaises(ValueError): self.build()
    def test_does_not_overwrite_reviewed_bytes(self):
        self.build()
        with self.assertRaises(FileExistsError): self.build()
    def test_source_repository_rejected(self):
        with self.assertRaises(ValueError): builder.build(builder.REPO,self.output,'fixture')
    def test_unsafe_release(self):
        with self.assertRaises(ValueError): builder.build(self.source,self.output,'../bad')

if __name__ == '__main__': unittest.main()
