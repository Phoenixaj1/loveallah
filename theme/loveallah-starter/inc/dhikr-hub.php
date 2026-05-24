<?php
/**
 * Dhikr hub — 4-card chooser between modes (Wave 58: sacred-night redesign).
 *
 * Visual direction: deep midnight gradient bg with a subtle starfield,
 * gold/cream accents instead of utility pastels, Arabic ornament
 * (ذِكْر) above the title — meant to feel like entering a sacred
 * space, not opening a tool.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<main class="la-app la-app--dhikr-hub">

	<!-- Sacred backdrop: midnight gradient + starfield -->
	<div class="la-dhikr-hub-bg" aria-hidden="true">
		<div class="la-dhikr-hub-stars"></div>
		<div class="la-dhikr-hub-glow"></div>
	</div>

	<header class="la-dhikr-hub-head">
		<div class="la-dhikr-hub-ornament" aria-hidden="true">
			<svg class="la-dhikr-hub-ornament-line" viewBox="0 0 120 8" preserveAspectRatio="none">
				<path d="M0 4 L48 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
				<circle cx="54" cy="4" r="1.2" fill="currentColor"/>
				<path d="M60 4 L72 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
				<circle cx="78" cy="4" r="1.2" fill="currentColor"/>
				<path d="M72 4 L120 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
			</svg>
			<span class="la-dhikr-hub-arabic" lang="ar" dir="rtl">ذِكْر</span>
			<svg class="la-dhikr-hub-ornament-line" viewBox="0 0 120 8" preserveAspectRatio="none">
				<path d="M0 4 L48 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
				<circle cx="54" cy="4" r="1.2" fill="currentColor"/>
				<path d="M60 4 L72 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
				<circle cx="78" cy="4" r="1.2" fill="currentColor"/>
				<path d="M72 4 L120 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
			</svg>
		</div>
		<div class="la-dhikr-hub-eyebrow">Remembrance</div>
		<h1 class="la-dhikr-hub-title">Find Him in stillness</h1>
		<p class="la-dhikr-hub-sub">Four doors to nearness. Choose what your heart needs right now.</p>
	</header>

	<div class="la-dhikr-hub-grid">

		<!-- Solitude — current orb experience -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--solitude" href="<?php echo esc_url( home_url( '/dhikr/?mode=solitude' ) ); ?>">
			<div class="la-dhikr-hub-card-frame" aria-hidden="true"></div>
			<button type="button" class="la-dhikr-hub-card-info" data-info aria-label="More about this path">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
			</button>
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
					<circle cx="24" cy="24" r="14"/>
					<circle cx="24" cy="24" r="8" stroke-dasharray="2 3"/>
					<circle cx="24" cy="24" r="2.4" fill="currentColor" stroke="none"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Solitude</div>
			<div class="la-dhikr-hub-card-tag">Your breath, your pace</div>
			<div class="la-dhikr-hub-card-desc">A quiet orb that breathes with you. Single phrase, sunnah count, total silence — the classical practice.</div>
		</a>

		<!-- Witness — feed of dhikr content + tap counter -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--witness" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">
			<div class="la-dhikr-hub-card-frame" aria-hidden="true"></div>
			<button type="button" class="la-dhikr-hub-card-info" data-info aria-label="More about this path">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
			</button>
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
					<rect x="8" y="10" width="32" height="28" rx="4"/>
					<path d="M20 18l10 6-10 6z" fill="currentColor" stroke="none"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Witness</div>
			<div class="la-dhikr-hub-card-tag">Scholars leading — join in</div>
			<div class="la-dhikr-hub-card-desc">A feed of qaris and shuyukh doing dhikr aloud. Tap the counter as you remember with them.</div>
		</a>

		<!-- Pulse — BPM-driven flow state -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--pulse" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">
			<div class="la-dhikr-hub-card-frame" aria-hidden="true"></div>
			<button type="button" class="la-dhikr-hub-card-info" data-info aria-label="More about this path">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
			</button>
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round">
					<circle cx="24" cy="24" r="5" fill="currentColor" stroke="none"/>
					<circle cx="24" cy="24" r="11" opacity="0.55"/>
					<circle cx="24" cy="24" r="17" opacity="0.25"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Pulse</div>
			<div class="la-dhikr-hub-card-tag">80 → 40 BPM · entrain to stillness</div>
			<div class="la-dhikr-hub-card-desc">A gentle rhythm that starts at your resting heart rate and slows you down. Body finds the cadence; mind follows.</div>
		</a>

		<!-- Names — 99 Names contemplation -->
		<a class="la-dhikr-hub-card la-dhikr-hub-card--names" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>">
			<div class="la-dhikr-hub-card-frame" aria-hidden="true"></div>
			<button type="button" class="la-dhikr-hub-card-info" data-info aria-label="More about this path">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
			</button>
			<div class="la-dhikr-hub-card-icon" aria-hidden="true">
				<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 32c0-10 6-16 12-16s12 6 12 16"/>
					<path d="M16 32c0-7 3.5-11 8-11s8 4 8 11"/>
					<circle cx="24" cy="36" r="2" fill="currentColor" stroke="none"/>
				</svg>
			</div>
			<div class="la-dhikr-hub-card-name">Names</div>
			<div class="la-dhikr-hub-card-tag">One name · one minute · closer through knowing</div>
			<div class="la-dhikr-hub-card-desc">Sit with His names. Pick 1, 3 or 7 — each one held in mind long enough that it changes how you see the day.</div>
		</a>

	</div>

	<p class="la-dhikr-hub-footnote">
		Any path counts toward today's remembrance — choose what your heart needs.
	</p>

	<!-- Info sheet: opens when a card's [data-info] is tapped. One global
	     instance — JS fills in title/icon/description from the source card. -->
	<div class="la-dhikr-hub-sheet" data-info-sheet hidden role="dialog" aria-modal="true" aria-labelledby="la-info-sheet-title">
		<button type="button" class="la-dhikr-hub-sheet-backdrop" data-info-close aria-label="Close"></button>
		<div class="la-dhikr-hub-sheet-panel">
			<button type="button" class="la-dhikr-hub-sheet-x" data-info-close aria-label="Close">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
			<div class="la-dhikr-hub-sheet-icon" data-info-icon aria-hidden="true"></div>
			<h2 class="la-dhikr-hub-sheet-name" id="la-info-sheet-title" data-info-name></h2>
			<div class="la-dhikr-hub-sheet-tag" data-info-tag></div>
			<p class="la-dhikr-hub-sheet-desc" data-info-desc></p>
			<a class="la-dhikr-hub-sheet-cta" data-info-cta href="#">
				<span>Begin</span>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
			</a>
		</div>
	</div>
</main>
