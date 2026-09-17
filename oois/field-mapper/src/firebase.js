import { initializeApp } from "firebase/app";
import { getAuth, signInWithEmailAndPassword, signOut } from "firebase/auth";
import {
  getFirestore,
  collection,
  doc,
  runTransaction,
  getDocs,
  getDoc,
  deleteDoc,
} from "firebase/firestore";
import {
  getStorage,
  ref,
  uploadBytes,
  getBytes,
  deleteObject,
} from "firebase/storage";
import { getFunctions, httpsCallable } from "firebase/functions";
import { validateSite } from "./data.js";
const config = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
};
const app =
  import.meta.env.VITE_ENABLE_FIREBASE === "true" &&
  config.apiKey &&
  config.projectId
    ? initializeApp(config)
    : null;
const auth = app ? getAuth(app) : null,
  db = app ? getFirestore(app) : null,
  storage = app ? getStorage(app) : null,
  functions = app ? getFunctions(app, "us-central1") : null;
export const isFirebaseEnabled = () => Boolean(app);
export const cloudUser = () => auth?.currentUser || null;
export async function cloudLogin(email, password) {
  if (!auth) throw Error("Cloud is not configured.");
  await signInWithEmailAndPassword(auth, email, password);
  const owner = await getDoc(doc(db, "owners", auth.currentUser.uid));
  if (!owner.exists()) {
    await signOut(auth);
    throw Error("This account is not authorized as an OOIS owner.");
  }
}
export const cloudLogout = () => (auth ? signOut(auth) : Promise.resolve());
function user() {
  const u = cloudUser();
  if (!u) throw Error("Sign in under Cloud settings first.");
  return u;
}
function bytes(data) {
  return Uint8Array.from(atob(data.split(",")[1]), (c) => c.charCodeAt(0));
}
function dataUrl(buffer) {
  let binary = "";
  for (const byte of new Uint8Array(buffer))
    binary += String.fromCharCode(byte);
  return "data:image/jpeg;base64," + btoa(binary);
}
export async function saveSiteCloud(site) {
  validateSite(site);
  const u = user(),
    photos = [],
    paths = [];
  try {
    for (const photo of site.photos) {
      const path = `users/${u.uid}/sites/${site.id}/photos/${crypto.randomUUID()}.jpg`;
      await uploadBytes(ref(storage, path), bytes(photo.dataUrl), {
        contentType: "image/jpeg",
      });
      paths.push(path);
      photos.push({ id: photo.id, name: photo.name, path });
    }
    const target = doc(db, "users", u.uid, "sites", site.id);
    let oldPhotos = [];
    const revision = await runTransaction(db, async (tx) => {
      const previous = await tx.get(target);
      const version = previous.exists()
        ? previous.data().cloudRevision || 0
        : 0;
      if (version !== (site.cloudRevision || 0))
        throw Error(
          "Cloud copy changed on another device. Export both versions before reconciling.",
        );
      oldPhotos = previous.exists() ? previous.data().photos || [] : [];
      const next = {
        ...site,
        photos,
        createdBy: u.uid,
        isPrivate: true,
        cloudRevision: version + 1,
      };
      tx.set(target, next);
      return version + 1;
    });
    await Promise.allSettled(
      oldPhotos.map((p) =>
        p.path ? deleteObject(ref(storage, p.path)) : Promise.resolve(),
      ),
    );
    return { ...site, cloudRevision: revision };
  } catch (error) {
    await Promise.allSettled(paths.map((p) => deleteObject(ref(storage, p))));
    throw error;
  }
}
export async function loadSitesCloud() {
  const u = user();
  const snap = await getDocs(collection(db, "users", u.uid, "sites"));
  if (snap.size > 1000)
    throw Error("Cloud notebook exceeds the device record limit.");
  const records = [];
  for (const d of snap.docs) {
    const s = d.data();
    s.photos = await Promise.all(
      (s.photos || []).map(async (p) => {
        if (
          typeof p.path !== "string" ||
          !p.path.startsWith(`users/${u.uid}/sites/${s.id}/photos/`)
        )
          throw Error("Unexpected cloud photo path.");
        return {
          id: p.id,
          name: p.name,
          type: "image/jpeg",
          dataUrl: dataUrl(await getBytes(ref(storage, p.path), 700000)),
        };
      }),
    );
    validateSite(s);
    records.push(s);
  }
  return records;
}
export async function deleteSiteCloud(id) {
  if (!/^[\w-]{1,80}$/.test(id)) throw Error("Invalid record ID.");
  const u = user(),
    target = doc(db, "users", u.uid, "sites", id);
  const previous = await getDoc(target);
  if (!previous.exists()) return;
  for (const p of previous.data().photos || [])
    if (p.path) await deleteObject(ref(storage, p.path));
  await deleteDoc(target);
}
export async function analyzeSiteCloud(site) {
  user();
  validateSite(site);
  const record = {
    name: site.name,
    type: site.type,
    notes: site.notes,
    observedIndicators: site.observedIndicators,
    photos: site.photos.map((p) => ({ dataUrl: p.dataUrl })),
  };
  const result = await httpsCallable(functions, "analyzeSite", {
    timeout: 65000,
  })({ site: record });
  const ai = result.data;
  if (!ai || ai.provider !== "gemini" || typeof ai.summary !== "string")
    throw Error("AI returned no usable result.");
  return ai;
}
