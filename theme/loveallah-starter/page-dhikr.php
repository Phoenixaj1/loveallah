<?php
/**
 * Dhikr page — interactive tasbeeh counter + curated dhikr feed below.
 *
 * The big circular tap target is the primary experience here; videos
 * scroll up from below for users who want to soak in recitation.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Tasbeeh phrases — cycles 33 → 33 → 34 (standard post-salah tasbih).
// Each has a hand-picked YouTube video that loops the chant — used as a
// karaoke-style background behind the tasbeeh bead.
$la_tasbeeh = [
	[
		'key' => 'subhanallah',
		'arabic' => 'سُبْحَانَ ٱللَّٰه',
		'translit' => 'Subḥān Allāh',
		'meaning' => 'Glory be to Allah',
		'target' => 33,
		'color' => '#1e8e3e',
		'video' => 'MR6q9ztx-Ds', // Subhanallah · Ali Dawud · 2hr loop
	],
	[
		'key' => 'alhamdulillah',
		'arabic' => 'ٱلْحَمْدُ لِلَّٰه',
		'translit' => 'Alḥamdulillāh',
		'meaning' => 'All praise is for Allah',
		'target' => 33,
		'color' => '#1976d2',
		'video' => 'jbeb7uNncys', // Alhamdulillah · Mohammad Shariq · 1hr zikr
	],
	[
		'key' => 'allahuakbar',
		'arabic' => 'ٱللَّٰهُ أَكْبَر',
		'translit' => 'Allāhu akbar',
		'meaning' => 'Allah is greatest',
		'target' => 34,
		'color' => '#ED1C6C',
		'video' => 'MpP891hfZUc', // Allahu Akbar takbeer · Mishary Alafasy · 1hr loop
	],
];

get_header();
?>
<main class="la-app la-app--dhikr">

	<!-- TASBEEH INTERFACE -->
	<section class="la-tasbeeh" data-tasbeeh aria-label="Tasbeeh counter">
		<!-- Karaoke background — autoplay muted YouTube of the current phrase chant.
		     Render the iframe with the full URL on initial server-render so the
		     browser counts the autoplay-muted as legitimate (changing src via JS
		     later loses that trust and the video stays paused/black). -->
		<?php
		$la_init_vid = $la_tasbeeh[0]['video'];
		$la_init_url = "https://www.youtube.com/embed/{$la_init_vid}?autoplay=1&mute=1&loop=1&playlist={$la_init_vid}&controls=0&modestbranding=1&playsinline=1&rel=0&iv_load_policy=3&cc_load_policy=0&disablekb=1&fs=0&enablejsapi=1";
		?>
		<div class="la-tasbeeh-bg" aria-hidden="true">
			<iframe class="la-tasbeeh-bg-iframe"
				data-tasbeeh-bg-iframe
				src="<?php echo esc_url( $la_init_url ); ?>"
				allow="autoplay; encrypted-media; picture-in-picture"
				allowfullscreen
				frameborder="0"
				tabindex="-1"></iframe>
			<div class="la-tasbeeh-bg-vignette"></div>
		</div>
		<header class="la-tasbeeh-head">
			<div class="la-tasbeeh-overline"><?php esc_html_e( "Today's Tasbeeh", 'loveallah' ); ?></div>
			<div class="la-tasbeeh-stats" data-tasbeeh-stats>
				<span data-stat-total>0</span>
				<span class="la-tasbeeh-stats-label"><?php esc_html_e( 'remembrance today', 'loveallah' ); ?></span>
			</div>
		</header>

		<!-- Phrase pills (which one we're on) -->
		<div class="la-tasbeeh-pills">
			<?php foreach ( $la_tasbeeh as $i => $p ) : ?>
				<button type="button"
					class="la-tasbeeh-pill <?php echo $i === 0 ? 'is-active' : ''; ?>"
					data-pill-index="<?php echo (int) $i; ?>"
					data-key="<?php echo esc_attr( $p['key'] ); ?>">
					<span class="la-tasbeeh-pill-name"><?php echo esc_html( $p['translit'] ); ?></span>
					<span class="la-tasbeeh-pill-count" data-pill-count="<?php echo esc_attr( $p['key'] ); ?>">0/<?php echo (int) $p['target']; ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- THE BIG TAP TARGET -->
		<div class="la-tasbeeh-stage">
			<button type="button"
				class="la-tasbeeh-bead"
				data-tasbeeh-bead
				aria-label="<?php esc_attr_e( 'Tap to count', 'loveallah' ); ?>">
				<svg class="la-tasbeeh-ring" viewBox="0 0 200 200" aria-hidden="true">
					<circle class="la-tasbeeh-ring-track" cx="100" cy="100" r="92" fill="none" stroke="rgba(237,28,108,0.12)" stroke-width="6"/>
					<circle class="la-tasbeeh-ring-progress" cx="100" cy="100" r="92" fill="none" stroke="#ED1C6C" stroke-width="6" stroke-linecap="round" stroke-dasharray="578" stroke-dashoffset="578" transform="rotate(-90 100 100)"/>
				</svg>
				<div class="la-tasbeeh-bead-inner">
					<div class="la-tasbeeh-arabic" data-tasbeeh-arabic dir="rtl" lang="ar"><?php echo esc_html( $la_tasbeeh[0]['arabic'] ); ?></div>
					<div class="la-tasbeeh-translit" data-tasbeeh-translit><?php echo esc_html( $la_tasbeeh[0]['translit'] ); ?></div>
					<div class="la-tasbeeh-count" data-tasbeeh-count>
						<span data-current>0</span>
						<span class="la-tasbeeh-count-sep">/</span>
						<span data-target><?php echo (int) $la_tasbeeh[0]['target']; ?></span>
					</div>
				</div>
				<div class="la-tasbeeh-ripple" aria-hidden="true"></div>
			</button>
		</div>

		<div class="la-tasbeeh-meaning" data-tasbeeh-meaning><?php echo esc_html( $la_tasbeeh[0]['meaning'] ); ?></div>

		<div class="la-tasbeeh-controls">
			<button type="button" class="la-tasbeeh-control" data-tasbeeh-action="reset" aria-label="<?php esc_attr_e( 'Reset today', 'loveallah' ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
				<span><?php esc_html_e( 'Reset', 'loveallah' ); ?></span>
			</button>
			<div class="la-tasbeeh-spacer"></div>
			<button type="button" class="la-tasbeeh-control" data-tasbeeh-action="sound-toggle" aria-label="<?php esc_attr_e( 'Play chant audio', 'loveallah' ); ?>" data-sound-state="off">
				<svg class="la-tasbeeh-snd-off" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
				<svg class="la-tasbeeh-snd-on" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
				<span data-sound-label><?php esc_html_e( 'Sound off', 'loveallah' ); ?></span>
			</button>
			<button type="button" class="la-tasbeeh-control" data-tasbeeh-action="vibrate-toggle" aria-label="<?php esc_attr_e( 'Toggle vibration', 'loveallah' ); ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 8v8M5 6v12M19 6v12M22 8v8M9 4v16M15 4v16"/></svg>
			</button>
		</div>

		<script id="la-tasbeeh-config" type="application/json"><?php
			echo wp_json_encode( $la_tasbeeh );
		?></script>
	</section>

	<!-- Divider before video feed -->
	<div class="la-tasbeeh-divider">
		<span><?php esc_html_e( 'or listen to a recitation', 'loveallah' ); ?></span>
	</div>

</main>
<?php
// Existing dhikr video feed below
la_render_feed_main( 'dhikr' );
get_footer();
