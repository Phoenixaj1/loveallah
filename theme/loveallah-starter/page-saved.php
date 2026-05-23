<?php
/**
 * Saved — videos this visitor has bookmarked.
 *
 * Reads from wp_la_feed_interactions where action='save' for the current
 * session/user, then renders them with the standard content-card template.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$la_user_id    = get_current_user_id() ?: null;
$la_session_id = function_exists( 'la_get_or_set_session_id' ) ? la_get_or_set_session_id() : null;

global $wpdb;
$t        = LA_DB::tables();
$col      = $la_user_id ? 'user_id' : 'session_id';
$val      = $la_user_id ?: $la_session_id;

$saved_posts = [];
if ( $val ) {
	$saved_posts = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.*,
				s.username as scholar_username,
				s.display_name as scholar_display_name,
				s.account_type as scholar_account_type,
				s.avatar as scholar_avatar
		 FROM {$t['feed_interactions']} fi
		 JOIN {$t['feed_posts']} p ON p.id = fi.post_id
		 LEFT JOIN {$t['scholars']} s ON s.id = p.scholar_id
		 WHERE fi.{$col} = %s AND fi.action = 'save'
		 ORDER BY fi.id DESC
		 LIMIT 60",
		(string) $val
	) );
	foreach ( $saved_posts as $r ) { $r->_card_type = 'content'; }
}

get_header();
?>
<main class="la-app la-app--feed">
	<header class="la-saved-head">
		<div class="la-saved-tag"><?php esc_html_e( 'Your library', 'loveallah' ); ?></div>
		<h1 class="la-saved-title"><?php esc_html_e( 'Saved', 'loveallah' ); ?></h1>
		<p class="la-saved-sub"><?php echo $saved_posts
			? esc_html( sprintf( _n( '%d saved item', '%d saved items', count( $saved_posts ), 'loveallah' ), count( $saved_posts ) ) )
			: esc_html__( 'Tap the bookmark on any video to keep it here.', 'loveallah' ); ?></p>
	</header>

	<?php if ( $saved_posts ) : ?>
		<div class="la-feed-snap" data-feed>
			<?php foreach ( $saved_posts as $card ) { echo LA_FeedRender::card( $card ); } ?>
		</div>
	<?php else : ?>
		<div class="la-saved-empty">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
				<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
			</svg>
			<p><?php esc_html_e( 'Nothing saved yet.', 'loveallah' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="la-saved-cta"><?php esc_html_e( 'Browse the feed', 'loveallah' ); ?></a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
