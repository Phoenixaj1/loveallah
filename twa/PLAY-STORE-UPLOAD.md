# Love Allah → Google Play Console upload

After `bubblewrap build` finishes you'll have `twa/build/app-release-signed.aab`. Follow this to publish.

## Prerequisites (one-time)

1. **Play Console developer account** — $25 one-time fee at https://play.google.com/console
2. **AAB file** — produced by Bubblewrap (see step-by-step in this file)
3. **App icon** 512×512 — `plugins/loveallah/assets/icons/icon-512.png`
4. **Feature graphic** 1024×500 — `twa/store-assets/feature_graphic.png`
5. **2–8 phone screenshots** — see `twa/store-assets/SCREENSHOT-GUIDE.md`
6. **Privacy Policy URL** — `https://loveallah.app/privacy` (exists)
7. **Store description** — from `twa/STORE-LISTING.md`

## Step 1 — Confirm AAB is built

```bash
ls -la C:\Users\user\Documents\loveallah\twa\build\app-release-signed.aab
```

Should be ~3 MB. If missing, see the bubblewrap build section below.

## Step 2 — Get the SHA-256 fingerprint

After the build, this is REQUIRED so the TWA opens without a Chrome URL bar:

```bash
cd C:\Users\user\Documents\loveallah\twa\build
bubblewrap fingerprint
```

You'll get a string like `AA:BB:CC:DD:...:FF` (32 hex pairs separated by colons).

## Step 3 — Wire SHA-256 into production

```bash
ssh -i ../deploy_key -p 22 -o StrictHostKeyChecking=no master_kczyayftkf@142.93.33.182 \
  "cd /home/master/applications/eddftjqqbg/public_html && wp option update la_android_sha256 'YOUR:SHA:HERE'"
```

Verify it landed in the assetlinks endpoint:

```bash
curl https://loveallah.app/.well-known/assetlinks.json
```

Should now show your package + sha256.

## Step 4 — Backup your keystore

**THE KEYSTORE IS THE ONLY WAY TO UPDATE THIS APP FOREVER. LOSE IT AND YOU CANNOT UPDATE.**

The keystore is at `twa/build/android.keystore`. Copy it to:
- 1Password or another encrypted vault
- A USB stick stored offline
- Email it to yourself encrypted

Bubblewrap will also have printed the keystore password during init — note that down too.

## Step 5 — Create the Play Console app

1. Go to https://play.google.com/console
2. **Create app**
   - App name: **Love Allah**
   - Default language: **English (UK)**
   - App or game: **App**
   - Free or paid: **Free**
   - Declarations: tick both
3. Click **Create app**

## Step 6 — Fill the dashboard tasks

In order on the left sidebar:

### Set up your app

- **App access** → "All functionality is available without restrictions" (no login wall)
- **Ads** → "No, my app does not contain ads" (it doesn't currently — change later when ads ship)
- **Content rating** → fill the questionnaire (this is a religious content / lifestyle app, no violence, no UGC)
- **Target audience** → 13 and over
- **News app** → No
- **COVID-19 contact tracing** → No
- **Data safety** → fill the form:
  - Collects: **Email address** (optional, for newsletter), **Approximate location** (only if user grants permission), **App interactions** (analytics)
  - Shared with third parties: **None**
  - All collected data is encrypted in transit (HTTPS)
  - User can request deletion: **Yes** (via hello@loveallah.app)
- **Government apps** → No

### Store listing

- **App name**: `Love Allah`
- **Short description** (80 chars): `Prayer times, daily dhikr, nasheeds — your masjid in your pocket.`
- **Full description** (4000 chars): paste from `twa/STORE-LISTING.md`
- **App icon**: upload `plugins/loveallah/assets/icons/icon-512.png`
- **Feature graphic**: upload `twa/store-assets/feature_graphic.png`
- **Phone screenshots**: upload 2-8 (see `SCREENSHOT-GUIDE.md`)
- **App category**: Lifestyle (primary), Social (secondary)
- **Tags**: `muslim`, `islam`, `dhikr`, `prayer`, `masjid`, `nasheed`, `quran`
- **Contact details**: hello@loveallah.app
- **Privacy Policy**: `https://loveallah.app/privacy`

### Release

1. **Production → Create new release**
2. **App bundles**: drag-drop `twa/build/app-release-signed.aab`
3. **Release name**: `0.6.0 — first launch`
4. **Release notes** (English): paste:
   ```
   Bismillah. Our first release.
   • Daily dhikr ritual unlocks a curated feed of Islamic content
   • Prayer times for your location, including high-latitude fallback
   • Scholars: Mufti Menk, Yasir Qadhi, Yaqeen Institute, Bayyinah,
     Mishary Alafasy, Harris J, Maher Zain and more
   • Your local masjid integrated with prayer times and services
   ```
5. **Save** → **Review release** → **Start rollout to production**

Review typically takes **1–7 days** for a new app, ~2 hours for updates.

## Step 7 — Wait, then verify

After the app is live (you'll get an email), open the Play Store on your Android device, search for "Love Allah", install. Confirm:

- App opens directly to the loveallah.app web view (no Chrome URL bar). If you see a URL bar, the assetlinks.json SHA-256 doesn't match — re-run step 3.
- Splash screen shows the magenta brand colour
- Prayer times correct for your location

## Updating the app

The genius of TWA: **the app IS the website**. Every push to your WordPress site updates the app instantly for all installed users, no Play Store re-submission needed.

You only need to re-build + re-upload the AAB when:
- The brand colour changes
- The PWA manifest changes (icon, name, scope)
- You bump the major version

For those:
1. Bump `appVersionCode` in `twa/twa-manifest.json` (must increase)
2. Bump `appVersionName` (e.g. `0.6.0` → `0.7.0`)
3. `cd twa/build && bubblewrap update`
4. `bubblewrap build`
5. Upload the new AAB to Play Console → Production → Create new release
