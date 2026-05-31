<?php
/**
 * Dhikr Names — Asma ul-Husna contemplation (Wave 62: sacred-night).
 *
 * The 99 Names are the most exalted vocabulary humanity has been given
 * for the Divine. This isn't a tool to pick from — it's an invitation
 * to sit with one of His attributes long enough that the day starts
 * looking different through it.
 *
 * UX: Setup (count + order) → one Name at a time, full-screen with a
 * glowing presence, the user can't advance for 30s (forced stillness)
 * → reflection completion screen.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once LA_DIR . 'inc/data/names.php';
$la_names = la_asma_ul_husna();
?>
<main class="la-app la-app--names">

	<!-- Sacred backdrop: midnight gradient + drifting stars + soft glow -->
	<div class="la-names-bg" aria-hidden="true">
		<div class="la-names-stars"></div>
		<div class="la-names-aurora"></div>
	</div>

	<?php // Wave 95b: mode switcher — same as Pulse/Witness. Replaces
	// the standalone back arrow; tapping "Solitude" returns home. ?>
	<nav class="la-dhikr-modes" aria-label="Dhikr modes">
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>">Solitude</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>">Pulse</a>
		<a class="la-dhikr-mode is-active" href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>" aria-current="page">Names</a>
		<a class="la-dhikr-mode" href="<?php echo esc_url( home_url( '/dhikr/?mode=witness' ) ); ?>">Witness</a>
	</nav>

	<!-- ─── SETUP screen ─── -->
	<section class="la-names-setup" data-names-scene="setup">
		<header class="la-names-setup-head">
			<div class="la-names-ornament" aria-hidden="true">
				<svg class="la-names-ornament-line" viewBox="0 0 120 8" preserveAspectRatio="none">
					<path d="M0 4 L48 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
					<circle cx="54" cy="4" r="1.2" fill="currentColor"/>
					<path d="M60 4 L72 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
					<circle cx="78" cy="4" r="1.2" fill="currentColor"/>
					<path d="M72 4 L120 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
				</svg>
				<span class="la-names-ornament-arabic" lang="ar" dir="rtl">أسماء الله الحسنى</span>
				<svg class="la-names-ornament-line" viewBox="0 0 120 8" preserveAspectRatio="none">
					<path d="M0 4 L48 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
					<circle cx="54" cy="4" r="1.2" fill="currentColor"/>
					<path d="M60 4 L72 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
					<circle cx="78" cy="4" r="1.2" fill="currentColor"/>
					<path d="M72 4 L120 4" stroke="currentColor" stroke-width="0.8" fill="none" stroke-linecap="round"/>
				</svg>
			</div>
			<div class="la-names-eyebrow">The Most Beautiful Names</div>
			<h1 class="la-names-title">Sit with His Majesty</h1>
			<p class="la-names-sub">"And to Allah belong the most beautiful names — so invoke Him by them."<br><span class="la-names-sub-attr">Qur'an 7:180</span></p>
		</header>

		<div class="la-names-section-label">How many will you carry?</div>
		<div class="la-names-count-row" data-names-count-pills>
			<button type="button" class="la-names-count-pill" data-names-count="1">
				<span class="la-names-count-pill-num">1</span>
				<span class="la-names-count-pill-label">One Name</span>
				<span class="la-names-count-pill-time">≈ 1 min</span>
			</button>
			<button type="button" class="la-names-count-pill is-active" data-names-count="3">
				<span class="la-names-count-pill-num">3</span>
				<span class="la-names-count-pill-label">A trio</span>
				<span class="la-names-count-pill-time">≈ 3 min</span>
			</button>
			<button type="button" class="la-names-count-pill" data-names-count="7">
				<span class="la-names-count-pill-num">7</span>
				<span class="la-names-count-pill-label">A circle</span>
				<span class="la-names-count-pill-time">≈ 7 min</span>
			</button>
		</div>

		<div class="la-names-section-label">How shall they come to you?</div>
		<div class="la-names-mode-row">
			<button type="button" class="la-names-mode-pill is-active" data-names-mode="random">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M4 20l16.2-16.2"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
				Surprise me
			</button>
			<button type="button" class="la-names-mode-pill" data-names-mode="sequence">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3" cy="6" r="1" fill="currentColor"/><circle cx="3" cy="12" r="1" fill="currentColor"/><circle cx="3" cy="18" r="1" fill="currentColor"/></svg>
				In order
			</button>
		</div>

		<div class="la-names-begin-bar">
			<button type="button" class="la-names-begin" data-names-begin>
				<span class="la-names-begin-label">Begin</span>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
			</button>
			<div class="la-names-begin-meta" data-names-begin-meta>3 random names · ≈ 3 min</div>
		</div>
	</section>

	<!-- ─── SESSION screen: one Name at a time ─── -->
	<section class="la-names-session" data-names-scene="session" hidden>
		<div class="la-names-progress-dots" data-names-progress></div>

		<div class="la-names-card" data-names-card>
			<div class="la-names-halo" aria-hidden="true"></div>
			<div class="la-names-arabic" dir="rtl" lang="ar" data-names-arabic>الرَّحْمٰن</div>
			<div class="la-names-translit" data-names-translit>Ar-Rahman</div>
			<div class="la-names-meaning" data-names-meaning>The Most Merciful</div>
			<div class="la-names-divider" aria-hidden="true">
				<span></span><span class="la-names-divider-diamond" aria-hidden="true">◆</span><span></span>
			</div>
			<p class="la-names-reflection" data-names-reflection>—</p>
		</div>

		<div class="la-names-cta-bar">
			<button type="button" class="la-names-cta" data-names-continue disabled>
				<span class="la-names-cta-label" data-names-cta-label>Sit with this · 30s</span>
				<span class="la-names-cta-progress" data-names-cta-progress></span>
			</button>
		</div>
	</section>

	<!-- ─── COMPLETE screen ─── -->
	<section class="la-names-complete" data-names-scene="complete" hidden>
		<div class="la-names-complete-glow"></div>
		<div class="la-names-complete-inner">
			<div class="la-names-complete-ornament" aria-hidden="true">◆</div>
			<div class="la-names-complete-arabic" dir="rtl" lang="ar">وَلِلَّهِ الْأَسْمَاءُ الْحُسْنَىٰ فَادْعُوهُ بِهَا</div>
			<div class="la-names-complete-meaning">"And to Allah belong the most beautiful names, so invoke Him by them."</div>
			<div class="la-names-complete-attr">— Qur'an 7:180</div>
			<div class="la-names-complete-actions">
				<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-names-secondary">Return</a>
				<button type="button" class="la-names-primary" data-names-again>Another circle</button>
			</div>
		</div>
	</section>

	<script id="la-names-data" type="application/json">
		<?php echo wp_json_encode( $la_names ); ?>
	</script>
</main>
