<?php
/**
 * Duas — left emoji sidebar with browsable categories.
 *
 * Mobile-first: vertical rail of emoji buttons on the left (one per
 * category), main pane on the right with scrollable cards. Tap an
 * emoji to switch categories instantly.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();
$duas = $wpdb->get_results( "SELECT * FROM {$t['duas']} ORDER BY sort_order, id" );

// Group by category
$by_cat = [];
foreach ( $duas as $d ) {
	$by_cat[ $d->category ][] = $d;
}

// My Ameens for this identity
$user_id    = get_current_user_id() ?: null;
$session_id = function_exists( 'la_get_or_set_session_id' ) ? la_get_or_set_session_id() : null;
$identity   = $user_id ? ( 'u' . (int) $user_id ) : ( $session_id ? ( 's' . $session_id ) : '' );
$my_ameen = [];
if ( $identity && $duas ) {
	$rows = $wpdb->get_col( $wpdb->prepare(
		"SELECT dua_id FROM {$t['dua_ameen']} WHERE identity = %s", $identity
	) );
	$my_ameen = array_flip( array_map( 'intval', $rows ) );
}

// Categories — ordered by daily relevance. Each: emoji, short label, long label.
$cats = [
	'morning'    => [ 'emoji' => '🌅', 'label' => 'Morning',    'sub' => 'After Fajr' ],
	'evening'    => [ 'emoji' => '🌙', 'label' => 'Evening',    'sub' => 'After Maghrib' ],
	'worry'      => [ 'emoji' => '💗', 'label' => 'Anxiety',    'sub' => 'When the heart is heavy' ],
	'general'    => [ 'emoji' => '⭐', 'label' => 'Daily',      'sub' => 'For every day' ],
	'waking'     => [ 'emoji' => '☀️', 'label' => 'Waking',     'sub' => 'On opening your eyes' ],
	'sleep'      => [ 'emoji' => '✨', 'label' => 'Sleep',      'sub' => 'As you lay down' ],
	'food'       => [ 'emoji' => '🍽️', 'label' => 'Food',       'sub' => 'Before & after meals' ],
	'travel'     => [ 'emoji' => '🛣️', 'label' => 'Travel',     'sub' => 'On the road' ],
	'sickness'   => [ 'emoji' => '🤲', 'label' => 'Sickness',   'sub' => 'When health falters' ],
	'gratitude'  => [ 'emoji' => '🌸', 'label' => 'Gratitude',  'sub' => 'For blessings' ],
];

// Filter to only categories that have duas
$active_cats = [];
foreach ( $cats as $key => $meta ) {
	if ( ! empty( $by_cat[ $key ] ) ) {
		$active_cats[ $key ] = $meta;
	}
}

$first_cat = array_key_first( $active_cats );

get_header();
?>
<main class="la-app la-app--duas-sidebar">

	<!-- Left sidebar: emoji column. Each is a button that switches the right pane. -->
	<aside class="la-duas-rail" aria-label="Dua categories">
		<?php $i = 0; foreach ( $active_cats as $key => $meta ) : ?>
			<button type="button"
				class="la-duas-rail-btn <?php echo $key === $first_cat ? 'is-active' : ''; ?>"
				data-cat="<?php echo esc_attr( $key ); ?>"
				aria-label="<?php echo esc_attr( $meta['label'] ); ?>">
				<span class="la-duas-rail-emoji" aria-hidden="true"><?php echo $meta['emoji']; ?></span>
				<span class="la-duas-rail-label"><?php echo esc_html( $meta['label'] ); ?></span>
				<span class="la-duas-rail-count"><?php echo count( $by_cat[ $key ] ); ?></span>
			</button>
		<?php $i++; endforeach; ?>
	</aside>

	<!-- Right pane: header + scrollable dua cards for the active category -->
	<div class="la-duas-pane">

		<!-- Active category header — updates via JS -->
		<header class="la-duas-pane-head">
			<div class="la-duas-pane-icon" data-cat-icon><?php echo $active_cats[ $first_cat ]['emoji']; ?></div>
			<div class="la-duas-pane-meta">
				<h1 class="la-duas-pane-title" data-cat-title><?php echo esc_html( $active_cats[ $first_cat ]['label'] ); ?></h1>
				<p class="la-duas-pane-sub" data-cat-sub><?php echo esc_html( $active_cats[ $first_cat ]['sub'] ); ?></p>
			</div>
		</header>

		<!-- Card lists — one section per category, only active is visible -->
		<div class="la-duas-lists">
			<?php foreach ( $active_cats as $key => $meta ) : ?>
				<section class="la-duas-list <?php echo $key === $first_cat ? 'is-active' : ''; ?>" data-cat-section="<?php echo esc_attr( $key ); ?>">

					<?php foreach ( $by_cat[ $key ] as $d ) :
						$is_amened = isset( $my_ameen[ (int) $d->id ] );
					?>
						<article class="la-dua" data-dua-id="<?php echo (int) $d->id; ?>">
							<header class="la-dua-head">
								<h2 class="la-dua-title"><?php echo esc_html( $d->title ); ?></h2>
								<?php if ( (int) $d->repeat_count > 1 ) : ?>
									<span class="la-dua-repeat" title="Recite this many times"><?php echo (int) $d->repeat_count; ?>×</span>
								<?php endif; ?>
							</header>
							<div class="la-dua-arabic" dir="rtl" lang="ar"><?php echo esc_html( $d->arabic ); ?></div>
							<?php if ( ! empty( $d->transliteration ) ) : ?>
								<div class="la-dua-translit"><?php echo esc_html( $d->transliteration ); ?></div>
							<?php endif; ?>
							<?php if ( ! empty( $d->meaning ) ) : ?>
								<p class="la-dua-meaning"><?php echo esc_html( $d->meaning ); ?></p>
							<?php endif; ?>
							<footer class="la-dua-foot">
								<?php if ( ! empty( $d->source ) ) : ?>
									<span class="la-dua-source">
										<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
										<?php echo esc_html( $d->source ); ?>
									</span>
								<?php endif; ?>
								<div class="la-dua-actions">
									<button type="button"
										class="la-dua-btn <?php echo $is_amened ? 'is-active' : ''; ?>"
										data-action="ameen"
										data-id="<?php echo (int) $d->id; ?>"
										aria-pressed="<?php echo $is_amened ? 'true' : 'false'; ?>"
										aria-label="Ameen">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c-3-2-5-4-5-7 0-3 1.5-4 3-4s2 1 2 1 1-1 2.5-1 2.5 1 2.5 4-2 5-5 7z"/></svg>
										<span>Ameen</span>
										<span class="la-dua-btn-count" data-ameen-count><?php echo (int) $d->ameen_count; ?></span>
									</button>
									<button type="button" class="la-dua-btn" data-action="save" data-id="<?php echo (int) $d->id; ?>" aria-label="Copy">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
										<span>Copy</span>
									</button>
									<button type="button" class="la-dua-btn" data-action="share" data-id="<?php echo (int) $d->id; ?>" aria-label="Share">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
										<span>Share</span>
									</button>
								</div>
							</footer>
						</article>
					<?php endforeach; ?>

				</section>
			<?php endforeach; ?>
		</div>

	</div>

	<script id="la-duas-cats" type="application/json">
		<?php echo wp_json_encode( $active_cats ); ?>
	</script>

</main>
<?php
get_footer();
