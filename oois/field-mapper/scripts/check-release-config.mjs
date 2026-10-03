import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const errors = [];
const required = [
  "VITE_FIREBASE_API_KEY",
  "VITE_FIREBASE_AUTH_DOMAIN",
  "VITE_FIREBASE_PROJECT_ID",
  "VITE_FIREBASE_STORAGE_BUCKET",
  "VITE_FIREBASE_APP_ID",
];
const value = (name) => String(process.env[name] || "").trim();
const placeholder = (input) =>
  !input || /^(change|replace|example|placeholder|todo|your[-_])/i.test(input);
const cloudEnabled = value("VITE_ENABLE_FIREBASE") === "true";
const allowCloudDisabled = process.argv.includes("--allow-cloud-disabled");

if (!cloudEnabled && !allowCloudDisabled)
  errors.push("VITE_ENABLE_FIREBASE must be true for an internal release.");
if (cloudEnabled) {
  for (const name of required)
    if (placeholder(value(name))) errors.push(`${name} is missing or a placeholder.`);

  const projectId = value("VITE_FIREBASE_PROJECT_ID");
  if (projectId && !/^[a-z][a-z0-9-]{4,28}[a-z0-9]$/.test(projectId))
    errors.push("VITE_FIREBASE_PROJECT_ID is not a valid Google Cloud project ID.");
  if (
    projectId &&
    value("VITE_FIREBASE_AUTH_DOMAIN") !== `${projectId}.firebaseapp.com`
  )
    errors.push("VITE_FIREBASE_AUTH_DOMAIN must match the dedicated project ID.");
  if (
    projectId &&
    ![
      `${projectId}.firebasestorage.app`,
      `${projectId}.appspot.com`,
    ].includes(value("VITE_FIREBASE_STORAGE_BUCKET"))
  )
    errors.push("VITE_FIREBASE_STORAGE_BUCKET must be this project's default bucket.");
  if (
    value("VITE_FIREBASE_APP_ID") &&
    !/^1:[0-9]+:web:[A-Za-z0-9]+$/.test(value("VITE_FIREBASE_APP_ID"))
  )
    errors.push("VITE_FIREBASE_APP_ID is not a Firebase Web App ID.");
  if (
    value("OOIS_EXPECTED_PROJECT_ID") &&
    value("OOIS_EXPECTED_PROJECT_ID") !== projectId
  )
    errors.push("Firebase project ID does not match OOIS_EXPECTED_PROJECT_ID.");
}

for (const [name, raw] of Object.entries(process.env))
  if (
    name.startsWith("VITE_") &&
    /(SECRET|PRIVATE|SERVICE_ACCOUNT|GEMINI)/.test(name) &&
    String(raw || "").trim()
  )
    errors.push(`${name} must not be bundled into the client application.`);

const capacitor = JSON.parse(readFileSync(resolve("capacitor.config.json"), "utf8"));
if (capacitor.appId !== "com.bobsome1.oois")
  errors.push("Production Android app ID must remain com.bobsome1.oois.");
if (capacitor.appName !== "OOIS Field Mapper")
  errors.push("Production Android app name must remain OOIS Field Mapper.");

const cors = JSON.parse(readFileSync(resolve("storage.cors.json"), "utf8"));
const origins = new Set(cors.flatMap((entry) => entry.origin || []));
for (const origin of ["https://bobsome1.com", "https://localhost"])
  if (!origins.has(origin)) errors.push(`Storage CORS is missing ${origin}.`);
if (origins.has("*")) errors.push("Storage CORS must not allow every origin.");

const privacy = readFileSync(resolve("public/privacy-policy.html"), "utf8");
for (const text of [
  "thebobsomest1@gmail.com",
  "precise",
  "Firebase",
  "Gemini",
  "delete",
])
  if (!privacy.toLowerCase().includes(text.toLowerCase()))
    errors.push(`Privacy policy is missing required disclosure: ${text}.`);

const versionCode = value("OOIS_VERSION_CODE");
const versionName = value("OOIS_VERSION_NAME");
if (versionCode && (!/^[1-9][0-9]*$/.test(versionCode) || Number(versionCode) > 2100000000))
  errors.push("OOIS_VERSION_CODE must be an integer from 1 through 2100000000.");
if (versionName && !/^[0-9]+[.][0-9]+[.][0-9]+(?:-[A-Za-z0-9.-]+)?$/.test(versionName))
  errors.push("OOIS_VERSION_NAME must look like 1.0.0 or 1.0.0-rc.1.");

if (errors.length) {
  console.error("OOIS release configuration failed:\n- " + errors.join("\n- "));
  process.exit(1);
}

console.log(
  cloudEnabled
    ? `OOIS release configuration passed for ${value("VITE_FIREBASE_PROJECT_ID")}.`
    : "OOIS device-only release configuration passed; cloud features are disabled.",
);
