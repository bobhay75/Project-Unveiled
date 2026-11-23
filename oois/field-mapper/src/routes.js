export const routePointLimit = 250;
const routeLimit = 100;
const routeBytesLimit = 1024 * 1024;

export function validateRoutes(routes) {
  if (!Array.isArray(routes) || routes.length > routeLimit)
    throw Error("Use up to 100 saved routes.");
  const ids = new Set();
  for (const route of routes) {
    if (
      !route ||
      typeof route.id !== "string" ||
      !/^[\w-]{1,80}$/.test(route.id) ||
      ids.has(route.id) ||
      typeof route.name !== "string" ||
      !route.name.trim() ||
      route.name.length > 120 ||
      !Array.isArray(route.points) ||
      route.points.length < 2 ||
      route.points.length > routePointLimit ||
      route.points.some((point) =>
        !point || typeof point.lat !== "number" || typeof point.lng !== "number" ||
        !Number.isFinite(point.lat) || !Number.isFinite(point.lng) ||
        Math.abs(point.lat) > 90 || Math.abs(point.lng) > 180
      )
    )
      throw Error("Invalid saved route.");
    ids.add(route.id);
  }
  const normalized = routes.map(({ id, name, points }) => ({
    id, name, points: points.map(({ lat, lng }) => ({ lat, lng })),
  }));
  if (new TextEncoder().encode(JSON.stringify(normalized)).length > routeBytesLimit)
    throw Error("Saved routes exceed 1 MB. Export a backup before removing routes.");
  return normalized;
}
