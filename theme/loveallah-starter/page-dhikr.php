<?php
/**
 * Dhikr — Solitude single-screen tasbih (Wave 96).
 *
 * Adopts Claude Design's "SolitudeLive" pattern: the breath-orb IS the
 * homepage. No more landing → setup → session split. The mode-switcher
 * pills (Wave 95b) still let you swap into Pulse/Names/Witness.
 *
 * Layout (translated from Claude Design's React SolitudeLive component):
 *   • Mode-switcher pills (top, fixed)
 *   • Full-bleed scene track — 6 CSS-gradient scenes, swipeable
 *   • Centre orb with progress ring + breath cue + Arabic
 *   • Counter below orb · transliteration · meaning
 *   • Scene dots + label above the bottom bar
 *   • Bottom bar: phrase-chip (left) · play/pause (centre) · target-chip (right)
 *   • Tap-anywhere-to-count via a transparent overlay (horizontal drag = scene swipe)
 *   • Bottom sheets for phrase and target pickers
 *
 * Preserved from our older implementation:
 *   • Breath-split halves: when playing, the orb's Arabic switches between
 *     the inhale half ("لَا إِلٰهَ") and the exhale half ("إِلَّا ٱللَّٰه") in
 *     time with the breath cue. Claude Design shows the full phrase only;
 *     the user explicitly preferred our breath-paced split.
 *   • Per-phrase Sunnah counts (33/100/300/∞) and the 5-7s breath cadence.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Wave 95b: route ?mode=… → partial; default = SolitudeLive ───────
$la_mode = sanitize_key( $_GET['mode'] ?? '' );
$la_route_partial = '';
if ( $la_mode === 'hub' ) {
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

// ─── Solitude content ────────────────────────────────────────────────
// Phrases the user can pick. Each carries the full Arabic, transliteration,
// English meaning, a "short" form (used in the bottom-left chip), and the
// inhale/exhale Arabic halves (used during a playing session — see the JS).
//
// `breath_s` = full cycle in seconds. We default to 7s (matches Claude
// Design's cueT cadence of 3500ms per half — Inhale/Exhale). Longer
// phrases get a slower cadence so each half has time to speak.
// Each phrase now carries BOTH Arabic and transliteration halves so
// non-Arabic readers can also follow the breath-paced split. Inhale
// half is shown during the inhale cue; exhale half during the exhale.
$la_sol_phrases = [
	[
		'key'             => 'kalimah',
		'arabic'          => 'لَا إِلٰهَ إِلَّا ٱللَّٰه',
		'short'           => 'لَا إِلٰهَ',
		'translit'        => 'Lā ilāha illa-llāh',
		'meaning'         => 'There is no god but Allah',
		'arabic_inhale'   => 'لَا إِلٰهَ',
		'arabic_exhale'   => 'إِلَّا ٱللَّٰه',
		'translit_inhale' => 'Lā ilāha',
		'translit_exhale' => 'illa-llāh',
		'breath_s'        => 10,
	],
	[
		'key'             => 'allah',
		'arabic'          => 'يَا ٱللَّٰه',
		'short'           => 'يَا ٱللَّٰه',
		'translit'        => 'Yā Allāh',
		'meaning'         => 'O Allah — calling on the Divine Name',
		'arabic_inhale'   => 'يَا',
		'arabic_exhale'   => 'ٱللَّٰه',
		'translit_inhale' => 'Yā',
		'translit_exhale' => 'Allāh',
		'breath_s'        => 10,
	],
	[
		'key'             => 'subhanallah',
		'arabic'          => 'سُبْحَانَ ٱللَّٰه',
		'short'           => 'سُبْحَانَ',
		'translit'        => 'Subḥān Allāh',
		'meaning'         => 'Glory be to Allah',
		'arabic_inhale'   => 'سُبْحَانَ',
		'arabic_exhale'   => 'ٱللَّٰه',
		'translit_inhale' => 'Subḥān',
		'translit_exhale' => 'Allāh',
		'breath_s'        => 7,
	],
	[
		'key'             => 'alhamdulillah',
		'arabic'          => 'ٱلْحَمْدُ لِلَّٰه',
		'short'           => 'ٱلْحَمْدُ',
		'translit'        => 'Alḥamdu lillāh',
		'meaning'         => 'All praise is for Allah',
		'arabic_inhale'   => 'ٱلْحَمْدُ',
		'arabic_exhale'   => 'لِلَّٰه',
		'translit_inhale' => 'Alḥamdu',
		'translit_exhale' => 'lillāh',
		'breath_s'        => 7,
	],
	[
		'key'             => 'allahuakbar',
		'arabic'          => 'ٱللَّٰهُ أَكْبَر',
		'short'           => 'أَكْبَر',
		'translit'        => 'Allāhu Akbar',
		'meaning'         => 'Allah is the Greatest',
		'arabic_inhale'   => 'ٱللَّٰهُ',
		'arabic_exhale'   => 'أَكْبَر',
		'translit_inhale' => 'Allāhu',
		'translit_exhale' => 'Akbar',
		'breath_s'        => 7,
	],
	[
		'key'             => 'astaghfirullah',
		'arabic'          => 'أَسْتَغْفِرُ ٱللَّٰه',
		'short'           => 'أَسْتَغْفِرُ',
		'translit'        => 'Astaghfirullāh',
		'meaning'         => 'I seek forgiveness of Allah',
		'arabic_inhale'   => 'أَسْتَغْفِرُ',
		'arabic_exhale'   => 'ٱللَّٰه',
		'translit_inhale' => 'Astaghfiru',
		'translit_exhale' => 'Allāh',
		'breath_s'        => 8,
	],
];

// Target counts. n = 0 means infinite ("until still"). Tags are short
// labels Claude Design uses to give each option a tradition (Sunnah,
// Tasbīḥ, etc.) — keeps the picker informative without being heavy.
$la_sol_targets = [
	[ 'n' => 33,  'tag' => 'Sunnah' ],
	[ 'n' => 100, 'tag' => 'Tasbīḥ' ],
	[ 'n' => 300, 'tag' => 'Long sitting' ],
	[ 'n' => 0,   'tag' => 'Until still' ],
];

// Wave 103: scene library extracted to a shared partial so Pulse
// + Names can use the same 6 ambient backdrops without duplicating
// the YouTube IDs / gradient gradients.
require_once get_template_directory() . '/inc/dhikr-live-scenes.php';
$la_sol_scenes = $la_dhikr_scenes;

get_header();
?>
<main class="la-app la-app--dhikr-meditate la-dhikr-live" data-dhikr-mode="solitude">

	<?php // Wave 95b mode-switcher pills (unchanged) ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" aria-current="page">Solitude</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">Pulse</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<?php // Wave 102: explicit scene chip row below the mode pills.
	// The swipe-with-dots gesture stays as a power-user alternative
	// but most users will use the chips — discoverable, one tap. ?>
	<nav class="sol-scene-chips" data-sol-chips aria-label="Ambient scene">
		<?php foreach ( $la_sol_scenes as $i => $s ) : ?>
			<button type="button"
				class="sol-scene-chip <?php echo $i === 0 ? 'is-active' : ''; ?>"
				data-sol-scene-chip="<?php echo (int) $i; ?>"
				aria-label="<?php echo esc_attr( $s['label'] ); ?> scene">
				<span class="sol-scene-chip-emoji" aria-hidden="true"><?php echo $s['emoji'] ?? '🌙'; ?></span>
				<span class="sol-scene-chip-label"><?php echo esc_html( $s['label'] ); ?></span>
			</button>
		<?php endforeach; ?>
	</nav>

	<div class="sol-live" data-sol>

		<?php // 1. Swipeable scene track — 6 full-bleed CSS gradients ?>
		<div class="sol-scene-track" data-sol-track>
			<?php foreach ( $la_sol_scenes as $s ) : ?>
				<div class="sol-scene" style="background: <?php echo esc_attr( $s['bg'] ); ?>;">
					<div class="sol-scene-stars" aria-hidden="true"></div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php // Wave 96b: YouTube ambient video layer. One iframe, JS swaps
		// src when the scene settles. Sits above the gradients, fades in
		// when a video is present.
		// Wave 103b: hard click blocker sits inside the wrapper at z 1
		// so any tap that would land on the iframe is captured and
		// discarded — iOS Safari ignores pointer-events:none on
		// loaded iframes, so this is the only reliable guarantee. ?>
		<div class="sol-yt" data-sol-yt aria-hidden="true">
			<div class="sol-yt-blocker" aria-hidden="true"></div>
		</div>

		<div class="sol-scrim" aria-hidden="true"></div>

		<?php // 2. Stage — orb (with progress ring) + counter + translit ?>
		<div class="sol-stage">
			<div class="sol-orb-wrap">
				<svg class="sol-ring" viewBox="0 0 264 264" aria-hidden="true">
					<circle cx="132" cy="132" r="118" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="2"></circle>
					<circle data-sol-ring-prog cx="132" cy="132" r="118" fill="none"
						stroke="var(--gold)" stroke-width="3" stroke-linecap="round"
						stroke-dasharray="741.42" stroke-dashoffset="741.42"
						transform="rotate(-90 132 132)"
						style="transition: stroke-dashoffset .45s ease; filter: drop-shadow(0 0 7px var(--gold));"></circle>
				</svg>
				<div class="sol-orb" data-sol-orb style="--orbtint: <?php echo esc_attr( $la_sol_scenes[0]['orb'] ); ?>;">
					<span class="sol-cue" data-sol-cue hidden>Inhale</span>
					<span class="sol-ar ar" data-sol-orb-ar dir="rtl" lang="ar"><?php echo esc_html( $la_sol_phrases[0]['arabic'] ); ?></span>
					<?php // Wave 96c: transliteration under the Arabic so
					// non-Arabic readers can also follow the breath split. ?>
					<span class="sol-orb-translit" data-sol-orb-translit hidden></span>
				</div>
			</div>
			<div class="sol-readout">
				<span class="sol-count" data-sol-count>0</span>
				<span class="sol-target" data-sol-target>/ 33</span>
			</div>
			<div class="sol-translit" data-sol-translit><?php echo esc_html( $la_sol_phrases[0]['translit'] ); ?> · <span class="sol-en"><?php echo esc_html( $la_sol_phrases[0]['meaning'] ); ?></span></div>
		</div>

		<?php // 3. Tap-anywhere overlay — count on tap, horizontal drag = scene swipe ?>
		<div class="sol-touch" data-sol-touch aria-hidden="true"></div>

		<?php // 4. Scene dots + current scene label ?>
		<div class="sol-dots" data-sol-dots>
			<?php foreach ( $la_sol_scenes as $i => $s ) : ?>
				<span class="sdot <?php echo $i === 0 ? 'on' : ''; ?>" data-sol-dot="<?php echo (int) $i; ?>"></span>
			<?php endforeach; ?>
			<span class="sol-scene-label" data-sol-scene-label><?php echo esc_html( $la_sol_scenes[0]['label'] ); ?></span>
		</div>

		<?php // 5. Bottom control bar — phrase / play / target ?>
		<div class="live-bar">
			<button type="button" class="live-ctl phrase" data-sol-sheet="phrase" aria-label="Choose dhikr phrase">
				<span class="live-ctl-ar ar" data-sol-phrase-short dir="rtl" lang="ar"><?php echo esc_html( $la_sol_phrases[0]['short'] ); ?></span>
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
			<button type="button" class="live-play" data-sol-play aria-label="Play or pause guided breathing">
				<svg data-sol-play-icon width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="margin-left:3px"><path d="M9 6l8 6-8 6V6z"/></svg>
				<svg data-sol-pause-icon width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
			</button>
			<button type="button" class="live-ctl target" data-sol-sheet="target" aria-label="Choose target count">
				<span class="live-ctl-n" data-sol-target-display>33</span>
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
		</div>

		<?php // 6. Reset button — only when count > 0 ?>
		<button type="button" class="live-reset" data-sol-reset aria-label="Reset count" hidden>
			<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
		</button>

		<?php // Wave 96b: mute toggle — top-left mirror of reset.
		// Wave 103b: explicit ambient-video play/pause button next to
		// mute. Since taps on the screen no longer reach the YT iframe
		// (click blocker), these two buttons are the only way to
		// control the backdrop video, by design — keeps the dhikr
		// surface uncluttered by accidental YT chrome. ?>
		<button type="button" class="live-mute is-muted" data-sol-mute aria-label="Toggle ambient sound" aria-pressed="false">
			<svg data-sol-mute-on  width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
			<svg data-sol-mute-off width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
		</button>
		<button type="button" class="live-vid" data-sol-vid aria-label="Pause or play ambient video" aria-pressed="true">
			<svg data-sol-vid-pause width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
			<svg data-sol-vid-play  width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" hidden><path d="M9 6l8 6-8 6V6z"/></svg>
		</button>

		<?php // 7. Bottom sheets — phrase + target pickers ?>
		<div class="sheet-backdrop" data-sol-sheet-close hidden></div>
		<div class="sheet" data-sol-sheet-panel="phrase" hidden role="dialog" aria-modal="true" aria-label="Choose dhikr">
			<div class="sheet-grab" aria-hidden="true"></div>
			<div class="sheet-title">Choose your dhikr</div>
			<div class="sheet-list">
				<?php foreach ( $la_sol_phrases as $i => $p ) : ?>
					<button type="button" class="phrase-opt <?php echo $i === 0 ? 'sel' : ''; ?>"
						data-sol-pick-phrase="<?php echo (int) $i; ?>">
						<span class="phrase-opt-ar ar" dir="rtl" lang="ar"><?php echo esc_html( $p['arabic'] ); ?></span>
						<span class="phrase-opt-meta">
							<b><?php echo esc_html( $p['translit'] ); ?></b>
							<span><?php echo esc_html( $p['meaning'] ); ?></span>
						</span>
						<span class="phrase-opt-check" aria-hidden="true">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="sheet" data-sol-sheet-panel="target" hidden role="dialog" aria-modal="true" aria-label="Choose count target">
			<div class="sheet-grab" aria-hidden="true"></div>
			<div class="sheet-title">How many times?</div>
			<div class="sheet-targets">
				<?php foreach ( $la_sol_targets as $i => $t ) : ?>
					<button type="button" class="target-opt <?php echo $i === 0 ? 'sel' : ''; ?>"
						data-sol-pick-target="<?php echo (int) $i; ?>">
						<span class="target-opt-n"><?php echo $t['n'] === 0 ? '∞' : (int) $t['n']; ?></span>
						<span class="target-opt-tag"><?php echo esc_html( $t['tag'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<script id="la-sol-config" type="application/json">
		<?php echo wp_json_encode( [
			'phrases' => $la_sol_phrases,
			'scenes'  => $la_sol_scenes,
			'targets' => $la_sol_targets,
		] ); ?>
	</script>

	<script>
	/* Wave 96: SolitudeLive state machine (vanilla JS, ports the React
	   component from Claude Design's dhikr-app.jsx). Self-contained.
	   Persists pi/ti/scene to localStorage so the user's last setup
	   survives reloads. */
	(function() {
		const root = document.querySelector('[data-sol]');
		if ( ! root ) return;
		const cfg  = JSON.parse( document.getElementById('la-sol-config').textContent );

		// ── state ──
		let pi    = parseInt( localStorage.getItem('la_sol_pi') || '0', 10 );
		let ti    = parseInt( localStorage.getItem('la_sol_ti') || '0', 10 );
		let scene = parseInt( localStorage.getItem('la_sol_scene') || '0', 10 );
		if ( pi    < 0 || pi    >= cfg.phrases.length ) pi    = 0;
		if ( ti    < 0 || ti    >= cfg.targets.length ) ti    = 0;
		if ( scene < 0 || scene >= cfg.scenes.length  ) scene = 0;
		let count   = 0;
		let playing = false;
		let cue     = 'Inhale';
		let cueT    = null;
		let cntT    = null;

		// ── refs ──
		const track       = root.querySelector('[data-sol-track]');
		const orb         = root.querySelector('[data-sol-orb]');
		const orbAr       = root.querySelector('[data-sol-orb-ar]');
		const orbTr       = root.querySelector('[data-sol-orb-translit]');  // Wave 96c
		const cueEl       = root.querySelector('[data-sol-cue]');
		const countEl     = root.querySelector('[data-sol-count]');
		const targetEl    = root.querySelector('[data-sol-target]');
		const translitEl  = root.querySelector('[data-sol-translit]');
		const ringProg    = root.querySelector('[data-sol-ring-prog]');
		const phraseShort = root.querySelector('[data-sol-phrase-short]');
		const targetDisp  = root.querySelector('[data-sol-target-display]');
		const playBtn     = root.querySelector('[data-sol-play]');
		const playIcon    = root.querySelector('[data-sol-play-icon]');
		const pauseIcon   = root.querySelector('[data-sol-pause-icon]');
		const resetBtn    = root.querySelector('[data-sol-reset]');
		const dots        = root.querySelectorAll('[data-sol-dot]');
		const sceneLabel  = root.querySelector('[data-sol-scene-label]');
		/* Wave 102: chip nav lives OUTSIDE .sol-live (it's a sibling
		   inside <main>) so we have to query the document directly,
		   not root. Wave 102 v1 used root.querySelectorAll and got
		   zero chips — handlers never attached, taps did nothing. */
		const chipsNav    = document.querySelector('[data-sol-chips]');
		const sceneChips  = chipsNav ? chipsNav.querySelectorAll('[data-sol-scene-chip]') : [];
		const touch       = root.querySelector('[data-sol-touch]');
		const sheetScrim  = root.querySelector('[data-sol-sheet-close]');
		const sheetPanels = root.querySelectorAll('[data-sol-sheet-panel]');
		const sheetBtns   = root.querySelectorAll('[data-sol-sheet]');
		const phraseOpts  = root.querySelectorAll('[data-sol-pick-phrase]');
		const targetOpts  = root.querySelectorAll('[data-sol-pick-target]');
		// Wave 96b — YouTube ambient layer + mute toggle
		// Wave 103b — explicit play/pause control + YT.Player API
		const yt          = root.querySelector('[data-sol-yt]');
		const muteBtn     = root.querySelector('[data-sol-mute]');
		const muteOn      = root.querySelector('[data-sol-mute-on]');
		const muteOff     = root.querySelector('[data-sol-mute-off]');
		const vidBtn      = root.querySelector('[data-sol-vid]');
		const vidPauseIcn = root.querySelector('[data-sol-vid-pause]');
		const vidPlayIcn  = root.querySelector('[data-sol-vid-play]');
		/* Wave 103c: default to UNMUTED preference (user wants nature
		   sound). The player still has to START muted because of
		   browser autoplay policy — but the moment the user picks
		   a scene chip (a real user gesture), we unmute. After that,
		   the preference is sticky unless the user explicitly hits
		   the mute button. The localStorage value is '1' = muted,
		   '0' or missing = unmuted. */
		let isMuted = ( localStorage.getItem('la_sol_muted') === '1' );
		let vidPlaying = true;  // ambient video is "playing" by default
		let currentVideoId = '';
		let ytPlayer = null;

		const RING_C = 2 * Math.PI * 118;  // ring circumference

		const ph = () => cfg.phrases[pi];
		const tg = () => cfg.targets[ti];
		const sc = () => cfg.scenes[scene];

		/* Wave 103b: lazy-load the YT IFrame Player API once. Returns
		   a promise that resolves to window.YT. Same pattern as
		   Witness. The API is global so multiple players coexist. */
		function loadYT() {
			return new Promise( resolve => {
				if ( window.YT && window.YT.Player ) return resolve(window.YT);
				if ( ! document.querySelector('script[src*="youtube.com/iframe_api"]') ) {
					const tag = document.createElement('script');
					tag.src = 'https://www.youtube.com/iframe_api';
					document.head.appendChild(tag);
				}
				const prev = window.onYouTubeIframeAPIReady;
				window.onYouTubeIframeAPIReady = function() {
					if ( typeof prev === 'function' ) try { prev(); } catch (_) {}
					resolve(window.YT);
				};
			});
		}

		/* Wave 103b: swap the YT video using the IFrame Player API.
		   First call mounts the player; subsequent calls swap the
		   video via loadVideoById (no full iframe re-create — clean,
		   no flash of YT chrome). onReady forces playVideo() to
		   guarantee autoplay even on platforms where the URL
		   `autoplay=1` parameter is ignored. */
		function applyVideo() {
			const v = sc().video || '';
			if ( v === currentVideoId ) return;
			currentVideoId = v;
			if ( ! v ) {
				yt.classList.remove('is-active');
				if ( ytPlayer && ytPlayer.stopVideo ) {
					try { ytPlayer.stopVideo(); } catch (_) {}
				}
				return;
			}
			yt.classList.add('is-active');
			if ( ytPlayer && ytPlayer.loadVideoById ) {
				try { ytPlayer.loadVideoById({ videoId: v }); } catch (_) {}
				setTimeout( () => {
					try {
						if ( isMuted ) ytPlayer.mute();  else ytPlayer.unMute();
						if ( vidPlaying ) ytPlayer.playVideo(); else ytPlayer.pauseVideo();
					} catch (_) {}
				}, 80 );
				return;
			}
			// First mount — create the YT.Player. Replace any old node
			// in .sol-yt with a fresh placeholder div for the API to
			// upgrade into an iframe.
			yt.innerHTML = '';
			const blocker = document.createElement('div');
			blocker.className = 'sol-yt-blocker';
			blocker.setAttribute('aria-hidden', 'true');
			const mount = document.createElement('div');
			yt.appendChild(mount);
			yt.appendChild(blocker);   // blocker AFTER iframe → stacks above
			loadYT().then( YT => {
				ytPlayer = new YT.Player(mount, {
					videoId: v,
					host: 'https://www.youtube-nocookie.com',
					playerVars: {
						autoplay: 1, mute: 1, controls: 0, playsinline: 1,
						rel: 0, modestbranding: 1, loop: 1, playlist: v,
						iv_load_policy: 3, fs: 0, disablekb: 1,
					},
					events: {
						onReady: (e) => {
							try {
								if ( isMuted ) e.target.mute(); else { e.target.unMute(); e.target.setVolume(100); }
								if ( vidPlaying ) e.target.playVideo(); else e.target.pauseVideo();
							} catch (_) {}
						},
					},
				});
			});
		}

		function setMute(on) {
			isMuted = !! on;
			localStorage.setItem('la_sol_muted', isMuted ? '1' : '0');
			muteBtn.classList.toggle('is-muted', isMuted);
			muteBtn.setAttribute('aria-pressed', String( ! isMuted ));
			if ( muteOn )  muteOn.hidden  =   isMuted;
			if ( muteOff ) muteOff.hidden = ! isMuted;
			if ( ytPlayer ) {
				try {
					if ( isMuted ) ytPlayer.mute();
					else { ytPlayer.unMute(); ytPlayer.setVolume(100); }
				} catch (_) {}
			}
		}

		/* Wave 103b: explicit ambient-video play/pause control. */
		function setVidPlaying(on) {
			vidPlaying = !! on;
			vidBtn.classList.toggle('is-paused', ! vidPlaying);
			vidBtn.setAttribute('aria-pressed', String( vidPlaying ));
			if ( vidPauseIcn ) vidPauseIcn.hidden = ! vidPlaying;
			if ( vidPlayIcn  ) vidPlayIcn.hidden  =   vidPlaying;
			if ( ytPlayer ) {
				try { vidPlaying ? ytPlayer.playVideo() : ytPlayer.pauseVideo(); } catch (_) {}
			}
		}

		function render() {
			// orb tint flows from scene
			orb.style.setProperty('--orbtint', sc().orb);
			// Wave 100: drive the orb's breathing CSS animation off the
			// current phrase's breath_s, so the visual peak (scale up)
			// lines up exactly with the inhale half and the trough
			// (scale down) lines up with the exhale half. Without this,
			// the animation was hardcoded to 7s and drifted against
			// any phrase with a different cadence (kalimah = 10s).
			orb.style.setProperty('--breath-s', ( ph().breath_s || 7 ) + 's');
			// breath-split halves while playing; full phrase when stopped
			if ( playing && ( ph().arabic_inhale || ph().arabic_exhale ) ) {
				const isInhale = ( cue === 'Inhale' );
				orbAr.textContent = isInhale
					? ( ph().arabic_inhale || ph().arabic )
					: ( ph().arabic_exhale || ph().arabic );
				// Wave 100: also flip the transliteration half so
				// non-Arabic readers can follow along audibly.
				if ( orbTr ) {
					const trHalf = isInhale
						? ( ph().translit_inhale || '' )
						: ( ph().translit_exhale || '' );
					orbTr.textContent = trHalf;
					orbTr.hidden = ! trHalf;
				}
			} else {
				orbAr.textContent = ph().arabic;
				if ( orbTr ) orbTr.hidden = true;
			}
			// inhale/exhale cue label
			cueEl.hidden = ! playing;
			cueEl.textContent = cue;
			// count + target readout
			countEl.textContent = count;
			const tn = tg().n;
			targetEl.textContent = ( tn === 0 ) ? '∞' : '/ ' + tn;
			// translit + meaning
			const en = document.createElement('span');
			en.className = 'sol-en';
			en.textContent = ph().meaning;
			translitEl.textContent = ph().translit + ' · ';
			translitEl.appendChild(en);
			// progress ring
			const prog = ( tn === 0 ) ? 0 : Math.min( 1, count / tn );
			ringProg.setAttribute('stroke-dasharray',  RING_C);
			ringProg.setAttribute('stroke-dashoffset', RING_C * ( 1 - prog ));
			// bottom-bar chips
			phraseShort.textContent = ph().short || ph().arabic;
			targetDisp.textContent  = ( tn === 0 ) ? '∞' : tn;
			// play/pause icons + orb breathing animation
			playIcon.hidden  =   playing;
			pauseIcon.hidden = ! playing;
			orb.classList.toggle('playing', playing);
			// scene track + dots + label + chip selector (Wave 102)
			track.style.transition = '';
			track.style.transform = 'translateX(' + ( -scene * 100 ) + '%)';
			dots.forEach( d => d.classList.toggle( 'on', parseInt(d.dataset.solDot, 10) === scene ) );
			sceneLabel.textContent = sc().label;
			sceneChips.forEach( c => c.classList.toggle( 'is-active', parseInt(c.dataset.solSceneChip, 10) === scene ) );
			// reset button
			resetBtn.hidden = ! ( count > 0 );
		}

		function doCount() {
			const tn = tg().n;
			if ( tn !== 0 && count >= tn ) return;
			count++;
			if ( navigator.vibrate ) navigator.vibrate( 8 );
			render();
			// Wave 101c: if we just landed on the target during a
			// guided-breathing session, end the session cleanly so
			// the orb stops pulsing + cue stops + auto-count stops.
			// (Manual tap-counts also benefit — once you reach the
			// target, further taps no-op above anyway.)
			if ( playing && tn !== 0 && count >= tn ) {
				setPlaying( false );
			}
		}

		function reset() { count = 0; render(); }

		/* Wave 100b: forcibly restart the orb's CSS keyframe animation
		   so its visual position is locked to frame 0 (= scale .84 =
		   ready to inflate). Used (a) when playback starts, (b) when
		   phrase changes mid-play (duration changes but the animation
		   would otherwise continue from its old position), and (c) at
		   the start of every breath cycle (when cue resets to Inhale)
		   so setInterval drift can't desync them over time. */
		function restartOrbAnim() {
			if ( ! orb ) return;
			orb.classList.remove('playing');
			void orb.offsetWidth;   // force reflow → next add re-triggers anim
			if ( playing ) orb.classList.add('playing');
		}

		function toggleCue() {
			cue = ( cue === 'Inhale' ) ? 'Exhale' : 'Inhale';
			// Start of a new breath cycle — re-lock the orb visual
			// to the cue so they never drift apart.
			if ( cue === 'Inhale' ) restartOrbAnim();
			render();
		}

		function setPlaying(p) {
			playing = !! p;
			if ( cueT ) { clearInterval(cueT); cueT = null; }
			if ( cntT ) { clearInterval(cntT); cntT = null; }
			if ( playing ) {
				cue = 'Inhale';
				restartOrbAnim();   // start visual at scale .84, ready to inflate
				const half = Math.round( ( ph().breath_s || 7 ) * 1000 / 2 );
				const full = Math.round( ( ph().breath_s || 7 ) * 1000 );
				cueT = setInterval( toggleCue,   half );
				cntT = setInterval( doCount,     full );
			}
			render();
		}

		function openSheet(name) {
			sheetScrim.hidden = false;
			sheetPanels.forEach( p => { p.hidden = ( p.dataset.solSheetPanel !== name ); } );
		}
		function closeSheet() {
			sheetScrim.hidden = true;
			sheetPanels.forEach( p => { p.hidden = true; } );
		}

		// ── tap + horizontal drag on the overlay ──
		const drag = { x: 0, active: false, moved: false, dx: 0 };
		touch.addEventListener('pointerdown', (e) => {
			drag.x = e.clientX; drag.active = true; drag.moved = false; drag.dx = 0;
			track.style.transition = 'none';
			try { touch.setPointerCapture( e.pointerId ); } catch (_) {}
		});
		touch.addEventListener('pointermove', (e) => {
			if ( ! drag.active ) return;
			const dx = e.clientX - drag.x;
			if ( Math.abs(dx) > 6 ) drag.moved = true;
			drag.dx = dx;
			// drag-follow with .55 damping (same coefficient as Claude Design)
			track.style.transform = 'translateX(calc(' + ( -scene * 100 ) + '% + ' + ( dx * 0.55 ) + 'px))';
		});
		const onUp = (e) => {
			if ( ! drag.active ) return;
			const dx = drag.dx;
			drag.active = false;
			if ( drag.moved ) {
				if      ( dx < -40 && scene < cfg.scenes.length - 1 ) scene++;
				else if ( dx >  40 && scene > 0 )                     scene--;
				localStorage.setItem('la_sol_scene', String(scene));
				applyVideo();   // settle on new scene → load its video
			} else {
				doCount();
			}
			render();
		};
		touch.addEventListener('pointerup',     onUp);
		touch.addEventListener('pointercancel', onUp);

		// ── controls ──
		playBtn.addEventListener('click',  () => setPlaying( ! playing ));
		resetBtn.addEventListener('click', () => reset());
		sheetScrim.addEventListener('click', () => closeSheet());
		sheetBtns.forEach( b => b.addEventListener('click', () => openSheet( b.dataset.solSheet )) );
		phraseOpts.forEach( b => b.addEventListener('click', () => {
			pi = parseInt( b.dataset.solPickPhrase, 10 );
			count = 0;
			localStorage.setItem('la_sol_pi', String(pi));
			phraseOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.solPickPhrase, 10) === pi ) );
			closeSheet();
			// if we were playing, restart timers with the new breath_s
			if ( playing ) setPlaying(true);
			render();
		}) );
		targetOpts.forEach( b => b.addEventListener('click', () => {
			ti = parseInt( b.dataset.solPickTarget, 10 );
			localStorage.setItem('la_sol_ti', String(ti));
			targetOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.solPickTarget, 10) === ti ) );
			closeSheet();
			render();
		}) );

		// restore selections in sheet UIs from persisted state
		phraseOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.solPickPhrase, 10) === pi ) );
		targetOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.solPickTarget, 10) === ti ) );

		/* Wave 102/103c: scene chip selector — tap a chip to switch
		   scene. Calls applyVideo + render so iframe / dots sync.
		   Wave 103c: chip click also unmutes the player (if the user
		   hasn't explicitly muted) — chip taps are user gestures, so
		   the browser allows sound to start. This is how the natural
		   sound for Ocean / Forest / etc. starts playing without the
		   user having to hunt for the mute button. */
		sceneChips.forEach( c => c.addEventListener( 'click', () => {
			const i = parseInt( c.dataset.solSceneChip, 10 );
			if ( i === scene ) {
				// Already active — but still treat as a "yes, I want this"
				// gesture: unmute if not explicitly muted.
				if ( ! isMuted ) setMute( false );
				return;
			}
			scene = i;
			localStorage.setItem( 'la_sol_scene', String(scene) );
			applyVideo();
			render();
			// Auto-unmute on this user-gesture (browser policy compliant).
			if ( ! isMuted ) setMute( false );
			// Scroll the chip into the centre of the row for visual
			// confirmation that the selection took.
			try { c.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); } catch (_) {}
		}) );
		// On first render, centre the currently-active chip if it's
		// off-screen (e.g. user previously selected scene #4 of 6).
		const activeChip = chipsNav?.querySelector('[data-sol-scene-chip].is-active');
		if ( activeChip ) {
			try { activeChip.scrollIntoView({ block: 'nearest', inline: 'center' }); } catch (_) {}
		}

		// Wave 96b/103b: mount the YT layer + sync controls.
		muteBtn.addEventListener('click', () => setMute( ! isMuted ));
		vidBtn .addEventListener('click', () => setVidPlaying( ! vidPlaying ));
		setMute( isMuted );          // syncs icon + aria-pressed before player exists
		setVidPlaying( vidPlaying ); // same — defaults to playing
		applyVideo();                // mount the current scene's video via YT.Player API

		render();
	})();
	</script>

</main>
<?php
get_footer();
