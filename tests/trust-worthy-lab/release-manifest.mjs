import assert from "node:assert/strict";
import fs from "node:fs";
import {
  ASSET_DEFINITIONS,
  MANIFEST_PATH,
  buildManifest,
  buildRootDigest,
  listDistFiles
} from "./release-integrity.mjs";

const mode = process.argv[2] || "--check";
if (!["--check", "--write"].includes(mode)) {
  throw new Error("Usage: node tests/release-manifest.mjs [--check|--write]");
}

const expected = buildManifest();
const expectedFiles = [...ASSET_DEFINITIONS.map(item => item.path), "release-manifest.json"].sort();

if (mode === "--write") {
  fs.writeFileSync(MANIFEST_PATH, `${JSON.stringify(expected, null, 2)}\n`, "utf8");
}

const actualFiles = listDistFiles();
assert.deepEqual(actualFiles, expectedFiles, "dist contains an unmanifested or missing public file");
const published = JSON.parse(fs.readFileSync(MANIFEST_PATH, "utf8"));
assert.deepEqual(published, expected, "release-manifest.json is stale or does not match the owned files");
assert.equal(
  buildRootDigest(published.scope.files),
  published.digest.root,
  "release root digest does not match the canonical asset records"
);

console.log(`Release manifest passed: ${published.scope.owned_asset_count} owned assets, SHA-256 root ${published.digest.root}`);
