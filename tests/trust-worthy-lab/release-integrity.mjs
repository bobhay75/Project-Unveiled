import { createHash } from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
export const DIST = path.join(PROJECT_ROOT, "truth", "lab");
export const MANIFEST_PATH = path.join(DIST, "release-manifest.json");
export const MANIFEST_SCHEMA = "trust-worthy-release-manifest-v1";
export const PUBLIC_RELEASE = 17;
export const OBSERVER_RELEASE = "trust-worthy-observer-v7";
export const BUILD_RECORDED_AT = "2026-09-16T22:40:06Z";
export const PREDECESSOR_COMMIT = "37ad89d77205d9479cee96834288d5cbb4b309b0";
export const PREDECESSOR_ROOT = "31035b2675d52f16863537ed7165e546c81a44cf38bdbe11d69a7b1096d85236";
export const ROLLBACK_SOURCE_COMMIT = "37ad89d77205d9479cee96834288d5cbb4b309b0";
export const PRODUCTION_ORIGIN = "https://bobsome1.com";
export const LAB_PATH = "/truth/lab/";
export const DELIVERY_POLICY = "exact-owned-static-v1";

const EXACT = Object.freeze({ mode: "exact" });

export const ASSET_DEFINITIONS = Object.freeze([
  { path: ".well-known/security.txt", route: "/.well-known/security.txt", content_type: "text/plain", delivery: EXACT },
  { path: "404.html", route: "/truth/lab/__trust_release_404_document__", expected_status: 404, content_type: "text/html", delivery: EXACT },
  { path: "app-icon-192.png", route: "/truth/lab/app-icon-192.png", content_type: "image/png", delivery: EXACT },
  { path: "app-icon-512.png", route: "/truth/lab/app-icon-512.png", content_type: "image/png", delivery: EXACT },
  { path: "app-icon.svg", route: "/truth/lab/app-icon.svg", content_type: "image/svg+xml", delivery: EXACT },
  { path: "app.js", route: "/truth/lab/app.js", content_type: "javascript", delivery: EXACT },
  { path: "index.html", route: LAB_PATH, content_type: "text/html", delivery: EXACT },
  { path: "manifest.webmanifest", route: "/truth/lab/manifest.webmanifest", content_type: "application/manifest+json", delivery: EXACT },
  { path: "observer.js", route: "/truth/lab/observer.js", content_type: "javascript", delivery: EXACT },
  { path: "research-sweep.js", route: "/truth/lab/research-sweep.js", content_type: "javascript", delivery: EXACT },
  { path: "robots.txt", route: "/truth/lab/robots.txt", content_type: "text/plain", delivery: EXACT },
  { path: "share-card.png", route: "/truth/lab/share-card.png", content_type: "image/png", delivery: EXACT },
  { path: "share-card.svg", route: "/truth/lab/share-card.svg", content_type: "image/svg+xml", delivery: EXACT },
  { path: "sitemap.xml", route: "/truth/lab/sitemap.xml", content_type: "xml", delivery: EXACT },
  { path: "status.json", route: "/truth/lab/status.json", content_type: "application/json", delivery: EXACT },
  { path: "styles.css", route: "/truth/lab/styles.css", content_type: "text/css", delivery: EXACT }
]);

export function sha256Hex(value) {
  return createHash("sha256").update(value).digest("hex");
}

export function canonicalAssetRootInput(files) {
  const rows = [...files]
    .sort((left, right) => left.path.localeCompare(right.path))
    .map(file => [
      file.path,
      file.route,
      file.expected_status,
      file.sha256,
      file.bytes,
      file.content_type,
      file.delivery.mode,
      file.delivery.policy || ""
    ]);
  return `${JSON.stringify(rows)}\n`;
}

export function buildRootDigest(files) {
  return sha256Hex(Buffer.from(canonicalAssetRootInput(files), "utf8"));
}

export function listDistFiles() {
  const found = [];
  const visit = directory => {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) visit(absolute);
      else if (entry.isFile()) {
        const relative = path.relative(DIST, absolute).split(path.sep).join("/");
        if (relative !== ".htaccess") found.push(relative);
      }
    }
  };
  visit(DIST);
  return found.sort();
}

export function buildManifest() {
  const files = ASSET_DEFINITIONS.map(definition => {
    const absolute = path.join(DIST, definition.path);
    if (!fs.statSync(absolute).isFile()) throw new Error(`Missing owned release asset: ${definition.path}`);
    const bytes = fs.readFileSync(absolute);
    return {
      path: definition.path,
      route: definition.route,
      expected_status: definition.expected_status || 200,
      sha256: sha256Hex(bytes),
      bytes: bytes.byteLength,
      content_type: definition.content_type,
      delivery: { ...definition.delivery }
    };
  });
  const root = buildRootDigest(files);

  return {
    schema: MANIFEST_SCHEMA,
    release_id: `trust-worthy-evidence-lab-r${PUBLIC_RELEASE}`,
    release: PUBLIC_RELEASE,
    observer: OBSERVER_RELEASE,
    recorded_at: BUILD_RECORDED_AT,
    event_category: "corrective-maintenance",
    organization: {
      name: "Bobsome1 / Project Unveiled",
      attribution: "Self-declared public owner identity; no third-party organization credential is asserted."
    },
    tracked_entity: {
      id: "trust-worthy-evidence-lab",
      type: "static-web-release",
      public_origin: `${PRODUCTION_ORIGIN}${LAB_PATH}`
    },
    predecessor: {
      release: 16,
      source_commit: PREDECESSOR_COMMIT,
      build_root_sha256: PREDECESSOR_ROOT,
      public_origin: `${PRODUCTION_ORIGIN}${LAB_PATH}`,
      relationship: "supersedes",
      note: "Release 16 is the verified canonical predecessor. Release 17 adds a progressive evidence-field experience while preserving exact provenance, source boundaries, and human judgment."
    },
    rollback: {
      project_unveiled_source_commit: ROLLBACK_SOURCE_COMMIT,
      evidence_lab_release: 16,
      tested_live_fallback: `${PRODUCTION_ORIGIN}${LAB_PATH}`,
      scope: "Evidence Lab files only; do not check out or redeploy the older whole-site commit.",
      instruction: "From the release 17 checkout, run scripts/rollback-trust-worthy-lab.sh against /home/bobsome1/public_html/truth/lab to restore the verified release 16 lab bytes from the named commit."
    },
    digest: {
      algorithm: "SHA-256",
      root,
      canonicalization: "UTF-8 JSON array of path, route, expected_status, sha256, bytes, content_type, delivery mode, and delivery policy rows sorted by path, followed by LF."
    },
    scope: {
      owned_asset_count: files.length,
      self_path: "/truth/lab/release-manifest.json",
      self_exclusion: "The manifest cannot contain its own digest. Live verification compares its bytes with the source copy before using it.",
      files
    },
    delivery_boundary: {
      provider: "Bobsome1 Namecheap shared hosting / LiteSpeed static delivery",
      policy: DELIVERY_POLICY,
      allowed_change: "None. Every owned public asset must match the recorded SHA-256 digest and byte count exactly.",
      verification: "Require exact bytes; verify response status, content type, method policy, and security headers separately.",
      fail_closed: true
    },
    verification_contract: {
      expected_reads: ["GET", "HEAD"],
      unknown_route_status: 404,
      expected_denials: { OPTIONS: 405, POST: 405, PUT: 405, PATCH: 405, DELETE: 405 },
      offline_gate: "bash tests/trust-worthy-lab/run_all.sh",
      live_gate: "node tests/trust-worthy-lab/live-release-gate.mjs"
    },
    selective_disclosure: {
      public: ["release identity", "owned asset routes", "content types", "byte counts", "SHA-256 digests", "delivery boundary", "rollback pointer"],
      withheld: ["credentials", "customer or case data", "financial data", "private logs", "proprietary diagnosis", "personal identifiers beyond existing public business contact"],
      reason: "Publish only what an independent verifier needs to test release integrity."
    },
    nist_ir_8536_alignment: {
      reference: "https://doi.org/10.6028/NIST.IR.8536",
      status: "Principle-informed adaptation for software release provenance; not NIST certification, conformance, or endorsement.",
      mapping: {
        record_identifier: "release_id",
        event_category: "event_category",
        event_timestamp: "recorded_at",
        organization_identifier: "organization.name plus explicit self-declared attribution limit",
        tracked_entity_identifier: "tracked_entity.id",
        traceability_link: "predecessor source commit, predecessor build root, and rollback source commit",
        data_type_identifier: "schema",
        tracked_entity_payload: "scope.files and digest",
        supplemental_references: "reference URLs only; sensitive evidence remains outside the public manifest"
      }
    },
    assurance_boundary: {
      provides: ["repeatable owned-byte comparison", "tamper evidence relative to a separately trusted copy", "explicit delivery boundary", "machine-readable rollback continuity"],
      does_not_provide: ["digital signature", "proof of authorship", "external timestamp", "proof that runtime behavior is safe", "trust in the manifest when no separate trusted copy exists"]
    }
  };
}
