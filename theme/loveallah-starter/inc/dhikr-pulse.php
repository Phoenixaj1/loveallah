<?php
/**
 * Dhikr Pulse — PulseLive single-screen (Wave 97).
 *
 * Ported from Claude Design's PulseLive component. Replaces the
 * previous setup → session flow with a live-first layout matching
 * Solitude's pattern:
 *
 *   • Top: Arabic phrase + transliteration
 *   • Centre: pulsing gold core + expanding ripple rings
 *   • Beat: starts at the phrase's resting BPM, eases toward stillness
 *     (e.g. 80 → 40). Each beat = +1 count + a 6ms vibrate.
 *   • Readout: live BPM number + a progress bar (resting → stillness)
 *   • Bottom bar: phrase chip (left) / play-pause (centre) / target chip (right)
 *   • Reset (top-right) appears once count > 0.
 *
 * Phrase-specific BPM curves preserved from the older implementation:
 *   subhanallah / alhamdulillah / allahu akbar  → 80 → 40
 *   la ilaha illa Allah                          → 60 → 30
 *   salawat (long)                               → 40 → 20
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Wave 101b: starting BPM lowered 80 → 74 per user — 80 felt rushed,
// 74 is closer to the resting heart-rate of a relaxed adult and lets
// the descent toward stillness feel gentler. Kalimah and salawat
// stay at their lower curves (longer phrases need slower cadence).
$la_pulse_phrases = [
	[ 'key' => 'subhanallah',
	  'ar' => 'سُبْحَانَ ٱللَّٰه',
	  'tr' => 'Subḥān Allāh',
	  'from' => 74, 'to' => 40 ],
	[ 'key' => 'alhamdulillah',
	  'ar' => 'ٱلْحَمْدُ لِلَّٰه',
	  'tr' => 'Alḥamdulillāh',
	  'from' => 74, 'to' => 40 ],
	[ 'key' => 'allahuakbar',
	  'ar' => 'ٱللَّٰهُ أَكْبَر',
	  'tr' => 'Allāhu Akbar',
	  'from' => 74, 'to' => 40 ],
	[ 'key' => 'kalimah',
	  'ar' => 'لَا إِلٰهَ إِلَّا ٱللَّٰه',
	  'tr' => 'Lā ilāha illa-llāh',
	  'from' => 60, 'to' => 30 ],
	[ 'key' => 'salawat',
	  'ar' => 'ٱللَّٰهُمَّ صَلِّ عَلَىٰ مُحَمَّد',
	  'tr' => 'Allāhumma ṣalli ʿalā Muḥammad',
	  'from' => 40, 'to' => 20 ],
];

$la_pulse_targets = [
	[ 'n' => 33,  'tag' => 'Sunnah' ],
	[ 'n' => 100, 'tag' => 'Tasbīḥ' ],
	[ 'n' => 300, 'tag' => 'Long sitting' ],
	[ 'n' => 0,   'tag' => 'Until still' ],
];

// Wave 103: shared ambient scene library — same 6 as Solitude.
require_once get_template_directory() . '/inc/dhikr-live-scenes.php';
?>
<main class="la-app la-app--pulse la-dhikr-live mode-pulse" data-dhikr-mode="pulse">

	<?php // Wave 95b mode-switcher pills (matches Solitude) ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Breathe</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>" aria-current="page">Focus</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pray' ) ); ?>">Pray</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<?php // Wave 103: scene chip selector — same as Solitude. ?>
	<nav class="sol-scene-chips" data-pul-chips aria-label="Ambient scene">
		<?php foreach ( $la_dhikr_scenes as $i => $s ) : ?>
			<button type="button"
				class="sol-scene-chip <?php echo $i === 0 ? 'is-active' : ''; ?>"
				data-pul-scene-chip="<?php echo (int) $i; ?>"
				aria-label="<?php echo esc_attr( $s['label'] ); ?> scene">
				<span class="sol-scene-chip-emoji" aria-hidden="true"><?php echo $s['emoji'] ?? '🌙'; ?></span>
				<span class="sol-scene-chip-label"><?php echo esc_html( $s['label'] ); ?></span>
			</button>
		<?php endforeach; ?>
	</nav>

	<div class="pul-live" data-pul>

		<?php // Wave 103/103b: ambient backdrop layers. Gradient base
		// (instant), YT iframe above (managed by YT.Player API), hard
		// click blocker so taps can't reach the iframe. ?>
		<div class="pul-bg-gradient" data-pul-bg style="background: <?php echo esc_attr( $la_dhikr_scenes[0]['bg'] ); ?>;" aria-hidden="true"></div>
		<div class="sol-yt" data-pul-yt aria-hidden="true">
			<div class="sol-yt-blocker" aria-hidden="true"></div>
		</div>
		<div class="sol-scrim" aria-hidden="true"></div>

		<?php // Wave 107: simplified rail — just mute + reset.
		// Heartbeat play/pause stays as the gold bottom-bar button. ?>
		<div class="amb-rail" data-pul-rail>
			<button type="button" class="amb-rail-btn is-muted" data-pul-mute aria-label="Toggle ambient sound" aria-pressed="false">
				<svg data-pul-mute-on  width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
				<svg data-pul-mute-off width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
			</button>
			<button type="button" class="amb-rail-btn" data-pul-reset aria-label="Reset count" hidden>
				<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
			</button>
		</div>

		<?php // Top — Arabic phrase + transliteration ?>
		<div class="pul-phrase">
			<div class="pul-ar ar" data-pul-ar dir="rtl" lang="ar"><?php echo esc_html( $la_pulse_phrases[0]['ar'] ); ?></div>
			<div class="pul-tr" data-pul-tr><?php echo esc_html( $la_pulse_phrases[0]['tr'] ); ?></div>
		</div>

		<?php // Wave 101b: centre column stacks pulse → count → BPM in a
		// single flex track so they can't overlap (was: pul-readout
		// absolute-positioned at bottom, count centered — collision
		// on shorter screens). One column, predictable rhythm. ?>
		<div class="pul-live-core">
			<div class="pulse-core-wrap" data-pul-wrap>
				<div class="pulse-core" data-pul-core></div>
			</div>
			<div class="sol-readout">
				<span class="sol-count" data-pul-count>0</span>
				<span class="sol-target" data-pul-target>/ 33</span>
			</div>
			<div class="pul-readout">
				<div class="pul-bpm">
					<b data-pul-bpm><?php echo (int) $la_pulse_phrases[0]['from']; ?></b>
					<small>BPM</small>
				</div>
				<div class="pul-progress" aria-hidden="true">
					<div class="pul-progress-fill" data-pul-progress style="width: 0%"></div>
				</div>
				<div class="pul-scale">
					<span>resting</span>
					<span>stillness</span>
				</div>
			</div>
		</div>

		<?php // Bottom bar — phrase / play / target ?>
		<div class="live-bar">
			<button type="button" class="live-ctl phrase" data-pul-sheet="phrase" aria-label="Choose dhikr phrase">
				<span class="live-ctl-ar ar" data-pul-phrase-short dir="rtl" lang="ar"><?php echo esc_html( $la_pulse_phrases[0]['ar'] ); ?></span>
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
			<button type="button" class="live-play" data-pul-play aria-label="Play or pause">
				<svg data-pul-play-icon width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="margin-left:3px"><path d="M9 6l8 6-8 6V6z"/></svg>
				<svg data-pul-pause-icon width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
			</button>
			<button type="button" class="live-ctl target" data-pul-sheet="target" aria-label="Choose target count">
				<span class="live-ctl-n" data-pul-target-display>33</span>
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:.6"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
		</div>

		<?php // Wave 104: reset moved into the right-rail above. ?>

		<?php // Bottom sheets ?>
		<div class="sheet-backdrop" data-pul-sheet-close hidden></div>
		<div class="sheet" data-pul-sheet-panel="phrase" hidden role="dialog" aria-modal="true">
			<div class="sheet-grab" aria-hidden="true"></div>
			<div class="sheet-title">Choose your dhikr</div>
			<div class="sheet-list">
				<?php foreach ( $la_pulse_phrases as $i => $p ) : ?>
					<button type="button" class="phrase-opt <?php echo $i === 0 ? 'sel' : ''; ?>"
						data-pul-pick-phrase="<?php echo (int) $i; ?>">
						<span class="phrase-opt-ar ar" dir="rtl" lang="ar"><?php echo esc_html( $p['ar'] ); ?></span>
						<span class="phrase-opt-meta">
							<b><?php echo esc_html( $p['tr'] ); ?></b>
							<span><?php echo (int) $p['from']; ?> → <?php echo (int) $p['to']; ?> BPM</span>
						</span>
						<span class="phrase-opt-check" aria-hidden="true">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="sheet" data-pul-sheet-panel="target" hidden role="dialog" aria-modal="true">
			<div class="sheet-grab" aria-hidden="true"></div>
			<div class="sheet-title">How many beats?</div>
			<div class="sheet-targets">
				<?php foreach ( $la_pulse_targets as $i => $t ) : ?>
					<button type="button" class="target-opt <?php echo $i === 0 ? 'sel' : ''; ?>"
						data-pul-pick-target="<?php echo (int) $i; ?>">
						<span class="target-opt-n"><?php echo $t['n'] === 0 ? '∞' : (int) $t['n']; ?></span>
						<span class="target-opt-tag"><?php echo esc_html( $t['tag'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<script id="la-pul-config" type="application/json">
		<?php echo wp_json_encode( [
			'phrases' => $la_pulse_phrases,
			'targets' => $la_pulse_targets,
			'scenes'  => $la_dhikr_scenes,
		] ); ?>
	</script>

	<script>
	/* Wave 97: PulseLive state machine.
	   Beat fires at the current BPM (setTimeout chained, so we can
	   re-schedule with the new tempo after BPM eases down). A slow
	   interval steps the BPM down by 1 every 4.2s until it reaches
	   the phrase's `to` value — that's the "settling toward stillness".
	   On each beat: scale the core, spawn a ripple, increment count. */
	(function() {
		const root = document.querySelector('[data-pul]');
		if ( ! root ) return;
		const cfg = JSON.parse( document.getElementById('la-pul-config').textContent );

		let pi = parseInt( localStorage.getItem('la_pul_pi') || '0', 10 );
		let ti = parseInt( localStorage.getItem('la_pul_ti') || '0', 10 );
		let scene = parseInt( localStorage.getItem('la_pul_scene') || '0', 10 );
		if ( pi    < 0 || pi    >= cfg.phrases.length ) pi    = 0;
		if ( ti    < 0 || ti    >= cfg.targets.length ) ti    = 0;
		if ( scene < 0 || scene >= ( cfg.scenes || [] ).length ) scene = 0;
		let count = 0, playing = false;
		let bpm   = cfg.phrases[pi].from;
		let beatT = null, slowT = null;
		/* Wave 103/107: ambient video state. Default unmuted; auto-
		   unmutes on chip click. Video always plays — no pause
		   control (Wave 107 simplification — was confusing the user
		   alongside the heartbeat play/pause). */
		let isMuted = ( localStorage.getItem('la_pul_muted') === '1' );
		let currentVideoId = '';
		let ytPlayer = null;

		const ph = () => cfg.phrases[pi];
		const tg = () => cfg.targets[ti];
		const sc = () => ( cfg.scenes || [] )[scene] || null;

		// Wave 103: chip + YT layer + mute refs (chip nav is OUTSIDE
		// .pul-live, so query at document/chipsNav level — same scope
		// bug we hit in Wave 102b).
		const chipsNav   = document.querySelector('[data-pul-chips]');
		const sceneChips = chipsNav ? chipsNav.querySelectorAll('[data-pul-scene-chip]') : [];
		const bgEl       = root.querySelector('[data-pul-bg]');
		const yt          = root.querySelector('[data-pul-yt]');
		const muteBtn     = root.querySelector('[data-pul-mute]');
		const muteOn      = root.querySelector('[data-pul-mute-on]');
		const muteOff     = root.querySelector('[data-pul-mute-off]');
		/* Wave 107: vid play/pause refs removed — no longer in the DOM. */

		/* Wave 103b: same YT.Player API pattern as Solitude. */
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
		function applyVideo() {
			if ( ! yt ) return;
			const s = sc();
			const v = s?.video || '';
			if ( v === currentVideoId ) return;
			currentVideoId = v;
			if ( ! v ) {
				yt.classList.remove('is-active');
				if ( ytPlayer && ytPlayer.stopVideo ) { try { ytPlayer.stopVideo(); } catch (_) {} }
				return;
			}
			yt.classList.add('is-active');
			if ( ytPlayer && ytPlayer.loadVideoById ) {
				/* Wave 103f/107: skip intro + always play. */
				try { ytPlayer.loadVideoById({ videoId: v, startSeconds: 30 }); } catch (_) {}
				setTimeout( () => {
					try {
						if ( isMuted ) ytPlayer.mute(); else ytPlayer.unMute();
						ytPlayer.playVideo();
					} catch (_) {}
				}, 80 );
				return;
			}
			// First mount: build placeholder + blocker, create YT.Player.
			yt.innerHTML = '';
			const blocker = document.createElement('div');
			blocker.className = 'sol-yt-blocker';
			blocker.setAttribute('aria-hidden', 'true');
			const mount = document.createElement('div');
			yt.appendChild(mount);
			yt.appendChild(blocker);
			loadYT().then( YT => {
				ytPlayer = new YT.Player(mount, {
					videoId: v,
					host: 'https://www.youtube-nocookie.com',
					playerVars: {
						autoplay: 1, mute: 1, controls: 0, playsinline: 1,
						rel: 0, modestbranding: 1, loop: 1, playlist: v,
						iv_load_policy: 3, fs: 0, disablekb: 1,
						start: 30,  // Wave 103f: skip channel intros
					},
					events: {
						onReady: (e) => {
							try {
								if ( isMuted ) e.target.mute(); else { e.target.unMute(); e.target.setVolume(100); }
								e.target.playVideo();
							} catch (_) {}
						},
					},
				});
			});
		}
		function setMute(on) {
			isMuted = !! on;
			localStorage.setItem('la_pul_muted', isMuted ? '1' : '0');
			if ( muteBtn ) {
				muteBtn.classList.toggle('is-muted', isMuted);
				muteBtn.setAttribute('aria-pressed', String( ! isMuted ));
			}
			if ( muteOn )  muteOn.hidden  =   isMuted;
			if ( muteOff ) muteOff.hidden = ! isMuted;
			if ( ytPlayer ) {
				try {
					if ( isMuted ) ytPlayer.mute();
					else { ytPlayer.unMute(); ytPlayer.setVolume(100); }
				} catch (_) {}
			}
		}
		/* Wave 107: setVidPlaying removed — video is always playing. */
		function applyBgGradient() {
			const s = sc();
			if ( bgEl && s?.bg ) bgEl.style.background = s.bg;
		}

		const arEl       = root.querySelector('[data-pul-ar]');
		const trEl       = root.querySelector('[data-pul-tr]');
		const core       = root.querySelector('[data-pul-core]');
		const wrap       = root.querySelector('[data-pul-wrap]');
		const countEl    = root.querySelector('[data-pul-count]');
		const targetEl   = root.querySelector('[data-pul-target]');
		const bpmEl      = root.querySelector('[data-pul-bpm]');
		const progress   = root.querySelector('[data-pul-progress]');
		const phraseChip = root.querySelector('[data-pul-phrase-short]');
		const targetChip = root.querySelector('[data-pul-target-display]');
		const playBtn    = root.querySelector('[data-pul-play]');
		const playIcon   = root.querySelector('[data-pul-play-icon]');
		const pauseIcon  = root.querySelector('[data-pul-pause-icon]');
		/* Wave 107: rail dhikr mirror removed — gold bottom button is
		   the ONLY heartbeat play/pause control. */
		const resetBtn   = root.querySelector('[data-pul-reset]');
		const sheetScrim = root.querySelector('[data-pul-sheet-close]');
		const sheetBtns  = root.querySelectorAll('[data-pul-sheet]');
		const sheetPanels= root.querySelectorAll('[data-pul-sheet-panel]');
		const phraseOpts = root.querySelectorAll('[data-pul-pick-phrase]');
		const targetOpts = root.querySelectorAll('[data-pul-pick-target]');

		function render() {
			arEl.textContent = ph().ar;
			trEl.textContent = ph().tr;
			phraseChip.textContent = ph().ar;
			countEl.textContent = count;
			const tn = tg().n;
			targetEl.textContent = tn === 0 ? '∞' : '/ ' + tn;
			targetChip.textContent = tn === 0 ? '∞' : tn;
			bpmEl.textContent = bpm;
			const range = ph().from - ph().to;
			const prog = range > 0 ? Math.max( 0, Math.min( 1, ( ph().from - bpm ) / range ) ) : 0;
			progress.style.width = ( prog * 100 ) + '%';
			playIcon.hidden  =   playing;
			pauseIcon.hidden = ! playing;
			resetBtn.hidden  = ! ( count > 0 );
		}

		/* Wave 101d: gentler ripple — slower expansion, longer
		   lifetime, starts at a soft 0.45 opacity rather than full
		   1.0 so the "ring" never feels like a startle. */
		function ripple() {
			if ( ! wrap ) return;
			const r = document.createElement('div');
			r.className = 'pulse-ripple';
			r.style.opacity = '0.45';
			wrap.appendChild(r);
			requestAnimationFrame(() => {
				r.style.width   = '220px';
				r.style.height  = '220px';
				r.style.opacity = '0';
			});
			setTimeout(() => r.remove(), 3600);   // matches 3.5s transition + a tick
		}

		function beat() {
			/* Wave 101e: retrigger the lub-dub keyframe animation on
			   each beat. Remove → reflow → add forces the CSS
			   animation to restart at frame 0; otherwise re-adding
			   the same class to an element that already has it
			   wouldn't re-fire the animation. */
			if ( core ) {
				core.classList.remove('beat');
				void core.offsetWidth;
				core.classList.add('beat');
			}
			ripple();
			const tn = tg().n;
			if ( tn === 0 || count < tn ) count++;
			/* Wave 101d: shorter, softer haptic — 4ms instead of 6.
			   Tactile but not buzzy. */
			if ( navigator.vibrate ) navigator.vibrate(4);
			render();
			// Wave 101c: auto-stop when target reached so the visual
			// pulse, ripples, and vibrate all end together. The beat
			// that lands ON the target still fires so the completion
			// is felt. tn === 0 means infinite — keep going.
			if ( tn !== 0 && count >= tn ) {
				setPlaying(false);
				return;
			}
			// re-schedule at the current BPM (it may have eased down)
			beatT = setTimeout(beat, 60000 / bpm);
		}

		function startTimers() {
			beatT = setTimeout(beat, 60000 / bpm);
			slowT = setInterval(() => {
				if ( bpm > ph().to ) { bpm--; render(); }
				else { bpm = ph().to; }
			}, 4200);
		}
		function stopTimers() {
			if ( beatT ) { clearTimeout(beatT); beatT = null; }
			if ( slowT ) { clearInterval(slowT); slowT = null; }
		}

		function setPlaying(p) {
			playing = !! p;
			stopTimers();
			if ( playing ) startTimers();
			render();
		}

		function openSheet(name) {
			sheetScrim.hidden = false;
			sheetPanels.forEach( el => { el.hidden = ( el.dataset.pulSheetPanel !== name ); } );
		}
		function closeSheet() {
			sheetScrim.hidden = true;
			sheetPanels.forEach( el => { el.hidden = true; } );
		}

		playBtn.addEventListener('click', () => setPlaying( ! playing ));
		resetBtn.addEventListener('click', () => { count = 0; bpm = ph().from; render(); });
		sheetScrim.addEventListener('click', closeSheet);
		sheetBtns.forEach( b => b.addEventListener('click', () => openSheet( b.dataset.pulSheet )) );
		phraseOpts.forEach( b => b.addEventListener('click', () => {
			pi = parseInt( b.dataset.pulPickPhrase, 10 );
			count = 0; bpm = ph().from;
			localStorage.setItem('la_pul_pi', String(pi));
			phraseOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.pulPickPhrase, 10) === pi ) );
			closeSheet();
			if ( playing ) { stopTimers(); startTimers(); }
			render();
		}) );
		targetOpts.forEach( b => b.addEventListener('click', () => {
			ti = parseInt( b.dataset.pulPickTarget, 10 );
			localStorage.setItem('la_pul_ti', String(ti));
			targetOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.pulPickTarget, 10) === ti ) );
			closeSheet();
			render();
		}) );

		phraseOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.pulPickPhrase, 10) === pi ) );
		targetOpts.forEach( o => o.classList.toggle( 'sel', parseInt(o.dataset.pulPickTarget, 10) === ti ) );

		/* Wave 103/103c: scene chip clicks — swap bg + iframe.
		   Wave 103c: chip click also unmutes the player (user gesture
		   satisfies browser autoplay-with-sound policy). */
		sceneChips.forEach( c => c.addEventListener( 'click', () => {
			const i = parseInt( c.dataset.pulSceneChip, 10 );
			if ( i === scene ) {
				if ( ! isMuted ) setMute( false );
				return;
			}
			scene = i;
			localStorage.setItem( 'la_pul_scene', String(scene) );
			sceneChips.forEach( o => o.classList.toggle( 'is-active', parseInt(o.dataset.pulSceneChip, 10) === scene ) );
			applyBgGradient();
			applyVideo();
			if ( ! isMuted ) setMute( false );
			try { c.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); } catch (_) {}
		}) );
		// Wave 107: just the mute toggle now.
		muteBtn?.addEventListener( 'click', () => setMute( ! isMuted ) );

		// Initial chip sync to persisted scene, then mount video + bg
		sceneChips.forEach( c => c.classList.toggle( 'is-active', parseInt(c.dataset.pulSceneChip, 10) === scene ) );
		const activeChip = chipsNav?.querySelector('[data-pul-scene-chip].is-active');
		if ( activeChip ) {
			try { activeChip.scrollIntoView({ block: 'nearest', inline: 'center' }); } catch (_) {}
		}
		setMute( isMuted );
		applyBgGradient();
		applyVideo();

		render();
	})();
	</script>
</main>
