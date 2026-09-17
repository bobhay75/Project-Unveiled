import { initializeApp } from 'firebase/app'
import { getAuth, signInAnonymously, onAuthStateChanged } from 'firebase/auth'
import { getFirestore, collection, doc, setDoc, getDocs, deleteDoc, serverTimestamp } from 'firebase/firestore'
import { getStorage, ref, uploadBytes, getDownloadURL } from 'firebase/storage'
import { getFunctions, httpsCallable } from 'firebase/functions'

const enabled = import.meta.env.VITE_ENABLE_FIREBASE === 'true'
const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
  measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID
}

let app = null, auth = null, db = null, storage = null, functions = null
if (enabled && firebaseConfig.apiKey && firebaseConfig.projectId) {
  app = initializeApp(firebaseConfig)
  auth = getAuth(app)
  db = getFirestore(app)
  storage = getStorage(app)
  functions = getFunctions(app)
}

export function isFirebaseEnabled() { return Boolean(app && auth && db && storage) }

export async function ensureUser() {
  if (!isFirebaseEnabled()) return null
  if (auth.currentUser) return auth.currentUser
  await signInAnonymously(auth)
  return new Promise((resolve) => {
    const unsub = onAuthStateChanged(auth, (user) => { unsub(); resolve(user) })
  })
}

function dataUrlToBlob(dataUrl) {
  const [header, base64] = dataUrl.split(',')
  const mime = header.match(/:(.*?);/)?.[1] || 'image/jpeg'
  const binary = atob(base64)
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i)
  return new Blob([bytes], { type: mime })
}

export async function uploadSitePhotos(site) {
  const user = await ensureUser()
  if (!user) return site.photos || []
  const uploaded = []
  for (const photo of site.photos || []) {
    if (photo.url && photo.url.startsWith('http')) { uploaded.push(photo); continue }
    const blob = dataUrlToBlob(photo.dataUrl)
    const fileRef = ref(storage, `users/${user.uid}/sites/${site.id}/photos/${crypto.randomUUID()}.jpg`)
    await uploadBytes(fileRef, blob, { contentType: blob.type })
    const url = await getDownloadURL(fileRef)
    uploaded.push({ ...photo, url, dataUrl: undefined })
  }
  return uploaded
}

export async function saveSiteCloud(site) {
  const user = await ensureUser()
  if (!user) return null
  const photos = await uploadSitePhotos(site)
  const cloudSite = { ...site, photos, createdBy: user.uid, updatedAt: new Date().toISOString(), syncedAt: serverTimestamp() }
  await setDoc(doc(db, 'users', user.uid, 'sites', site.id), cloudSite)
  return cloudSite
}

export async function loadSitesCloud() {
  const user = await ensureUser()
  if (!user) return []
  const snap = await getDocs(collection(db, 'users', user.uid, 'sites'))
  return snap.docs.map((d) => d.data())
}

export async function deleteSiteCloud(siteId) {
  const user = await ensureUser()
  if (!user) return
  await deleteDoc(doc(db, 'users', user.uid, 'sites', siteId))
}

export async function analyzeSiteCloud(site, nearbySites) {
  if (!functions) return null
  await ensureUser()
  const callable = httpsCallable(functions, 'analyzeSite')
  const result = await callable({ site, nearbySites })
  return result.data
}
