import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { writeFileSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { createHash } from "node:crypto";
import { offlineWorkerSource } from "./scripts/offline-worker.mjs";
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
        const code = offlineWorkerSource(createHash('sha256').update(readFileSync(resolve('dist/asset-manifest.json'))).digest('hex').slice(0,12));
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
