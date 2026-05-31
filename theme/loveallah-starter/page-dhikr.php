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

// ─── Wave 40 / Wave 95b: Dhikr modes router ──────────────────────────
// /dhikr/                       → SOLITUDE (was: hub). The user shouldn't
//                                 have to make a choice before they
//                                 remember Allah. The default mode is
//                                 the established Sufi-circle practice
//                                 (breath-paced kalimah) and a top
//                                 pill-row lets them swap mode inline.
// /dhikr/?mode=hub              → the 4-card chooser (preserved for
//                                 deep-link backward compatibility)
// /dhikr/?mode=solitude         → same as default — explicit Solitude
// /dhikr/?mode=witness          → feed of dhikr content + tap counter
// /dhikr/?mode=pulse            → BPM ticker with 80 → 40 BPM descent
// /dhikr/?mode=names            → 99 Names of Allah contemplation
$la_mode = sanitize_key( $_GET['mode'] ?? '' );
$la_route_partial = '';
if ( $la_mode === 'hub' ) {
	$la_route_partial = 'dhikr-hub.php';
} elseif ( in_array( $la_mode, [ 'witness', 'pulse', 'names' ], true ) ) {
	$la_route_partial = 'dhikr-' . $la_mode . '.php';
}
// Wave 95b: empty $la_mode now falls through to the inline Solitude
// rendering below (the established default landing → orb session flow).
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

// Modes — Wave 61: locked to Heart (Qalbi). Tongue/Secret were removed
// because nobody used them and they added noise to the landing UI.
// Heart is the default Sufi practice anyway: silent breath, dhikr
// enters the heart. The session UI no longer renders the Station row.
$la_modes = [
	[ 'key' => 'qalbi', 'label' => 'Heart', 'desc' => 'Silent, breath only — the dhikr enters the heart' ],
];

// Background scenes — each has a YouTube ambient loop AND a CSS-gradient
// fallback so the experience never goes blank if YT fails.
//
// Wave 62: scenes are organised by what KIND OF AUDIO the user wants.
// No mute toggle — pick a scene that already sounds the way you want.
//   genre =
//     'nature'   nature sounds only, no music (waves, birds)
//     'silence'  pure dark, no audio (rare — most users skip)
//     'ambient'  cinematic / relaxation music
//     'nasheed'  vocal nasheeds, duff-based (no melodic instruments)
//     'hiphop'   modern Islamic hip-hop / produced nasheeds
//                (Maher Zain / Native Deen / Omar Esa style)
//
//   video: YouTube ID — VERIFIED via oEmbed API (see commit notes).
//   Always verify new IDs with `curl -s -o /dev/null -w "%{http_code}" \
//   "https://www.youtube.com/oembed?url=...&format=json"` returns 200.
$la_scenes = [
	// ─── NATURAL SOUNDS (no music) ───────────────────────────
	[ 'key' => 'ocean',   'genre' => 'nature',  'emoji' => '🌊', 'label' => 'Ocean',
	  'desc' => 'Waves only — no music',     'video' => 'NJXzcQJi_A8' ],
	[ 'key' => 'forest',  'genre' => 'nature',  'emoji' => '🌿', 'label' => 'Forest',
	  'desc' => 'Birds at dawn — no music',  'video' => 'BHACKCNDMW8' ],
	[ 'key' => 'none',    'genre' => 'silence', 'emoji' => '🌑', 'label' => 'Stillness',
	  'desc' => 'Pure dark, no sound',       'video' => '' ],

	// ─── AMBIENT MUSIC ───────────────────────────────────────
	// "COSMIC RELAXATION: 8 HOURS of 4K Deep Space NASA Footage" by Nature
	// Relaxation Films — actual cosmos / nebula footage from Hubble.
	[ 'key' => 'cosmos',  'genre' => 'ambient', 'emoji' => '✨', 'label' => 'Cosmos',
	  'desc' => 'Deep space — Hubble + ambient music', 'video' => 'Y_plhk1FUQA' ],
	// "Sahara Desert 4K - Scenic Relaxation Film" by Scenic Relaxation.
	[ 'key' => 'desert',  'genre' => 'ambient', 'emoji' => '🌅', 'label' => 'Sahara',
	  'desc' => 'Dunes at first light — cinematic score', 'video' => 'gFmDx9oj3DU' ],

	// ─── NASHEEDS (duff + vocals — no pitched instruments) ───
	// Mevlan Kurtishi — Astaghfirullah Dhikr (duff + vocal nasheed loop).
	[ 'key' => 'duff_astaghfirullah', 'genre' => 'nasheed', 'emoji' => '🥁',
	  'label' => 'Astaghfirullah', 'desc' => 'Mevlan Kurtishi — duff + vocal',
	  'video' => 'r4YrbaVbqPk' ],
	// Adam Islamic Animation — Allahu Akbar Takbir 1hr.
	[ 'key' => 'duff_takbir',         'genre' => 'nasheed', 'emoji' => '🪘',
	  'label' => 'Allahu Akbar', 'desc' => 'Takbir nasheed — duff drumming',
	  'video' => 'n9oLl0HjV3Y' ],
	// Omar Hisham — 1 Hour Salat on the Prophet (salawat with duff).
	[ 'key' => 'duff_salawat',        'genre' => 'nasheed', 'emoji' => '🌙',
	  'label' => 'Salawat', 'desc' => 'Omar Hisham — 1hr salawat loop',
	  'video' => 'maHPe1byTfk' ],
	// "Makkah Live HD" — the traditional choice.
	[ 'key' => 'kaaba',               'genre' => 'nasheed', 'emoji' => '🕋',
	  'label' => 'Haram', 'desc' => 'Live tawaf from Makkah',
	  'video' => 'bNY8a2BB5Gc' ],

	// ─── ISLAMIC HIP-HOP / MODERN ────────────────────────────
	// Placeholder slots — the look + UI are wired; final video IDs need
	// the user's curation. Maher Zain, Native Deen, Omar Esa, Boonaa
	// Mohammed, The Reminders are the obvious starting names. For now
	// each placeholder shows the gradient backdrop with no audio — clearly
	// marked "coming soon" so users see the category exists.
	[ 'key' => 'hiphop_1', 'genre' => 'hiphop', 'emoji' => '🎤',
	  'label' => 'Maher Zain', 'desc' => 'Coming soon — Maher Zain produced nasheeds',
	  'video' => '' ],
	[ 'key' => 'hiphop_2', 'genre' => 'hiphop', 'emoji' => '🎧',
	  'label' => 'Native Deen', 'desc' => 'Coming soon — Native Deen hip-hop',
	  'video' => '' ],
	[ 'key' => 'hiphop_3', 'genre' => 'hiphop', 'emoji' => '🎶',
	  'label' => 'Omar Esa', 'desc' => 'Coming soon — Omar Esa UK nasheeds',
	  'video' => '' ],
];

// Genre metadata for the in-session library headers.
$la_scene_genres = [
	'nature'  => [ 'label' => 'Natural sounds', 'sub' => 'No music — waves, birds, silence' ],
	'silence' => [ 'label' => 'Silence',         'sub' => 'No audio at all' ],
	'ambient' => [ 'label' => 'Ambient music',   'sub' => 'Cinematic visuals + soundtrack' ],
	'nasheed' => [ 'label' => 'Nasheeds',        'sub' => 'Duff + vocal — no melodic instruments' ],
	'hiphop'  => [ 'label' => 'Islamic hip-hop', 'sub' => 'Modern Muslim hip-hop & rap' ],
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

	<?php // Wave 95b: top mode switcher — 4 pills sit at the very top of
	// every dhikr screen so the user can swap mode without bouncing back
	// to a hub. Currently rendered in PHP per-page; could be hoisted to
	// a shared partial later. Solitude is the default URL (no ?mode=).
	?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" aria-current="page">Solitude</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">Pulse</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

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

		<?php // Wave 61: Station row removed. Heart (Qalbi) is now the
			  // only mode — silent breath, the default Sufi practice.
			  // A hidden input keeps the JS path working without UI noise. ?>
		<input type="hidden" data-mode-list value="qalbi">
		<div class="la-dhikr-mode-row" data-mode-list-fallback hidden>
			<button type="button" class="la-dhikr-mode-pill is-selected" role="radio" aria-checked="true" data-mode="qalbi">Heart</button>
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

		<?php // Wave 61: rhythm +/- slider removed. The arc-based auto-ramp
			  // (set on phrase) decides cadence — user input added noise
			  // without improving the experience. Heart-coach prompts
			  // gently guide breath without explicit BPM controls. ?>

		<!-- Progress arc -->
		<div class="la-dhikr-progress" aria-hidden="true">
			<div class="la-dhikr-progress-fill" data-progress-fill></div>
		</div>

		<!-- Wave 62: only the Scenes chip remains. Audio mute removed —
		     the user picks a scene that already sounds the way they
		     want (silence / nature / ambient / nasheed / hip-hop). -->
		<div class="la-dhikr-session-controls" data-session-controls>
			<button type="button"
				class="la-dhikr-session-ctrl la-dhikr-session-ctrl--scene"
				data-toggle-scenes
				aria-expanded="false"
				aria-label="Change scene">
				<span class="la-dhikr-session-ctrl-emoji" data-current-scene-emoji aria-hidden="true">🌊</span>
				<span class="la-dhikr-session-ctrl-label">Scenes</span>
			</button>
		</div>

		<!-- Scene library — slides up from the bottom when the chip is
		     tapped. Vertical scroll. Each genre has its own section
		     header so users see at a glance what's silent vs musical
		     vs Islamic. -->
		<div class="la-dhikr-scene-library" data-scene-library hidden role="dialog" aria-modal="true" aria-label="Background scenes">
			<button type="button" class="la-dhikr-scene-library-scrim" data-scenes-close aria-label="Close"></button>
			<div class="la-dhikr-scene-library-panel">
				<div class="la-dhikr-scene-library-handle" aria-hidden="true"></div>
				<header class="la-dhikr-scene-library-head">
					<h3>Choose a scene</h3>
					<button type="button" class="la-dhikr-scene-library-x" data-scenes-close aria-label="Close">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</header>
				<div class="la-dhikr-scene-library-body" data-scene-list-session role="radiogroup" aria-label="Background scene">
					<?php
					// Render scenes grouped by audio-type genre (Wave 62).
					// First scene in $la_scenes is the initial default; JS
					// replaces it if the user previously picked something
					// else on the landing.
					$grouped = [];
					foreach ( $la_scenes as $s ) {
						$g = $s['genre'] ?? 'nature';
						if ( ! isset( $grouped[ $g ] ) ) $grouped[ $g ] = [];
						$grouped[ $g ][] = $s;
					}
					$first_key = $la_scenes[0]['key'] ?? 'ocean';
					// Order: nature → silence → ambient → nasheed → hiphop.
					// Most-mainstream/contemplative first; hip-hop last as
					// the "modern energetic" option.
					foreach ( [ 'nature', 'silence', 'ambient', 'nasheed', 'hiphop' ] as $g ) :
						if ( empty( $grouped[ $g ] ) ) continue;
						$meta = $la_scene_genres[ $g ] ?? [];
					?>
						<section class="la-dhikr-scene-library-section">
							<header class="la-dhikr-scene-library-section-head">
								<div class="la-dhikr-scene-library-section-title"><?php echo esc_html( $meta['label'] ?? '' ); ?></div>
								<div class="la-dhikr-scene-library-section-sub"><?php echo esc_html( $meta['sub'] ?? '' ); ?></div>
							</header>
							<?php foreach ( $grouped[ $g ] as $s ) :
								$is_first = ( $s['key'] === $first_key );
							?>
								<button type="button"
									class="la-dhikr-scene-library-item <?php echo $is_first ? 'is-selected' : ''; ?>"
									role="radio"
									aria-checked="<?php echo $is_first ? 'true' : 'false'; ?>"
									data-session-scene="<?php echo esc_attr( $s['key'] ); ?>"
									data-session-scene-video="<?php echo esc_attr( $s['video'] ?? '' ); ?>"
									data-session-scene-emoji="<?php echo esc_attr( $s['emoji'] ); ?>"
									data-session-scene-genre="<?php echo esc_attr( $s['genre'] ?? '' ); ?>">
									<span class="la-dhikr-scene-library-item-emoji" aria-hidden="true"><?php echo $s['emoji']; ?></span>
									<span class="la-dhikr-scene-library-item-text">
										<span class="la-dhikr-scene-library-item-label"><?php echo esc_html( $s['label'] ); ?></span>
										<span class="la-dhikr-scene-library-item-desc"><?php echo esc_html( $s['desc'] ); ?></span>
									</span>
									<span class="la-dhikr-scene-library-item-check" aria-hidden="true">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
									</span>
								</button>
							<?php endforeach; ?>
						</section>
					<?php endforeach; ?>
				</div>
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
