import { useEffect, useMemo, useState } from 'react'
import { MapContainer, Marker, Popup, TileLayer, useMapEvents } from 'react-leaflet'
import L from 'leaflet'
import { analyzeSiteLocally } from './analyzer'
import { linkNearbySites } from './geo'
import { CONFIDENCE_LEVELS, SITE_TYPES, TYPE_COLORS } from './siteTypes'
import { analyzeSiteCloud, deleteSiteCloud, isFirebaseEnabled, loadSitesCloud, saveSiteCloud } from './firebase'

const STORAGE_KEY = 'oois_sites_v2'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png'
})

function makeTypeIcon(type) {
  const color = TYPE_COLORS[type] || '#525252'
  return L.divIcon({ className: 'oois-marker', html: `<div style="background:${color}" class="oois-marker-dot"></div>`, iconSize: [28, 28], iconAnchor: [14, 14] })
}

function MapClickHandler({ onClick }) {
  useMapEvents({ click(e) { onClick({ lat: Number(e.latlng.lat.toFixed(7)), lng: Number(e.latlng.lng.toFixed(7)) }) } })
  return null
}

function emptyForm() {
  return { name: '', type: 'Rock Shelter', confidence: 'Unverified', notes: '', observedIndicatorsText: '', photos: [], isPrivate: true }
}

export default function App() {
  const [sites, setSites] = useState([])
  const [selectedCoords, setSelectedCoords] = useState(null)
  const [gpsCoords, setGpsCoords] = useState(null)
  const [activeSite, setActiveSite] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [status, setStatus] = useState('')
  const [showEntry, setShowEntry] = useState(false)
  const [mapLayer, setMapLayer] = useState('satellite')

  useEffect(() => {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved) { try { setSites(JSON.parse(saved)) } catch { setSites([]) } }
  }, [])

  useEffect(() => { localStorage.setItem(STORAGE_KEY, JSON.stringify(sites)) }, [sites])

  const center = useMemo(() => gpsCoords ? [gpsCoords.lat, gpsCoords.lng] : [36.6437, -93.2982], [gpsCoords])

  const onMapClick = (coords) => { setSelectedCoords(coords); setShowEntry(true); setActiveSite(null) }

  const captureGPS = () => {
    setStatus('Capturing GPS...')
    if (!navigator.geolocation) { setStatus('GPS is not supported in this browser/device.'); return }
    navigator.geolocation.getCurrentPosition(
      (position) => {
        const next = {
          lat: Number(position.coords.latitude.toFixed(7)),
          lng: Number(position.coords.longitude.toFixed(7)),
          accuracyMeters: position.coords.accuracy ? Number(position.coords.accuracy.toFixed(1)) : null
        }
        setGpsCoords(next); setSelectedCoords(next); setShowEntry(true); setStatus('GPS captured.')
      },
      () => setStatus('GPS permission denied or unavailable.'),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    )
  }

  const handlePhotos = async (fileList) => {
    const files = Array.from(fileList || []).slice(0, 8)
    const encoded = await Promise.all(files.map((file) => new Promise((resolve) => {
      const reader = new FileReader()
      reader.onload = () => resolve({ id: crypto.randomUUID(), name: file.name, type: file.type, dataUrl: reader.result, url: null })
      reader.readAsDataURL(file)
    })))
    setForm((prev) => ({ ...prev, photos: [...prev.photos, ...encoded].slice(0, 12) }))
  }

  const removePhoto = (photoId) => setForm((prev) => ({ ...prev, photos: prev.photos.filter((p) => p.id !== photoId) }))

  const saveAndAnalyze = async () => {
    if (!selectedCoords) { setStatus('Click the map or capture GPS first.'); return }
    if (!form.name.trim()) { setStatus('Add a site name before saving.'); return }

    const observedIndicators = form.observedIndicatorsText.split(',').map((v) => v.trim()).filter(Boolean)
    const now = new Date().toISOString()
    const draftSite = {
      id: crypto.randomUUID(), createdAt: now, updatedAt: now, createdBy: 'local', name: form.name.trim(), type: form.type,
      confidence: form.confidence, coords: selectedCoords, accuracyMeters: selectedCoords.accuracyMeters || null,
      notes: form.notes.trim(), observedIndicators, photos: form.photos, isPrivate: form.isPrivate, isSynced: false, ai: null, linkedSites: []
    }

    const linkedSites = linkNearbySites(draftSite, sites)
    const localAi = analyzeSiteLocally({ ...draftSite, linkedSites }, sites)
    let completed = { ...draftSite, linkedSites, ai: localAi }

    setSites((prev) => [...prev, completed])
    setActiveSite(completed)
    setShowEntry(false)
    setForm(emptyForm())
    setStatus('Saved locally. Running cloud sync/AI if configured...')

    if (isFirebaseEnabled()) {
      try {
        const cloudAi = await analyzeSiteCloud(completed, linkedSites)
        if (cloudAi) completed = { ...completed, ai: { ...localAi, ...cloudAi } }
        const synced = await saveSiteCloud({ ...completed, isSynced: true })
        const finalSite = synced || { ...completed, isSynced: true }
        setSites((prev) => prev.map((s) => s.id === completed.id ? finalSite : s))
        setActiveSite(finalSite)
        setStatus('Saved to cloud and analyzed.')
      } catch (error) { setStatus(`Saved locally. Cloud sync failed: ${error.message}`) }
    } else setStatus('Saved locally. Firebase is not enabled yet.')
  }

  const syncFromCloud = async () => {
    if (!isFirebaseEnabled()) { setStatus('Firebase is not enabled. Fill .env and set VITE_ENABLE_FIREBASE=true.'); return }
    try { setStatus('Loading cloud records...'); const cloudSites = await loadSitesCloud(); setSites(cloudSites); setStatus(`Loaded ${cloudSites.length} cloud record(s).`) }
    catch (error) { setStatus(`Cloud load failed: ${error.message}`) }
  }

  const deleteSite = async (siteId) => {
    if (!confirm('Delete this site from this device and cloud if enabled?')) return
    setSites((prev) => prev.filter((s) => s.id !== siteId))
    if (activeSite?.id === siteId) setActiveSite(null)
    if (isFirebaseEnabled()) { try { await deleteSiteCloud(siteId) } catch {} }
  }

  const exportJson = () => {
    const blob = new Blob([JSON.stringify({ project: 'Ozark Occupation Intelligence System', exportedAt: new Date().toISOString(), sites }, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a'); a.href = url; a.download = 'oois-field-records.json'; a.click(); URL.revokeObjectURL(url)
  }

  const importJson = async (file) => {
    if (!file) return
    const parsed = JSON.parse(await file.text())
    if (!Array.isArray(parsed.sites)) { setStatus('Invalid OOIS export.'); return }
    setSites(parsed.sites); setStatus(`Imported ${parsed.sites.length} site record(s).`)
  }

  const reAnalyze = (site) => {
    const ai = analyzeSiteLocally(site, sites)
    const updated = { ...site, ai, linkedSites: ai.linkedSites }
    setSites((prev) => prev.map((s) => s.id === site.id ? updated : s)); setActiveSite(updated); setStatus('Local analysis refreshed.')
  }

  const tile = mapLayer === 'topo'
    ? { url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', attribution: 'Map data: © OpenStreetMap contributors, SRTM | Map style: © OpenTopoMap' }
    : { url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attribution: 'Tiles © Esri, Maxar, Earthstar Geographics, and GIS User Community' }

  return (
    <main className="app-shell">
      <header className="topbar">
        <div><h1>OOIS Field Mapper</h1><p>Private terrain documentation for shelters, caves, flint sources, lithic scatters, mine indicators, waterfalls, corridors, photos, and AI summaries.</p></div>
        <div className="top-actions">
          <button onClick={captureGPS}>Capture GPS</button>
          <button onClick={exportJson} className="secondary">Export JSON</button>
          <label className="secondary file-button">Import JSON<input type="file" accept="application/json" onChange={(e) => importJson(e.target.files?.[0])} /></label>
          <button onClick={syncFromCloud} className="secondary">Load Cloud</button>
        </div>
      </header>
      {status && <div className="status">{status}</div>}
      <section className="workspace">
        <div className="map-card">
          <div className="card-head"><div><h2>Live Satellite Map</h2><p>Click any location to record what you found there.</p></div>
            <div className="pill-row"><button className={mapLayer === 'satellite' ? 'pill active' : 'pill'} onClick={() => setMapLayer('satellite')}>Satellite</button><button className={mapLayer === 'topo' ? 'pill active' : 'pill'} onClick={() => setMapLayer('topo')}>Topo</button></div>
          </div>
          <div className="map-wrap"><MapContainer center={center} zoom={13} className="map"><TileLayer key={mapLayer} attribution={tile.attribution} url={tile.url} /><MapClickHandler onClick={onMapClick} />
            {selectedCoords && <Marker position={[selectedCoords.lat, selectedCoords.lng]}><Popup>Selected point<br />{selectedCoords.lat}, {selectedCoords.lng}</Popup></Marker>}
            {sites.map((site) => <Marker key={site.id} icon={makeTypeIcon(site.type)} position={[site.coords.lat, site.coords.lng]} eventHandlers={{ click: () => { setActiveSite(site); setShowEntry(false) } }}><Popup><strong>{site.name}</strong><br />{site.type}<br />{site.coords.lat}, {site.coords.lng}</Popup></Marker>)}
          </MapContainer></div>
        </div>
        <aside className="side-panel">
          <div className="panel"><h2>{activeSite ? 'Site Detail + AI' : 'Field Entry'}</h2>{!activeSite && <p className="muted">Click the map or use GPS to start a field record.</p>}
            {activeSite && <div className="detail"><div className="badge">{activeSite.type}</div><h3>{activeSite.name}</h3><p className="coords">{activeSite.coords.lat}, {activeSite.coords.lng}</p><p>{activeSite.notes || 'No notes recorded.'}</p>
              {activeSite.photos?.length > 0 && <div className="photo-grid">{activeSite.photos.map((photo) => <img key={photo.id || photo.url || photo.name} src={photo.url || photo.dataUrl} alt={photo.name || 'Site photo'} />)}</div>}
              <div className="analysis"><h4>AI Field Analysis</h4><div className="rating">{activeSite.ai?.confidenceLabel || 'Not analyzed'}</div><p>{activeSite.ai?.summary}</p>
                {activeSite.ai?.warnings?.length > 0 && <div className="warning">{activeSite.ai.warnings.map((warning) => <p key={warning}>{warning}</p>)}</div>}
                <h4>Linked nearby sites</h4>{activeSite.ai?.linkedSites?.length ? <ul>{activeSite.ai.linkedSites.map((link) => <li key={link.siteId}>{link.name} — {link.type} — {link.distanceMeters}m</li>)}</ul> : <p className="muted">No linked nearby sites yet.</p>}
                <h4>Recommended next documentation</h4><ul>{(activeSite.ai?.recommendedNextDocumentation || []).map((item) => <li key={item}>{item}</li>)}</ul>
              </div>
              <div className="button-row"><button onClick={() => reAnalyze(activeSite)}>Re-analyze</button><button className="danger" onClick={() => deleteSite(activeSite.id)}>Delete</button><button className="secondary" onClick={() => setActiveSite(null)}>New Entry</button></div>
            </div>}
          </div>
          <div className="panel"><h2>Saved Records</h2><p className="muted">{sites.length} site record(s)</p><div className="record-list">{sites.map((site) => <button key={site.id} className="record" onClick={() => setActiveSite(site)}><strong>{site.name}</strong><span>{site.type}</span><small>{site.coords.lat}, {site.coords.lng}</small></button>)}</div></div>
        </aside>
      </section>
      {showEntry && <div className="modal-backdrop" onClick={() => setShowEntry(false)}><div className="entry-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-head"><div><h2>Record what you found here</h2><p>{selectedCoords?.lat}, {selectedCoords?.lng}</p></div><button className="icon-button" onClick={() => setShowEntry(false)}>×</button></div>
        <div className="form-grid"><label>Site name<input value={form.name} placeholder="Salt Mine Shelter" onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} /></label>
          <label>Site type<select value={form.type} onChange={(e) => setForm((p) => ({ ...p, type: e.target.value }))}>{SITE_TYPES.map((type) => <option key={type}>{type}</option>)}</select></label>
          <label>Confidence<select value={form.confidence} onChange={(e) => setForm((p) => ({ ...p, confidence: e.target.value }))}>{CONFIDENCE_LEVELS.map((level) => <option key={level}>{level}</option>)}</select></label>
          <label>Observed indicator tags<input value={form.observedIndicatorsText} placeholder="lithic, sorted rock, soot, water, airflow" onChange={(e) => setForm((p) => ({ ...p, observedIndicatorsText: e.target.value }))} /></label></div>
        <label>Evidence notes<textarea value={form.notes} placeholder="Describe exactly what you observed: material, rock size, sorting, ceiling scars, water relation, field position, photos taken, comparison to nearby caves..." onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} /></label>
        <label className="photo-picker">Add photos<input type="file" accept="image/*" capture="environment" multiple onChange={(e) => handlePhotos(e.target.files)} /></label>
        {form.photos.length > 0 && <div className="photo-grid">{form.photos.map((photo) => <div key={photo.id} className="photo-thumb"><img src={photo.dataUrl} alt={photo.name} /><button onClick={() => removePhoto(photo.id)}>Remove</button></div>)}</div>}
        <label className="check"><input type="checkbox" checked={form.isPrivate} onChange={(e) => setForm((p) => ({ ...p, isPrivate: e.target.checked }))} />Keep this record private by default</label>
        <div className="button-row"><button onClick={saveAndAnalyze}>Save + Analyze</button><button className="secondary" onClick={() => setShowEntry(false)}>Cancel</button></div>
        <p className="legal">Documentation-first: do not trespass, disturb protected sites, remove artifacts unlawfully, or dig without permission, safety planning, and legal authority.</p>
      </div></div>}
    </main>
  )
}
