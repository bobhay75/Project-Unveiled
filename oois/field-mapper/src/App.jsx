import { useEffect, useMemo, useRef, useState } from "react";
import {
  MapContainer,
  Marker,
  Popup,
  Polyline,
  ScaleControl,
  TileLayer,
  useMap,
  useMapEvents,
} from "react-leaflet";
import L from "leaflet";
import { Capacitor } from "@capacitor/core";
import { Geolocation } from "@capacitor/geolocation";
import { analyzeSiteLocally } from "./analyzer.js";
import { linkNearbySites, metersBetween } from "./geo.js";
import { CONFIDENCE_LEVELS, TYPE_COLORS } from "./siteTypes.js";
import {
  loadSites,
  saveSite,
  mergeSites,
  removeSite,
  photoFromFile,
  validateImport,
  migrateLegacy,
  siteCategories,
  videoFromFile,
} from "./data.js";
import {
  analyzeSiteCloud,
  deleteSiteCloud,
  isFirebaseEnabled,
  loadSitesCloud,
  saveSiteCloud,
  cloudLogin,
  cloudLogout,
  cloudUser,
} from "./firebase.js";
import CategoryPicker from "./CategoryPicker.jsx";
import MediaLibrary from "./MediaLibrary.jsx";
function makeIcon(type) {
  return L.divIcon({
    className: "oois-marker",
    html: `<div style="background:${TYPE_COLORS[type] || "#b5bb83"}" class="oois-marker-dot"></div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14],
  });
}
function MapEvents({ onClick, center, expanded }) {
  const map = useMap();
  useEffect(() => {
    if (center) map.setView([center.lat, center.lng], Math.max(15, map.getZoom()));
  }, [center, map]);
  useEffect(() => {
    const frame = requestAnimationFrame(() => map.invalidateSize({ pan: false }));
    return () => cancelAnimationFrame(frame);
  }, [map, expanded]);
  useMapEvents({
    click: (e) => {
      const p = e.latlng.wrap();
      onClick({ lat: p.lat, lng: p.lng });
    },
  });
  return null;
}
const initialForm = () => ({
  name: "",
  categories: [],
  confidence: "Unverified",
  notes: "",
  observedIndicatorsText: "",
  photos: [],
  videos: [],
});
async function download(name, value) {
  if (Capacitor.isNativePlatform()) {
    const { Filesystem, Directory, Encoding } =
      await import("@capacitor/filesystem");
    const { Share } = await import("@capacitor/share");
    const result = await Filesystem.writeFile({
      path: name,
      data: JSON.stringify(value),
      directory: Directory.Cache,
      encoding: Encoding.UTF8,
    });
    await Share.share({ title: "Private OOIS backup", url: result.uri });
    return;
  }
  const url = URL.createObjectURL(
    new Blob([JSON.stringify(value)], { type: "application/json" }),
  );
  const a = document.createElement("a");
  a.href = url;
  a.download = name;
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
export default function App() {
  const [sites, setSites] = useState([]),
    [coords, setCoords] = useState(null),
    [center, setCenter] = useState(null),
    [activeId, setActiveId] = useState(null),
    [form, setForm] = useState(initialForm),
    [editing, setEditing] = useState(null),
    [status, setStatus] = useState("Opening device notebook…"),
    [busy, setBusy] = useState(false),
    [layer, setLayer] = useState("satellite"),
    [query, setQuery] = useState(""),
    [filter, setFilter] = useState([]),
    [expanded, setExpanded] = useState(false),
    [moving, setMoving] = useState(null),
    [measuring, setMeasuring] = useState(false),
    [points, setPoints] = useState([]),
    [email, setEmail] = useState(""),
    [password, setPassword] = useState(""),
    [user, setUser] = useState(null),
    [online, setOnline] = useState(navigator.onLine);
  const entry = useRef(),
    settings = useRef(),
    importInput = useRef(),
    initialized = useRef(false);
  const active = sites.find((s) => s.id === activeId);
  const distance = points.slice(1).reduce(
    (sum, point, index) => sum + metersBetween(points[index], point),
    0,
  );
  useEffect(() => {
    if (!expanded) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const escape = (event) => {
      if (event.key === "Escape" && !entry.current.open) setExpanded(false);
    };
    window.addEventListener("keydown", escape);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", escape);
    };
  }, [expanded]);
  const refresh = async () => setSites(await loadSites());
  useEffect(() => {
    if (!initialized.current) {
      initialized.current = true;
      (async () => {
        try {
          await migrateLegacy();
          await refresh();
          setStatus("Saved on this device. Export regular backups.");
        } catch (e) {
          setStatus(e.message);
        }
      })();
    }
    const update = () => setOnline(navigator.onLine);
    window.addEventListener("online", update);
    window.addEventListener("offline", update);
    return () => {
      window.removeEventListener("online", update);
      window.removeEventListener("offline", update);
    };
  }, []);
  const run = async (fn) => {
    setBusy(true);
    try {
      await fn();
    } catch (e) {
      setStatus(e.message);
    } finally {
      setBusy(false);
    }
  };
  const openEntry = (point, site = null) => {
    setEditing(site);
    setCoords(point);
    setForm(
      site
        ? {
            ...site,
            categories: siteCategories(site),
            videos: [...(site.videos ?? [])],
            photos: [...site.photos],
            observedIndicatorsText: site.observedIndicators.join(", "),
          }
        : initialForm(),
    );
    entry.current.querySelector(".category-picker").open = false;
    entry.current.showModal();
  };
  const mapClick = (point) => {
    if (measuring) {
      setPoints((previous) => [...previous, point]);
    } else {
      openEntry(point, moving);
      setMoving(null);
    }
  };
  const captureGPS = () =>
    run(async () => {
      setStatus("Getting a GPS fix…");
      const p = await Geolocation.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 20000,
        maximumAge: 0,
      });
      const c = {
        lat: p.coords.latitude,
        lng: p.coords.longitude,
        accuracyMeters: p.coords.accuracy,
      };
      setCenter(c);
      openEntry(c);
      setStatus(
        `GPS captured within approximately ${Math.round(p.coords.accuracy)} m.`,
      );
    });
  const addPhotos = async (files) =>
    run(async () => {
      if (form.photos.length + files.length > 5)
        throw Error("Use up to five photos per record.");
      const additions = [];
      for (const f of files) additions.push(await photoFromFile(f));
      setForm((prev) => ({ ...prev, photos: [...prev.photos, ...additions] }));
    });
  const addVideos = (files) =>
    run(async () => {
      if (form.videos.length + files.length > 3)
        throw Error("Use up to three videos per site.");
      const videos = await Promise.all(files.map(videoFromFile));
      setForm((previous) => ({ ...previous, videos: [...previous.videos, ...videos] }));
    });
  const save = async (event) => {
    event.preventDefault();
    await run(async () => {
      if (!form.categories.length) throw Error("Choose at least one site category.");
      const now = new Date().toISOString();
      const s = {
        id: editing?.id || crypto.randomUUID(),
        createdAt: editing?.createdAt || now,
        updatedAt: now,
        name: form.name.trim(),
        type: form.categories[0],
        categories: form.categories,
        confidence: form.confidence,
        coords: { lat: Number(coords.lat), lng: Number(coords.lng) },
        accuracyMeters: coords.accuracyMeters ?? null,
        notes: form.notes.trim(),
        observedIndicators: form.observedIndicatorsText
          .split(",")
          .map((x) => x.trim())
          .filter(Boolean),
        photos: form.photos,
        videos: form.videos,
        isPrivate: true,
        isSynced: false,
        cloudRevision: editing?.cloudRevision || 0,
        ai: null,
        linkedSites: [],
      };
      s.linkedSites = linkNearbySites(s, sites);
      s.ai = analyzeSiteLocally(s, sites);
      await saveSite(s, editing?.updatedAt);
      await refresh();
      setActiveId(s.id);
      entry.current.close();
      setStatus(
        "Record and media saved on this device. Documentation checklist updated.",
      );
    });
  };
  const show = (site) => {
    setActiveId(site.id);
    setCenter(site.coords);
  };
  const importBackup = async (file) =>
    run(async () => {
      if (!file) return;
      if (file.size > 20 * 1024 * 1024) throw Error("Backup exceeds 20 MB.");
      const incoming = validateImport(JSON.parse(await file.text()));
      if (
        !confirm(
          `Import ${incoming.length} records? Existing IDs remain unchanged.`,
        )
      )
        return;
      const count = await mergeSites(incoming);
      await refresh();
      setStatus(`${count} records added. Existing records preserved.`);
    });
  const analyze = (site) =>
    run(async () => {
      if (!isFirebaseEnabled())
        throw Error(
          "Cloud AI needs Firebase configuration. Your device notebook works now.",
        );
      if (!cloudUser()) throw Error("Sign in under Cloud settings first.");
      if (
        !confirm(
          "Send this record’s notes and resized photos to Gemini for analysis? Coordinates and nearby records are excluded. Your cloud account may be charged.",
        )
      )
        return;
      setStatus("Analyzing evidence…");
      const ai = await analyzeSiteCloud(site);
      const latest = (await loadSites()).find((s) => s.id === site.id);
      if (!latest) throw Error("The record was deleted during analysis.");
      if (latest.updatedAt !== site.updatedAt)
        throw Error(
          "The record changed during analysis. Run analysis on the updated record.",
        );
      await saveSite({ ...latest, ai }, latest.updatedAt);
      await refresh();
      setStatus("AI hypotheses saved for your review.");
    });
  const cloudSave = (site) =>
    run(async () => {
      if (
        !confirm(
          "Upload this record, precise location, and photos to your private Firebase account? Videos stay on this device and in exported backups.",
        )
      )
        return;
      const synced = await saveSiteCloud(site);
      await saveSite(synced, site.updatedAt);
      await refresh();
      setStatus("Private cloud copy saved. Device copy retained.");
    });
  const deleteRecord = (site) =>
    run(async () => {
      if (
        !confirm(
          `Delete “${site.name}” from this device? Exported backups and cloud copies remain.`,
        )
      )
        return;
      await removeSite(site.id);
      await refresh();
      setActiveId(null);
      setStatus("Device record deleted.");
    });
  const visible = useMemo(
    () =>
      sites.filter(
        (s) =>
          (!filter.length || filter.some((type) => siteCategories(s).includes(type))) &&
          [s.name, s.notes, ...siteCategories(s), ...s.observedIndicators]
            .join(" ")
            .toLowerCase()
            .includes(query.toLowerCase()),
      ),
    [sites, query, filter],
  );
  const tiles =
    layer === "topo"
      ? {
          url: "https://basemap.nationalmap.gov/arcgis/rest/services/USGSTopo/MapServer/tile/{z}/{y}/{x}",
          attribution: "USGS The National Map",
          maxZoom: 16,
        }
      : {
          url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
          attribution:
            "Tiles © Esri, Maxar, Earthstar Geographics, and GIS User Community",
          maxZoom: 19,
        };
  const nearby = active ? linkNearbySites(active, sites) : [];
  return (
    <main className="app-shell">
      <header className="topbar">
        <div>
          <div className="eyebrow">OZARK OCCUPATION INTELLIGENCE SYSTEM</div>
          <h1>
            OOIS <span>Field Mapper</span>
          </h1>
          <p>Read the land. Keep the evidence.</p>
        </div>
        <div className="top-actions">
          <button
            onClick={() => openEntry({ lat: "", lng: "" })}
          >
            + Add a find
          </button>
          <button onClick={captureGPS}>Capture GPS</button>
          <button
            className="secondary"
            onClick={() =>
              run(() =>
                download("oois-field-records.json", {
                  project: "Ozark Occupation Intelligence System",
                  version: 1,
                  exportedAt: new Date().toISOString(),
                  sites,
                }),
              )
            }
          >
            Export backup
          </button>
          <button
            className="secondary"
            onClick={() => importInput.current.click()}
          >
            Import backup
          </button>
          <input
            hidden
            ref={importInput}
            type="file"
            accept="application/json,.json"
            onChange={(e) => {
              importBackup(e.target.files[0]);
              e.target.value = "";
            }}
          />
          <button
            className="secondary"
            onClick={() => {
              setUser(cloudUser());
              settings.current.showModal();
            }}
          >
            Cloud settings
          </button>
        </div>
      </header>
      <div className="status" role="status">
        {!online ? "Offline · " : ""}
        {status}
      </div>
      <section className="workspace">
        <div className={expanded ? "map-card map-expanded" : "map-card"}>
          <div className="card-head">
            <div>
              <h2>Follow the evidence.</h2>
              <p>{moving ? "Tap the new position for " + moving.name : measuring ? "Tap along a path to measure it." : "Tap a spot to record what you found."}</p>
            </div>
            <label>
              Map layer
              <select value={layer} onChange={(e) => setLayer(e.target.value)}>
                <option value="satellite">Satellite</option>
                <option value="topo">USGS Topographic</option>
                <option value="none">No external map</option>
              </select>
            </label>
          </div>
          <div className="button-row map-tools">
            <button type="button" aria-pressed={expanded} onClick={() => setExpanded(!expanded)}>
              {expanded ? "Exit full-screen map" : "Full-screen map"}
            </button>
            <button type="button" aria-pressed={measuring} disabled={!!moving} onClick={() => {
              setMeasuring(!measuring);
              setPoints([]);
            }}>
              {measuring ? "Finish measuring" : "Measure distance"}
            </button>
            {moving && <button onClick={() => setMoving(null)}>Cancel moving pin</button>}
            {measuring && <button onClick={() => setPoints(points.slice(0, -1))} disabled={!points.length}>Undo point</button>}
            {measuring && <output aria-live="polite">{Math.round(distance)} m · {(distance * 3.28084).toFixed(0)} ft</output>}
          </div>
          <div className="map-wrap">
            <MapContainer
              center={[36.6437, -93.2982]}
              zoom={12}
              maxZoom={22}
              className="map"
            >
              <MapEvents onClick={mapClick} center={center} expanded={expanded} />
              <ScaleControl position="bottomleft" />
              {points.length > 1 && <Polyline positions={points.map(({ lat, lng }) => [lat, lng])} interactive={false} /> }
              {layer !== "none" && online && (
                <TileLayer
                  key={layer}
                  url={tiles.url}
                  attribution={tiles.attribution}
                  maxNativeZoom={tiles.maxZoom}
                  maxZoom={22}
                  eventHandlers={{
                    tileerror: () =>
                      setStatus(
                        "Map tiles unavailable. Saved points and coordinate entry still work.",
                      ),
                  }}
                />
              )}
              {visible.map((s) => (
                <Marker
                  key={s.id}
                  icon={makeIcon(s.type)}
                  position={[s.coords.lat, s.coords.lng]}
                  eventHandlers={{ click: () => {
                    if (moving || measuring) mapClick(s.coords);
                    else show(s);
                  } }}
                >
                  <Popup>{s.name}<br />{siteCategories(s).join(" · ")}</Popup>
                </Marker>
              ))}
            </MapContainer>
          </div>
          <p className="map-footer">
            External tiles require internet. Your notebook works offline after
            its first online visit.
          </p>
        </div>
        <aside className="side-panel">
          {active && (
            <section className="panel">
              <div className="badge">{siteCategories(active).join(" · ")}</div>
              <h2>{active.name}</h2>
              <p className="coords">
                {active.coords.lat.toFixed(6)}, {active.coords.lng.toFixed(6)}
                {active.accuracyMeters != null
                  ? ` · GPS ±${Math.round(active.accuracyMeters)} m`
                  : ""}
              </p>
              <p className="evidence">
                {active.notes || "No observation notes."}
              </p>
              <p className="muted">Your review status: {active.confidence}</p>
              <MediaLibrary site={active} />
              <div className="button-row">
                <button onClick={() => openEntry(active.coords, active)}>
                  Edit
                </button>
                <button className="secondary" onClick={() => {
                  setMeasuring(false);
                  setPoints([]);
                  setMoving(active);
                  setCenter(active.coords);
                  setExpanded(true);
                }}>
                  Move pin on map
                </button>
                <button className="danger" onClick={() => deleteRecord(active)}>
                  Delete device record
                </button>
              </div>
              <div className="analysis">
                <h3>
                  {active.ai?.provider === "gemini"
                    ? "AI hypotheses"
                    : "Documentation checklist"}
                </h3>
                <p className="muted">
                  {active.ai?.provider === "gemini"
                    ? "AI output is not authentication, dating, or proof."
                    : "Keyword-based prompts from your notes. Photos have not been analyzed by this checklist."}
                </p>
                <p className="evidence">{active.ai?.summary}</p>
                <ul>
                  {active.ai?.recommendedNextDocumentation?.map((x, i) => (
                    <li key={i}>{x}</li>
                  ))}
                </ul>
                <button disabled={busy} onClick={() => analyze(active)}>
                  Analyze photos with AI
                </button>
                <h3>Nearby records</h3>
                <p className="muted">
                  Within one mile by straight-line distance. Proximity does not
                  prove a historical connection.
                </p>
                {nearby.length ? (
                  <ul>
                    {nearby.map((n) => (
                      <li key={n.siteId}>
                        <button
                          className="text-button"
                          onClick={() =>
                            show(sites.find((s) => s.id === n.siteId))
                          }
                        >
                          {n.name} · {n.distanceMeters} m
                        </button>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="muted">No nearby records yet.</p>
                )}
                <div className="button-row">
                  <button
                    className="secondary"
                    disabled={busy}
                    onClick={() => cloudSave(active)}
                  >
                    Save cloud copy
                  </button>
                  <button
                    className="secondary"
                    onClick={() => setActiveId(null)}
                  >
                    Close details
                  </button>
                </div>
              </div>
            </section>
          )}
          <section className="panel">
            <div className="eyebrow">YOUR DEVICE NOTEBOOK</div>
            <h2>
              Sites & finds <span className="badge">{sites.length}</span>
            </h2>
            <label>
              Search records
              <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Names, notes, tags…"
              />
            </label>
            <CategoryPicker label="Filter categories" selected={filter} onChange={setFilter} emptyLabel="All categories" />
            <div className="record-list">
              {visible.map((s) => (
                <button key={s.id} className="record" onClick={() => show(s)}>
                  <strong>{s.name}</strong>
                  <span>
                    {siteCategories(s).join(" · ")} · {s.photos.length} photos · {s.videos?.length ?? 0} videos
                  </span>
                  <small>
                    {s.coords.lat.toFixed(5)}, {s.coords.lng.toFixed(5)}
                  </small>
                </button>
              ))}
            </div>
            {!sites.length && (
              <p>Your next discovery starts here. Tap the map or add a find.</p>
            )}
            <p className="muted">
              Stored in this browser profile, without password encryption. Keep
              private backups; anyone using this profile can open its records.
            </p>
            <a href="https://bobsome1.com" target="_blank" rel="noopener">
              A Bobsome1 project ↗
            </a>{" "}
            · <a href="privacy-policy.html">Privacy</a>
          </section>
        </aside>
      </section>
      <dialog ref={entry} className="entry-modal">
        <form onSubmit={save}>
          <div className="modal-head">
            <div>
              <div className="eyebrow">DOCUMENT THE DISCOVERY</div>
              <h2>{editing ? "Edit field record" : "Record what you found"}</h2>
            </div>
            <button
              type="button"
              aria-label="Close editor"
              onClick={() => entry.current.close()}
            >
              ×
            </button>
          </div>
          <div className="form-grid">
            <label>
              Site name
              <input
                autoFocus
                required
                maxLength={120}
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </label>
            <CategoryPicker
              label="Site categories"
              selected={form.categories}
              onChange={(categories) => setForm((previous) => ({ ...previous, categories }))}
              emptyLabel="Choose one or more"
            />
            <label>
              Latitude
              <input
                required
                type="number"
                step="any"
                min="-90"
                max="90"
                value={coords?.lat ?? ""}
                onChange={(e) =>
                  setCoords({
                    ...coords,
                    lat: e.target.value,
                    accuracyMeters: null,
                  })
                }
              />
            </label>
            <label>
              Longitude
              <input
                required
                type="number"
                step="any"
                min="-180"
                max="180"
                value={coords?.lng ?? ""}
                onChange={(e) =>
                  setCoords({
                    ...coords,
                    lng: e.target.value,
                    accuracyMeters: null,
                  })
                }
              />
            </label>
            <label>
              Your review status
              <select
                value={form.confidence}
                onChange={(e) =>
                  setForm({ ...form, confidence: e.target.value })
                }
              >
                {CONFIDENCE_LEVELS.map((c) => (
                  <option key={c}>{c}</option>
                ))}
              </select>
            </label>
            <label>
              Observed tags
              <input
                maxLength={500}
                value={form.observedIndicatorsText}
                onChange={(e) =>
                  setForm({ ...form, observedIndicatorsText: e.target.value })
                }
                placeholder="chert, creek, flakes"
              />
            </label>
          </div>
          <label>
            Evidence notes
            <textarea
              maxLength={6000}
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              placeholder="Describe what you observed. Separate interpretation from measured evidence."
            />
          </label>
          <label className="photo-picker">
            Add photos (up to 5)
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              multiple
              disabled={busy}
              onChange={(e) => {
                addPhotos(Array.from(e.target.files));
                e.target.value = "";
              }}
            />
          </label>
          <label>
            Add videos (up to 3, 4 MB each)
            <input type="file" accept="video/mp4,video/webm" multiple disabled={busy}
              onChange={(event) => {
                addVideos(Array.from(event.target.files));
                event.target.value = "";
              }}
            />
          </label>
          <MediaLibrary site={form} onRemove={(kind, id) =>
            setForm((previous) => ({ ...previous, [kind]: previous[kind].filter((item) => item.id !== id) }))
          } />
          <p className="muted">
            Photos are resized JPEG copies. Videos are included in device backups.
          </p>
          <p role="status">{status}</p>
          <div className="button-row">
            <button disabled={busy} type="submit">
              Save record
            </button>
            <button
              type="button"
              className="secondary"
              onClick={() => entry.current.close()}
            >
              Cancel
            </button>
          </div>
        </form>
      </dialog>
      <dialog ref={settings} className="entry-modal">
        <div className="modal-head">
          <h2>Private cloud settings</h2>
          <button
            aria-label="Close cloud settings"
            onClick={() => settings.current.close()}
          >
            ×
          </button>
        </div>
        {!isFirebaseEnabled() ? (
          <p>
            Cloud backup and AI require your Firebase project and Gemini secret.
            Field records, photos, exports, and the documentation checklist work
            on this device now.
          </p>
        ) : (
          <>
            <p>
              Only the configured owner account can use cloud storage and AI.
            </p>
            {!user ? (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  run(async () => {
                    await cloudLogin(email, password);
                    setUser(cloudUser());
                    setPassword("");
                    setStatus("Owner account signed in.");
                  });
                }}
              >
                <label>
                  Email
                  <input
                    type="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    autoComplete="username"
                  />
                </label>
                <label>
                  Password
                  <input
                    type="password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    autoComplete="current-password"
                  />
                </label>
                <button disabled={busy}>Sign in</button>
              </form>
            ) : (
              <>
                <p>Signed in as {user.email}</p>
                <button
                  disabled={busy}
                  onClick={() =>
                    run(async () => {
                      const cloud = await loadSitesCloud();
                      const added = await mergeSites(cloud);
                      await refresh();
                      setStatus(
                        `${added} cloud records imported. Existing device records kept.`,
                      );
                    })
                  }
                >
                  Import cloud records
                </button>
                <button
                  className="secondary"
                  onClick={() =>
                    run(async () => {
                      await cloudLogout();
                      setUser(null);
                      setStatus("Cloud signed out. Device records remain.");
                    })
                  }
                >
                  Sign out
                </button>
                {active && (
                  <button
                    className="danger"
                    onClick={() =>
                      run(async () => {
                        if (
                          confirm(
                            "Delete this record and its photos from the cloud? The device copy stays.",
                          )
                        ) {
                          await deleteSiteCloud(active.id);
                          setStatus(
                            "Cloud copy deleted. Device copy retained.",
                          );
                        }
                      })
                    }
                  >
                    Delete selected cloud copy
                  </button>
                )}
              </>
            )}
          </>
        )}
        <p className="status" role="status">
          {status}
        </p>
        <p>
          AI sharing sends notes and photos to Gemini only after confirmation.
          Exact coordinate fields and nearby records are omitted; photos and
          written descriptions may still reveal a place.
        </p>
      </dialog>
    </main>
  );
}
