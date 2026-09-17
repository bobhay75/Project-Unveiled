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
for (const permission of ["ACCESS_COARSE_LOCATION", "ACCESS_FINE_LOCATION"])
  if (!manifest.includes("android.permission." + permission))
    manifest = manifest.replace(
      "</manifest>",
      `    <uses-permission android:name="android.permission.${permission}" />\n</manifest>`,
    );
writeFileSync(file, manifest);
console.log(
  "Android source prepared with foreground GPS and automatic OS backup disabled. Signing and device verification remain required.",
);
