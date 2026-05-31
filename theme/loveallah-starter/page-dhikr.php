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
$la_sol_phrases = [
	[
		'key'           => 'kalimah',
		'arabic'        => 'لَا إِلٰهَ إِلَّا ٱللَّٰه',
		'short'         => 'لَا إِلٰهَ',
		'translit'      => 'Lā ilāha illa-llāh',
		'meaning'       => 'There is no god but Allah',
		'arabic_inhale' => 'لَا إِلٰهَ',
		'arabic_exhale' => 'إِلَّا ٱللَّٰه',
		'breath_s'      => 10,
	],
	[
		'key'           => 'allah',
		'arabic'        => 'يَا ٱللَّٰه',
		'short'         => 'يَا ٱللَّٰه',
		'translit'      => 'Yā Allāh',
		'meaning'       => 'O Allah — calling on the Divine Name',
		'arabic_inhale' => 'يَا',
		'arabic_exhale' => 'ٱللَّٰه',
		'breath_s'      => 10,
	],
	[
		'key'           => 'subhanallah',
		'arabic'        => 'سُبْحَانَ ٱللَّٰه',
		'short'         => 'سُبْحَانَ',
		'translit'      => 'Subḥān Allāh',
		'meaning'       => 'Glory be to Allah',
		'arabic_inhale' => 'سُبْحَانَ',
		'arabic_exhale' => 'ٱللَّٰه',
		'breath_s'      => 7,
	],
	[
		'key'           => 'alhamdulillah',
		'arabic'        => 'ٱلْحَمْدُ لِلَّٰه',
		'short'         => 'ٱلْحَمْدُ',
		'translit'      => 'Alḥamdu lillāh',
		'meaning'       => 'All praise is for Allah',
		'arabic_inhale' => 'ٱلْحَمْدُ',
		'arabic_exhale' => 'لِلَّٰه',
		'breath_s'      => 7,
	],
	[
		'key'           => 'allahuakbar',
		'arabic'        => 'ٱللَّٰهُ أَكْبَر',
		'short'         => 'أَكْبَر',
		'translit'      => 'Allāhu Akbar',
		'meaning'       => 'Allah is the Greatest',
		'arabic_inhale' => 'ٱللَّٰهُ',
		'arabic_exhale' => 'أَكْبَر',
		'breath_s'      => 7,
	],
	[
		'key'           => 'astaghfirullah',
		'arabic'        => 'أَسْتَغْفِرُ ٱللَّٰه',
		'short'         => 'أَسْتَغْفِرُ',
		'translit'      => 'Astaghfirullāh',
		'meaning'       => 'I seek forgiveness of Allah',
		'arabic_inhale' => 'أَسْتَغْفِرُ',
		'arabic_exhale' => 'ٱللَّٰه',
		'breath_s'      => 8,
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

// Ambient scenes — full-bleed CSS gradients (instant, always visible)
// + an optional YouTube ambient video that fades in once the scene
// settles after a swipe. The gradient gives an instant identity; the
// video deepens it. Picking a scene with no video keeps the gradient
// only (silent option).
//
// Video IDs are the same ones we curated for the older Wave 95 scene
// library — verified to be ambient loops that allow embedding.
$la_sol_scenes = [
	[ 'id' => 'moonlit', 'label' => 'Moonlit',
	  'bg'    => 'radial-gradient(80% 55% at 50% 26%, #33386a 0%, #1a1d3e 48%, #0a0b1c 100%)',
	  'orb'   => '#cfd6ff',
	  'video' => '' ],   // gradient only — pure silence
	[ 'id' => 'ocean', 'label' => 'Ocean',
	  'bg'    => 'radial-gradient(85% 58% at 50% 24%, #155560 0%, #0c2f38 46%, #06151b 100%)',
	  'orb'   => '#bfeef0',
	  'video' => 'NJXzcQJi_A8' ],   // waves only — no music
	[ 'id' => 'forest', 'label' => 'Forest',
	  'bg'    => 'radial-gradient(85% 58% at 50% 26%, #265141 0%, #143026 46%, #08160f 100%)',
	  'orb'   => '#cdeed2',
	  'video' => 'BHACKCNDMW8' ],   // birds at dawn — no music
	[ 'id' => 'cosmos', 'label' => 'Cosmos',
	  'bg'    => 'radial-gradient(85% 58% at 50% 24%, #3a2a5c 0%, #1e1438 48%, #0a0712 100%)',
	  'orb'   => '#e6d4ff',
	  'video' => 'Y_plhk1FUQA' ],   // hubble cosmos — ambient music
	[ 'id' => 'dawn', 'label' => 'Sahara',
	  'bg'    => 'radial-gradient(90% 60% at 50% 30%, #6e4444 0%, #3a2330 46%, #160c18 100%)',
	  'orb'   => '#ffd9c2',
	  'video' => 'gFmDx9oj3DU' ],   // sahara at first light — cinematic
	[ 'id' => 'haram', 'label' => 'Haram',
	  'bg'    => 'radial-gradient(85% 58% at 50% 26%, #3a2e1c 0%, #20180c 46%, #0a0805 100%)',
	  'orb'   => '#f0d8a8',
	  'video' => 'bNY8a2BB5Gc' ],   // live tawaf from Makkah
];

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
		// when a video is present. ?>
		<div class="sol-yt" data-sol-yt aria-hidden="true"></div>

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

		<?php // Wave 96b: mute toggle — top-left mirror of reset. The scene
		// video is muted by default (otherwise YouTube autoplay blocks),
		// tap to unmute (counts as a user gesture). ?>
		<button type="button" class="live-mute is-muted" data-sol-mute aria-label="Toggle ambient sound" aria-pressed="false">
			<svg data-sol-mute-on  width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
			<svg data-sol-mute-off width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
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
		const touch       = root.querySelector('[data-sol-touch]');
		const sheetScrim  = root.querySelector('[data-sol-sheet-close]');
		const sheetPanels = root.querySelectorAll('[data-sol-sheet-panel]');
		const sheetBtns   = root.querySelectorAll('[data-sol-sheet]');
		const phraseOpts  = root.querySelectorAll('[data-sol-pick-phrase]');
		const targetOpts  = root.querySelectorAll('[data-sol-pick-target]');
		// Wave 96b — YouTube ambient layer + mute toggle
		const yt          = root.querySelector('[data-sol-yt]');
		const muteBtn     = root.querySelector('[data-sol-mute]');
		const muteOn      = root.querySelector('[data-sol-mute-on]');
		const muteOff     = root.querySelector('[data-sol-mute-off]');
		// Start muted by default — YouTube blocks autoplay-with-sound.
		let isMuted = ( localStorage.getItem('la_sol_muted') !== '0' );
		let currentVideoId = '';

		const RING_C = 2 * Math.PI * 118;  // ring circumference

		const ph = () => cfg.phrases[pi];
		const tg = () => cfg.targets[ti];
		const sc = () => cfg.scenes[scene];

		/* Wave 96b: swap the YT iframe to the current scene's video.
		   If no video, fade the layer out. Idempotent — does nothing
		   if the video hasn't changed (so we don't re-mount on every
		   render() call). */
		function applyVideo() {
			const v = sc().video || '';
			if ( v === currentVideoId ) return;
			currentVideoId = v;
			yt.innerHTML = '';
			if ( ! v ) { yt.classList.remove('is-active'); return; }
			const mp = isMuted ? '1' : '0';
			const url = 'https://www.youtube-nocookie.com/embed/' + v
				+ '?autoplay=1&mute=' + mp + '&loop=1&playlist=' + v
				+ '&controls=0&modestbranding=1&playsinline=1&rel=0'
				+ '&iv_load_policy=3&cc_load_policy=0&disablekb=1&fs=0&enablejsapi=1';
			const f = document.createElement('iframe');
			f.src = url;
			f.allow = 'autoplay; encrypted-media';
			f.setAttribute('frameborder', '0');
			f.setAttribute('aria-hidden', 'true');
			yt.appendChild(f);
			yt.classList.add('is-active');
		}

		/* Wave 96b: postMessage mute control. The YT IFrame API responds
		   to `{event:'command', func:'mute'/'unMute'}` on its window. */
		function postYt(func) {
			const f = yt.querySelector('iframe');
			if ( ! f || ! f.contentWindow ) return;
			try { f.contentWindow.postMessage(JSON.stringify({ event: 'command', func, args: [] }), '*'); } catch (_) {}
		}
		function setMute(on) {
			isMuted = !! on;
			localStorage.setItem('la_sol_muted', isMuted ? '1' : '0');
			muteBtn.classList.toggle('is-muted', isMuted);
			muteBtn.setAttribute('aria-pressed', String( ! isMuted ));
			if ( muteOn )  muteOn.hidden  =   isMuted;
			if ( muteOff ) muteOff.hidden = ! isMuted;
			postYt(isMuted ? 'mute' : 'unMute');
		}

		function render() {
			// orb tint flows from scene
			orb.style.setProperty('--orbtint', sc().orb);
			// breath-split halves while playing; full phrase when stopped
			if ( playing && ( ph().arabic_inhale || ph().arabic_exhale ) ) {
				orbAr.textContent = ( cue === 'Inhale' )
					? ( ph().arabic_inhale || ph().arabic )
					: ( ph().arabic_exhale || ph().arabic );
			} else {
				orbAr.textContent = ph().arabic;
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
			// scene track + dots + label
			track.style.transition = '';
			track.style.transform = 'translateX(' + ( -scene * 100 ) + '%)';
			dots.forEach( d => d.classList.toggle( 'on', parseInt(d.dataset.solDot, 10) === scene ) );
			sceneLabel.textContent = sc().label;
			// reset button
			resetBtn.hidden = ! ( count > 0 );
		}

		function doCount() {
			const tn = tg().n;
			if ( tn !== 0 && count >= tn ) return;
			count++;
			if ( navigator.vibrate ) navigator.vibrate( 8 );
			render();
		}

		function reset() { count = 0; render(); }

		function setPlaying(p) {
			playing = p;
			if ( cueT ) { clearInterval(cueT); cueT = null; }
			if ( cntT ) { clearInterval(cntT); cntT = null; }
			if ( playing ) {
				cue = 'Inhale';
				const half = Math.round( ( ph().breath_s || 7 ) * 1000 / 2 );
				const full = Math.round( ( ph().breath_s || 7 ) * 1000 );
				cueT = setInterval( () => { cue = ( cue === 'Inhale' ) ? 'Exhale' : 'Inhale'; render(); }, half );
				cntT = setInterval( () => { doCount(); }, full );
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

		// Wave 96b: mount the YT layer + sync the mute button to persisted state
		muteBtn.addEventListener('click', () => setMute( ! isMuted ));
		setMute( isMuted );   // syncs icon + aria-pressed without postMessage (no iframe yet)
		applyVideo();         // mount the current scene's video

		render();
	})();
	</script>

</main>
<?php
get_footer();
