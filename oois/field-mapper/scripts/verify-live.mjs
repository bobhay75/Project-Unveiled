import assert from "node:assert/strict";
import { createHash, randomUUID } from "node:crypto";
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { initializeApp, deleteApp } from "firebase/app";
import { getAuth, signInWithEmailAndPassword, signOut } from "firebase/auth";
import { deleteDoc, doc, getDoc, getFirestore, setDoc } from "firebase/firestore";
import { deleteObject, getBytes, getStorage, ref, uploadBytes } from "firebase/storage";
import { getFunctions, httpsCallable } from "firebase/functions";

const required = (name) => {
  const result = String(process.env[name] || "").trim();
  if (!result) throw Error(`${name} is required.`);
  return result;
};
const flag = (name) => String(process.env[name] || "").toLowerCase() === "true";
const config = {
  apiKey: required("VITE_FIREBASE_API_KEY"),
  authDomain: required("VITE_FIREBASE_AUTH_DOMAIN"),
  projectId: required("VITE_FIREBASE_PROJECT_ID"),
  storageBucket: required("VITE_FIREBASE_STORAGE_BUCKET"),
  appId: required("VITE_FIREBASE_APP_ID"),
};
const ownerEmail = required("OOIS_OWNER_EMAIL");
const ownerPassword = required("OOIS_OWNER_PASSWORD");
const verifyDenial = flag("OOIS_VERIFY_DENIAL");
const verifyGemini = flag("OOIS_VERIFY_GEMINI");
const deniedEmail = verifyDenial ? required("OOIS_DENIED_EMAIL") : "";
const deniedPassword = verifyDenial ? required("OOIS_DENIED_PASSWORD") : "";
const jpegBase64 =
  "/9j/4AAQSkZJRgABAQAAAAAAAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAACAAIBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==";
const jpeg = Uint8Array.from(Buffer.from(jpegBase64, "base64"));
const ownerApp = initializeApp(config, `oois-live-owner-${randomUUID()}`);
const ownerAuth = getAuth(ownerApp);
const db = getFirestore(ownerApp);
const storage = getStorage(ownerApp);
const functions = getFunctions(ownerApp, "us-central1");
let deniedApp;
let recordRef;
let photoRef;
const receipt = {
  schema: 1,
  checkedAt: new Date().toISOString(),
  projectId: config.projectId,
  storageBucket: config.storageBucket,
  checks: {},
};

async function expectDenied(action, label) {
  try {
    await action();
  } catch (error) {
    if (/permission|unauthorized/i.test(String(error?.code || error?.message))) {
      receipt.checks[label] = "passed";
      return;
    }
    throw error;
  }
  throw Error(`${label} unexpectedly succeeded.`);
}

async function verifyCors(objectPath) {
  for (const origin of ["https://bobsome1.com", "https://localhost"]) {
    const target = `https://storage.googleapis.com/${config.storageBucket}/${objectPath}`;
    const response = await fetch(target, {
      method: "OPTIONS",
      headers: {
        Origin: origin,
        "Access-Control-Request-Method": "GET",
      },
    });
    assert.ok(response.ok, `Storage CORS preflight failed for ${origin}.`);
    assert.equal(
      response.headers.get("access-control-allow-origin"),
      origin,
      `Storage CORS did not return the approved origin ${origin}.`,
    );
  }
  receipt.checks.storageCors = "passed";
}

try {
  const credential = await signInWithEmailAndPassword(
    ownerAuth,
    ownerEmail,
    ownerPassword,
  );
  const ownerUid = credential.user.uid;
  assert.ok((await getDoc(doc(db, "owners", ownerUid))).exists(), "Owner allowlist document is missing.");
  receipt.ownerUidHash = createHash("sha256").update(ownerUid).digest("hex").slice(0, 12);
  receipt.checks.ownerAuthentication = "passed";

  const id = `release-test-${randomUUID()}`;
  const objectPath = `users/${ownerUid}/sites/${id}/photos/${randomUUID()}.jpg`;
  photoRef = ref(storage, objectPath);
  recordRef = doc(db, "users", ownerUid, "sites", id);
  await uploadBytes(photoRef, jpeg, { contentType: "image/jpeg" });
  assert.deepEqual(new Uint8Array(await getBytes(photoRef, 700000)), jpeg);
  receipt.checks.ownerStorage = "passed";

  const now = new Date().toISOString();
  await setDoc(recordRef, {
    id,
    createdAt: now,
    updatedAt: now,
    name: "SYNTHETIC RELEASE VERIFICATION",
    type: "Other",
    confidence: "Unverified",
    coords: { lat: 0, lng: 0 },
    accuracyMeters: null,
    notes: "Synthetic white image used only to verify the release path.",
    observedIndicators: ["synthetic-test"],
    photos: [{ id: randomUUID(), name: "synthetic.jpg", path: objectPath }],
    isPrivate: true,
    isSynced: true,
    cloudRevision: 1,
    ai: null,
    linkedSites: [],
    createdBy: ownerUid,
  });
  assert.equal((await getDoc(recordRef)).data().id, id);
  receipt.checks.ownerFirestore = "passed";
  await verifyCors(objectPath);

  if (verifyGemini) {
    const response = await httpsCallable(functions, "analyzeSite", {
      timeout: 65000,
    })({
      site: {
        name: "SYNTHETIC RELEASE VERIFICATION",
        type: "Other",
        notes: "Synthetic white image. Do not infer a real object or location.",
        observedIndicators: ["synthetic-test"],
        photos: [{ dataUrl: `data:image/jpeg;base64,${jpegBase64}` }],
      },
    });
    assert.equal(response.data?.provider, "gemini");
    assert.equal(typeof response.data?.summary, "string");
    receipt.checks.geminiImage = "passed";
  } else receipt.checks.geminiImage = "skipped";

  if (verifyDenial) {
    deniedApp = initializeApp(config, `oois-live-denied-${randomUUID()}`);
    const deniedAuth = getAuth(deniedApp);
    const deniedCredential = await signInWithEmailAndPassword(
      deniedAuth,
      deniedEmail,
      deniedPassword,
    );
    assert.notEqual(deniedCredential.user.uid, ownerUid, "Denied test account must differ from the owner.");
    await expectDenied(
      () => getDoc(doc(getFirestore(deniedApp), "users", ownerUid, "sites", id)),
      "deniedFirestoreRead",
    );
    await expectDenied(
      () => getBytes(ref(getStorage(deniedApp), objectPath), 700000),
      "deniedStorageRead",
    );
    await signOut(deniedAuth);
  } else {
    receipt.checks.deniedFirestoreRead = "skipped";
    receipt.checks.deniedStorageRead = "skipped";
  }
} finally {
  if (recordRef) await deleteDoc(recordRef).catch(() => {});
  if (photoRef) await deleteObject(photoRef).catch(() => {});
  await signOut(ownerAuth).catch(() => {});
  if (deniedApp) await deleteApp(deniedApp).catch(() => {});
  await deleteApp(ownerApp).catch(() => {});
}

const receiptPath = process.env.OOIS_RECEIPT_PATH
  ? resolve(process.env.OOIS_RECEIPT_PATH)
  : resolve("verification-receipts", `live-${Date.now()}.json`);
mkdirSync(dirname(receiptPath), { recursive: true });
writeFileSync(receiptPath, JSON.stringify(receipt, null, 2) + "\n", {
  mode: 0o600,
});
console.log(`OOIS live verification passed. Receipt: ${receiptPath}`);
