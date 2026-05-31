<?php
/**
 * Dhikr Names — NamesLive endless 99 Names stream (Wave 98).
 *
 * Ported from Claude Design's NamesLive component. Replaces the
 * previous "count + ordering" setup with a meditative, endless
 * random stream: one Name at a time, full-screen.
 *
 *   • Top: ornamental header "Asma ul-Husna" + dwell count
 *   • Centre card: huge Arabic + transliteration + English +
 *     a reflective note. A ring around the card fills as the
 *     user dwells ("Sit with this" timer, SIT_SECONDS = 30s).
 *   • Bottom hint: "Take your time" until 30s have passed,
 *     then "When you are ready, swipe up" (gold accent).
 *   • Swipe up → next Name (random shuffled sequence, no
 *     immediate repeat across batches, never ends).
 *   • Swipe down → previous Name (if not at position 0).
 *
 * The point is reflection, not flying through. The dwell timer
 * gates the "swipe up" hint so users actually sit a moment with
 * each attribute of Allah before moving on.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once LA_DIR . 'inc/data/names.php';
$la_names = la_asma_ul_husna();

// Reshape to the field names NamesLive's JS expects.
$la_names_data = array_values( array_map( function( $n ) {
	return [
		'ar'   => $n['ar']         ?? '',
		'tr'   => $n['n']          ?? '',
		'en'   => $n['meaning']    ?? '',
		'note' => $n['reflection'] ?? '',
	];
}, $la_names ) );
?>
<main class="la-app la-app--names la-dhikr-live mode-names" data-dhikr-mode="names">

	<?php // Wave 95b mode-switcher pills ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Breathe</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">Focus</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pray' ) ); ?>">Pray</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>" aria-current="page">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<div class="nam-live" data-nam>

		<?php // Top header ?>
		<div class="nam-top">
			<span class="nam-top-ar ar" dir="rtl" lang="ar">أَسْمَاءُ ٱللَّٰهِ ٱلْحُسْنَىٰ</span>
			<span class="nam-top-sub" data-nam-top-sub>1 name sat with</span>
		</div>

		<?php // Live card — the JS swaps innerHTML on swipe ?>
		<div class="nam-live-card" data-nam-card>
			<div class="nam-ring">
				<svg class="nam-svg" viewBox="0 0 324 324" aria-hidden="true">
					<circle cx="162" cy="162" r="150" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="2"></circle>
					<circle data-nam-ring-prog cx="162" cy="162" r="150" fill="none"
						stroke="var(--gold)" stroke-width="2.5" stroke-linecap="round"
						stroke-dasharray="942.48" stroke-dashoffset="942.48"
						transform="rotate(-90 162 162)"
						style="transition: stroke-dashoffset 1s linear; filter: drop-shadow(0 0 6px var(--gold));"></circle>
				</svg>
				<div class="nam-core">
					<div class="nam-ar ar" data-nam-ar dir="rtl" lang="ar"></div>
					<div class="nam-tr" data-nam-tr></div>
					<div class="nam-en" data-nam-en></div>
				</div>
			</div>

			<div class="nam-sit">
				<span class="nam-sit-label">Sit with this</span>
				<span class="nam-sit-dot">·</span>
				<span class="nam-sit-time" data-nam-time>0:00</span>
			</div>

			<div class="nam-div" aria-hidden="true"><span></span><span class="dot"></span><span></span></div>
			<p class="nam-note" data-nam-note></p>
		</div>

		<div class="nam-swipe" data-nam-swipe>
			<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
			<span data-nam-swipe-text>Take your time</span>
		</div>
	</div>

	<script id="la-nam-config" type="application/json">
		<?php echo wp_json_encode( $la_names_data ); ?>
	</script>

	<script>
	/* Wave 98: NamesLive state machine.
	   Endless random sequence — when we're 2 away from the end of the
	   current shuffled batch, append a new shuffled batch (re-using
	   Fisher-Yates), avoiding back-to-back duplicate across the seam.
	   Dwell timer ticks per second; ring fills as a fraction of
	   SIT_SECONDS. Swipe up to advance once "ready". */
	(function() {
		const root = document.querySelector('[data-nam]');
		if ( ! root ) return;
		const NAMES = JSON.parse( document.getElementById('la-nam-config').textContent );
		const SIT_SECONDS = 30;
		const RING_C      = 2 * Math.PI * 150;

		function shuffled() {
			const a = NAMES.map( (_, i) => i );
			for ( let i = a.length - 1; i > 0; i-- ) {
				const j = Math.floor( Math.random() * ( i + 1 ) );
				[ a[i], a[j] ] = [ a[j], a[i] ];
			}
			return a;
		}
		let seq = shuffled();
		let pos = 0;
		let sit = 0;
		let sitT = null;

		const card        = root.querySelector('[data-nam-card]');
		const arEl        = root.querySelector('[data-nam-ar]');
		const trEl        = root.querySelector('[data-nam-tr]');
		const enEl        = root.querySelector('[data-nam-en]');
		const noteEl      = root.querySelector('[data-nam-note]');
		const timeEl      = root.querySelector('[data-nam-time]');
		const topSub      = root.querySelector('[data-nam-top-sub]');
		const ringProg    = root.querySelector('[data-nam-ring-prog]');
		const swipeBlock  = root.querySelector('[data-nam-swipe]');
		const swipeText   = root.querySelector('[data-nam-swipe-text]');

		function ensureSeq() {
			while ( pos + 2 >= seq.length ) {
				const next = shuffled();
				if ( next[0] === seq[ seq.length - 1 ] ) {
					[ next[0], next[1] ] = [ next[1], next[0] ];
				}
				seq = seq.concat(next);
			}
		}

		function render() {
			ensureSeq();
			const n = NAMES[ seq[pos] ];
			arEl.textContent   = n.ar;
			trEl.textContent   = n.tr;
			enEl.textContent   = n.en;
			noteEl.textContent = n.note;
			const mm = Math.floor( sit / 60 );
			const ss = String( sit % 60 ).padStart(2, '0');
			timeEl.textContent = mm + ':' + ss;
			const prog = Math.min( 1, sit / SIT_SECONDS );
			ringProg.setAttribute('stroke-dashoffset', RING_C * ( 1 - prog ));
			const ready = sit >= SIT_SECONDS;
			swipeBlock.classList.toggle('ready', ready);
			swipeText.textContent = ready ? 'When you are ready, swipe up' : 'Take your time';
			const total = pos + 1;
			topSub.textContent = total + ' ' + ( total === 1 ? 'name' : 'names' ) + ' sat with';
		}

		function startSit() {
			sit = 0;
			if ( sitT ) clearInterval(sitT);
			sitT = setInterval( () => { sit++; render(); }, 1000 );
		}

		function advance(dir) {
			if ( dir < 0 && pos === 0 ) return;
			pos += dir;
			if ( pos < 0 ) pos = 0;
			startSit();
			render();
			// Subtle entrance — re-trigger CSS animation
			card.style.animation = 'none';
			void card.offsetWidth;
			card.style.animation = '';
		}

		/* Wave 104d: vertical-swipe gesture with pointer capture so
		   the browser doesn't try to steal mid-drag. touch-action:
		   none on .nam-live (CSS) ensures we own the gesture from
		   the start. */
		const drag = { y: 0, active: false, moved: false, dy: 0 };
		root.addEventListener('pointerdown', (e) => {
			drag.y = e.clientY; drag.active = true; drag.moved = false; drag.dy = 0;
			card.style.transition = 'none';
			try { root.setPointerCapture( e.pointerId ); } catch (_) {}
		});
		root.addEventListener('pointermove', (e) => {
			if ( ! drag.active ) return;
			const dy = e.clientY - drag.y;
			if ( Math.abs(dy) > 6 ) drag.moved = true;
			drag.dy = dy;
			card.style.transform = 'translateY(' + ( dy * 0.5 ) + 'px)';
			card.style.opacity   = String( 1 - Math.min( 0.5, Math.abs(dy) / 320 ) );
		});
		const onUp = (e) => {
			if ( ! drag.active ) return;
			const dy = drag.dy;
			drag.active = false;
			try { root.releasePointerCapture( e.pointerId ); } catch (_) {}
			card.style.transition = 'transform .42s cubic-bezier(.22,.61,.36,1), opacity .42s';
			card.style.transform  = '';
			card.style.opacity    = '';
			if ( dy < -48 )                advance( 1 );
			else if ( dy > 48 && pos > 0 ) advance( -1 );
		};
		root.addEventListener('pointerup',     onUp);
		root.addEventListener('pointercancel', onUp);

		startSit();
		render();
	})();
	</script>
</main>
