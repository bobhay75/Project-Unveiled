export function metersBetween(a, b) {
  const R = 6371000;
  const toRad = (v) => (v * Math.PI) / 180;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const lat1 = toRad(a.lat);
  const lat2 = toRad(b.lat);
  const x =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.atan2(Math.sqrt(x), Math.sqrt(Math.max(0, 1 - x)));
}

export function linkNearbySites(site, allSites, radiusMeters = 1609.34) {
  return allSites
    .filter((s) => s.id !== site.id)
    .map((s) => ({
      siteId: s.id,
      name: s.name,
      type: s.type,
      distanceMeters: Math.round(metersBetween(site.coords, s.coords)),
      reason:
        "Within one mile by straight-line distance; relationship unverified.",
    }))
    .filter((s) => s.distanceMeters <= radiusMeters)
    .sort((a, b) => a.distanceMeters - b.distanceMeters)
    .slice(0, 10);
}
