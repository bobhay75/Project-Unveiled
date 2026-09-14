import assert from "node:assert/strict";
import crypto from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, "../..");
const positional = process.argv.slice(2).find(argument => !argument.startsWith("--"));
const base = new URL(positional || process.env.REVENUE_FUNNEL_BASE_URL || "https://bobsome1.com/");
const recordAnalytics = process.argv.includes("--record-analytics");
const cacheKey = `revenue-preflight-${Date.now()}`;

const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
const sha256 = bytes => crypto.createHash("sha256").update(bytes).digest("hex");

async function request(target, options = {}) {
  const url = target instanceof URL ? target : new URL(target, base);
  let lastError;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20_000);
    try {
      const response = await fetch(url, {
        method: options.method || "GET",
        body: options.body,
        headers: {
          "User-Agent": "Project-Unveiled-Revenue-Preflight/2.0",
          ...(options.headers || {})
        },
        cache: "no-store",
        credentials: "omit",
        redirect: options.redirect || "error",
        signal: controller.signal
      });
      clearTimeout(timer);
      if (response.status < 500 || attempt === 2) return response;
      lastError = new Error(`${url.href} returned ${response.status}`);
    } catch (error) {
      clearTimeout(timer);
      lastError = error;
      if (attempt === 2) throw error;
    }
    await wait(400 * (attempt + 1));
  }
  throw lastError;
}

function withCacheKey(route) {
  const url = new URL(route, base);
  url.searchParams.set("preflight", cacheKey);
  return url;
}

function contentTypeIncludes(response, expected, label) {
  const observed = String(response.headers.get("content-type") || "").toLowerCase();
  assert.ok(observed.includes(expected.toLowerCase()), `${label} content type ${observed || "<missing>"} does not include ${expected}`);
}

async function verifyStatic(route, localFile, contentType) {
  const target = withCacheKey(route);
  const [getResponse, headResponse] = await Promise.all([
    request(target),
    request(target, { method: "HEAD" })
  ]);
  assert.equal(getResponse.status, 200, `GET ${route} must return 200`);
  assert.equal(headResponse.status, 200, `HEAD ${route} must return 200`);
  contentTypeIncludes(getResponse, contentType, `GET ${route}`);
  contentTypeIncludes(headResponse, contentType, `HEAD ${route}`);
  const liveBytes = Buffer.from(await getResponse.arrayBuffer());
  const localBytes = fs.readFileSync(path.join(root, localFile));
  assert.equal(sha256(liveBytes), sha256(localBytes), `${route} is not the repository version under test`);
  return { route, bytes: liveBytes.byteLength, sha256: sha256(liveBytes), text: liveBytes.toString("utf8") };
}

function requireMarkers(text, label, markers) {
  for (const marker of markers) {
    assert.ok(text.includes(marker), `${label} is missing required marker: ${marker}`);
  }
}

function checkoutHref(html, eventName) {
  const escaped = eventName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const direct = html.match(new RegExp(`<a[^>]+href="([^"]+)"[^>]+data-pu-event="${escaped}"`, "i"));
  if (direct) return direct[1].replaceAll("&amp;", "&");
  const reverse = html.match(new RegExp(`<a[^>]+data-pu-event="${escaped}"[^>]+href="([^"]+)"`, "i"));
  assert.ok(reverse, `store page has no ${eventName} link`);
  return reverse[1].replaceAll("&amp;", "&");
}

function collectorHeaders(origin) {
  return {
    "Content-Type": "application/json",
    "Origin": origin,
    "Sec-Fetch-Site": "same-origin",
    "Sec-Fetch-Dest": "empty"
  };
}

async function run() {
  const staticSpecs = [
    ["/store/", "store/index.html", "text/html"],
    ["/store/store.css", "store/store.css", "text/css"],
    ["/", "index.html", "text/html"],
    ["/services/", "services/index.html", "text/html"],
    ["/privacy.html", "privacy.html", "text/html"],
    ["/project-unveiled-analytics/tracker.js", "project-unveiled-analytics/tracker.js", "javascript"],
    ["/sitemap.xml", "sitemap.xml", "xml"]
  ];
  const staticResults = await Promise.allSettled(staticSpecs.map(spec => verifyStatic(...spec)));
  const staticFailures = staticResults.flatMap((result, index) => result.status === "rejected" ? [{
    route: staticSpecs[index][0],
    error: result.reason instanceof Error ? result.reason.message : String(result.reason)
  }] : []);
  if (staticFailures.length) {
    throw new Error(`deployed funnel bytes failed verification: ${JSON.stringify(staticFailures)}`);
  }
  const [store, storeCss, home, services, privacy, tracker, sitemap] = staticResults.map(result => result.value);

  requireMarkers(home.text, "homepage", [
    'href="/store/" data-pu-event="store_click"',
    'href="/store/#books" data-pu-event="store_click"',
    "/project-unveiled-analytics/tracker.js?v=5"
  ]);
  requireMarkers(store.text, "store", [
    "Digital edition · $7",
    "PDF and EPUB files for phone, tablet, computer or e-reader",
    "within two business days",
    'data-pu-event="product_checkout_click"',
    "Fixed-scope starter · $250",
    'data-pu-event="service_checkout_click"',
    'data-pu-event="qualified_lead_click"',
    'data-pu-event="partner_inquiry_click"',
    "/project-unveiled-analytics/tracker.js?v=5"
  ]);
  requireMarkers(services.text, "services page", [
    'href="/store/"',
    "Visibility Starter",
    'data-pu-event="service_checkout_click"',
    "/project-unveiled-analytics/tracker.js?v=5"
  ]);
  requireMarkers(privacy.text, "privacy page", [
    "first-party readership and funnel analytics",
    "checkout, service-inquiry, and partner-inquiry links",
    "analytics do not reveal whether a PayPal payment was completed",
    "PayPal processes payment details under its own privacy terms"
  ]);
  requireMarkers(tracker.text, "analytics tracker", [
    "product_checkout_click",
    "service_checkout_click",
    "qualified_lead_click",
    "partner_inquiry_click",
    "engaged_30s",
    "privacySafeTarget"
  ]);
  requireMarkers(sitemap.text, "sitemap", ["<loc>https://bobsome1.com/store/</loc>"]);

  const collectorUrl = withCacheKey("/project-unveiled-analytics/collect.php");
  const invalidPayload = {
    event: "unsupported_preflight_event",
    path: "/store/",
    title: "Rejected revenue preflight",
    session: "rejected-preflight",
    chapter: 0
  };
  const [collectorGet, collectorHead, dashboard, rejectedOrigin, rejectedEvent] = await Promise.all([
    request(collectorUrl),
    request(collectorUrl, { method: "HEAD" }),
    request(withCacheKey("/project-unveiled-analytics/")),
    request(collectorUrl, {
      method: "POST",
      headers: collectorHeaders("https://example.invalid"),
      body: JSON.stringify({ ...invalidPayload, event: "pageview" })
    }),
    request(collectorUrl, {
      method: "POST",
      headers: collectorHeaders(base.origin),
      body: JSON.stringify(invalidPayload)
    })
  ]);

  for (const [label, response] of [["GET collector", collectorGet], ["HEAD collector", collectorHead]]) {
    assert.equal(response.status, 405, `${label} must return 405`);
    assert.equal(response.headers.get("allow"), "POST", `${label} must advertise POST only`);
    assert.equal(response.headers.get("x-pu-analytics-schema"), "revenue-funnel-v1", `${label} is not the revenue-funnel collector`);
  }
  assert.equal(rejectedOrigin.status, 403, "collector must reject a cross-origin write");
  assert.equal(rejectedEvent.status, 422, "collector must reject an unsupported event");
  assert.equal(dashboard.status, 200, "private analytics dashboard must be configured and reachable");
  contentTypeIncludes(dashboard, "text/html", "analytics dashboard");
  requireMarkers(await dashboard.text(), "analytics dashboard login", [
    "Private Traffic Dashboard",
    "Payments remain confirmed separately in PayPal"
  ]);

  const productCheckout = new URL(checkoutHref(store.text, "product_checkout_click"));
  const serviceCheckout = new URL(checkoutHref(store.text, "service_checkout_click"));
  assert.notEqual(productCheckout.hostname, "paypal.me", "$7 checkout must use a fixed-price PayPal Payment Link, not PayPal.Me");
  assert.equal(productCheckout.hostname, "www.paypal.com", "$7 checkout must stay on PayPal");
  assert.match(
    productCheckout.pathname,
    /^\/ncp\/payment\/[A-Za-z0-9]+$/,
    "$7 checkout must use a fixed-price PayPal Payment Link, not PayPal.Me"
  );
  assert.notEqual(productCheckout.href, serviceCheckout.href, "$7 and $250 offers must not share a checkout");

  const [productPayment, servicePayment] = await Promise.all([
    request(productCheckout, { redirect: "follow" }),
    request(serviceCheckout, { redirect: "follow" })
  ]);
  assert.equal(productPayment.status, 200, "$7 PayPal destination must return 200");
  requireMarkers(await productPayment.text(), "$7 PayPal destination", [
    "Project Unveiled",
    '"status":"ACTIVE"',
    '"currency_code","value":"USD"',
    '"amount","value":"7.00"'
  ]);

  assert.equal(serviceCheckout.href, "https://www.paypal.com/ncp/payment/X77R4KF9E2VXC", "$250 destination changed unexpectedly");
  assert.equal(servicePayment.status, 200, "$250 PayPal destination must return 200");
  requireMarkers(await servicePayment.text(), "$250 PayPal destination", [
    "Bobsome1 Visibility Starter",
    '"status":"ACTIVE"',
    '"currency_code","value":"USD"',
    '"amount","value":"250.00"'
  ]);

  let analyticsWrite = "not requested";
  if (recordAnalytics) {
    const session = `revenue-preflight-${Date.now()}`;
    const response = await request(collectorUrl, {
      method: "POST",
      headers: collectorHeaders(base.origin),
      body: JSON.stringify({
        event: "product_checkout_click",
        path: "/store/",
        title: "Revenue funnel controlled preflight",
        session,
        chapter: 0,
        referrer: "",
        source: "internal",
        medium: "preflight",
        campaign: "revenue_launch_preflight",
        content: "synthetic_checkout",
        target: productCheckout.href,
        label: "Synthetic checkout event — not a payment"
      })
    });
    assert.equal(response.status, 200, "collector must persist the controlled checkout event");
    assert.deepEqual(await response.json(), { ok: true }, "collector acknowledgement must be explicit");
    analyticsWrite = session;
  }

  console.log(JSON.stringify({
    gate: "bobsome1-revenue-funnel-live-preflight-v2",
    base_url: base.href,
    repository_static_assets_verified: [home, store, storeCss, services, privacy, tracker, sitemap].length,
    collector_schema: collectorGet.headers.get("x-pu-analytics-schema"),
    collector_boundaries_verified: true,
    analytics_dashboard_configured: true,
    controlled_analytics_write: analyticsWrite,
    payment_destinations_verified: {
      project_unveiled: "$7 USD fixed-price hosted checkout active; merchant, transaction type, shipping behavior, and settlement still require a controlled purchase",
      visibility_starter: "$250 USD fixed-price hosted checkout active"
    },
    result: "pass",
    paid_traffic: "HOLD until the controlled purchase, PDF + EPUB delivery, dashboard visibility, phone/desktop review, and Meta $35 cap are manually confirmed"
  }, null, 2));
}

run().catch(error => {
  console.error(JSON.stringify({
    gate: "bobsome1-revenue-funnel-live-preflight-v2",
    base_url: base.href,
    result: "fail",
    paid_traffic: "HOLD",
    error: error instanceof Error ? error.message : String(error)
  }, null, 2));
  process.exitCode = 1;
});
