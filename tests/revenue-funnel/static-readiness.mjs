import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, "../..");
const allowOwnerLinkPending = process.argv.includes("--allow-owner-link-pending");

const read = relativePath => fs.readFileSync(path.join(root, relativePath), "utf8");
const store = read("store/index.html");
const dashboard = read("project-unveiled-analytics/index.php");
const campaign = read("campaigns/35-dollar-revenue-test.md");
const fulfillment = read("campaigns/digital-edition-fulfillment.md");
const liveGate = read("tests/revenue-funnel/live-preflight.mjs");

function requireMarkers(text, label, markers) {
  for (const marker of markers) {
    assert.ok(text.includes(marker), `${label} is missing required marker: ${marker}`);
  }
}

function checkoutHref(html, eventName) {
  const escaped = eventName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const direct = html.match(new RegExp(`<a[^>]+href="([^"]+)"[^>]+data-pu-event="${escaped}"`, "i"));
  const reverse = html.match(new RegExp(`<a[^>]+data-pu-event="${escaped}"[^>]+href="([^"]+)"`, "i"));
  const matches = html.match(new RegExp(`data-pu-event="${escaped}"`, "gi")) || [];
  assert.equal(matches.length, 1, `store must contain exactly one ${eventName} control`);
  assert.ok(direct || reverse, `store has no href for ${eventName}`);
  return (direct || reverse)[1].replaceAll("&amp;", "&");
}

function validateFixedPaymentLink(url, label) {
  assert.equal(url.protocol, "https:", `${label} must use HTTPS`);
  assert.equal(url.hostname, "www.paypal.com", `${label} must stay on www.paypal.com`);
  assert.match(url.pathname, /^\/ncp\/payment\/[A-Za-z0-9]+$/, `${label} must be a PayPal fixed-price Payment Link`);
  assert.equal(url.username, "", `${label} must not contain URL credentials`);
  assert.equal(url.password, "", `${label} must not contain URL credentials`);
  assert.equal(url.search, "", `${label} must not contain query parameters`);
  assert.equal(url.hash, "", `${label} must not contain a fragment`);
}

function run() {
  requireMarkers(store, "store", [
    "Digital edition · $7",
    "PDF and EPUB files for phone, tablet, computer or e-reader",
    "within two business days",
    'data-pu-event="product_checkout_click"',
    "Fixed-scope starter · $250",
    'data-pu-event="service_checkout_click"'
  ]);

  const productCheckout = new URL(checkoutHref(store, "product_checkout_click"));
  const serviceCheckout = new URL(checkoutHref(store, "service_checkout_click"));
  validateFixedPaymentLink(serviceCheckout, "$250 checkout");
  assert.equal(
    serviceCheckout.href,
    "https://www.paypal.com/ncp/payment/X77R4KF9E2VXC",
    "$250 Visibility Starter destination changed unexpectedly"
  );
  assert.notEqual(productCheckout.href, serviceCheckout.href, "$7 and $250 offers must not share a checkout");

  const ownerLinkPending = productCheckout.hostname === "paypal.me";
  if (ownerLinkPending) {
    assert.equal(
      productCheckout.href,
      "https://paypal.me/Bobsome1975/7USD",
      "unexpected personal-payment placeholder; keep the reviewed URL or supply the fixed Payment Link"
    );
    assert.ok(
      allowOwnerLinkPending,
      "OWNER_ACTION_REQUIRED: replace the $7 PayPal.Me href with the named, fixed-price Project Unveiled $7 Payment Link"
    );
  } else {
    validateFixedPaymentLink(productCheckout, "$7 checkout");
  }

  assert.ok(!dashboard.toLowerCase().includes("paypal.me"), "private campaign shortcuts must not bypass the Store through PayPal.Me");
  requireMarkers(dashboard, "private dashboard", [
    "A checkout click is <strong>not</strong> a completed payment",
    "Confirm in PayPal",
    "https://bobsome1.com/store/?utm_source=facebook"
  ]);
  requireMarkers(campaign, "$35 Meta brief", [
    "Budget: $5/day for 7 days; $35 lifetime maximum",
    "Objective: Traffic",
    "Performance goal: Maximize landing-page views",
    "Do not send ad traffic to a generic person-to-person payment page",
    "Refund the controlled purchase after verification",
    "Only then activate the ad"
  ]);
  requireMarkers(fulfillment, "manual fulfillment procedure", [
    "Project_Unveiled_Print_Ready_Interior_FINAL.pdf",
    "Project_Unveiled_Kindle_FINAL.epub",
    "goods/services purchase",
    "Open both received attachments on the buyer device",
    "Refund the controlled purchase"
  ]);
  requireMarkers(liveGate, "live revenue gate", [
    'assert.notEqual(productCheckout.hostname, "paypal.me"',
    '/^\\/ncp\\/payment\\/[A-Za-z0-9]+$/',
    '"Project Unveiled"',
    '\'"amount","value":"7.00"\'',
    '"Bobsome1 Visibility Starter"',
    '\'"amount","value":"250.00"\'',
    'paid_traffic: "HOLD"'
  ]);

  console.log(JSON.stringify({
    gate: "project-unveiled-revenue-static-readiness-v1",
    result: ownerLinkPending ? "ready_except_owner_payment_link" : "pass",
    owner_action_required: ownerLinkPending
      ? "Supply the named, fixed-price Project Unveiled $7 PayPal Payment Link."
      : null,
    verified: {
      store_contract: true,
      dashboard_direct_paypal_bypass_removed: true,
      visibility_starter_checkout_pinned: true,
      analytics_payment_boundary: true,
      fulfillment_runbook: true,
      meta_lifetime_cap: "$35",
      live_gate_contract: true
    },
    paid_traffic: "HOLD"
  }, null, 2));
}

try {
  run();
} catch (error) {
  console.error(JSON.stringify({
    gate: "project-unveiled-revenue-static-readiness-v1",
    result: "fail",
    paid_traffic: "HOLD",
    error: error instanceof Error ? error.message : String(error)
  }, null, 2));
  process.exitCode = 1;
}
