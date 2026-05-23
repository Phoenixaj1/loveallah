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

		<?php // Soundscape picker removed — scene video now carries its own audio ?>

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
					<!-- Arabic also flips to match: kalimah inhale = لَا إِلَهَ,
					     exhale = إِلَّا ٱللَّٰه — same split a Sufi practitioner
					     would use mid-breath. -->
					<div class="la-breath-arabic" data-breath-arabic dir="rtl" lang="ar">—</div>
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
