# Icons

`icon.svg` is the master vector. PNG sizes for PWA + Android are generated from it.

## Generate PNGs (one-time, from a workstation with ImageMagick or sharp installed)

```bash
# Using ImageMagick
for size in 48 72 96 128 144 152 192 384 512; do
  magick icon.svg -resize ${size}x${size} icon-${size}.png
done

# Maskable versions need extra padding (safe zone)
magick icon.svg -resize 154x154 -background "#ED1C6C" -gravity center -extent 192x192 icon-192-maskable.png
magick icon.svg -resize 410x410 -background "#ED1C6C" -gravity center -extent 512x512 icon-512-maskable.png
```

Or via npm:
```bash
npx pwa-asset-generator icon.svg . --opaque false --icon-only --type png --padding "0"
```

Commit the resulting PNGs alongside `icon.svg`. The PWA manifest (`/wp-json/loveallah/v1/manifest` or built into the plugin) references the sizes 192, 384, 512, and 512-maskable.
