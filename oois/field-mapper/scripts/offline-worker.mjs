// Generate one installation-scoped worker for the public application shell.
// Device records stay in IndexedDB; they never enter the service-worker cache.
function installWorker(self, buildId) {
  const scope = new URL(self.registration.scope);
  const namespace = "oois-shell-" + encodeURIComponent(scope.href) + "-";
  const cacheName = namespace + buildId;
  const shell = new Set([
    "", "index.html", "manifest.webmanifest", "privacy-policy.html",
    "icon.svg", "icon-192.png", "icon-512.png",
  ]);
  function isShellUrl(input) {
    const url = new URL(input, scope);
    if (url.origin !== scope.origin || !url.pathname.startsWith(scope.pathname) || url.search || url.hash)
      return false;
    const path = url.pathname.slice(scope.pathname.length);
    return shell.has(path) || /^assets\/[A-Za-z0-9._-]+\.(js|css)$/.test(path);
  }
  function cacheable(response) {
    return response.ok && !response.redirected && response.type !== "opaque" &&
      !/(?:^|,)\s*(?:no-store|no-cache|private)(?:\s|=|,|$)/i.test(response.headers.get("Cache-Control") || "") &&
      (!response.url || isShellUrl(response.url));
  }
  self.addEventListener("install", (event) => {
    event.waitUntil((async () => {
      const response = await fetch(new URL("asset-manifest.json", scope).href, {
        credentials: "omit", cache: "no-store",
      });
      if (!response.ok || response.redirected) throw Error("Offline manifest unavailable.");
      const files = await response.json();
      if (!Array.isArray(files) || !files.length || files.length > 128 ||
          files.some((file) => typeof file !== "string" || !isShellUrl(file)))
        throw Error("Invalid offline asset manifest.");
      const assets = await Promise.all(files.map(async (file) => {
        const url = new URL(file, scope).href;
        const asset = await fetch(url, { credentials: "omit", cache: "reload" });
        if (!cacheable(asset)) throw Error("Offline shell contains a nonpublic response.");
        return [url, asset];
      }));
      const cache = await caches.open(cacheName);
      await Promise.all(assets.map(([url, asset]) => cache.put(url, asset)));
    })());
  });
  self.addEventListener("activate", (event) => {
    event.waitUntil((async () => {
      const keys = await caches.keys();
      await Promise.all(keys.filter((key) => key.startsWith(namespace) && key !== cacheName)
        .map((key) => caches.delete(key)));
      await self.clients.claim();
    })());
  });
  self.addEventListener("fetch", (event) => {
    if (event.request.method !== "GET" || !isShellUrl(event.request.url)) return;
    event.respondWith((async () => {
      try {
        const response = await fetch(event.request);
        if (cacheable(response)) {
          const copy = response.clone();
          event.waitUntil(caches.open(cacheName).then((cache) => cache.put(event.request, copy)));
        }
        return response;
      } catch {
        const cache = await caches.open(cacheName);
        const saved = await cache.match(event.request);
        if (saved) return saved;
        if (event.request.mode === "navigate") {
          const home = await cache.match(new URL("index.html", scope).href);
          if (home) return home;
        }
        return Response.error();
      }
    })());
  });
}

export function offlineWorkerSource(buildId) {
  if (!/^[a-f0-9]{12}$/.test(buildId)) throw Error("Invalid offline build ID.");
  return `(${installWorker.toString()})(self, ${JSON.stringify(buildId)});\n`;
}
