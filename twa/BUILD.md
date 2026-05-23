# Love Allah Android — Build & Publish

Trusted Web Activity (TWA) wrapping `loveallah.app` as a native Android app.

## Prerequisites

1. **Node.js 18+**
2. **Java JDK 17** (Bubblewrap needs it for the Android build)
   - macOS: `brew install openjdk@17`
   - Windows: download from https://adoptium.net/temurin/releases/?version=17
3. **Android SDK** (cmdline-tools is enough — no need for Android Studio)
   - Bubblewrap will offer to download it on first run
4. **Google Play Console account** ($25 one-time): https://play.google.com/console

## 1. Install Bubblewrap CLI

```bash
npm install -g @bubblewrap/cli
```

Verify: `bubblewrap --version`

## 2. Initialize the TWA project (from this directory)

```bash
cd C:\Users\user\Documents\loveallah\twa
bubblewrap init --manifest=https://loveallah.app/manifest.json --directory=./build
```

Bubblewrap reads your `twa-manifest.json` defaults. Accept the prompts (it'll generate a keystore in `./build/android.keystore`).

> **Keystore safety:** the keystore is the *only* way to sign updates. Back it up to a secure place (1Password / encrypted vault). Lose it and you cannot update the app — you'd have to publish a new package.

## 3. Build the AAB (Android App Bundle for Play Store)

```bash
cd build
bubblewrap build
```

Output: `app-release-signed.aab` — this is what you upload to Google Play Console.

## 4. Get the SHA-256 fingerprint for Digital Asset Links

After the first build:
```bash
bubblewrap fingerprint
```

Or extract from the keystore:
```bash
keytool -list -v -keystore ./build/android.keystore -alias loveallah | grep "SHA256:"
```

You'll get something like: `AA:BB:CC:DD:...:FF` (colon-separated hex).

## 5. Wire the fingerprint into loveallah.app

The PHP plugin already serves `https://loveallah.app/.well-known/assetlinks.json` — it needs the fingerprint:

```bash
# On the production server
wp option update la_android_sha256 "AA:BB:CC:DD:...:FF"
wp option update la_android_package "app.loveallah.app"
```

Verify:
```bash
curl https://loveallah.app/.well-known/assetlinks.json
```

You should see the JSON with `relation`, `target.namespace`, `package_name`, and `sha256_cert_fingerprints`.

## 6. Publish to Google Play

1. Log into https://play.google.com/console
2. **Create app** → Love Allah → choose category (Lifestyle), free
3. Fill out store listing:
   - Short description (80 chars)
   - Full description (4000 chars)
   - Screenshots (at least 2 phone, recommended 8)
   - Feature graphic (1024×500)
   - App icon (use `icon-512.png`)
4. Content rating → fill survey
5. Pricing & distribution → Free, all countries
6. **App content** → Privacy Policy URL: https://loveallah.app/privacy
7. **Release → Production → Create new release**
8. Upload `app-release-signed.aab`
9. Submit for review (usually 1–7 days for new apps)

## 7. After launch — updating the app

For any subsequent release:
1. Bump `appVersionCode` in `twa-manifest.json` (must be higher than previous)
2. Bump `appVersionName` (e.g., `0.5.1`)
3. `bubblewrap update` then `bubblewrap build`
4. Upload the new AAB to Play Console → Production → Create new release

> The TWA itself rarely changes — Bubblewrap only needs rebuilding for icon changes, version bumps, or shortcut changes. **Day-to-day updates ship via the WordPress site** (push to GitHub → auto-deploy via Actions). Users always get the latest web content immediately.

## Troubleshooting

- **"App not installed"**: assetlinks.json mismatch. Check fingerprint matches keystore.
- **App shows browser chrome (URL bar)**: assetlinks.json isn't being served correctly. Hit https://loveallah.app/.well-known/assetlinks.json — should be valid JSON.
- **TWA crashes on launch**: PWA manifest isn't valid. Test at https://loveallah.app/manifest.json.

## Optional: Internal testing track first

In Play Console → **Testing → Internal testing** → upload AAB, add yourself as a tester → install via Play Store opt-in link → verify before production rollout.
