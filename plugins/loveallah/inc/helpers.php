<?php
/**
 * Template helpers.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function la_get_or_set_session_id() : string {
	if ( ! empty( $_COOKIE['wordpress_la_session'] ) ) {
		return sanitize_key( $_COOKIE['wordpress_la_session'] );
	}
	$sid = wp_generate_password( 32, false, false );
	if ( ! headers_sent() ) {
		setcookie(
			'wordpress_la_session',
			$sid,
			[
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}
	$_COOKIE['wordpress_la_session'] = $sid;
	return $sid;
}

/**
 * Set the wordpress_la_session cookie early on every request, before any output.
 * Without this, get_header() flushes output before la_render_feed_main()
 * runs — so setcookie() silently fails (headers already sent) and every
 * page load creates a brand-new session, wiping the user's unlock state.
 */
add_action( 'send_headers', 'la_seed_session_cookie_early', 1 );
function la_seed_session_cookie_early() : void {
	if ( is_admin() ) return;
	if ( headers_sent() ) return;
	if ( ! empty( $_COOKIE['wordpress_la_session'] ) ) return;
	la_get_or_set_session_id();
}

function la_chosen_mosque() {
	if ( ! empty( $_COOKIE['la_masjid_slug'] ) ) {
		$slug = sanitize_title( $_COOKIE['la_masjid_slug'] );
		$m = LA_Mosques::get_by_slug( $slug );
		if ( $m ) return $m;
	}
	return LA_Mosques::default_mosque();
}

function la_unlock_state_for_view() : array {
	$user_id = get_current_user_id() ?: null;
	$session_id = la_get_or_set_session_id();
	$state = LA_Unlock::today_state( $user_id, $session_id );
	$completed = (int) $state->dhikr_completed;
	return [
		'completed' => $completed,
		'required'  => LA_Unlock::REQUIRED_DHIKR,
		'unlocked'  => $completed >= LA_Unlock::REQUIRED_DHIKR,
		'streak'    => LA_Unlock::get_streak( $user_id, $session_id ),
	];
}

/**
 * Render the feed app shell (used by Feed, Dhikr, Nasheed, Mindfulness pages).
 * Pre-filters by type; hides chips on filtered pages since the tab IS the filter.
 *
 * @param string $type_filter Empty for unfiltered (Feed tab), else 'dhikr', 'nasheed', 'mindfulness', etc.
 */
function la_render_feed_main( string $type_filter = '' ) : void {
	$user_id    = get_current_user_id() ?: null;
	$session_id = la_get_or_set_session_id();

	// Feed pages personalise on session state (unlock, signups, affinities)
	// so they must never be served from a shared cache.
	if ( ! headers_sent() ) {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'X-Breeze-Cache-Bypass: 1' );   // explicit hint for Breeze
		header( 'X-Cache-Bypass: 1' );           // explicit hint for Varnish
	}
	defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );

	$cards = LA_Algorithm::for_user( $user_id, $session_id, 20, 0, $type_filter ?: null );

	// Wave 36: if the visitor arrived via a /clip/{id}/ share link, prepend
	// that specific post as the first card. Without this, the feed algorithm
	// might rank the shared clip lower than the freshness winner and the
	// recipient lands on a different video than the sender intended.
	$clip_id = (int) get_query_var( 'la_clip' );
	if ( $clip_id ) {
		$clip_post = LA_Feed::get_by_id( $clip_id );
		if ( $clip_post && empty( $clip_post->expires_at ) || ( $clip_post && strtotime( $clip_post->expires_at ) > time() ) ) {
			// Hydrate the scholar fields the renderer expects (the algorithm
			// adds them via SQL JOIN; LA_Feed::by_id returns just the post row).
			if ( class_exists( 'LA_Scholars' ) ) {
				$scholar = LA_Scholars::get_by_id( (int) $clip_post->scholar_id );
				if ( $scholar ) {
					$clip_post->scholar_username     = $scholar->username     ?? '';
					$clip_post->scholar_display_name = $scholar->display_name ?? '';
					$clip_post->scholar_account_type = $scholar->account_type ?? 'curated';
					$clip_post->scholar_avatar       = $scholar->avatar       ?? '';
					$clip_post->scholar_source_url   = $scholar->source_url   ?? '';
				}
			}
			$clip_post->_card_type = 'content';
			// Drop the dupe if the algorithm already put this clip in $cards.
			$cards = array_values( array_filter( $cards, function ( $c ) use ( $clip_id ) {
				return ! ( ( $c->_card_type ?? '' ) === 'content' && (int) ( $c->id ?? 0 ) === $clip_id );
			} ) );
			array_unshift( $cards, $clip_post );
		}
	}

	// Bulk-decorate content cards with this identity's saved/liked state
	// so the bookmark + heart icons render in the correct state on first
	// paint — no flicker waiting for client-side localStorage hydration.
	$post_ids = [];
	foreach ( $cards as $c ) {
		if ( ( $c->_card_type ?? '' ) === 'content' && ! empty( $c->id ) ) {
			$post_ids[] = (int) $c->id;
		}
	}
	$saved_map = LA_Feed::active_actions_for( $user_id, $session_id, $post_ids, 'save' );
	$liked_map = LA_Feed::active_actions_for( $user_id, $session_id, $post_ids, 'like' );
	foreach ( $cards as $c ) {
		if ( ( $c->_card_type ?? '' ) === 'content' && ! empty( $c->id ) ) {
			$c->_is_saved = isset( $saved_map[ (int) $c->id ] );
			$c->_is_liked = isset( $liked_map[ (int) $c->id ] );
		}
	}
	?>
	<main class="la-app la-app--feed" data-active-filter="<?php echo esc_attr( $type_filter ); ?>">
		<div class="la-feed-snap" data-feed data-initial-filter="<?php echo esc_attr( $type_filter ); ?>">
			<?php foreach ( $cards as $card ) {
				echo LA_FeedRender::card( $card );
			} ?>

			<?php if ( empty( $cards ) ) : ?>
				<article class="la-snap la-snap--empty">
					<div class="la-snap-inner">
						<?php if ( $type_filter ) : ?>
							<h3><?php printf( esc_html__( 'No %s yet', 'loveallah' ), esc_html( ucfirst( $type_filter ) ) ); ?></h3>
							<p><?php esc_html_e( 'We curate this carefully. Fresh content arrives daily — check back soon.', 'loveallah' ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'No content yet. Check back soon — we curate fresh content every day.', 'loveallah' ); ?></p>
						<?php endif; ?>
					</div>
				</article>
			<?php endif; ?>

			<div class="la-feed-loader" data-feed-loader hidden>
				<div class="la-feed-loader-spinner" aria-hidden="true"></div>
				<span><?php esc_html_e( 'Loading more', 'loveallah' ); ?></span>
			</div>
			<div class="la-feed-sentinel" data-feed-sentinel aria-hidden="true"></div>
		</div>

		<div class="la-affirmation-overlay" hidden>
			<div class="la-affirmation-text"></div>
		</div>

		<!-- Events bottom sheet (reusable across all feed pages) -->
		<div class="la-sheet-backdrop" data-sheet-backdrop hidden></div>
		<aside class="la-sheet" data-sheet="events" hidden aria-label="Upcoming masjid events" aria-modal="true" role="dialog">
			<div class="la-sheet-handle" aria-hidden="true"></div>
			<header class="la-sheet-head">
				<div>
					<div class="la-sheet-overline"><?php esc_html_e( 'Upcoming at', 'loveallah' ); ?></div>
					<h2 class="la-sheet-title" data-events-mosque>—</h2>
				</div>
				<button class="la-sheet-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'loveallah' ); ?>" data-sheet-close>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>
			</header>
			<div class="la-sheet-body" data-events-body>
				<p class="la-sheet-loading"><?php esc_html_e( 'Loading events…', 'loveallah' ); ?></p>
			</div>
		</aside>

	</main>
	<?php
}

/**
 * Time-aware short Arabic phrase + transliteration.
 * Sacred, never performative.
 */
function la_greeting() : array {
	$hour = (int) current_time( 'H' );
	if ( $hour < 5 )       return [ 'arabic' => 'سُبْحَانَكَ اللَّٰهُمَّ', 'translit' => 'Subḥānak Allāhumma' ];
	if ( $hour < 12 )      return [ 'arabic' => 'بِسْمِ ٱللَّٰه',         'translit' => 'Bismillāh' ];
	if ( $hour < 16 )      return [ 'arabic' => 'ٱلْحَمْدُ لِلَّٰه',       'translit' => 'Alḥamdulillāh' ];
	if ( $hour < 19 )      return [ 'arabic' => 'مَسَاءُ الْخَيْر',         'translit' => 'Masāʾu al-khayr' ];
	if ( $hour < 21 )      return [ 'arabic' => 'مَغْرِب مُبَارَك',        'translit' => 'Maghrib mubārak' ];
	return                    [ 'arabic' => 'لَيْلَة مُبَارَكَة',          'translit' => 'Layla mubārakah' ];
}
