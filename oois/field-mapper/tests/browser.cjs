const { chromium } = require("playwright"),
  assert = require("node:assert/strict"),
  { spawn } = require("node:child_process"),
  fs = require("node:fs"),
  path = require("node:path");
(async () => {
  const server = spawn(
    "python3",
    ["-m", "http.server", "8790", "--bind", "127.0.0.1", "--directory", "dist"],
    { stdio: "ignore" },
  );
  let browser;
  const chooseCategories = async (target, ...categories) => {
    await target.locator("dialog[open] .category-picker summary").click();
    for (const category of categories)
      await target.locator("dialog[open]").getByRole("checkbox", { name: category, exact: true }).check();
  };
  try {
    for (let i = 0; i < 40; i++) {
      try {
        await fetch("http://127.0.0.1:8790");
        break;
      } catch {
        await new Promise((r) => setTimeout(r, 100));
      }
    }
    browser = await chromium.launch({
      executablePath: process.env.ATLAS_TEST_CHROMIUM,
      headless: true,
      args: ["--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage"],
    });
    const context = await browser.newContext({
      viewport: { width: 1440, height: 1000 },
      permissions: ["geolocation"],
      geolocation: { latitude: 36.65, longitude: -93.21, accuracy: 8 },
    });
    await context.route(
      /https:\/\/(server\.arcgisonline\.com|basemap\.nationalmap\.gov)/,
      (r) => r.abort(),
    );
    const page = await context.newPage(),
      errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    page.on("dialog", (d) => d.accept());
    await page.goto("http://127.0.0.1:8790");
    await page
      .getByText("Your next discovery starts here.", { exact: false })
      .waitFor();
    await page.getByLabel("Map layer").selectOption("none");
    const originalHeight = (await page.locator(".map").boundingBox()).height;
    await page.getByRole("button", { name: "Full-screen map", exact: true }).click();
    assert.ok((await page.locator(".map").boundingBox()).height > originalHeight);
    await page.getByRole("button", { name: "Measure distance" }).click();
    await page.locator(".map").click({ position: { x: 225, y: 190 } });
    await page.locator(".map").click({ position: { x: 300, y: 230 } });
    assert.match(await page.locator("output").textContent(), /^[1-9]/);
    await page.getByRole("button", { name: "Save route", exact: true }).click();
    await page.getByLabel("Route name").fill("Test trail");
    await page.locator("dialog[open]").getByRole("button", { name: "Save route" }).click();
    await page.getByRole("button", { name: "Hide route" }).click();
    await page.locator(".map").click({ position: { x: 300, y: 230 } });
    await chooseCategories(page, "Rock Shelter", "Water Source");
    await page.getByLabel("Site name").fill("TEST ONLY — field observation");
    await page
      .getByLabel("Evidence notes")
      .fill("Chert flakes. A workflow test, not a real find.");
    await page.getByLabel("Observed tags").fill("chert, creek");
    const png = Buffer.from(
      "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1S8AAAAASUVORK5CYII=",
      "base64",
    );
    await page
      .getByLabel("Add photos (up to 5)")
      .setInputFiles({
        name: "test-pixel.png",
        mimeType: "image/png",
        buffer: png,
      });
    await page.locator("dialog .photo-thumb img").waitFor();
    if (process.env.OOIS_TEST_VIDEO) {
      await page.getByLabel("Add videos (up to 3, 4 MB each)").setInputFiles(process.env.OOIS_TEST_VIDEO);
      await page.locator("dialog video").waitFor();
    }
    await page
      .getByRole("button", { name: "Save record", exact: true })
      .click();
    await page.locator(".record").waitFor();
    await page.getByRole("button", { name: "Exit full-screen map" }).click();
    await page.reload();
    await page.locator(".record").click();
    assert.equal(await page.locator(".panel .photo-grid img").count(), 1);
    assert.match(await page.locator(".panel .badge").first().textContent(), /Rock Shelter · Water Source/);
    if (process.env.OOIS_TEST_VIDEO) {
      await page.locator(".panel video").waitFor();
      await page.waitForFunction(() => document.querySelector(".panel video")?.readyState >= 1);
    }
    const previousCoords = await page.locator(".panel .coords").textContent();
    await page.getByRole("button", { name: "Move pin on map" }).click();
    await page.locator(".map").click({ position: { x: 300, y: 230 } });
    await page.getByRole("button", { name: "Save record", exact: true }).click();
    await page.getByRole("button", { name: "Exit full-screen map" }).click();
    assert.notEqual(await page.locator(".panel .coords").textContent(), previousCoords);
    await page.getByRole("button", { name: "Edit", exact: true }).click();
    await page.getByLabel("Site name").fill("<img src=x onerror=alert(1)>");
    await page
      .getByRole("button", { name: "Save record", exact: true })
      .click();
    await page.waitForFunction(
      () =>
        document.querySelector(".panel h2")?.textContent ===
        "<img src=x onerror=alert(1)>",
    );
    assert.equal(await page.locator(".panel h2 img").count(), 0);
    await page
      .getByRole("button", { name: "Capture GPS", exact: true })
      .click();
    await page.getByLabel("Site name").fill("TEST ONLY — second find");
    await chooseCategories(page, "Other");
    assert.equal(
      await page.getByLabel("Latitude", { exact: true }).inputValue(),
      "36.65",
    );
    await page
      .getByRole("button", { name: "Save record", exact: true })
      .click();
    await page.waitForFunction(
      () => document.querySelectorAll(".record").length === 2,
    );
    await page.getByLabel("Search records").fill("second");
    assert.equal(await page.locator(".record").count(), 1);
    await page.getByLabel("Search records").fill("");
    const dlp = page.waitForEvent("download");
    await page
      .getByRole("button", { name: "Export backup", exact: true })
      .click();
    const file = await (await dlp).path();
    const backup = JSON.parse(fs.readFileSync(file));
    assert.equal(backup.sites.length, 2);
    assert.equal(backup.routes[0].name, "Test trail");
    assert.equal(backup.routes[0].points.length, 2);
    assert.deepEqual(backup.sites.find((site) => site.photos.length).categories, ["Rock Shelter", "Water Source"]);
    if (process.env.OOIS_TEST_VIDEO) {
      assert.equal(backup.sites.find((site) => site.photos.length).videos.length, 1);
      assert.equal(backup.sites.find((site) => !site.photos.length).videos.length, 0);
    }
    assert.equal(
      backup.sites.reduce((sum, s) => sum + s.photos.length, 0),
      1,
    );
    const context2 = await browser.newContext();
    await context2.route(
      /https:\/\/(server\.arcgisonline\.com|basemap\.nationalmap\.gov)/,
      (r) => r.abort(),
    );
    const restored = await context2.newPage();
    restored.on("dialog", (d) => d.accept());
    await restored.goto("http://127.0.0.1:8790");
    await restored
      .locator('input[accept="application/json,.json"]')
      .setInputFiles(file);
    await restored.waitForFunction(
      () => document.querySelectorAll(".record").length === 2,
    );
    if (process.env.OOIS_TEST_VIDEO) {
      await restored.locator(".record").filter({ hasText: "Water Source" }).click();
      await restored.waitForFunction(() => document.querySelector(".panel video")?.readyState >= 1);
    }
    await restored.getByRole("button", { name: "Test trail", exact: true }).click();
    await restored.getByRole("button", { name: "Hide route" }).waitFor();
    await context2.close();
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.reload();
    await page.waitForFunction(
      () => navigator.serviceWorker.controller !== null,
    );
    await context.setOffline(true);
    await page.reload();
    await page.waitForFunction(
      () => document.querySelectorAll(".record").length === 2,
    );
    await page.getByRole("button", { name: "Test trail", exact: true }).click();
    await page.getByRole("button", { name: "Exit full-screen map" }).click();
    await page.getByRole("button", { name: "Add a find" }).click();
    await page.getByLabel("Site name").fill("Offline save");
    await chooseCategories(page, "Other");
    await page.getByLabel("Latitude", { exact: true }).fill("36.64");
    await page.getByLabel("Longitude", { exact: true }).fill("-93.29");
    await page
      .getByRole("button", { name: "Save record", exact: true })
      .click();
    await page.waitForFunction(
      () => document.querySelectorAll(".record").length === 3,
    );
    await context.setOffline(false);
    await page.getByRole("button", { name: "Delete device record" }).click();
    await page.waitForFunction(
      () => document.querySelectorAll(".record").length === 2,
    );
    await page.getByRole("button", { name: "Cloud settings" }).click();
    await page
      .getByText(
        "Cloud backup and AI require your Firebase project and Gemini secret.",
      )
      .waitFor({ state: "visible" });
    await page.getByRole("button", { name: "Close cloud settings" }).click();
    await page.setViewportSize({ width: 390, height: 844 });
    assert.equal(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      true,
    );
    await page.getByRole("button", { name: "Add a find" }).click();
    await page.keyboard.press("Escape");
    assert.equal(await page.locator("dialog[open]").count(), 0);
    assert.deepEqual(errors, []);
    if (process.env.ATLAS_TEST_ARTIFACTS) {
      fs.mkdirSync(process.env.ATLAS_TEST_ARTIFACTS, { recursive: true });
      await page.screenshot({
        path: path.join(process.env.ATLAS_TEST_ARTIFACTS, "oois-mobile.png"),
        fullPage: true,
      });
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.screenshot({
        path: path.join(process.env.ATLAS_TEST_ARTIFACTS, "oois-desktop.png"),
        fullPage: true,
      });
    }
    console.log(
      "OOIS browser checks passed: map entry, photos, persistence, edit, text injection, GPS, search, route save and restore, offline reload/save, deletion, cloud unavailable state, mobile layout, keyboard dialog.",
    );
  } finally {
    if (browser) await browser.close();
    server.kill();
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
