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

$la_pulse_phrases = [
	[ 'key' => 'subhanallah',
	  'ar' => 'سُبْحَانَ ٱللَّٰه',
	  'tr' => 'Subḥān Allāh',
	  'from' => 80, 'to' => 40 ],
	[ 'key' => 'alhamdulillah',
	  'ar' => 'ٱلْحَمْدُ لِلَّٰه',
	  'tr' => 'Alḥamdulillāh',
	  'from' => 80, 'to' => 40 ],
	[ 'key' => 'allahuakbar',
	  'ar' => 'ٱللَّٰهُ أَكْبَر',
	  'tr' => 'Allāhu Akbar',
	  'from' => 80, 'to' => 40 ],
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
?>
<main class="la-app la-app--pulse la-dhikr-live mode-pulse" data-dhikr-mode="pulse">

	<?php // Wave 95b mode-switcher pills (matches Solitude) ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Solitude</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>" aria-current="page">Pulse</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<div class="pul-live" data-pul>

		<?php // Top — Arabic phrase + transliteration ?>
		<div class="pul-phrase">
			<div class="pul-ar ar" data-pul-ar dir="rtl" lang="ar"><?php echo esc_html( $la_pulse_phrases[0]['ar'] ); ?></div>
			<div class="pul-tr" data-pul-tr><?php echo esc_html( $la_pulse_phrases[0]['tr'] ); ?></div>
		</div>

		<?php // Centre — pulsing core + count readout ?>
		<div class="pul-live-core">
			<div class="pulse-core-wrap" data-pul-wrap>
				<div class="pulse-core" data-pul-core></div>
			</div>
			<div class="sol-readout">
				<span class="sol-count" data-pul-count>0</span>
				<span class="sol-target" data-pul-target>/ 33</span>
			</div>
		</div>

		<?php // BPM readout + progress bar ?>
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

		<button type="button" class="live-reset" data-pul-reset aria-label="Reset count" hidden>
			<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
		</button>

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
		<?php echo wp_json_encode( [ 'phrases' => $la_pulse_phrases, 'targets' => $la_pulse_targets ] ); ?>
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
		if ( pi < 0 || pi >= cfg.phrases.length ) pi = 0;
		if ( ti < 0 || ti >= cfg.targets.length ) ti = 0;
		let count = 0, playing = false;
		let bpm   = cfg.phrases[pi].from;
		let beatT = null, slowT = null;

		const ph = () => cfg.phrases[pi];
		const tg = () => cfg.targets[ti];

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

		function ripple() {
			if ( ! wrap ) return;
			const r = document.createElement('div');
			r.className = 'pulse-ripple';
			wrap.appendChild(r);
			requestAnimationFrame(() => {
				r.style.width   = '236px';
				r.style.height  = '236px';
				r.style.opacity = '0';
			});
			setTimeout(() => r.remove(), 2000);
		}

		function beat() {
			// pulse the core
			if ( core ) {
				core.style.transform = 'scale(1.34)';
				setTimeout(() => { if ( core ) core.style.transform = 'scale(1)'; }, 130);
			}
			ripple();
			const tn = tg().n;
			if ( tn === 0 || count < tn ) count++;
			if ( navigator.vibrate ) navigator.vibrate(6);
			render();
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

		render();
	})();
	</script>
</main>
