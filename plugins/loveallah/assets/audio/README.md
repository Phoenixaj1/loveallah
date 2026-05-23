# Dhikr audio layers

Drop MP3 files into this directory using the naming convention:

```
dhikr-{phrase-key}-{layer}.mp3
```

Where:

- **phrase-key** is one of: `kalimah`, `allah`, `subhanallah`, `alhamdulillah`,
  `allahuakbar`, `astaghfirullah`, `salawat`
- **layer** is one of: `chant`, `duff`, `breath`

## Examples

```
dhikr-kalimah-chant.mp3        ← reciter holding "La ilaha illa Allah"
dhikr-kalimah-duff.mp3         ← frame-drum heartbeat at the kalimah's breath_s rhythm
dhikr-kalimah-breath.mp3       ← audible inhale/exhale cue at 4s/4s
dhikr-allah-chant.mp3          ← "Allah... Allah..." reciter loop
dhikr-subhanallah-duff.mp3     ← duff for "Subhan Allah" cadence
dhikr-astaghfirullah-chant.mp3 ← Astaghfirullah reciter loop
```

## Requirements

- **Format**: MP3, 96-128 kbps mono is plenty (these are ambient loops)
- **Loop-clean**: the file must loop seamlessly — no silence at start/end,
  no fade-out. Cut on the beat for the duff/breath layers.
- **Length**: 30-90 seconds is the sweet spot. Longer files cost more
  bandwidth and most users will rotate phrases before hearing the loop end.
- **Tonal match**: the chant layer should use the breath_s timing of the
  phrase it accompanies (defined in page-dhikr.php $la_phrases).
- **Rights**: only use CC0 / licensed audio. Many qaris release their dhikr
  recordings under permissive terms — credit them in the source.

## Missing files

If a phrase/layer combo doesn't have a file, the audio element will fail
its play() silently — the UI still toggles, just no sound. No errors thrown.

## Recommended sourcing

- archive.org → Islamic audio collection (many CC-licensed dhikr loops)
- Pixabay / Freesound for duff samples
- Record your own breath cue with a phone mic (4s in / 4s out, mono, normalize)
