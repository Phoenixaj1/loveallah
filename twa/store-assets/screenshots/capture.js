// Phone-screenshot generator for Love Allah Play Store listing.
// Uses puppeteer (headless Chromium). Captures 1080x2400 portrait shots
// at several /paths so we hit a variety of surfaces.
//
// Run:  node capture.js
//
// Output: this directory, files screenshot-1.png … screenshot-8.png.

const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE   = 'https://loveallah.app';
const OUT    = __dirname;

// Each entry: { path, label, prepare?(page) }
const SHOTS = [
  { path: '/',          label: 'feed-home' },
  { path: '/dhikr/',    label: 'dhikr-hub' },
  { path: '/masjid/',   label: 'masjid' },
  { path: '/duas/',     label: 'duas' },
  { path: '/saved/',    label: 'saved' },
  { path: '/connect/',  label: 'connect' },
];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  for (let i = 0; i < SHOTS.length; i++) {
    const s = SHOTS[i];
    const page = await browser.newPage();
    // Pixel-7-ish viewport, portrait
    await page.setViewport({
      width: 412,
      height: 915,
      deviceScaleFactor: 2.625,
      isMobile: true,
      hasTouch: true,
    });
    await page.setUserAgent(
      'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 ' +
      '(KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'
    );
    try {
      await page.goto(BASE + s.path, { waitUntil: 'networkidle2', timeout: 45000 });
    } catch (e) {
      console.error('goto failed for', s.path, e.message);
    }
    // Let dhikr/animation settle.
    await new Promise(r => setTimeout(r, 2500));

    const out = path.join(OUT, `screenshot-${i + 1}-${s.label}.png`);
    await page.screenshot({ path: out, type: 'png', fullPage: false });
    console.log('wrote', out);
    await page.close();
  }

  await browser.close();
})();
