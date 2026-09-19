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
const expectedFiles = [
  ...ASSET_DEFINITIONS.filter(item => !item.source_path).map(item => item.path),
  "release-manifest.json"
].sort();

const indexHtml = fs.readFileSync(new URL("../../truth/lab/index.html", import.meta.url), "utf8");
const dependencyTags = indexHtml.match(/<(?:script|link)\b[^>]*>/gi) || [];
const dependencyRoutes = dependencyTags.flatMap(tag => {
  const source = tag.match(/\bsrc=["']([^"']+)["']/i)?.[1];
  const stylesheet = /<link\b/i.test(tag) && /\brel=["'][^"']*\bstylesheet\b[^"']*["']/i.test(tag)
    ? tag.match(/\bhref=["']([^"']+)["']/i)?.[1]
    : undefined;
  const reference = source || stylesheet;
  if (!reference) return [];
  const resolved = new URL(reference, "https://bobsome1.com/truth/lab/");
  return resolved.origin === "https://bobsome1.com" ? [resolved.pathname] : [];
});
const ownedRoutes = new Set(ASSET_DEFINITIONS.map(item => item.route));
assert.deepEqual(
  dependencyRoutes.filter(route => !ownedRoutes.has(route)),
  [],
  "every same-origin script and stylesheet loaded by the Evidence Lab must be release-manifested"
);

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
