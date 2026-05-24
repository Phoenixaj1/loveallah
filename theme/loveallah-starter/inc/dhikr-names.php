<?php
/**
 * Dhikr Names — 99 Names of Allah contemplation.
 *
 * Pick 1, 3, or 7 Names. Each Name appears full-screen with calligraphy,
 * transliteration, meaning, and a ~40-word reflection drawn from
 * al-Ghazali and Ibn Qayyim. The "Continue" button is greyed out for
 * the first 30 seconds — forcing presence with each Name.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once LA_DIR . 'inc/data/names.php';
$la_names = la_asma_ul_husna();
?>
<main class="la-app la-app--names">

	<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-names-back" aria-label="Back to dhikr modes">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
	</a>

	<!-- ─── SETUP screen ─── -->
	<section class="la-names-setup" data-names-scene="setup">
		<header class="la-names-setup-head">
			<div class="la-names-eyebrow">Asma ul-Husna</div>
			<h1 class="la-names-title">Know Him by name</h1>
			<p class="la-names-sub">Sit with His attributes — one minute each, long enough to change how you see the day.</p>
		</header>

		<div class="la-names-count-row" data-names-count-pills>
			<button type="button" class="la-names-count-pill" data-names-count="1">
				<span class="la-names-count-pill-num">1</span>
				<span class="la-names-count-pill-label">Just one</span>
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

		<div class="la-names-mode-row">
			<button type="button" class="la-names-mode-pill is-active" data-names-mode="random">Random selection</button>
			<button type="button" class="la-names-mode-pill" data-names-mode="sequence">In order (from where I left off)</button>
		</div>

		<div class="la-names-begin-bar">
			<button type="button" class="la-names-begin" data-names-begin>
				<span class="la-names-begin-label">Begin</span>
				<span class="la-names-begin-meta" data-names-begin-meta>3 random names · ≈ 3 min</span>
			</button>
		</div>
	</section>

	<!-- ─── SESSION screen: one Name at a time ─── -->
	<section class="la-names-session" data-names-scene="session" hidden>
		<div class="la-names-progress-dots" data-names-progress></div>

		<div class="la-names-card" data-names-card>
			<div class="la-names-arabic" dir="rtl" lang="ar" data-names-arabic>الرَّحْمٰن</div>
			<div class="la-names-translit" data-names-translit>Ar-Rahman</div>
			<div class="la-names-meaning" data-names-meaning>The Most Merciful</div>
			<div class="la-names-divider" aria-hidden="true"></div>
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
		<div class="la-names-complete-inner">
			<div class="la-names-complete-arabic" dir="rtl" lang="ar">وَلِلَّهِ الْأَسْمَاءُ الْحُسْنَىٰ فَادْعُوهُ بِهَا</div>
			<div class="la-names-complete-meaning">"And to Allah belong the most beautiful names, so invoke Him by them" — Quran 7:180</div>
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
