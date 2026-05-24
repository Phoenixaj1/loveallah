<?php
/**
 * Dhikr Pulse — heart-rate-entrainment metronome.
 *
 * Starts at the user's resting BPM (80 default), exponentially decays
 * toward 40 BPM over ~5 min following bpm(t) = 40 + 40 * e^(-t/90).
 * The body entrains to the visual pulse; the mind follows. Tempo
 * entrainment via the inferior olive nucleus + slow-breathing vagal
 * tone = parasympathetic state → flow.
 *
 * Phrase-aware starting BPM (longer phrases need lower BPM):
 *   subhanallah / alhamdulillah / allahu akbar (4-5 syll) → 80 → 40
 *   la ilaha illa Allah (7 syll)                          → 60 → 30
 *   salawat (10+ syll)                                    → 40 → 20
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Phrases the user can pick. start/end BPM tuned for syllable count.
$la_pulse_phrases = [
	[
		'key'      => 'subhanallah',
		'arabic'   => 'سُبْحَانَ ٱللَّٰه',
		'translit' => 'Subḥān Allāh',
		'meaning'  => 'Glory be to Allah',
		'startBpm' => 80, 'endBpm' => 40,
	],
	[
		'key'      => 'alhamdulillah',
		'arabic'   => 'ٱلْحَمْدُ لِلَّٰه',
		'translit' => 'Alḥamdulillāh',
		'meaning'  => 'Praise be to Allah',
		'startBpm' => 80, 'endBpm' => 40,
	],
	[
		'key'      => 'allahuakbar',
		'arabic'   => 'ٱللَّٰهُ أَكْبَر',
		'translit' => 'Allāhu Akbar',
		'meaning'  => 'Allah is the Greatest',
		'startBpm' => 80, 'endBpm' => 40,
	],
	[
		'key'      => 'kalimah',
		'arabic'   => 'لَا إِلٰهَ إِلَّا ٱللَّٰه',
		'translit' => 'Lā ilāha illa-llāh',
		'meaning'  => 'There is no god but Allah',
		'startBpm' => 60, 'endBpm' => 30,
	],
	[
		'key'      => 'salawat',
		'arabic'   => 'ٱللَّٰهُمَّ صَلِّ عَلَىٰ مُحَمَّد',
		'translit' => 'Allāhumma ṣalli ʿalā Muḥammad',
		'meaning'  => 'O Allah, send blessings upon Muhammad ﷺ',
		'startBpm' => 40, 'endBpm' => 20,
	],
];
?>
<main class="la-app la-app--pulse">

	<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-pulse-back" aria-label="Back to dhikr modes">
		<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
	</a>

	<!-- ─── SETUP screen: pick phrase + count target ─── -->
	<section class="la-pulse-setup" data-pulse-scene="setup">
		<header class="la-pulse-setup-head">
			<div class="la-pulse-eyebrow">Pulse</div>
			<h1 class="la-pulse-title">Let the beat take you</h1>
			<p class="la-pulse-sub">Starts at your resting rate · slowly settles to a meditative 40 BPM</p>
		</header>

		<div class="la-pulse-pickrow">
			<div class="la-pulse-pickrow-label">Phrase</div>
			<div class="la-pulse-phrase-pills" data-pulse-phrase-pills>
				<?php foreach ( $la_pulse_phrases as $i => $p ) : ?>
					<button type="button"
						class="la-pulse-phrase-pill <?php echo $i === 0 ? 'is-active' : ''; ?>"
						data-pulse-phrase="<?php echo esc_attr( $p['key'] ); ?>"
						data-start-bpm="<?php echo (int) $p['startBpm']; ?>"
						data-end-bpm="<?php echo (int) $p['endBpm']; ?>">
						<span class="la-pulse-phrase-pill-translit"><?php echo esc_html( $p['translit'] ); ?></span>
						<span class="la-pulse-phrase-pill-bpm"><?php echo (int) $p['startBpm']; ?> → <?php echo (int) $p['endBpm']; ?> BPM</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="la-pulse-pickrow">
			<div class="la-pulse-pickrow-label">Count</div>
			<div class="la-pulse-count-pills" data-pulse-count-pills>
				<button type="button" class="la-pulse-count-pill is-active" data-pulse-count="33">33</button>
				<button type="button" class="la-pulse-count-pill" data-pulse-count="100">100</button>
				<button type="button" class="la-pulse-count-pill" data-pulse-count="300">300</button>
			</div>
		</div>

		<div class="la-pulse-begin-bar">
			<button type="button" class="la-pulse-begin" data-pulse-begin>
				<span class="la-pulse-begin-label">Begin</span>
				<span class="la-pulse-begin-meta" data-pulse-begin-meta>33 × Subḥān Allāh</span>
			</button>
		</div>
	</section>

	<!-- ─── SESSION screen: pulse + counter ─── -->
	<section class="la-pulse-session" data-pulse-scene="session" hidden>
		<!-- Background pulse rings (3 expanding circles, staggered) -->
		<div class="la-pulse-stage">
			<div class="la-pulse-ring la-pulse-ring--3" data-pulse-ring="3"></div>
			<div class="la-pulse-ring la-pulse-ring--2" data-pulse-ring="2"></div>
			<div class="la-pulse-ring la-pulse-ring--1" data-pulse-ring="1"></div>
			<div class="la-pulse-core" data-pulse-core>
				<div class="la-pulse-arabic" dir="rtl" lang="ar" data-pulse-arabic>سُبْحَانَ ٱللَّٰه</div>
				<div class="la-pulse-translit" data-pulse-translit>Subḥān Allāh</div>
			</div>
		</div>

		<!-- Bottom HUD: count + BPM display + controls -->
		<div class="la-pulse-hud">
			<div class="la-pulse-counter">
				<span class="la-pulse-counter-num" data-pulse-count-display>0</span>
				<span class="la-pulse-counter-sep">/</span>
				<span class="la-pulse-counter-target" data-pulse-target-display>33</span>
			</div>
			<div class="la-pulse-bpm">
				<span class="la-pulse-bpm-num" data-pulse-bpm-display>80</span>
				<span class="la-pulse-bpm-unit">BPM</span>
			</div>
			<div class="la-pulse-controls">
				<button type="button" class="la-pulse-ctrl" data-pulse-hold aria-label="Hold this pace" title="Hold this pace">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
				</button>
				<button type="button" class="la-pulse-ctrl" data-pulse-deepen aria-label="Go deeper" title="Go deeper">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
				</button>
				<button type="button" class="la-pulse-ctrl la-pulse-ctrl--end" data-pulse-end aria-label="End session" title="End session">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>
			</div>
		</div>
	</section>

	<!-- ─── COMPLETE screen ─── -->
	<section class="la-pulse-complete" data-pulse-scene="complete" hidden>
		<div class="la-pulse-complete-inner">
			<div class="la-pulse-complete-arabic" dir="rtl" lang="ar">سُبْحَانَ ٱللَّٰه</div>
			<div class="la-pulse-complete-line">The heart has remembered.</div>
			<div class="la-pulse-complete-actions">
				<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-pulse-secondary">Return</a>
				<button type="button" class="la-pulse-primary" data-pulse-again>Again</button>
			</div>
		</div>
	</section>

	<script id="la-pulse-config" type="application/json">
		<?php echo wp_json_encode( $la_pulse_phrases ); ?>
	</script>
</main>
