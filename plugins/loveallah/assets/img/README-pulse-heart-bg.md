# Pulse heart background

Save the blossoming-heart artwork as **`pulse-heart-bg.jpg`** in this folder
(same directory as `logo.png`):

```
plugins/loveallah/assets/img/pulse-heart-bg.jpg
```

Recommended:
- Format: JPG (smaller than PNG, no transparency needed) — WebP is fine too,
  in which case rename the CSS reference in `assets/css/loveallah.css`
  search for `pulse-heart-bg.jpg`.
- Dimensions: ~1080×1080 (square) or 1080×1350 (4:5). Cover-cropped on render.
- Target size: keep under 250 KB so the pulse session loads instantly.

The CSS layers a radial gold-glow over the centre and dims the edges so the
pulse rings + Arabic text remain readable. The image breathes (slow scale)
over 14s to feel alive.

If the file is missing the pulse session falls back to the existing dark
midnight gradient — nothing breaks.
<!-- Wave 63 image redeploy -->
