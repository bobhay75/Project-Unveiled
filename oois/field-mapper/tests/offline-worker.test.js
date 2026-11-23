import { test } from "node:test";
import assert from "node:assert/strict";
import vm from "node:vm";
import { offlineWorkerSource } from "../scripts/offline-worker.mjs";

const scope = "https://example.test/oois/app/";
const namespace = (url = scope) => "oois-shell-" + encodeURIComponent(url) + "-";
const current = namespace() + "0123456789ab";
const response = (body = "public shell", headers = {}) => new Response(body, { headers });

function fixture() {
  const handlers = new Map(), stores = new Map(), calls = [], removed = [];
  let onlineFetch = async () => response();
  const key = (request) => typeof request === "string" ? request : request.url;
  const cacheStorage = {
    async open(name) {
      if (!stores.has(name)) stores.set(name, new Map());
      const store = stores.get(name);
      return {
        async put(request, value) { store.set(key(request), value.clone()); },
        async match(request) { return store.get(key(request))?.clone(); },
      };
    },
    async keys() { return [...stores.keys()]; },
    async delete(name) { removed.push(name); return stores.delete(name); },
    async match() { throw Error("Global cache lookup is forbidden."); },
  };
  const self = {
    registration: { scope },
    clients: { async claim() {} },
    addEventListener(name, callback) { handlers.set(name, callback); },
  };
  vm.runInNewContext(offlineWorkerSource("0123456789ab"), {
    self, caches: cacheStorage, URL, Response, Set,
    fetch: async (request, options) => {
      calls.push({ url: key(request), options });
      return onlineFetch(request, options);
    },
  });
  return {
    stores, calls, removed, cacheStorage,
    network(fn) { onlineFetch = fn; },
    async event(name) {
      const pending = [];
      handlers.get(name)({ waitUntil(promise) { pending.push(promise); } });
      await Promise.all(pending);
    },
    async request(path, { method = "GET", mode = "cors" } = {}) {
      const pending = [];
      let result;
      handlers.get("fetch")({
        request: { url: new URL(path, scope).href, method, mode },
        waitUntil(promise) { pending.push(promise); },
        respondWith(promise) { result = promise; },
      });
      if (!result) return null;
      const value = await result;
      await Promise.all(pending);
      return value;
    },
  };
}

test("installation precaches only scoped public assets without credentials", async () => {
  const f = fixture();
  f.network(async (request) => String(request).endsWith("asset-manifest.json")
    ? Response.json(["./", "index.html", "assets/index-123.js"]) : response());
  await f.event("install");
  assert.equal(f.stores.get(current).size, 3);
  assert.ok(f.calls.every(({ options }) => options.credentials === "omit"));
  assert.equal(f.calls[0].options.cache, "no-store");
  assert.ok(f.calls.slice(1).every(({ options }) => options.cache === "reload"));
});

test("installation rejects unknown, cross-origin and credential-bearing manifest paths", async () => {
  for (const path of ["records.json", "../owner/", "index.html?token=private", "https://other.test/index.html"]) {
    const f = fixture();
    f.network(async () => Response.json([path]));
    await assert.rejects(() => f.event("install"), /Invalid offline/);
    assert.equal(f.stores.size, 0);
  }
});

test("private responses cannot enter the precache", async () => {
  const f = fixture();
  f.network(async (request) => String(request).endsWith("asset-manifest.json")
    ? Response.json(["index.html"])
    : response("private", { "Cache-Control": "no-store, max-age=0" }));
  await assert.rejects(() => f.event("install"), /nonpublic response/);
  assert.equal(f.stores.size, 0);
});

test("activation retires only this installation's older caches", async () => {
  const f = fixture();
  const old = namespace() + "aaaaaaaaaaaa";
  const staging = namespace("https://example.test/oois/stage/") + "aaaaaaaaaaaa";
  for (const name of [current, old, staging, "oois-shell-legacy", "kcmc-public"])
    await f.cacheStorage.open(name);
  await f.event("activate");
  assert.deepEqual(f.removed, [old]);
  assert.ok(f.stores.has(staging));
  assert.ok(f.stores.has("oois-shell-legacy"));
});

test("private paths, query strings and non-GET requests stay outside the worker", async () => {
  const f = fixture();
  for (const path of ["records.json", "index.html?token=private", "assets/a.js?secret=private", "../owner/", "https://other.test/index.html"])
    assert.equal(await f.request(path), null, path);
  assert.equal(await f.request("index.html", { method: "POST" }), null);
  assert.equal(f.calls.length, 0);
});

test("a public runtime response is saved before the fetch lifetime completes", async () => {
  const f = fixture();
  f.network(async () => response("current asset"));
  const result = await f.request("assets/app-new.js");
  assert.equal(await result.text(), "current asset");
  assert.equal(await f.stores.get(current).get(scope + "assets/app-new.js").text(), "current asset");
});

test("private, no-cache and redirected runtime responses are never saved", async () => {
  for (const control of ["private, max-age=60", "no-store", "no-cache=Set-Cookie"]) {
    const f = fixture();
    f.network(async () => response("private", { "Cache-Control": control }));
    assert.equal((await f.request("index.html")).status, 200);
    assert.equal(f.stores.size, 0);
  }
  const f = fixture();
  const redirected = response("sign-in");
  Object.defineProperty(redirected, "redirected", { value: true });
  f.network(async () => redirected);
  await f.request("index.html");
  assert.equal(f.stores.size, 0);
});

test("offline lookup cannot use a different build or installation cache", async () => {
  const f = fixture();
  f.network(async () => { throw Error("offline"); });
  const old = await f.cacheStorage.open(namespace() + "aaaaaaaaaaaa");
  await old.put(scope + "index.html", response("old unrelated shell"));
  const failed = await f.request("index.html", { mode: "navigate" });
  assert.equal(failed.type, "error");
  const cache = await f.cacheStorage.open(current);
  await cache.put(scope + "index.html", response("current shell"));
  const home = await f.request("", { mode: "navigate" });
  assert.equal(await home.text(), "current shell");
  const script = await f.request("assets/missing.js");
  assert.equal(script.type, "error", "missing JavaScript must not receive HTML");
});

test("invalid build identifiers are refused", () => {
  assert.throws(() => offlineWorkerSource("unreviewed"), /Invalid offline build/);
});
