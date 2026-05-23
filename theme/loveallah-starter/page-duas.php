<?php
/**
 * Duas — TikTok-style vertical snap feed of supplications.
 *
 * Each card is full-screen: Arabic dominates, transliteration + meaning
 * below. Right-rail actions: Ameen (count), Save, Share.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();

// Annotate with my Ameen state for this session
$user_id    = get_current_user_id() ?: null;
$session_id = function_exists( 'la_get_or_set_session_id' ) ? la_get_or_set_session_id() : null;
$identity   = $user_id ? ( 'u' . (int) $user_id ) : ( $session_id ? ( 's' . $session_id ) : '' );

$duas = $wpdb->get_results( "SELECT * FROM {$t['duas']} ORDER BY sort_order, id" );

$my_ameen = [];
if ( $identity && $duas ) {
	$rows = $wpdb->get_col( $wpdb->prepare(
		"SELECT dua_id FROM {$t['dua_ameen']} WHERE identity = %s",
		$identity
	) );
	$my_ameen = array_flip( array_map( 'intval', $rows ) );
}

// Category metadata — icon + colour accent for each section
$category_meta = [
	'morning'    => [ 'label' => 'Morning Adhkar',   'icon' => '🌅', 'tint' => '#F5C16C' ],
	'evening'    => [ 'label' => 'Evening Adhkar',   'icon' => '🌙', 'tint' => '#7B6BC4' ],
	'waking'     => [ 'label' => 'Upon Waking',      'icon' => '☀️', 'tint' => '#F09040' ],
	'sleep'      => [ 'label' => 'Before Sleep',     'icon' => '✨', 'tint' => '#5C6BC0' ],
	'food'       => [ 'label' => 'Eating & Drinking','icon' => '🍽️', 'tint' => '#26A69A' ],
	'travel'     => [ 'label' => 'Travelling',       'icon' => '🛣️', 'tint' => '#8D6E63' ],
	'worry'      => [ 'label' => 'Worry & Distress', 'icon' => '💗', 'tint' => '#E91E63' ],
	'sickness'   => [ 'label' => 'Sickness',         'icon' => '🤲', 'tint' => '#42A5F5' ],
	'gratitude'  => [ 'label' => 'Gratitude',        'icon' => '🌸', 'tint' => '#EC407A' ],
	'general'    => [ 'label' => 'Daily Remembrance','icon' => '⭐', 'tint' => '#FFB300' ],
];

get_header();
?>
<main class="la-app la-app--duas-feed">

	<div class="la-duas-snap" data-duas-feed>

		<?php foreach ( $duas as $d ) :
			$cat = $d->category ?: 'general';
			$meta = $category_meta[ $cat ] ?? $category_meta['general'];
			$is_amened = isset( $my_ameen[ (int) $d->id ] );
		?>
			<article class="la-dua-snap la-dua-cat-<?php echo esc_attr( $cat ); ?>"
				data-dua-id="<?php echo (int) $d->id; ?>"
				data-dua-slug="<?php echo esc_attr( $d->slug ); ?>"
				style="--dua-tint: <?php echo esc_attr( $meta['tint'] ); ?>;"
				tabindex="0">

				<!-- Soft gradient backdrop tied to category -->
				<div class="la-dua-snap-bg" aria-hidden="true"></div>

				<!-- Top label -->
				<div class="la-dua-snap-cat">
					<span class="la-dua-snap-cat-icon" aria-hidden="true"><?php echo $meta['icon']; ?></span>
					<span class="la-dua-snap-cat-label"><?php echo esc_html( $meta['label'] ); ?></span>
				</div>

				<!-- Title + repeat indicator -->
				<header class="la-dua-snap-head">
					<h2 class="la-dua-snap-title"><?php echo esc_html( $d->title ); ?></h2>
					<?php if ( (int) $d->repeat_count > 1 ) : ?>
						<span class="la-dua-snap-repeat"><?php echo (int) $d->repeat_count; ?>×</span>
					<?php endif; ?>
				</header>

				<!-- The Arabic, large and centered -->
				<div class="la-dua-snap-arabic" dir="rtl" lang="ar"><?php echo esc_html( $d->arabic ); ?></div>

				<!-- Transliteration -->
				<?php if ( ! empty( $d->transliteration ) ) : ?>
					<div class="la-dua-snap-translit"><?php echo esc_html( $d->transliteration ); ?></div>
				<?php endif; ?>

				<!-- Meaning -->
				<?php if ( ! empty( $d->meaning ) ) : ?>
					<p class="la-dua-snap-meaning"><?php echo esc_html( $d->meaning ); ?></p>
				<?php endif; ?>

				<!-- Source -->
				<?php if ( ! empty( $d->source ) ) : ?>
					<div class="la-dua-snap-source">
						<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
						<?php echo esc_html( $d->source ); ?>
					</div>
				<?php endif; ?>

				<!-- Right-rail actions (Ameen replaces Like) -->
				<div class="la-dua-snap-actions" aria-label="Dua actions">
					<button type="button" class="la-dua-snap-action <?php echo $is_amened ? 'is-active' : ''; ?>"
						data-action="ameen"
						data-id="<?php echo (int) $d->id; ?>"
						aria-pressed="<?php echo $is_amened ? 'true' : 'false'; ?>"
						aria-label="<?php esc_attr_e( 'Say Ameen', 'loveallah' ); ?>">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c-3-2-5-4-5-7 0-3 1.5-4 3-4s2 1 2 1 1-1 2.5-1 2.5 1 2.5 4-2 5-5 7z" fill="currentColor" stroke="none"/></svg>
						<span class="la-dua-snap-action-label">Ameen</span>
						<span class="la-dua-snap-action-count" data-ameen-count><?php echo (int) $d->ameen_count; ?></span>
					</button>
					<button type="button" class="la-dua-snap-action" data-action="save" data-id="<?php echo (int) $d->id; ?>" aria-label="<?php esc_attr_e( 'Save', 'loveallah' ); ?>">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
						<span class="la-dua-snap-action-label">Save</span>
					</button>
					<button type="button" class="la-dua-snap-action" data-action="share" data-id="<?php echo (int) $d->id; ?>" aria-label="<?php esc_attr_e( 'Share', 'loveallah' ); ?>">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
						<span class="la-dua-snap-action-label">Share</span>
					</button>
				</div>
			</article>
		<?php endforeach; ?>

		<?php if ( empty( $duas ) ) : ?>
			<article class="la-dua-snap"><p style="padding:40px;color:#fff">No duas yet.</p></article>
		<?php endif; ?>

	</div>

</main>
<?php
get_footer();
