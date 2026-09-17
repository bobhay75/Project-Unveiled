import { before, after, test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { initializeTestEnvironment, assertFails, assertSucceeds } from '@firebase/rules-unit-testing';
import { doc, setDoc, getDoc, deleteDoc, collection, getDocs } from 'firebase/firestore';
import { ref, uploadBytes, getBytes, deleteObject, getMetadata } from 'firebase/storage';

const projectId = 'demo-oois-tests';
// This suite must never fall through to a real Firebase project.
assert.equal(process.env.GCLOUD_PROJECT, projectId);
assert.match(process.env.FIRESTORE_EMULATOR_HOST || '', /^(127\.0\.0\.1|localhost):\d+$/);
assert.match(process.env.FIREBASE_STORAGE_EMULATOR_HOST || '', /^(127\.0\.0\.1|localhost):\d+$/);
let env, owner, otherOwner, outsider, guest;
const site = (id = 'record') => ({ id, createdBy: 'owner-a', isPrivate: true, photos: [], cloudRevision: 1 });
const target = (ctx, id = 'record', uid = 'owner-a') => doc(ctx.firestore(), `users/${uid}/sites/${id}`);
const photo = (ctx, name = 'sample.jpg', uid = 'owner-a') => ref(ctx.storage(), `users/${uid}/sites/record/photos/${name}`);

before(async () => {
  env = await initializeTestEnvironment({
    projectId,
    firestore: { host: '127.0.0.1', port: 8088, rules: readFileSync('../../firestore.rules', 'utf8') },
    storage: { host: '127.0.0.1', port: 9199, rules: readFileSync('../../storage.rules', 'utf8') },
  });
  await env.clearFirestore();
  await env.clearStorage();
  await env.withSecurityRulesDisabled(async ctx => {
    await setDoc(doc(ctx.firestore(), 'owners/owner-a'), {});
    await setDoc(doc(ctx.firestore(), 'owners/owner-b'), {});
  });
  owner = env.authenticatedContext('owner-a');
  otherOwner = env.authenticatedContext('owner-b');
  outsider = env.authenticatedContext('not-authorized');
  guest = env.unauthenticatedContext();
});
after(async () => { await env?.cleanup(); });

test('owner can create, list, read, update and delete their record', async () => {
  await assertSucceeds(setDoc(target(owner), site()));
  assert.equal((await assertSucceeds(getDoc(target(owner)))).data().id, 'record');
  assert.equal((await assertSucceeds(getDocs(collection(owner.firestore(), 'users/owner-a/sites')))).size, 1);
  await assertSucceeds(setDoc(target(owner), { ...site(), cloudRevision: 2 }));
  await assertSucceeds(deleteDoc(target(owner)));
});

test('guest, unapproved account and another approved owner cannot access owner data', async () => {
  await setDoc(target(owner), site());
  for (const ctx of [guest, outsider, otherOwner]) {
    await assertFails(getDoc(target(ctx)));
    await assertFails(getDocs(collection(ctx.firestore(), 'users/owner-a/sites')));
    await assertFails(setDoc(target(ctx), site()));
    await assertFails(deleteDoc(target(ctx)));
  }
  await assertFails(setDoc(target(outsider, 'mine', 'not-authorized'), { ...site('mine'), createdBy: 'not-authorized' }));
});

test('owner cannot publish, spoof ownership, mismatch IDs or exceed photo cap', async () => {
  for (const patch of [{ isPrivate: false }, { createdBy: 'owner-b' }, { id: 'wrong' }, { photos: Array(6).fill({}) }]) {
    await assertFails(setDoc(target(owner, 'invalid'), { ...site('invalid'), ...patch }));
  }
});

test('clients cannot promote owners, enumerate owners, or modify AI quotas', async () => {
  await assertSucceeds(getDoc(doc(owner.firestore(), 'owners/owner-a')));
  for (const ctx of [guest, outsider, owner]) {
    await assertFails(setDoc(doc(ctx.firestore(), 'owners/not-authorized'), {}));
    await assertFails(deleteDoc(doc(ctx.firestore(), 'owners/owner-a')));
    await assertFails(getDocs(collection(ctx.firestore(), 'owners')));
    await assertFails(setDoc(doc(ctx.firestore(), 'aiLimits/owner-a_day'), { used: 0 }));
    await assertFails(getDoc(doc(ctx.firestore(), 'aiLimits/owner-a_day')));
    await assertFails(setDoc(doc(ctx.firestore(), 'other/collection'), {}));
  }
});

test('owner can upload, read and delete their private JPEG', async () => {
  const bytes = new Uint8Array([255, 216, 255, 217]);
  await assertSucceeds(uploadBytes(photo(owner), bytes, { contentType: 'image/jpeg' }));
  assert.deepEqual(new Uint8Array(await assertSucceeds(getBytes(photo(owner)))), bytes);
  await assertSucceeds(deleteObject(photo(owner)));
  await assert.rejects(getMetadata(photo(owner)), error => error.code === 'storage/object-not-found');
});

test('known photo paths do not grant access to guests or other accounts', async () => {
  await uploadBytes(photo(owner), new Uint8Array([255, 216, 255, 217]), { contentType: 'image/jpeg' });
  for (const ctx of [guest, outsider, otherOwner]) {
    await assertFails(getBytes(photo(ctx)));
    await assertFails(uploadBytes(photo(ctx), new Uint8Array([1]), { contentType: 'image/jpeg' }));
    await assertFails(deleteObject(photo(ctx)));
  }
  await assertFails(uploadBytes(photo(outsider, 'mine.jpg', 'not-authorized'), new Uint8Array([1]), { contentType: 'image/jpeg' }));
});

test('storage rejects oversized, non-JPEG and out-of-scope uploads', async () => {
  await assertFails(uploadBytes(photo(owner, 'large.jpg'), new Uint8Array(700001), { contentType: 'image/jpeg' }));
  await assertFails(uploadBytes(photo(owner, 'script.html'), new Uint8Array([1]), { contentType: 'text/html' }));
  await assertFails(uploadBytes(ref(owner.storage(), 'public/sample.jpg'), new Uint8Array([1]), { contentType: 'image/jpeg' }));
});

test('revoking an owner blocks subsequent record and photo access', async () => {
  await env.withSecurityRulesDisabled(ctx => deleteDoc(doc(ctx.firestore(), 'owners/owner-a')));
  await assertFails(getDoc(target(owner)));
  await assertFails(getBytes(photo(owner)));
  await assertFails(setDoc(target(owner, 'revoked'), site('revoked')));
});
