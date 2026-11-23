import { test } from "node:test";
import assert from "node:assert/strict";
import { validateSite, validateImport } from "../src/data.js";
import { analyzeSiteLocally } from "../src/analyzer.js";
import { metersBetween, linkNearbySites } from "../src/geo.js";
const record = (extra = {}) => ({
  id: "sample-1",
  name: "Test only",
  type: "Rock Shelter",
  confidence: "Unverified",
  coords: { lat: 36.6, lng: -93.2 },
  notes: "Chert flakes near creek",
  observedIndicators: ["chert"],
  photos: [],
  ai: null,
  updatedAt: "2026-09-17T00:00:00Z",
  ...extra,
});
test("valid records and backup", () => {
  validateSite(record());
  validateImport({ sites: [record()] });
});
test("invalid inputs cannot enter notebook", () => {
  for (const patch of [
    { id: undefined },
    { name: "" },
    { coords: { lat: 91, lng: 0 } },
    { coords: { lat: 0, lng: NaN } },
    { photos: [{ dataUrl: "data:image/svg+xml,<svg/>" }] },
    { observedIndicators: [{}] },
    { ai: { summary: "a", signals: [{}] } },
  ])
    assert.throws(() => validateSite(record(patch)));
});
test("duplicate backup IDs rejected", () =>
  assert.throws(() => validateImport({ sites: [record(), record()] })));
test("distance and same-ID exclusion", () => {
  assert.equal(metersBetween({ lat: 0, lng: 0 }, { lat: 0, lng: 0 }), 0);
  assert.ok(
    Math.abs(metersBetween({ lat: 0, lng: 0 }, { lat: 0, lng: 1 }) - 111195) <
      2,
  );
  assert.equal(linkNearbySites(record(), [record()]).length, 0);
});
test("keywords never claim AI image inspection or confidence", () => {
  const a = analyzeSiteLocally(
    record({
      notes: "chert flakes soot fire sorted tailings creek tool marks airflow",
    }),
    [],
  );
  assert.equal(a.provider, "local-checklist");
  assert.equal(
    a.confidenceLabel,
    "Documentation checklist — not AI identification",
  );
  assert.ok(!a.confidenceLabel.includes("Strong Evidence"));
});

test("backup validation accepts legacy records and preserves per-site categories and video", () => {
  const base = record({ id: "legacy" });
  const video = { id: "clip", name: "clip.webm", dataUrl: "data:video/webm;base64,YQ==" };
  const current = { ...base, id: "current", categories: ["Rock Shelter", "Water Source"], videos: [video] };
  assert.deepEqual(validateImport({ sites: [base, current] }), [base, current]);
  assert.throws(() => validateSite({ ...current, categories: ["Water Source"] }), /category/);
  assert.throws(() => validateSite({ ...current, videos: [{ ...video, dataUrl: "data:text/html;base64,YQ==" }] }), /Invalid video/);
});
