<?php
/**
 * Dhikr — heart-polishing contemplation.
 *
 * Not a counter, not a game. A breath-paced silent practice rooted in
 * the Sufi sciences: dhikr is the polish for the heart's rust, the
 * means by which the slave's heart finds rest (Quran 13:28).
 *
 * UX:  Landing (phrase + duration + mode) → breath circle session →
 *      reflection completion.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The dhikr phrases — ordered from the highest to the everyday.
// La ilaha illa Allah is the primary dhikr of the Sufi orders (the kalimah),
// the testimony itself and the means by which the heart is unlocked.
$la_phrases = [
	[
		'key'       => 'kalimah',
		'arabic'    => 'لَا إِلَهَ إِلَّا ٱللَّٰه',
		'translit'  => 'Lā ilāha illa-llāh',
		'meaning'   => 'There is no god but Allah',
		'inhale'    => 'Lā ilāha',
		'exhale'    => 'illa-llāh',
		'note'      => 'The kalimah — the testimony and the highest dhikr',
		'breath_s'  => 8,
	],
	[
		'key'       => 'allah',
		'arabic'    => 'ٱللَّٰه',
		'translit'  => 'Allāh',
		'meaning'   => 'The Divine Name',
		'inhale'    => 'Al-',
		'exhale'    => 'lāh',
		'note'      => 'The singular Name — the dhikr of the gnostics',
		'breath_s'  => 8,
	],
	[
		'key'       => 'subhanallah',
		'arabic'    => 'سُبْحَانَ ٱللَّٰه',
		'translit'  => 'Subḥān Allāh',
		'meaning'   => 'Glory be to Allah',
		'inhale'    => 'Subḥān',
		'exhale'    => 'Allāh',
		'note'      => 'Glorification — the dhikr of declaring Allah free from any imperfection',
		'breath_s'  => 7,
	],
	[
		'key'       => 'alhamdulillah',
		'arabic'    => 'ٱلْحَمْدُ لِلَّٰه',
		'translit'  => 'Alḥamdulillāh',
		'meaning'   => 'All praise is for Allah',
		'inhale'    => 'Alḥamdu',
		'exhale'    => 'lillāh',
		'note'      => 'Gratitude — the dhikr that fills the scales',
		'breath_s'  => 7,
	],
	[
		'key'       => 'allahuakbar',
		'arabic'    => 'ٱللَّٰهُ أَكْبَر',
		'translit'  => 'Allāhu akbar',
		'meaning'   => 'Allah is greater',
		'inhale'    => 'Allāhu',
		'exhale'    => 'akbar',
		'note'      => 'Magnification — the dhikr that puts every other concern in its place',
		'breath_s'  => 7,
	],
	[
		'key'       => 'astaghfirullah',
		'arabic'    => 'أَسْتَغْفِرُ ٱللَّٰه',
		'translit'  => 'Astaghfirullāh',
		'meaning'   => 'I seek forgiveness of Allah',
		'inhale'    => 'Astaghfi',
		'exhale'    => 'rullāh',
		'note'      => 'The polish — the Prophet ﷺ sought forgiveness 70+ times a day',
		'breath_s'  => 8,
	],
	[
		'key'       => 'salawat',
		'arabic'    => 'صَلَّى ٱللَّٰهُ عَلَيْهِ وَسَلَّم',
		'translit'  => 'Ṣalla-llāhu ʿalayhi wa sallam',
		'meaning'   => 'Peace and blessings upon the Prophet ﷺ',
		'inhale'    => 'Ṣalla-llāhu',
		'exhale'    => 'ʿalayhi wa sallam',
		'note'      => 'Salawat — every blessing on him returns to you tenfold',
		'breath_s'  => 9,
	],
];

// Durations (minutes)
$la_durations = [ 3, 7, 11, 21 ];

// Modes — the stations of dhikr in Sufi sciences
$la_modes = [
	[ 'key' => 'lisani', 'label' => 'Tongue', 'desc' => 'Audible, with recitation playing' ],
	[ 'key' => 'qalbi',  'label' => 'Heart',  'desc' => 'Silent, breath only — the dhikr enters the heart' ],
	[ 'key' => 'sirri',  'label' => 'Secret', 'desc' => 'No Arabic shown — pure presence' ],
];

// Background scenes — each has a YouTube ambient loop AND a CSS-gradient
// fallback so the experience never goes blank if YT fails.
//   video: YouTube ID — VERIFIED via oEmbed API (see commit notes).
//   Swap the IDs below for whatever ambient loops you prefer. Always
//   verify with `curl -s -o /dev/null -w "%{http_code}" \
//   "https://www.youtube.com/oembed?url=...&format=json"` returns 200.
$la_scenes = [
	// "COSMIC RELAXATION: 8 HOURS of 4K Deep Space NASA Footage" by Nature
	// Relaxation Films — actual cosmos / nebula footage from Hubble, not
	// the NASA TV news stream. Re-verified after the previous ID showed
	// "video unavailable" + the live stream cycled to talking-head content.
	[ 'key' => 'cosmos',  'emoji' => '✨', 'label' => 'Cosmos',   'desc' => 'Deep space — Hubble + nebulae', 'video' => 'Y_plhk1FUQA' ],
	// "Sahara Desert 4K - Scenic Relaxation Film" by Scenic Relaxation —
	// drifting dunes, the fajr-light aesthetic we want.
	[ 'key' => 'desert',  'emoji' => '🌅', 'label' => 'Sahara',   'desc' => 'Drifting dunes at first light', 'video' => 'gFmDx9oj3DU' ],
	[ 'key' => 'forest',  'emoji' => '🌿', 'label' => 'Forest',   'desc' => 'Green canopy at dawn',          'video' => 'BHACKCNDMW8' ],
	[ 'key' => 'ocean',   'emoji' => '🌊', 'label' => 'Ocean',    'desc' => 'Slow tide',                     'video' => 'V-_O7nl0Ii0' ],
	// "Makkah Live HD" by Muhammad Ali — community re-broadcast of the
	// official Saudi Quran TV Haram feed.
	[ 'key' => 'kaaba',   'emoji' => '🕋', 'label' => 'Haram',    'desc' => 'The tawaf, live from Makkah',   'video' => 'bNY8a2BB5Gc' ],
	[ 'key' => 'none',    'emoji' => '🌑', 'label' => 'Stillness','desc' => 'Pure dark, nothing else',       'video' => '' ],
];

// Sound layers — optional auxiliary tracks to layer with the breath.
// V1 ships the UI + state; audio assets land in /assets/audio/ and the
// player wires them up. Toggling without assets is a silent no-op.
$la_sound_layers = [
	[ 'key' => 'chant',    'emoji' => '🎙', 'label' => 'Chant',  'desc' => 'A reciter holds the phrase under you' ],
	[ 'key' => 'duff',     'emoji' => '🥁', 'label' => 'Duff',   'desc' => 'Soft frame-drum heartbeat' ],
	[ 'key' => 'breath',   'emoji' => '🌬', 'label' => 'Breath', 'desc' => 'Audible inhale/exhale cue' ],
];

// Wisdom — load + pick three (one for landing, rest rotate during session)
$la_wisdom = [];
$wisdom_path = LA_DIR . 'inc/data/dhikr-wisdom.json';
if ( file_exists( $wisdom_path ) ) {
	$la_wisdom = json_decode( file_get_contents( $wisdom_path ), true ) ?: [];
}
$la_wisdom_landing = $la_wisdom ? $la_wisdom[ array_rand( $la_wisdom ) ] : null;

get_header();
?>
<main class="la-app la-app--dhikr-meditate" data-dhikr-app>

	<!-- ─── LANDING — choose phrase + duration + mode ─── -->
	<section class="la-dhikr-landing" data-dhikr-scene="landing">

		<!-- Wisdom hero — rotates on each page load -->
		<div class="la-dhikr-wisdom" data-dhikr-wisdom>
			<?php if ( $la_wisdom_landing ) : ?>
				<blockquote class="la-dhikr-wisdom-quote">"<?php echo esc_html( $la_wisdom_landing['quote'] ); ?>"</blockquote>
				<cite class="la-dhikr-wisdom-cite">— <?php echo esc_html( $la_wisdom_landing['speaker'] ); ?> · <span><?php echo esc_html( $la_wisdom_landing['source'] ); ?></span></cite>
			<?php endif; ?>
		</div>

		<!-- Phrase selector — small horizontal scroll of cards -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Choose your dhikr</h2>
			<div class="la-dhikr-phrase-list" data-phrase-list role="radiogroup" aria-label="Dhikr phrase">
				<?php foreach ( $la_phrases as $i => $p ) : ?>
					<button type="button"
						class="la-dhikr-phrase-card <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-phrase-key="<?php echo esc_attr( $p['key'] ); ?>"
						data-phrase='<?php echo esc_attr( wp_json_encode( $p ) ); ?>'>
						<span class="la-dhikr-phrase-arabic" dir="rtl" lang="ar"><?php echo esc_html( $p['arabic'] ); ?></span>
						<span class="la-dhikr-phrase-translit"><?php echo esc_html( $p['translit'] ); ?></span>
						<span class="la-dhikr-phrase-note"><?php echo esc_html( $p['note'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Duration -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Duration</h2>
			<div class="la-dhikr-duration-row" data-duration-list role="radiogroup" aria-label="Duration">
				<?php foreach ( $la_durations as $i => $m ) : ?>
					<button type="button"
						class="la-dhikr-duration-pill <?php echo $i === 1 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 1 ? 'true' : 'false'; ?>"
						data-duration="<?php echo (int) $m; ?>">
						<?php echo (int) $m; ?> <span>min</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Mode (station of dhikr) -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Station</h2>
			<div class="la-dhikr-mode-list" data-mode-list role="radiogroup" aria-label="Mode">
				<?php foreach ( $la_modes as $i => $m ) : ?>
					<button type="button"
						class="la-dhikr-mode-card <?php echo $i === 1 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 1 ? 'true' : 'false'; ?>"
						data-mode="<?php echo esc_attr( $m['key'] ); ?>">
						<strong><?php echo esc_html( $m['label'] ); ?></strong>
						<span><?php echo esc_html( $m['desc'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Scene — animated CSS backgrounds set the visual immersion. No
		     external assets, no autoplay video bandwidth, no copyright issues. -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Scene</h2>
			<div class="la-dhikr-scene-list" data-scene-list role="radiogroup" aria-label="Visual scene">
				<?php foreach ( $la_scenes as $i => $s ) : ?>
					<button type="button"
						class="la-dhikr-scene-chip <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-scene="<?php echo esc_attr( $s['key'] ); ?>"
						data-scene-video="<?php echo esc_attr( $s['video'] ?? '' ); ?>"
						title="<?php echo esc_attr( $s['desc'] ); ?>">
						<span class="la-dhikr-scene-emoji"><?php echo $s['emoji']; ?></span>
						<span class="la-dhikr-scene-label"><?php echo esc_html( $s['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Sound layers — optional companion audio. Each is independent +
		     stackable: a reciter chant can play under a duff drum under
		     audible breath cues. V1 ships UI + state; audio files drop into
		     /assets/audio/dhikr-{phrase}-{layer}.mp3 — toggling without
		     a file is a silent no-op so the UI doesn't break. -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Sound layers <span class="la-dhikr-section-hint">optional · stackable</span></h2>
			<div class="la-dhikr-sound-list" data-sound-list aria-label="Audio layers">
				<?php foreach ( $la_sound_layers as $s ) : ?>
					<button type="button"
						class="la-dhikr-sound-chip"
						data-sound="<?php echo esc_attr( $s['key'] ); ?>"
						aria-pressed="false"
						title="<?php echo esc_attr( $s['desc'] ); ?>">
						<span class="la-dhikr-sound-emoji"><?php echo $s['emoji']; ?></span>
						<span class="la-dhikr-sound-label"><?php echo esc_html( $s['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<button type="button" class="la-dhikr-begin" data-action="begin-dhikr">
			<span>Begin</span>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l8 6-8 6V6z" fill="currentColor"/></svg>
		</button>

		<p class="la-dhikr-landing-footnote">
			Dhikr is the polish of the heart. Let the breath guide you in.
		</p>
	</section>

	<!-- ─── ACTIVE SESSION — the heart pulses with the breath ─── -->
	<section class="la-dhikr-session" data-dhikr-scene="session" hidden>

		<!-- Scene backdrop —
		     Layer 1: YouTube ambient video (cosmos / nature etc) muted on loop
		     Layer 2: CSS gradient + animated stars/haze (fallback + colour wash)
		     Layer 3: Psychedelic colour-cycle that pulses with the breath
		     All three stack so even if YT fails the visuals stay rich. -->
		<div class="la-dhikr-backdrop" data-dhikr-backdrop aria-hidden="true">
			<div class="la-dhikr-bg-yt" data-bg-yt>
				<!-- iframe is injected by JS only when a scene has a video ID,
				     so the bandwidth hit only happens during an active session. -->
			</div>
			<div class="la-dhikr-backdrop-stars"></div>
			<div class="la-dhikr-backdrop-haze"></div>
			<div class="la-dhikr-backdrop-psyche" data-dhikr-psyche></div>
		</div>

		<!-- Slim header — phrase + countdown -->
		<div class="la-dhikr-session-head">
			<button type="button" class="la-dhikr-back" data-action="end-session" aria-label="End session">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
			</button>
			<div class="la-dhikr-session-phrase" data-active-phrase>—</div>
			<div class="la-dhikr-session-timer" data-active-timer>—:—</div>
		</div>

		<!-- The breath orb — smaller, pulsing core (visual anchor only) -->
		<div class="la-breath" data-breath-ring>
			<div class="la-breath-glow"></div>
			<div class="la-breath-circle">
				<div class="la-breath-inner">
					<div class="la-breath-arabic" data-breath-arabic dir="rtl" lang="ar">—</div>
				</div>
			</div>
		</div>

		<!-- BIG SUBTITLE BLOCK — TikTok-style auto-captions, three lines:
		     1. CUE: current breath half ("Lā ilāha" / "illa-llāh") — biggest
		     2. MEANING: literal translation — different colour for readability
		     3. HEART PROMPT: a rotating Sufi-style psychological prompt that
		        draws the heart closer — NOT the translation, these are
		        contemplations like "He is closer to you than your jugular".
		        Rotates every ~15s so the reader gets several across a session. -->
		<div class="la-dhikr-subs" data-dhikr-subs>
			<div class="la-dhikr-subs-cue" data-breath-cue>Settle</div>
			<div class="la-dhikr-subs-meaning" data-breath-meaning>—</div>
			<div class="la-dhikr-subs-heart" data-heart-prompt>—</div>
		</div>

		<!-- Rhythm slider — adjust breath cycle speed in real time. -->
		<div class="la-dhikr-rhythm" data-rhythm-control>
			<button type="button" class="la-dhikr-rhythm-btn" data-rhythm="slower" aria-label="Slower">−</button>
			<div class="la-dhikr-rhythm-meta">
				<div class="la-dhikr-rhythm-label">Rhythm</div>
				<div class="la-dhikr-rhythm-value" data-rhythm-value>8s</div>
			</div>
			<button type="button" class="la-dhikr-rhythm-btn" data-rhythm="faster" aria-label="Faster">+</button>
		</div>

		<!-- Progress arc -->
		<div class="la-dhikr-progress" aria-hidden="true">
			<div class="la-dhikr-progress-fill" data-progress-fill></div>
		</div>
	</section>

	<!-- ─── COMPLETION — reflection, not celebration ─── -->
	<section class="la-dhikr-complete" data-dhikr-scene="complete" hidden>
		<div class="la-dhikr-complete-glow"></div>
		<div class="la-dhikr-complete-inner">
			<div class="la-dhikr-complete-arabic" dir="rtl" lang="ar">وَلَذِكْرُ ٱللَّهِ أَكْبَرُ</div>
			<div class="la-dhikr-complete-translit">Wa la dhikru-llāhi akbar</div>
			<div class="la-dhikr-complete-meaning">"And the remembrance of Allah is greater" — Quran 29:45</div>
			<div class="la-dhikr-complete-actions">
				<button type="button" class="la-dhikr-secondary" data-action="reset-session">Settle longer</button>
				<button type="button" class="la-dhikr-primary" data-action="return-home">Return</button>
			</div>
		</div>
	</section>

	<!-- ─── Data embedded for JS (phrases + wisdom) ─── -->
	<script id="la-dhikr-config" type="application/json">
		<?php echo wp_json_encode( [ 'phrases' => $la_phrases, 'wisdom' => $la_wisdom ] ); ?>
	</script>

</main>
<?php
get_footer();
