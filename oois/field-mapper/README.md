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

## Current Android toolchain
- Node.js 22+
- Capacitor 8.5.2+
- Android compile/target SDK 36 for new Google Play submissions
- Java/JDK 21
- Gemini model default: `gemini-2.5-flash`

## Local run
```bash
cp .env.example .env
npm install
npm run dev
```
Open http://localhost:5173

## Firebase
Create a Firebase project, enable Anonymous Auth, Firestore, and Storage, then fill `.env` and set `VITE_ENABLE_FIREBASE=true`.

Required function environment:
```text
GEMINI_API_KEY=<secret>
GEMINI_MODEL=gemini-2.5-flash
```

## Android
```bash
npm run build
npx cap add android
npx cap sync android
npx cap open android
```

After Capacitor creates the Android project, verify `compileSdkVersion` and `targetSdkVersion` are both 36 before generating the release bundle.

Generate a signed Android App Bundle in Android Studio, upload to Play Console internal testing, complete Data Safety and privacy policy fields, then promote after testing.

Do not commit `.env`, API keys, service account files, `google-services.json`, or signing keystores.
