<?php
/**
 * Dhikr Witness — chant-along feed.
 *
 * Wave 52: queries the dedicated `dhikr_videos` table directly. No
 * algorithm, no regex matching, no scholar-type joins. Each entry in
 * that table is a hand-verified dhikr video — pure content, predictable
 * behaviour. Adding new videos = INSERT a row.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();
$videos = $wpdb->get_results(
	"SELECT * FROM {$t['dhikr_videos']} ORDER BY sort_order ASC, id ASC LIMIT 50"
);

// Convert each row into a feed-card shape so LA_FeedRender::card()
// can render it via the existing snap-feed template.
$cards = [];
foreach ( $videos as $v ) {
	$cards[] = (object) [
		'_card_type'           => 'content',
		'id'                   => (int) $v->id + 1000000,   // synthetic id space, avoid collision with feed_posts
		'scholar_id'           => 0,
		'type'                 => 'dhikr',
		'title'                => $v->title,
		'caption'              => '',
		'video_url'            => 'https://www.youtube.com/embed/' . $v->youtube_id,
		'thumbnail_url'        => 'https://i.ytimg.com/vi/' . $v->youtube_id . '/hqdefault.jpg',
		'duration_sec'         => (int) $v->duration_sec,
		'published_at'         => $v->added_at,
		'expires_at'           => null,
		'likes_count'          => 0,
		'saves_count'          => 0,
		'shares_count'         => 0,
		'original_source_url'  => 'https://www.youtube.com/watch?v=' . $v->youtube_id,
		'scholar_username'     => $v->channel_handle ?: '',
		'scholar_display_name' => $v->scholar_name ?: ( $v->channel_handle ?: 'Dhikr' ),
		'scholar_account_type' => 'curated',
		'scholar_avatar'       => '',
		'scholar_source_url'   => '',
		'_is_saved'            => false,
		'_is_liked'            => false,
	];
}
?>
<main class="la-app la-app--witness">

	<!-- Session tally — counts the user's taps, no fixed target. -->
	<div class="la-witness-hud" data-witness-hud>
		<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-witness-back-link" aria-label="Back to dhikr modes">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
		</a>
		<div class="la-witness-hud-tally">
			<div class="la-witness-hud-count" data-witness-count>0</div>
			<div class="la-witness-hud-label">this session</div>
		</div>
	</div>

	<?php if ( ! empty( $cards ) ) : ?>
		<div class="la-feed-snap la-feed-snap--witness" data-feed data-witness>
			<?php foreach ( $cards as $card ) {
				echo LA_FeedRender::card( $card );
			} ?>

			<div class="la-feed-loader" data-feed-loader hidden>
				<div class="la-feed-loader-spinner" aria-hidden="true"></div>
				<span><?php esc_html_e( 'Loading more', 'loveallah' ); ?></span>
			</div>
			<div class="la-feed-sentinel" data-feed-sentinel aria-hidden="true"></div>
		</div>

		<button class="la-witness-tap" type="button" data-witness-tap aria-label="Tap each remembrance">
			<span class="la-witness-tap-plus">+1</span>
			<span class="la-witness-tap-label">Tap each one</span>
		</button>

	<?php else : ?>
		<section class="la-witness-empty">
			<div class="la-witness-empty-inner">
				<div class="la-witness-empty-glyph" aria-hidden="true">◯</div>
				<h2 class="la-witness-empty-title">No dhikr videos yet</h2>
				<p class="la-witness-empty-body">
					Adding curated content. Try one of the other paths in the meantime.
				</p>
				<div class="la-witness-empty-actions">
					<a href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>" class="la-witness-empty-btn la-witness-empty-btn--primary">Try Pulse</a>
					<a href="<?php echo esc_url( home_url( '/dhikr/?mode=solitude' ) ); ?>" class="la-witness-empty-btn">Solitude</a>
					<a href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>" class="la-witness-empty-btn">Names</a>
				</div>
			</div>
		</section>
	<?php endif; ?>

</main>
