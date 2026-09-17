# OOIS Field Mapper

Ozark Occupation Intelligence System production starter.

## Features
- Live satellite and topo maps
- Tap any location to create a record
- GPS capture
- Photos and notes
- Local evidence analysis
- Nearby-site linking within one mile
- JSON export/import
- Firebase-ready Auth, Firestore and Storage
- Gemini analysis through Firebase Cloud Functions
- Capacitor Android wrapper configuration

## Local run
```bash
cp .env.example .env
npm install
npm run dev
```
Open http://localhost:5173

## Firebase
Create a Firebase project, enable Anonymous Auth, Firestore, and Storage, then fill `.env` and set `VITE_ENABLE_FIREBASE=true`.

## Android
```bash
npm run build
npx cap add android
npx cap sync android
npx cap open android
```
Generate a signed Android App Bundle in Android Studio, upload to Play Console internal testing, complete Data Safety and privacy policy fields, then promote after testing.

Do not commit `.env`, API keys, service account files, or signing keystores.
