#!/usr/bin/env node

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const [inputArgument, outputArgument] = process.argv.slice(2);
if (!inputArgument || !outputArgument) {
  throw new Error('Usage: node dashboard-layout.mjs /absolute/dashboard.html /absolute/output-directory');
}

const inputPath = resolve(inputArgument);
const outputDirectory = resolve(outputArgument);
mkdirSync(outputDirectory, { recursive: true, mode: 0o700 });

function findChrome() {
  const candidates = [
    process.env.CHROME_BIN,
    'google-chrome-stable',
    'google-chrome',
    'chromium',
    'chromium-browser',
  ].filter(Boolean);
  for (const candidate of candidates) {
    try {
      return execFileSync('which', [candidate], { encoding: 'utf8' }).trim();
    } catch {
      // Try the next GitHub-hosted runner browser name.
    }
  }
  throw new Error('A supported Chrome or Chromium binary is required for dashboard layout evidence.');
}

const chrome = findChrome();
const original = readFileSync(inputPath, 'utf8');
const instrumentation = String.raw`<script>
window.addEventListener('load', function () {
  var shown = function (element) {
    var style = window.getComputedStyle(element);
    var rect = element.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
  };
  var focusables = Array.prototype.slice.call(document.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled])')).filter(shown);
  var focusFailures = [];
  var unnamedControls = [];
  focusables.forEach(function (element, index) {
    element.focus();
    if (document.activeElement !== element) focusFailures.push(index);
    var name = (element.getAttribute('aria-label') || element.textContent || element.getAttribute('value') || '').trim();
    if (!name && element.getAttribute('type') !== 'hidden') unnamedControls.push(index);
  });
  var overflows = Array.prototype.slice.call(document.querySelectorAll('header *, main *')).filter(shown).filter(function (element) {
    var rect = element.getBoundingClientRect();
    return rect.left < -1 || rect.right > window.innerWidth + 1;
  }).slice(0, 20).map(function (element) {
    return element.tagName.toLowerCase() + (element.className ? '.' + String(element.className).trim().replace(/\s+/g, '.') : '');
  });
  var columns = function (selector) {
    var element = document.querySelector(selector);
    return element ? window.getComputedStyle(element).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length : 0;
  };
  var result = {
    innerWidth: window.innerWidth,
    documentWidth: document.documentElement.scrollWidth,
    horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    overflowingElements: overflows,
    gridColumns: columns('.grid'),
    sectionColumns: columns('.sections'),
    campaignColumns: columns('.campaign'),
    focusableCount: focusables.length,
    focusFailures: focusFailures,
    unnamedControls: unnamedControls,
    cardLabels: Array.prototype.slice.call(document.querySelectorAll('.card .label')).map(function (element) { return element.textContent.trim(); }),
    noticeCount: document.querySelectorAll('.notice').length
  };
  var evidence = document.createElement('pre');
  evidence.id = 'dashboard-layout-result';
  evidence.hidden = true;
  evidence.textContent = btoa(JSON.stringify(result));
  document.body.appendChild(evidence);
});
</script>`;

if (!original.includes('</body>')) {
  throw new Error('Synthetic dashboard render has no closing body element.');
}

const instrumented = original.replace('</body>', `${instrumentation}</body>`);
const cases = [
  { name: 'mobile', width: 360, height: 2400, gridColumns: 2, sectionColumns: 1, campaignColumns: 1 },
  { name: 'desktop', width: 1365, height: 1800, gridColumns: 4, sectionColumns: 2, campaignColumns: 3 },
];
const expectedLabels = [
  'Journey landing sessions',
  'Signup selections',
  'Check-email-page sessions',
  'Welcome-page sessions',
  'Journey share-button clicks',
];

for (const review of cases) {
  const caseHtml = join(outputDirectory, `dashboard-${review.name}.html`);
  const screenshot = join(outputDirectory, `dashboard-${review.name}.png`);
  const resultPath = join(outputDirectory, `dashboard-${review.name}-result.json`);
  writeFileSync(caseHtml, instrumented, { encoding: 'utf8', mode: 0o600 });
  const url = pathToFileURL(caseHtml).href;
  const common = [
    '--headless=new',
    '--no-sandbox',
    '--disable-gpu',
    '--disable-dev-shm-usage',
    '--hide-scrollbars',
    '--run-all-compositor-stages-before-draw',
    '--force-device-scale-factor=1',
    `--window-size=${review.width},${review.height}`,
    '--virtual-time-budget=1500',
  ];
  const dump = spawnSync(chrome, [...common, '--dump-dom', url], {
    encoding: 'utf8',
    maxBuffer: 20 * 1024 * 1024,
  });
  if (dump.status !== 0) {
    throw new Error(`${basename(chrome)} failed to render ${review.name}: ${dump.stderr}`);
  }
  const match = dump.stdout.match(/<pre\b[^>]*\bid="dashboard-layout-result"[^>]*>([A-Za-z0-9+/=]+)<\/pre>/);
  if (!match) {
    throw new Error(`The ${review.name} dashboard did not emit layout evidence.`);
  }
  const result = JSON.parse(Buffer.from(match[1], 'base64').toString('utf8'));
  const failures = [];
  if (result.innerWidth !== review.width) failures.push(`viewport width was ${result.innerWidth}, expected ${review.width}`);
  if (result.horizontalOverflow) failures.push(`document width ${result.documentWidth} exceeds viewport width ${result.innerWidth}`);
  if (result.overflowingElements.length) failures.push(`elements crossed the viewport: ${result.overflowingElements.join(', ')}`);
  if (result.gridColumns !== review.gridColumns) failures.push(`metric grid had ${result.gridColumns} columns, expected ${review.gridColumns}`);
  if (result.sectionColumns !== review.sectionColumns) failures.push(`detail grid had ${result.sectionColumns} columns, expected ${review.sectionColumns}`);
  if (result.campaignColumns !== review.campaignColumns) failures.push(`campaign row had ${result.campaignColumns} columns, expected ${review.campaignColumns}`);
  if (result.focusableCount < 10) failures.push(`only ${result.focusableCount} keyboard-focusable controls were found`);
  if (result.focusFailures.length) failures.push(`controls failed programmatic focus at indexes ${result.focusFailures.join(', ')}`);
  if (result.unnamedControls.length) failures.push(`controls lacked accessible names at indexes ${result.unnamedControls.join(', ')}`);
  if (result.noticeCount < 2) failures.push('measurement-limit notices were not rendered');
  for (const label of expectedLabels) {
    if (!result.cardLabels.includes(label)) failures.push(`missing metric card: ${label}`);
  }
  if (failures.length) {
    throw new Error(`${review.name} dashboard review failed:\n- ${failures.join('\n- ')}`);
  }
  writeFileSync(resultPath, `${JSON.stringify(result, null, 2)}\n`, { encoding: 'utf8', mode: 0o600 });

  const capture = spawnSync(chrome, [...common, `--screenshot=${screenshot}`, url], {
    encoding: 'utf8',
    maxBuffer: 4 * 1024 * 1024,
  });
  if (capture.status !== 0 || !existsSync(screenshot) || statSync(screenshot).size < 10_000) {
    throw new Error(`${basename(chrome)} did not create the ${review.name} evidence screenshot: ${capture.stderr}`);
  }
  console.log(
    `Dashboard ${review.name} review passed at ${result.innerWidth}px: no horizontal overflow, `
      + `${result.gridColumns}-column metric grid, ${result.focusableCount} focusable controls.`,
  );
}
