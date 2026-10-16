import { SITE_TYPES, CONFIDENCE_LEVELS } from "./siteTypes.js";
export function validateSite(s) {
  if (
    !s ||
    typeof s.id !== "string" ||
    !/^[\w-]{1,80}$/.test(s.id) ||
    typeof s.name !== "string" ||
    !s.name.trim() ||
    s.name.length > 120
  )
    throw Error("Invalid record name or ID.");
  if (
    !s.coords ||
    typeof s.coords.lat !== "number" ||
    typeof s.coords.lng !== "number" ||
    !Number.isFinite(s.coords.lat) ||
    !Number.isFinite(s.coords.lng) ||
    Math.abs(s.coords.lat) > 90 ||
    Math.abs(s.coords.lng) > 180
  )
    throw Error("Invalid coordinates.");
  if (!SITE_TYPES.includes(s.type) || !CONFIDENCE_LEVELS.includes(s.confidence))
    throw Error("Invalid record category or review status.");
  if (
    typeof s.notes !== "string" ||
    s.notes.length > 6000 ||
    !Array.isArray(s.observedIndicators) ||
    s.observedIndicators.length > 50 ||
    s.observedIndicators.some((x) => typeof x !== "string" || x.length > 120)
  )
    throw Error("Invalid notes or tags.");
  if (!Array.isArray(s.photos) || s.photos.length > 5)
    throw Error("Use up to five photos per record.");
  for (const p of s.photos)
    if (
      !p ||
      typeof p.id !== "string" ||
      !/^[\w-]{1,80}$/.test(p.id) ||
      typeof p.name !== "string" ||
      p.name.length > 160 ||
      typeof p.dataUrl !== "string" ||
      p.dataUrl.length > 900000 ||
      !/^data:image\/jpeg;base64,[A-Za-z0-9+/]+=*$/.test(p.dataUrl)
    )
      throw Error("Photos must be app-compressed JPEGs.");
  if (typeof s.updatedAt !== "string" || Number.isNaN(Date.parse(s.updatedAt)))
    throw Error("Invalid update timestamp.");
  if (s.ai !== null && s.ai !== undefined) {
    if (
      typeof s.ai !== "object" ||
      typeof s.ai.summary !== "string" ||
      s.ai.summary.length > 18000
    )
      throw Error("Invalid analysis.");
    for (const k of ["signals", "warnings", "recommendedNextDocumentation"])
      if (
        !Array.isArray(s.ai[k]) ||
        s.ai[k].some((x) => typeof x !== "string" || x.length > 3000)
      )
        throw Error("Invalid analysis fields.");
  }
  return s;
}
export function validateImport(data) {
  if (!data || !Array.isArray(data.sites) || data.sites.length > 1000)
    throw Error("Not an OOIS backup (maximum 1,000 records).");
  const ids = new Set();
  for (const s of data.sites) {
    validateSite(s);
    if (ids.has(s.id)) throw Error("Duplicate record IDs in backup.");
    ids.add(s.id);
  }
  return data.sites;
}
let connection;
function db() {
  return (connection ??= new Promise((resolve, reject) => {
    const req = indexedDB.open("oois-field-notebook-v1", 1);
    req.onupgradeneeded = () =>
      req.result.createObjectStore("sites", { keyPath: "id" });
    req.onsuccess = () => resolve(req.result);
    req.onerror = () =>
      reject(Error("Device storage unavailable. Use a normal browser window."));
  }));
}
export async function loadSites() {
  const d = await db();
  return new Promise((resolve, reject) => {
    const r = d.transaction("sites", "readonly").objectStore("sites").getAll();
    r.onsuccess = () => resolve(r.result);
    r.onerror = () => reject(r.error);
  });
}
async function mutate(fn) {
  const d = await db();
  return new Promise((resolve, reject) => {
    const tx = d.transaction("sites", "readwrite"),
      s = tx.objectStore("sites");
    let error, result;
    tx.oncomplete = () => resolve(result);
    tx.onabort = tx.onerror = () =>
      reject(
        error || tx.error || Error("Save failed; original records are intact."),
      );
    s.getAll().onsuccess = (e) => {
      try {
        result = fn(e.target.result, s);
      } catch (err) {
        error = err;
        tx.abort();
      }
    };
  });
}
export const saveSite = (site, expected) => {
  validateSite(site);
  return mutate((all, store) => {
    const current = all.find((s) => s.id === site.id);
    if (expected !== undefined && current?.updatedAt !== expected)
      throw Error("Another tab changed this record. Reopen it before editing.");
    if (!current && all.length >= 1000)
      throw Error("Notebook limit reached. Export a backup.");
    if (
      JSON.stringify([...all.filter((s) => s.id !== site.id), site]).length >
      18 * 1024 * 1024
    )
      throw Error(
        "Notebook capacity reached (18 MB). Export a backup and archive older records first.",
      );
    store.put(site);
  });
};
export const mergeSites = (incoming) => {
  validateImport({ sites: incoming });
  return mutate((all, store) => {
    const ids = new Set(all.map((s) => s.id));
    const add = incoming.filter((s) => !ids.has(s.id));
    if (all.length + add.length > 1000)
      throw Error("Notebook exceeds 1,000 records.");
    if (JSON.stringify([...all, ...add]).length > 18 * 1024 * 1024)
      throw Error("Import exceeds the 18 MB device notebook limit.");
    for (const s of add) store.put(s);
    return add.length;
  });
};
export const removeSite = (id) =>
  mutate((all, store) => {
    store.delete(id);
    for (const s of all)
      if (s.id !== id && s.linkedSites?.some((x) => x.siteId === id))
        store.put({
          ...s,
          linkedSites: s.linkedSites.filter((x) => x.siteId !== id),
          ai: s.ai
            ? {
                ...s.ai,
                linkedSites: (s.ai.linkedSites || []).filter(
                  (x) => x.siteId !== id,
                ),
              }
            : null,
        });
  });
export async function photoFromFile(file) {
  if (
    !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
    file.size > 20 * 1024 * 1024
  )
    throw Error("Use JPEG, PNG or WebP under 20 MB.");
  const url = URL.createObjectURL(file);
  try {
    const img = new Image();
    img.src = url;
    await img.decode();
    if (img.naturalWidth * img.naturalHeight > 65000000)
      throw Error("Photo dimensions are too large.");
    const scale = Math.min(
        1,
        1400 / Math.max(img.naturalWidth, img.naturalHeight),
      ),
      canvas = document.createElement("canvas");
    canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
    const ctx = canvas.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    let dataUrl = canvas.toDataURL("image/jpeg", 0.75);
    if (dataUrl.length > 900000) dataUrl = canvas.toDataURL("image/jpeg", 0.5);
    if (dataUrl.length > 900000)
      throw Error("Photo is too large after compression.");
    return {
      id: crypto.randomUUID(),
      name: file.name.slice(0, 120),
      type: "image/jpeg",
      dataUrl,
    };
  } finally {
    URL.revokeObjectURL(url);
  }
}
export async function migrateLegacy() {
  const raw = localStorage.getItem("oois_sites_v2");
  if (!raw || localStorage.getItem("oois_legacy_migrated")) return;
  const legacy = JSON.parse(raw);
  if (!Array.isArray(legacy))
    throw Error(
      "The older notebook is unreadable. Its original data was preserved.",
    );
  for (const s of legacy) {
    s.photos = await Promise.all(
      (s.photos || []).map(async (p) => {
        if (!p.dataUrl)
          throw Error(
            "An older cloud photo needs to be downloaded before migration. The old notebook was preserved.",
          );
        const blob = await (await fetch(p.dataUrl)).blob();
        return photoFromFile(
          new File([blob], p.name || "Legacy photo", { type: blob.type }),
        );
      }),
    );
  }
  await mergeSites(legacy);
  localStorage.setItem("oois_legacy_migrated", "1");
}
