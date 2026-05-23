<?php
/**
 * Duas — supplications library from authentic Hadith.
 *
 * Categorised cards with Arabic + transliteration + meaning + source.
 * No video, no autoplay — designed for the quiet moments between scrolling.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();
$duas_all = $wpdb->get_results( "SELECT * FROM {$t['duas']} ORDER BY sort_order, id" );

// Group by category
$by_cat = [];
foreach ( $duas_all as $d ) {
	$by_cat[ $d->category ][] = $d;
}

// Category metadata — order + display label + icon emoji
$category_meta = [
	'morning'    => [ 'label' => 'Morning Adhkar', 'icon' => '🌅', 'note' => 'After Fajr' ],
	'evening'   => [ 'label' => 'Evening Adhkar', 'icon' => '🌙', 'note' => 'After Maghrib' ],
	'waking'    => [ 'label' => 'Upon Waking',     'icon' => '☀️', 'note' => 'When opening your eyes' ],
	'sleep'     => [ 'label' => 'Before Sleep',    'icon' => '✨', 'note' => 'As you lay down' ],
	'food'      => [ 'label' => 'Eating & Drinking','icon' => '🍽️', 'note' => 'Before and after meals' ],
	'travel'    => [ 'label' => 'Travelling',      'icon' => '🛣️', 'note' => 'On the road' ],
	'worry'     => [ 'label' => 'Worry & Distress','icon' => '💗', 'note' => 'When the heart is heavy' ],
	'sickness'  => [ 'label' => 'Sickness',        'icon' => '🤲', 'note' => 'When health falters' ],
	'gratitude' => [ 'label' => 'Gratitude',       'icon' => '🌸', 'note' => 'For blessings received' ],
	'general'   => [ 'label' => 'Throughout the Day', 'icon' => '⭐', 'note' => 'Everyday remembrance' ],
];

get_header();
?>
<main class="la-app la-app--duas">

	<header class="la-duas-hero">
		<div class="la-duas-overline"><?php esc_html_e( 'From authentic Hadith', 'loveallah' ); ?></div>
		<h1 class="la-duas-title"><?php esc_html_e( 'Duas', 'loveallah' ); ?></h1>
		<p class="la-duas-sub"><?php esc_html_e( 'Supplications from the Prophet ﷺ for every moment of your day.', 'loveallah' ); ?></p>
	</header>

	<!-- Category jump chips -->
	<nav class="la-duas-chips" aria-label="<?php esc_attr_e( 'Jump to category', 'loveallah' ); ?>">
		<?php foreach ( $category_meta as $cat => $m ) :
			if ( empty( $by_cat[ $cat ] ) ) continue;
		?>
			<a class="la-duas-chip" href="#cat-<?php echo esc_attr( $cat ); ?>">
				<span class="la-duas-chip-icon" aria-hidden="true"><?php echo $m['icon']; ?></span>
				<span><?php echo esc_html( $m['label'] ); ?></span>
				<span class="la-duas-chip-count"><?php echo count( $by_cat[ $cat ] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php foreach ( $category_meta as $cat => $m ) :
		if ( empty( $by_cat[ $cat ] ) ) continue;
	?>
		<section class="la-duas-section" id="cat-<?php echo esc_attr( $cat ); ?>">
			<header class="la-duas-section-head">
				<div class="la-duas-section-icon" aria-hidden="true"><?php echo $m['icon']; ?></div>
				<div>
					<h2 class="la-duas-section-title"><?php echo esc_html( $m['label'] ); ?></h2>
					<p class="la-duas-section-note"><?php echo esc_html( $m['note'] ); ?></p>
				</div>
			</header>

			<?php foreach ( $by_cat[ $cat ] as $d ) : ?>
				<article class="la-dua-card" data-dua-slug="<?php echo esc_attr( $d->slug ); ?>">
					<header class="la-dua-card-head">
						<h3 class="la-dua-card-title"><?php echo esc_html( $d->title ); ?></h3>
						<?php if ( (int) $d->repeat_count > 1 ) : ?>
							<span class="la-dua-card-repeat" title="<?php esc_attr_e( 'Recite this many times', 'loveallah' ); ?>"><?php echo (int) $d->repeat_count; ?>×</span>
						<?php endif; ?>
					</header>
					<div class="la-dua-arabic" dir="rtl" lang="ar"><?php echo esc_html( $d->arabic ); ?></div>
					<?php if ( ! empty( $d->transliteration ) ) : ?>
						<div class="la-dua-translit"><?php echo esc_html( $d->transliteration ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $d->meaning ) ) : ?>
						<p class="la-dua-meaning"><?php echo esc_html( $d->meaning ); ?></p>
					<?php endif; ?>
					<footer class="la-dua-card-foot">
						<?php if ( ! empty( $d->source ) ) : ?>
							<span class="la-dua-source">
								<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
								<?php echo esc_html( $d->source ); ?>
							</span>
						<?php endif; ?>
						<button type="button" class="la-dua-action" data-action="copy-dua" aria-label="<?php esc_attr_e( 'Copy', 'loveallah' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
							<span><?php esc_html_e( 'Copy', 'loveallah' ); ?></span>
						</button>
						<button type="button" class="la-dua-action" data-action="share-dua" aria-label="<?php esc_attr_e( 'Share', 'loveallah' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
							<span><?php esc_html_e( 'Share', 'loveallah' ); ?></span>
						</button>
					</footer>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>

	<?php if ( empty( $duas_all ) ) : ?>
		<div class="la-duas-empty">
			<p><?php esc_html_e( 'No duas yet. They\'ll appear here as soon as the database seeds.', 'loveallah' ); ?></p>
		</div>
	<?php endif; ?>

</main>
<?php
get_footer();
