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

// ─── Wave 40: Dhikr hub + modes router ────────────────────────────────
// /dhikr/                       → hub (4-card chooser)
// /dhikr/?mode=solitude         → the existing breath-paced orb (this file)
// /dhikr/?mode=witness          → feed of dhikr content + tap counter
// /dhikr/?mode=pulse            → BPM ticker with 80 → 40 BPM descent
// /dhikr/?mode=names            → 99 Names of Allah contemplation
$la_mode = sanitize_key( $_GET['mode'] ?? '' );
$la_route_partial = '';
if ( $la_mode === '' || $la_mode === 'hub' ) {
	$la_route_partial = 'dhikr-hub.php';
} elseif ( in_array( $la_mode, [ 'witness', 'pulse', 'names' ], true ) ) {
	$la_route_partial = 'dhikr-' . $la_mode . '.php';
}
if ( $la_route_partial ) {
	$la_route_path = get_template_directory() . '/inc/' . $la_route_partial;
	if ( file_exists( $la_route_path ) ) {
		get_header();
		include $la_route_path;
		get_footer();
		return;
	}
}
// Fall through: mode=solitude or unknown → render the original page below.

// The dhikr phrases — ordered from the highest to the everyday.
// La ilaha illa Allah is the primary dhikr of the Sufi orders (the kalimah).
// breath_s = full cycle in seconds. We default to 10s (6 breaths/min) —
// the gold-standard "resonance frequency" for HRV coherence + maximum
// vagal tone (Lehrer & Gevirtz 2014; Sevoz-Couche & Laborde 2022).
// Per-breath ratio is asymmetric: 40% inhale / 60% exhale (4s in / 6s out
// at 10s cycle), because longer exhales activate the parasympathetic
// nervous system more strongly (Russo et al. 2017, "The physiological
// effects of slow breathing in the healthy human"). The user can dial
// faster or slower mid-session via the rhythm pill.
// Each phrase carries BOTH:
//   - The full arabic / translit / meaning (shown in the landing card)
//   - The inhale and exhale halves IN ARABIC AND TRANSLITERATION — the
//     breath orb cycles between these two halves so the dhikr is split
//     across the breath the way Sufi practitioners actually do it:
//     inhale draws the negation, exhale releases the affirmation
//     (or inhale draws the Name in, exhale lets it descend into the heart).
$la_phrases = [
	[ 'key' => 'kalimah',        'arabic' => 'لَا إِلَهَ إِلَّا ٱللَّٰه',           'translit' => 'Lā ilāha illa-llāh',
	  'meaning' => 'There is no god but Allah',
	  'inhale' => 'Lā ilāha',    'exhale' => 'illa-llāh',
	  'arabic_inhale' => 'لَا إِلَهَ', 'arabic_exhale' => 'إِلَّا ٱللَّٰه',
	  // Group halaqa chant — Sheikh Eshref Efendi + Sufi Centre Rabbaniyya.
	  // Multiple voices = communal feel, like sitting in the circle. Used to
	  // be a single-voice recording which felt isolated; this is the dhikr
	  // gathering you're standing inside.
	  'chant_video' => 'YVpdl2xfKss',
	  'note' => 'The kalimah — the testimony and the highest dhikr', 'breath_s' => 10 ],
	// "Yā Allāh" — the established Sufi formula for the dhikr-of-the-Name
	// (used across Qadiri, Chishti, Shadhili, Naqshbandi orders). Splits
	// at the natural particle/Name boundary, NOT mid-syllable across the
	// Name itself (which is not an established practice).
	[ 'key' => 'allah',          'arabic' => 'يَا ٱللَّٰه',                          'translit' => 'Yā Allāh',
	  'meaning' => 'O Allah — calling on the Divine Name',
	  'inhale' => 'Yā',          'exhale' => 'Allāh',
	  'arabic_inhale' => 'يَا', 'arabic_exhale' => 'ٱللَّٰه',
	  'chant_video' => 'CHnuKaZcMjI',  // Islamic Sukoon — YA ALLAHu YA ALLAH continuous
	  'note' => 'The singular Name — calling, then descent into the heart', 'breath_s' => 10 ],
	[ 'key' => 'subhanallah',    'arabic' => 'سُبْحَانَ ٱللَّٰه',                   'translit' => 'Subḥān Allāh',
	  'meaning' => 'Glory be to Allah',
	  'inhale' => 'Subḥān',      'exhale' => 'Allāh',
	  'arabic_inhale' => 'سُبْحَانَ', 'arabic_exhale' => 'ٱللَّٰه',
	  'chant_video' => 'aeeVsvAa0H8',  // Omar Hisham — SUBHANALLAH WA BIHAMDIH 1hr
	  'note' => 'Glorification — the dhikr that frees Allah from imperfection', 'breath_s' => 10 ],
	[ 'key' => 'alhamdulillah',  'arabic' => 'ٱلْحَمْدُ لِلَّٰه',                   'translit' => 'Alḥamdulillāh',
	  'meaning' => 'All praise is for Allah',
	  'inhale' => 'Alḥamdu',     'exhale' => 'lillāh',
	  'arabic_inhale' => 'ٱلْحَمْدُ', 'arabic_exhale' => 'لِلَّٰه',
	  'chant_video' => 'q-TtS8LRIkU',  // 5 HOURS RELAXING DHIKR (SubhanAllah/Alhamdu/Akbar)
	  'note' => 'Gratitude — the dhikr that fills the scales', 'breath_s' => 10 ],
	[ 'key' => 'allahuakbar',    'arabic' => 'ٱللَّٰهُ أَكْبَر',                     'translit' => 'Allāhu akbar',
	  'meaning' => 'Allah is greater',
	  'inhale' => 'Allāhu',      'exhale' => 'akbar',
	  'arabic_inhale' => 'ٱللَّٰهُ', 'arabic_exhale' => 'أَكْبَر',
	  'chant_video' => 'n9oLl0HjV3Y',  // Adam Islamic Animation — Allahu Akbar 1hr Takbir
	  'note' => 'Magnification — the dhikr that puts every concern in its place', 'breath_s' => 10 ],
	[ 'key' => 'astaghfirullah', 'arabic' => 'أَسْتَغْفِرُ ٱللَّٰه',                 'translit' => 'Astaghfirullāh',
	  'meaning' => 'I seek forgiveness of Allah',
	  'inhale' => 'Astaghfiru',  'exhale' => 'Allāh',
	  'arabic_inhale' => 'أَسْتَغْفِرُ', 'arabic_exhale' => 'ٱللَّٰه',
	  'chant_video' => 'r4YrbaVbqPk',  // Mevlan Kurtishi — Astaghfirullah Dhikr 2025
	  'note' => 'The polish — the Prophet ﷺ sought forgiveness 70+ times a day', 'breath_s' => 11 ],
	// SALAWAT — the durood DHIKR formula (asking Allah to bless the
	// Prophet ﷺ), not the honorific 'Sallallahu ʿalayhi wa sallam'
	// that we say AFTER his name. This is the formula every Sufi order
	// uses for repeated salawat practice + the Friday sunnah of sending
	// 80+ salawat. Hadith: 'Whoever sends one blessing on me, Allah
	// sends ten on him' (Muslim 408).
	[ 'key' => 'salawat',        'arabic' => 'اللَّهُمَّ صَلِّ عَلَى مُحَمَّد',        'translit' => 'Allāhumma ṣalli ʿalā Muḥammad',
	  'meaning' => 'O Allah, send blessings upon Muhammad ﷺ',
	  'inhale' => 'Allāhumma',   'exhale' => 'ṣalli ʿalā Muḥammad',
	  'arabic_inhale' => 'اللَّهُمَّ', 'arabic_exhale' => 'صَلِّ عَلَى مُحَمَّد',
	  'chant_video' => 'maHPe1byTfk',  // Omar Hisham — 1 Hour Salat on the Prophet
	  'note' => 'Salawat — every blessing on him returns to you tenfold (Muslim 408)', 'breath_s' => 12 ],
];

// SUNNAH COUNTS — how many repetitions for each phrase.
// Drawn from established hadith, not arbitrary numbers:
//   33 Subhan + 33 Hamd + 33 Akbar (or 34 Akbar) after salah = Tasbih
//     Fatima (Bukhari 6329 + Muslim 595) — totals 99 or 100, the 99
//     beautiful Names.
//   La ilaha illa Allah ×100 daily: 'Whoever says it 100 times in a
//     day, equals freeing 10 slaves, 100 good deeds recorded, 100 sins
//     erased' (Bukhari 6403, Muslim 2691).
//   Astaghfirullah ×70+ daily — 'By Allah I seek forgiveness more than
//     70 times a day' (Bukhari 6307). Some hadith mention 100.
//   Salawat ×80 each Friday — recommended in Sunnah, blessings × 10
//     return for each one (Muslim 408).
//
// Each phrase carries an ordered list of Sunnah counts; the first is
// the default. The user picks a count; duration is computed live from
// count × breath_s and shown next to the chip.
$la_phrases_meta = [
	'kalimah'        => [ 33, 100, 300 ],
	'allah'          => [ 100, 300, 1000 ],
	'subhanallah'    => [ 33, 100 ],
	'alhamdulillah'  => [ 33, 100 ],
	'allahuakbar'    => [ 33, 100 ],
	'astaghfirullah' => [ 70, 100, 300 ],
	'salawat'        => [ 10, 80, 100 ],
];
foreach ( $la_phrases as &$p ) {
	$p['counts'] = $la_phrases_meta[ $p['key'] ] ?? [ 33, 100 ];
}
unset( $p );

// Legacy minute-based durations — kept for the fallback path but the
// landing now uses count-based pacing per the Sunnah.
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
	[ 'key' => 'ocean',   'emoji' => '🌊', 'label' => 'Ocean',    'desc' => '10hr peaceful ocean ambience', 'video' => 'NJXzcQJi_A8' ],
	// "Makkah Live HD" by Muhammad Ali — community re-broadcast of the
	// official Saudi Quran TV Haram feed.
	[ 'key' => 'kaaba',   'emoji' => '🕋', 'label' => 'Haram',    'desc' => 'The tawaf, live from Makkah',   'video' => 'bNY8a2BB5Gc' ],
	[ 'key' => 'none',    'emoji' => '🌑', 'label' => 'Stillness','desc' => 'Pure dark, nothing else',       'video' => '' ],
];

// Soundscape picker removed Wave 21 — the SCENE video already carries the
// right audio for itself (ocean has waves, forest has birds, etc.). A
// separate sound picker was incoherent (cosmos with ocean = wrong).
// The scene picker now drives both visual + audio in one choice.

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

	<!-- ─── LANDING — Wave 26 redesign: hero preview + compact selectors + sticky CTA -->
	<section class="la-dhikr-landing" data-dhikr-scene="landing">

		<!-- HERO — live preview of the selected phrase. Updates as the user
		     picks. The whole 'what am I about to do' question is answered
		     in one glance: big Arabic, transliteration, meaning, source. -->
		<div class="la-dhikr-hero" data-hero>
			<?php $p0 = $la_phrases[0]; ?>
			<div class="la-dhikr-hero-arabic" data-hero-arabic dir="rtl" lang="ar"><?php echo esc_html( $p0['arabic'] ); ?></div>
			<div class="la-dhikr-hero-translit" data-hero-translit><?php echo esc_html( $p0['translit'] ); ?></div>
			<div class="la-dhikr-hero-meaning" data-hero-meaning><?php echo esc_html( $p0['meaning'] ); ?></div>
			<div class="la-dhikr-hero-note" data-hero-note><?php echo esc_html( $p0['note'] ); ?></div>
		</div>

		<!-- Wisdom — moved to a slim banner below the hero so it's a quiet
		     blessing not the loudest thing on the page. -->
		<?php if ( $la_wisdom_landing ) : ?>
		<div class="la-dhikr-wisdom-mini">
			<span class="la-dhikr-wisdom-mark">“</span>
			<span class="la-dhikr-wisdom-text"><?php echo esc_html( $la_wisdom_landing['quote'] ); ?></span>
			<span class="la-dhikr-wisdom-attr">— <?php echo esc_html( $la_wisdom_landing['speaker'] ); ?></span>
		</div>
		<?php endif; ?>

		<!-- PHRASE PICKER — compact Arabic pill row (was 7 bulky cards).
		     Tap any to update the hero above. Active one is highlighted. -->
		<div class="la-dhikr-pickrow">
			<div class="la-dhikr-pickrow-label">Phrase</div>
			<div class="la-dhikr-phrase-pills" data-phrase-list role="radiogroup" aria-label="Dhikr phrase">
				<?php foreach ( $la_phrases as $i => $p ) : ?>
					<button type="button"
						class="la-dhikr-phrase-pill <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-phrase-key="<?php echo esc_attr( $p['key'] ); ?>"
						data-phrase='<?php echo esc_attr( wp_json_encode( $p ) ); ?>'
						title="<?php echo esc_attr( $p['translit'] . ' — ' . $p['meaning'] ); ?>">
						<span class="la-dhikr-phrase-pill-arabic" dir="rtl" lang="ar"><?php echo esc_html( $p['arabic'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- COUNT — inline label + Sunnah-prescribed pill row -->
		<div class="la-dhikr-pickrow">
			<div class="la-dhikr-pickrow-label">Count<span class="la-dhikr-pickrow-hint">Sunnah</span></div>
			<div class="la-dhikr-count-row" data-count-list role="radiogroup" aria-label="Count">
				<?php foreach ( $p0['counts'] as $i => $c ) :
					$est_min = (int) max( 1, round( $c * $p0['breath_s'] / 60 ) );
				?>
					<button type="button"
						class="la-dhikr-count-pill <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-count="<?php echo (int) $c; ?>">
						<strong><?php echo (int) $c; ?>×</strong>
						<span>~<?php echo $est_min; ?>m</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- STATION — inline pill row (was bulky 3-card grid) -->
		<div class="la-dhikr-pickrow">
			<div class="la-dhikr-pickrow-label">Station</div>
			<div class="la-dhikr-mode-row" data-mode-list role="radiogroup" aria-label="Mode">
				<?php foreach ( $la_modes as $i => $m ) : ?>
					<button type="button"
						class="la-dhikr-mode-pill <?php echo $i === 1 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 1 ? 'true' : 'false'; ?>"
						data-mode="<?php echo esc_attr( $m['key'] ); ?>"
						title="<?php echo esc_attr( $m['desc'] ); ?>">
						<?php echo esc_html( $m['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- SCENE — emoji-only chips, super compact -->
		<div class="la-dhikr-pickrow">
			<div class="la-dhikr-pickrow-label">Scene</div>
			<div class="la-dhikr-scene-row" data-scene-list role="radiogroup" aria-label="Visual scene">
				<?php foreach ( $la_scenes as $i => $s ) : ?>
					<button type="button"
						class="la-dhikr-scene-icon <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-scene="<?php echo esc_attr( $s['key'] ); ?>"
						data-scene-video="<?php echo esc_attr( $s['video'] ?? '' ); ?>"
						title="<?php echo esc_attr( $s['label'] . ' — ' . $s['desc'] ); ?>">
						<?php echo $s['emoji']; ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- STICKY BEGIN BAR — anchored to bottom, always reachable.
		     Shows the live count + duration summary so the user always
		     knows exactly what they're about to do. -->
		<div class="la-dhikr-begin-bar">
			<button type="button" class="la-dhikr-begin" data-action="begin-dhikr">
				<span class="la-dhikr-begin-icon" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 6l8 6-8 6V6z"/></svg>
				</span>
				<span class="la-dhikr-begin-label">Begin</span>
				<span class="la-dhikr-begin-meta" data-begin-meta>33× · ~6 min</span>
			</button>
		</div>
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

		<?php // Soundscape iframe removed — scene backdrop iframe now carries audio ?>

		<!-- Slim header — phrase + countdown + breath count.
		     The count is the ONE quiet improvement: a soft tally of breaths
		     done. Connects to the tasbeeh tradition without gamifying —
		     just shows the user their persistence accumulating. No targets,
		     no streaks, no notifications. Just a number rising. -->
		<div class="la-dhikr-session-head">
			<button type="button" class="la-dhikr-back" data-action="end-session" aria-label="End session">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
			</button>
			<div class="la-dhikr-session-phrase" data-active-phrase>—</div>
			<div class="la-dhikr-session-count" data-breath-count title="Breath cycles" aria-label="Breath cycles">
				<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="3" r="1.4" fill="currentColor"/><circle cx="12" cy="21" r="1.4" fill="currentColor"/><circle cx="3" cy="12" r="1.4" fill="currentColor"/><circle cx="21" cy="12" r="1.4" fill="currentColor"/></svg>
				<span data-breath-count-num>0</span>
			</div>
			<div class="la-dhikr-session-timer" data-active-timer>—:—</div>
		</div>

		<!-- The breath orb. The water-cascade decoration was removed in
		     Wave 23 — it read as artificial CSS droplets, not the polish-of-
		     the-heart metaphor it was meant to evoke. The orb's pulse +
		     INHALE/EXHALE label do the visual work without distractions. -->
		<div class="la-breath" data-breath-ring>
			<div class="la-breath-glow"></div>
			<div class="la-breath-circle">
				<div class="la-breath-inner">
					<!-- Inhale / Exhale cue ON THE ORB — drives the user's
					     breath in real time. Above the Arabic so the eye
					     reads it first before the contemplated meaning. -->
					<div class="la-breath-phase" data-breath-phase>Inhale</div>
					<!-- Arabic flips per breath half: kalimah inhale = لَا إِلَهَ,
					     exhale = إِلَّا ٱللَّٰه — same split a Sufi practitioner
					     would use mid-breath. -->
					<div class="la-breath-arabic" data-breath-arabic dir="rtl" lang="ar">—</div>
					<!-- Transliteration below the Arabic, also flips per half.
					     Helps non-Arabic-reading users follow along by sound. -->
					<div class="la-breath-translit" data-breath-translit>—</div>
				</div>
			</div>
		</div>

		<!-- BIG SUBTITLE BLOCK — TikTok-style auto-captions, three lines:
		     1. CUE: current breath half ("Lā ilāha" / "illa-llāh") — biggest
		     2. MEANING: literal translation — different colour for readability
		     3. HEART COACH: rotating Sufi-style coaching that guides the
		        heart closer to Allah. Framed as a coach speaking quietly
		        beside you — small "💧 Heart coach" label above each prompt
		        so the user reads it as guidance, not random text. -->
		<div class="la-dhikr-subs" data-dhikr-subs>
			<div class="la-dhikr-subs-cue" data-breath-cue>Settle</div>
			<div class="la-dhikr-subs-meaning" data-breath-meaning>—</div>
			<div class="la-dhikr-coach" data-heart-coach>
				<div class="la-dhikr-coach-label">
					<span class="la-dhikr-coach-icon" aria-hidden="true">💧</span>
					<span>Heart coach</span>
				</div>
				<div class="la-dhikr-coach-text" data-heart-prompt>—</div>
			</div>
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

		<!-- Wave 60: in-session controls (scene switcher + audio toggle).
		     Floats above the bottom-left so it doesn't compete with the
		     rhythm slider on the right. Picker is collapsed by default —
		     tap the scene chip to reveal the full list, tap the speaker
		     to mute/unmute the ambient audio. -->
		<div class="la-dhikr-session-controls" data-session-controls>
			<button type="button"
				class="la-dhikr-session-ctrl la-dhikr-session-ctrl--scene"
				data-toggle-scenes
				aria-expanded="false"
				aria-label="Change scene">
				<span class="la-dhikr-session-ctrl-emoji" data-current-scene-emoji aria-hidden="true">✨</span>
				<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
			</button>

			<button type="button"
				class="la-dhikr-session-ctrl la-dhikr-session-ctrl--audio is-on"
				data-toggle-audio
				aria-pressed="true"
				aria-label="Toggle ambient audio">
				<svg class="la-dhikr-session-ctrl-icon-on"  width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>
				<svg class="la-dhikr-session-ctrl-icon-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
			</button>

			<div class="la-dhikr-session-scenes" data-session-scenes hidden role="radiogroup" aria-label="Background scene">
				<?php foreach ( $la_scenes as $i => $s ) : ?>
					<button type="button"
						class="la-dhikr-session-scene <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-session-scene="<?php echo esc_attr( $s['key'] ); ?>"
						data-session-scene-video="<?php echo esc_attr( $s['video'] ?? '' ); ?>"
						data-session-scene-emoji="<?php echo esc_attr( $s['emoji'] ); ?>"
						title="<?php echo esc_attr( $s['label'] . ' — ' . $s['desc'] ); ?>">
						<span class="la-dhikr-session-scene-emoji" aria-hidden="true"><?php echo $s['emoji']; ?></span>
						<span class="la-dhikr-session-scene-label"><?php echo esc_html( $s['label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
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
