import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { writeFileSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { createHash } from "node:crypto";
export default defineConfig({
  base: "./",
  plugins: [
    react(),
    {
      name: "oois-offline-shell",
      transformIndexHtml: {
        order: "post",
        handler: (html) =>
          html.replace(
            /href="[^"]*manifest-[^"]*\.webmanifest"/g,
            'href="./manifest.webmanifest"',
          ),
      },
      closeBundle() {
        const index = resolve("dist/index.html");
        writeFileSync(
          index,
          readFileSync(index, "utf8").replace(
            /href="[^\"]*manifest-[^\"]*\.webmanifest"/g,
            'href="./manifest.webmanifest"',
          ),
        );
        // The generated worker caches only hashed build assets and public shell files.
        const code = `const CACHE='oois-shell-${createHash('sha256').update(readFileSync(resolve('dist/asset-manifest.json'))).digest('hex').slice(0,12)}';self.addEventListener('install',e=>e.waitUntil(fetch('asset-manifest.json').then(r=>r.json()).then(files=>caches.open(CACHE).then(c=>c.addAll(files)))));self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('oois-shell-')&&k!==CACHE).map(k=>caches.delete(k))))));self.addEventListener('fetch',e=>{const u=new URL(e.request.url),scope=new URL(self.registration.scope);if(e.request.method!=='GET'||u.origin!==scope.origin||!u.pathname.startsWith(scope.pathname))return;const p=u.pathname.slice(scope.pathname.length);if(!['','index.html','manifest.webmanifest','privacy-policy.html','icon.svg','icon-192.png','icon-512.png'].includes(p)&&!/^assets\\/[^/]+\\.(js|css)$/.test(p))return;e.respondWith(fetch(e.request).then(r=>{if(r.ok){const copy=r.clone();caches.open(CACHE).then(c=>c.put(e.request,copy));}return r;}).catch(()=>caches.match(e.request).then(r=>r||caches.match('index.html'))));});`;
        writeFileSync(resolve("dist/sw.js"), code);
      },
      generateBundle(_, bundle) {
        this.emitFile({
          type: "asset",
          fileName: "asset-manifest.json",
          source: JSON.stringify([
            "./",
            "index.html",
            "manifest.webmanifest",
            "privacy-policy.html",
            "icon.svg",
            "icon-192.png",
            "icon-512.png",
            ...Object.keys(bundle).filter((p) => /\.(js|css)$/.test(p)),
          ]),
        });
      },
    },
  ],
  build: { outDir: "dist", sourcemap: false },
  server: { host: "127.0.0.1", port: 5173 },
});
