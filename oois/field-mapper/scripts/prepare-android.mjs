import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
if (!existsSync("android"))
  execFileSync("npx", ["cap", "add", "android"], { stdio: "inherit" });
execFileSync("npx", ["cap", "sync", "android"], { stdio: "inherit" });
const file = "android/app/src/main/AndroidManifest.xml";
let manifest = readFileSync(file, "utf8").replace(
  'android:allowBackup="true"',
  'android:allowBackup="false"',
);
if (!manifest.includes("android:usesCleartextTraffic"))
  manifest = manifest.replace(
    'android:allowBackup="false"',
    'android:allowBackup="false"\n        android:usesCleartextTraffic="false"',
  );
for (const permission of ["ACCESS_COARSE_LOCATION", "ACCESS_FINE_LOCATION"])
  if (!manifest.includes("android.permission." + permission))
    manifest = manifest.replace(
      "</manifest>",
      `    <uses-permission android:name="android.permission.${permission}" />\n</manifest>`,
    );
writeFileSync(file, manifest);
// A preview has a separate app identity and cannot replace a signed production app.
// Remove only our own preview settings when preparing a production build again.
const preview = process.argv.includes("--preview");
const gradlePath = "android/app/build.gradle";
let gradle = readFileSync(gradlePath, "utf8")
  .replace(/^\s*applicationIdSuffix "\.preview"\s*$/gm, "")
  .replace(/^\s*versionNameSuffix "-preview"\s*$/gm, "");
const packageVersion = JSON.parse(readFileSync("package.json", "utf8")).version;
const versionCode = process.env.OOIS_VERSION_CODE || "1";
const versionName = process.env.OOIS_VERSION_NAME || packageVersion;
if (versionCode) {
  if (!/^[1-9][0-9]*$/.test(versionCode) || Number(versionCode) > 2100000000)
    throw Error("OOIS_VERSION_CODE must be an integer from 1 through 2100000000.");
  gradle = gradle.replace(/versionCode \d+/, `versionCode ${versionCode}`);
}
if (versionName) {
  if (!/^[0-9]+[.][0-9]+[.][0-9]+(?:-[A-Za-z0-9.-]+)?$/.test(versionName))
    throw Error("OOIS_VERSION_NAME must look like 1.0.0 or 1.0.0-rc.1.");
  gradle = gradle.replace(/versionName "[^"]+"/, `versionName "${versionName}"`);
}
if (preview)
  gradle = gradle.replace("defaultConfig {", 'defaultConfig {\n        applicationIdSuffix ".preview"\n        versionNameSuffix "-preview"');
writeFileSync(gradlePath, gradle);
const stringsPath = "android/app/src/main/res/values/strings.xml";
writeFileSync(stringsPath, readFileSync(stringsPath, "utf8")
  .replaceAll('>OOIS Field Preview<', '>OOIS Field Mapper<')
  .replaceAll('>OOIS Field Mapper<', preview ? '>OOIS Field Preview<' : '>OOIS Field Mapper<'));
console.log(
  `Android source prepared (${preview ? "preview" : "production"} identity, version ${versionName}).`,
);
