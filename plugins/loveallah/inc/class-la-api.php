<?php
/**
 * REST API — namespace loveallah/v1.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_API {

	const NS = 'loveallah/v1';

	public static function register_routes() {
		register_rest_route( self::NS, '/unlock-state', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'get_unlock_state' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/dhikr/complete', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'complete_dhikr' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		register_rest_route( self::NS, '/feed', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'get_feed' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/feed/more', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'get_feed_more' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/youtube/sync', [
			'methods'  => [ 'POST', 'GET' ],
			'callback' => [ __CLASS__, 'youtube_sync' ],
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		] );

		register_rest_route( self::NS, '/events/upcoming', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'events_upcoming' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/signup', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'signup' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Wave 31: 'skip' and 'engage' are watch-through quality signals
		// fired by the client when a card leaves view. 'complete' fires when
		// the embedded video reaches end-of-playback. Together with view/
		// like/save/share they feed the post_quality_scores() ranker.
		register_rest_route( self::NS, '/feed/(?P<id>\d+)/(?P<action>like|save|share|view|skip|engage|complete)', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'feed_interaction' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Wave 91: follow system. Tap-to-follow each scholar — boosts their
		// content in the user's algorithm. Toggle endpoint (POST) returns
		// the new state; bootstrap GET endpoint returns the full follow list
		// so the renderer can mark the right buttons as "Following" on load.
		register_rest_route( self::NS, '/follow/(?P<scholar_id>\d+)', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'toggle_follow' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );
		register_rest_route( self::NS, '/follows', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'list_follows' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::NS, '/choose-mosque', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'choose_mosque' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Prayer log — tap a prayer cell in header to mark prayed today.
		register_rest_route( self::NS, '/prayer-log/today', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'prayer_log_today' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NS, '/prayer-log/toggle', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'prayer_log_toggle' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
			'args' => [
				'prayer' => [ 'type' => 'string', 'required' => true ],
			],
		] );

		// Tasbeeh — interactive counter on /dhikr tab.
		register_rest_route( self::NS, '/tasbeeh/today', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'tasbeeh_today' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NS, '/tasbeeh/increment', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'tasbeeh_increment' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
			'args' => [
				'phrase' => [ 'type' => 'string', 'required' => true ],
				'count'  => [ 'type' => 'integer', 'required' => true ],
			],
		] );

		// Duas — read-only public library
		register_rest_route( self::NS, '/duas', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'duas_list' ],
			'permission_callback' => '__return_true',
			'args' => [
				'category' => [ 'type' => 'string', 'default' => '' ],
			],
		] );

		// Ameen reaction — tap to second a supplication
		register_rest_route( self::NS, '/duas/(?P<id>\d+)/ameen', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'dua_ameen' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Masjid event RSVP / favourite — toggleable, per identity
		register_rest_route( self::NS, '/events/(?P<id>\d+)/(?P<status>rsvp|fav)', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'event_toggle' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Connect — skills marketplace
		register_rest_route( self::NS, '/skills', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'skills_list' ],
			'permission_callback' => '__return_true',
			'args' => [
				'category' => [ 'type' => 'string', 'default' => '' ],
				'q'        => [ 'type' => 'string', 'default' => '' ],
				'limit'    => [ 'type' => 'integer', 'default' => 30 ],
			],
		] );
		register_rest_route( self::NS, '/skills/submit', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'skills_submit' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );
		register_rest_route( self::NS, '/skills/(?P<id>\d+)/contact', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'skills_contact' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		register_rest_route( self::NS, '/mosques/nearest', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'nearest_mosques' ],
			'permission_callback' => '__return_true',
		] );

		// Wave 56: full nearby payload — masjid + jamaat times + favourite
		// flag — for the rebuilt masjid tab. Separate from /nearest (which
		// is the minimal header dropdown) so the heavier query doesn't
		// run on every header render.
		register_rest_route( self::NS, '/masjids/nearby', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'masjids_nearby' ],
			'permission_callback' => '__return_true',
		] );

		// Toggle a masjid as favourite for the current identity.
		register_rest_route( self::NS, '/masjids/favourite', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'masjids_favourite' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Wave 64: passwordless "sign in". Email + phone → stable identity.
		register_rest_route( self::NS, '/identity', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'identity_signin' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );
		register_rest_route( self::NS, '/identity/me', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'identity_me' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NS, '/identity/signout', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'identity_signout' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
		] );

		// Geo prayer times — visitor's own location, computed locally
		// (Aladhan is firewalled from Cloudways).
		register_rest_route( self::NS, '/prayer-times', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'prayer_times_for_geo' ],
			'permission_callback' => '__return_true',
			'args' => [
				'lat'    => [ 'type' => 'number', 'required' => true ],
				'lng'    => [ 'type' => 'number', 'required' => true ],
				'method' => [ 'type' => 'string', 'default' => 'ISNA' ],
				'tz'     => [ 'type' => 'string', 'default' => '' ],
			],
		] );
	}

	public static function prayer_times_for_geo( WP_REST_Request $req ) {
		$lat = (float) $req->get_param( 'lat' );
		$lng = (float) $req->get_param( 'lng' );
		$tz  = (string) $req->get_param( 'tz' );
		$method = (string) $req->get_param( 'method' );

		// Sanity bounds
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return new WP_Error( 'bad_geo', 'Invalid coordinates', [ 'status' => 400 ] );
		}

		$timings = LA_Prayer_Compute::times_for( $lat, $lng, $tz ?: '', in_array( $method, array_keys( LA_Prayer_Compute::METHODS ), true ) ? $method : 'ISNA' );
		$next    = LA_Prayer_Times::next_prayer( $timings );
		return [
			'lat'     => $lat,
			'lng'     => $lng,
			'method'  => $method,
			'tz'      => $tz,
			'timings' => $timings,
			'next'    => $next,
		];
	}

	public static function check_nonce( WP_REST_Request $req ) {
		$nonce = $req->get_header( 'x-wp-nonce' );
		return wp_verify_nonce( $nonce, 'wp_rest' ) ? true : new WP_Error( 'rest_forbidden', 'Invalid nonce', [ 'status' => 403 ] );
	}

	public static function get_unlock_state( WP_REST_Request $req ) {
		[ $user_id, $session_id ] = self::identity( $req );
		$state = LA_Unlock::today_state( $user_id, $session_id );
		return [
			'dhikr_completed' => (int) $state->dhikr_completed,
			'required' => LA_Unlock::REQUIRED_DHIKR,
			'unlocked' => (int) $state->dhikr_completed >= LA_Unlock::REQUIRED_DHIKR,
		];
	}

	public static function complete_dhikr( WP_REST_Request $req ) {
		[ $user_id, $session_id ] = self::identity( $req );
		$result = LA_Unlock::complete_dhikr( $user_id, $session_id );
		return $result;
	}

	public static function get_feed( WP_REST_Request $req ) {
		self::bypass_cdn_cache();
		[ $user_id, $session_id ] = self::identity( $req );

		if ( ! LA_Unlock::is_unlocked( $user_id, $session_id ) ) {
			return new WP_Error( 'feed_locked', 'Complete today\'s dhikr to unlock the feed', [ 'status' => 423 ] );
		}

		$mosque_id = null;
		$slug = isset( $_COOKIE['la_masjid_slug'] ) ? sanitize_title( $_COOKIE['la_masjid_slug'] ) : '';
		if ( $slug ) {
			$m = LA_Mosques::get_by_slug( $slug );
			if ( $m ) $mosque_id = (int) $m->id;
		}

		// Wave 87j: feed must go through LA_Algorithm::for_user() so the
		// per-scholar cap, freshness decay, scholar diversity round-robin,
		// type-balance pass, and 90-day seen-exclusion all apply. The old
		// path was LA_Feed::recent() — a flat published_at-DESC SQL query
		// with no diversity logic, which is why a single deep-import flood
		// (Mufti Menk: 48 fresh posts) returned 100% Menk for 20 cards.
		$limit  = max( 1, min( 50, (int) $req->get_param( 'limit' ) ?: 20 ) );
		$page   = max( 0, (int) $req->get_param( 'page' ) );
		$type   = sanitize_key( (string) $req->get_param( 'type' ) );
		$type   = $type ?: null;
		$posts  = LA_Algorithm::for_user( $user_id, $session_id, $limit, $page, $type );
		// Drop any non-content cards (signup interruption, dhikr) that the
		// algorithm may interleave — the JSON feed API serves video posts only.
		$posts = array_values( array_filter( $posts, function( $p ) {
			return ( $p->_card_type ?? 'content' ) === 'content';
		} ) );
		$out = [];
		foreach ( $posts as $p ) {
			$scholar = LA_Scholars::get_by_id( (int) $p->scholar_id );
			$out[] = [
				'id' => (int) $p->id,
				'type' => $p->type,
				'title' => $p->title,
				'caption' => $p->caption,
				'video_url' => $p->video_url,
				'thumbnail_url' => $p->thumbnail_url,
				'duration_sec' => (int) $p->duration_sec,
				'likes_count' => (int) $p->likes_count,
				'scholar' => $scholar ? [
					'username' => $scholar->username,
					'display_name' => $scholar->display_name,
					'avatar' => $scholar->avatar,
					'account_type' => $scholar->account_type,
					'label' => LA_Scholars::label( $scholar ),
					'attribution' => LA_Scholars::attribution( $scholar ),
				] : null,
			];
		}
		return [ 'posts' => $out ];
	}

	public static function signup( WP_REST_Request $req ) {
		$email = sanitize_email( (string) $req->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'bad_email', 'Please enter a valid email address.', [ 'status' => 400 ] );
		}
		$source     = sanitize_key( (string) $req->get_param( 'source' ) ) ?: 'feed';
		[ $user_id, $session_id ] = self::identity( $req );

		global $wpdb;
		$t = LA_DB::tables();

		// Upsert by email
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t['email_captures']} WHERE email = %s LIMIT 1",
			$email
		) );

		$data = [
			'email'      => $email,
			'user_id'    => $user_id,
			'session_id' => $session_id,
			'source'     => $source,
			'ip_hash'    => hash( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? '' ) . ( wp_salt( 'auth' ) ?: '' ) ),
			'referrer'   => sanitize_text_field( $_SERVER['HTTP_REFERER'] ?? '' ),
		];
		if ( $existing ) {
			$wpdb->update( $t['email_captures'], $data, [ 'id' => $existing ] );
		} else {
			$wpdb->insert( $t['email_captures'], $data );
		}

		do_action( 'la_email_captured', $email, $source, $user_id, $session_id );

		return [ 'ok' => true, 'email' => $email ];
	}

	public static function events_upcoming( WP_REST_Request $req ) {
		$slug = isset( $_COOKIE['la_masjid_slug'] ) ? sanitize_title( $_COOKIE['la_masjid_slug'] ) : '';
		$mosque = $slug ? LA_Mosques::get_by_slug( $slug ) : LA_Mosques::default_mosque();
		if ( ! $mosque ) return [ 'events' => [], 'mosque' => null ];

		$events = LA_Events::upcoming( (int) $mosque->id, 10 );
		$out = [];
		foreach ( $events as $e ) {
			$out[] = [
				'id'          => (int) $e->id,
				'title'       => $e->title,
				'description' => $e->description,
				'starts_at'   => $e->starts_at,
				'ends_at'     => $e->ends_at,
				'location'    => $e->location,
				'cta_label'   => $e->cta_label,
				'cta_url'     => $e->cta_url,
				'tag'         => $e->tag,
			];
		}
		return [
			'events' => $out,
			'mosque' => [ 'name' => $mosque->name, 'slug' => $mosque->slug ],
		];
	}

	/** Admin trigger: sync all scholar YouTube channels now */
	public static function youtube_sync( WP_REST_Request $req ) {
		if ( ! class_exists( 'LA_YouTube' ) ) {
			return new WP_Error( 'no_youtube', 'YouTube class missing', [ 'status' => 500 ] );
		}
		$result = LA_YouTube::sync_all();
		return $result;
	}

	/**
	 * Infinite scroll endpoint — returns rendered HTML for next batch.
	 * Supports optional ?type= filter for nasheeds / dhikr / qirat / reminder.
	 */
	public static function get_feed_more( WP_REST_Request $req ) {
		self::bypass_cdn_cache();
		[ $user_id, $session_id ] = self::identity( $req );
		$page  = max( 0, (int) $req->get_param( 'page' ) );
		$limit = min( 20, max( 5, (int) ( $req->get_param( 'limit' ) ?: 10 ) ) );
		$type  = sanitize_key( (string) $req->get_param( 'type' ) ) ?: null;

		// Wave 77: client-side seen-ids backstop. The frontend mirrors every
		// 'view' interaction into localStorage and ships up to 200 of the most
		// recent ids back in X-LA-Seen on each feed fetch. This keeps the
		// repeat-exclusion working even when the server-side identity has
		// rolled (cookie clear, PWA reinstall, switched device).
		$extra_seen = [];
		$header = (string) $req->get_header( 'x-la-seen' );
		if ( $header ) {
			foreach ( explode( ',', $header ) as $tok ) {
				$n = (int) trim( $tok );
				if ( $n > 0 ) $extra_seen[] = $n;
				if ( count( $extra_seen ) >= 250 ) break;
			}
		}

		$cards = LA_Algorithm::for_user( $user_id, $session_id, $limit, $page, $type, $extra_seen );

		// Bulk-decorate saved/liked state for the new batch (mirrors first-paint behaviour)
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

		$html  = '';
		foreach ( $cards as $card ) {
			$html .= LA_FeedRender::card( $card );
		}
		return [
			'html'  => $html,
			'count' => count( $cards ),
			'page'  => $page,
			'type'  => $type,
		];
	}

	public static function feed_interaction( WP_REST_Request $req ) {
		$id = (int) $req->get_param( 'id' );
		$action = sanitize_key( $req->get_param( 'action' ) );
		[ $user_id, $session_id ] = self::identity( $req );
		// Returns { ok: bool, active: bool } — active is the resulting state
		// AFTER the toggle, so client can sync its is-active class correctly.
		return LA_Feed::record_interaction( $id, $action, $user_id, $session_id );
	}

	/**
	 * Wave 91: toggle follow state for a scholar.
	 *
	 * Returns { ok: true, following: bool } — the resulting state AFTER toggle.
	 * Client uses that to flip the pill between "+Follow" and "✓Following"
	 * without a second GET.
	 *
	 * Identity scoping: prefers user_id if signed in, falls back to
	 * session_id. We don't merge anonymous follows into the WP user account
	 * on sign-in — Wave 64's identity system handles that elsewhere.
	 */
	public static function toggle_follow( WP_REST_Request $req ) {
		$scholar_id = (int) $req->get_param( 'scholar_id' );
		if ( ! $scholar_id ) return new WP_Error( 'bad_request', 'scholar_id required', [ 'status' => 400 ] );
		[ $user_id, $session_id ] = self::identity( $req );
		if ( ! $user_id && ! $session_id ) return new WP_Error( 'no_identity', 'No session', [ 'status' => 400 ] );

		global $wpdb;
		$t = LA_DB::tables();

		// Existence check by identity column. We treat (user_id, scholar_id)
		// and (session_id, scholar_id) as the unique key for a follow.
		if ( $user_id ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['follows']} WHERE user_id = %d AND scholar_id = %d LIMIT 1",
				$user_id, $scholar_id
			) );
		} else {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['follows']} WHERE session_id = %s AND scholar_id = %d LIMIT 1",
				$session_id, $scholar_id
			) );
		}

		if ( $existing ) {
			$wpdb->delete( $t['follows'], [ 'id' => $existing ] );
			return [ 'ok' => true, 'following' => false ];
		}

		$wpdb->insert( $t['follows'], [
			'user_id'    => $user_id ?: null,
			'session_id' => $user_id ? null : $session_id,
			'scholar_id' => $scholar_id,
		] );
		return [ 'ok' => true, 'following' => true ];
	}

	/**
	 * Wave 91: return the list of scholar IDs the current identity follows.
	 * Client loads this once on page boot to mark "Following" buttons.
	 */
	public static function list_follows( WP_REST_Request $req ) {
		[ $user_id, $session_id ] = self::identity( $req );
		if ( ! $user_id && ! $session_id ) return [ 'follows' => [] ];

		global $wpdb;
		$t = LA_DB::tables();
		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;

		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT scholar_id FROM {$t['follows']} WHERE {$col} = %s",
			(string) $val
		) );
		return [ 'follows' => array_map( 'intval', $ids ) ];
	}

	public static function choose_mosque( WP_REST_Request $req ) {
		$slug = sanitize_title( (string) $req->get_param( 'slug' ) );
		$m = LA_Mosques::get_by_slug( $slug );
		if ( ! $m ) {
			return new WP_Error( 'not_found', 'Mosque not found', [ 'status' => 404 ] );
		}
		setcookie( 'la_masjid_slug', $slug, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false );
		return [ 'ok' => true, 'mosque' => [ 'slug' => $m->slug, 'name' => $m->name ] ];
	}

	public static function nearest_mosques( WP_REST_Request $req ) {
		$lat = (float) $req->get_param( 'lat' );
		$lng = (float) $req->get_param( 'lng' );
		if ( ! $lat || ! $lng ) {
			return new WP_Error( 'bad_request', 'lat and lng required', [ 'status' => 400 ] );
		}
		$rows = LA_Mosques::nearest( $lat, $lng, 10 );
		return [ 'mosques' => array_map( function( $m ) {
			return [
				'slug' => $m->slug,
				'name' => $m->name,
				'city' => $m->city,
				'distance_km' => round( (float) $m->distance_km, 1 ),
			];
		}, $rows ) ];
	}

	/**
	 * Wave 56: full nearby masjids list for the rebuilt masjid tab.
	 *
	 * GET /loveallah/v1/masjids/nearby?lat=X&lng=Y&limit=20
	 *
	 * If lat/lng omitted, falls back to listing all mosques by name —
	 * gives a working response even when GPS is denied (offline / IP
	 * geo fallback is the next layer up, handled by the client).
	 *
	 * Each row includes the masjid's next-prayer (with jamaat time
	 * if configured) and whether the current identity has favourited
	 * it. Cap at 30 results.
	 */
	public static function masjids_nearby( WP_REST_Request $req ) {
		global $wpdb;
		$t = LA_DB::tables();

		$lat   = (float) $req->get_param( 'lat' );
		$lng   = (float) $req->get_param( 'lng' );
		$limit = (int)   $req->get_param( 'limit' );
		if ( $limit <= 0 || $limit > 30 ) $limit = 20;

		if ( $lat && $lng ) {
			$rows = LA_Mosques::nearest( $lat, $lng, $limit );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$t['mosques']} ORDER BY name ASC LIMIT %d",
				$limit
			) );
			foreach ( $rows as $r ) { $r->distance_km = null; }
		}

		$identity = self::identity_str( $req );
		$fav_ids  = [];
		if ( $identity ) {
			$fav_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT mosque_id FROM {$t['masjid_favourites']} WHERE identity = %s",
				$identity
			) );
			$fav_ids = array_map( 'intval', $fav_ids );
		}

		$out = [];
		foreach ( $rows as $m ) {
			$timings = LA_Prayer_Times::for_mosque( $m );
			$jamaat  = LA_Prayer_Times::apply_jamaat_offsets( $timings, $m );
			$next    = LA_Prayer_Times::next_prayer( $timings );
			$next_name = $next['name'] ?? '';
			$out[] = [
				'id'           => (int) $m->id,
				'slug'         => $m->slug,
				'name'         => $m->name,
				'address'      => trim( ( $m->address ?? '' ) . ( ! empty( $m->city ) ? ', ' . $m->city : '' ), ', ' ),
				'city'         => $m->city,
				'distance_km'  => isset( $m->distance_km ) ? round( (float) $m->distance_km, 1 ) : null,
				'distance_mi'  => isset( $m->distance_km ) ? round( ( (float) $m->distance_km ) * 0.621371, 1 ) : null,
				'brand_colour' => $m->branding_color_primary ?? null,
				'jumuah'       => $m->jumuah_time ? substr( $m->jumuah_time, 0, 5 ) : null,
				'jumuah_lang'  => $m->jumuah_khutbah_lang ?? null,
				'next'         => [
					'name'   => $next_name,
					'begin'  => $next_name && isset( $timings[ $next_name ] ) ? $timings[ $next_name ] : null,
					'jamaat' => $next_name && isset( $jamaat[  $next_name ] ) ? $jamaat[  $next_name ] : null,
				],
				'is_favourite' => in_array( (int) $m->id, $fav_ids, true ),
			];
		}

		// Favourites pinned to top (preserving distance order within each group)
		usort( $out, function( $a, $b ) {
			if ( $a['is_favourite'] !== $b['is_favourite'] ) return $b['is_favourite'] - $a['is_favourite'];
			$ad = $a['distance_km'] ?? PHP_INT_MAX;
			$bd = $b['distance_km'] ?? PHP_INT_MAX;
			return $ad <=> $bd;
		} );

		return [ 'masjids' => $out ];
	}

	/**
	 * Toggle a masjid favourite for the current identity.
	 *
	 * POST /loveallah/v1/masjids/favourite { slug, action: "add"|"remove" }
	 *
	 * Returns the new state so the client can update its UI without a
	 * follow-up GET.
	 */
	public static function masjids_favourite( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) {
			return new WP_Error( 'no_identity', 'No identity (cookie/login required)', [ 'status' => 401 ] );
		}
		$slug   = sanitize_title( (string) $req->get_param( 'slug' ) );
		$action = (string) $req->get_param( 'action' );
		if ( ! $slug ) {
			return new WP_Error( 'bad_request', 'slug required', [ 'status' => 400 ] );
		}
		$m = LA_Mosques::get_by_slug( $slug );
		if ( ! $m ) {
			return new WP_Error( 'not_found', 'Mosque not found', [ 'status' => 404 ] );
		}

		global $wpdb;
		$t = LA_DB::tables();

		if ( $action === 'remove' ) {
			$wpdb->delete( $t['masjid_favourites'], [
				'identity'  => $identity,
				'mosque_id' => (int) $m->id,
			] );
			return [ 'ok' => true, 'is_favourite' => false ];
		}

		// default: add (idempotent — unique key on identity+mosque)
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$t['masjid_favourites']} (identity, mosque_id) VALUES (%s, %d)",
			$identity, (int) $m->id
		) );
		return [ 'ok' => true, 'is_favourite' => true ];
	}

	// ─── Wave 64: passwordless email+phone identity ───────────────

	/**
	 * POST /loveallah/v1/identity { email, phone, name? }
	 *
	 * Creates the user row (or finds an existing one by email/phone),
	 * sets a long-lived la_user_token cookie, and migrates the device's
	 * pre-signin history (feed views, prayer log, dhikr count, masjid
	 * favourites) onto the new stable identity so nothing is lost.
	 *
	 * Returns the user payload so the client can flip its UI to
	 * "signed in" without a follow-up GET.
	 */
	public static function identity_signin( WP_REST_Request $req ) {
		$email = sanitize_email( (string) $req->get_param( 'email' ) );
		$phone = preg_replace( '/[^0-9+]/', '', (string) $req->get_param( 'phone' ) );
		$name  = sanitize_text_field( (string) $req->get_param( 'name' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'bad_email', 'Please enter a valid email', [ 'status' => 400 ] );
		}
		if ( strlen( $phone ) < 7 ) {
			return new WP_Error( 'bad_phone', 'Please enter a valid phone number', [ 'status' => 400 ] );
		}

		global $wpdb;
		$t = LA_DB::tables();

		// Find existing by email (most reliable match across devices)
		$user = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['users']} WHERE email = %s LIMIT 1", $email
		) );

		if ( $user ) {
			// Update phone/name if newer, refresh last_seen
			$wpdb->update( $t['users'], [
				'phone'        => $phone ?: $user->phone,
				'name'         => $name  ?: $user->name,
				'last_seen_at' => current_time( 'mysql', 1 ),
			], [ 'id' => $user->id ] );
			// Re-fetch so the response reflects the updated row (was
			// returning stale pre-update snapshot)
			$user = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$t['users']} WHERE id = %d LIMIT 1", $user->id
			) );
		} else {
			// New user — generate token
			$token = wp_generate_password( 48, false, false );
			$wpdb->insert( $t['users'], [
				'email'        => $email,
				'phone'        => $phone,
				'name'         => $name,
				'token'        => $token,
				'created_at'   => current_time( 'mysql', 1 ),
				'last_seen_at' => current_time( 'mysql', 1 ),
			] );
			$user = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$t['users']} WHERE id = %d LIMIT 1", $wpdb->insert_id
			) );
		}

		if ( ! $user ) {
			return new WP_Error( 'create_failed', 'Could not save identity', [ 'status' => 500 ] );
		}

		// Set the cookie — long-lived (1 year). HttpOnly + SameSite=Lax
		// + Secure on HTTPS. The cookie itself doesn't grant access —
		// it just lets the server find the user row.
		if ( ! headers_sent() ) {
			setcookie( 'la_user_token', $user->token, [
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			] );
		}
		$_COOKIE['la_user_token'] = $user->token;

		// Migrate pre-signin history onto the stable identity. The
		// session_id column on each tracking table is varchar so we
		// can just rewrite it. Idempotent — if the user already had
		// rows under 'e{id}' we just leave them, and the old session
		// rows get re-tagged to the same identity.
		$old_session = '';
		if ( isset( $_COOKIE['wordpress_la_session'] ) ) {
			$old_session = sanitize_key( $_COOKIE['wordpress_la_session'] );
		}
		$new_identity = 'e' . (int) $user->id;
		if ( $old_session && $old_session !== $new_identity ) {
			// Tables that key by session_id (varchar) for anonymous users
			$session_tables = [
				$t['feed_interactions'] => 'session_id',
				$t['prayer_log']        => 'identity',
				$t['tasbeeh_log']       => 'identity',
				$t['unlock_state']      => 'session_id',
				$t['event_rsvps']       => 'identity',
				$t['dua_ameen']         => 'identity',
				$t['masjid_favourites'] => 'identity',
			];
			foreach ( $session_tables as $tbl => $col ) {
				// Skip tables that don't exist yet on older installs
				$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
				if ( ! $exists ) continue;
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$tbl} SET {$col} = %s WHERE {$col} = %s",
					$new_identity, $old_session
				) );
			}
		}

		return [
			'ok'   => true,
			'user' => [
				'id'    => (int) $user->id,
				'name'  => $user->name,
				'email' => $user->email,
				'phone' => $user->phone,
			],
		];
	}

	/**
	 * GET /loveallah/v1/identity/me — returns the current user (or null).
	 */
	public static function identity_me( WP_REST_Request $req ) {
		$user = function_exists( 'la_current_user' ) ? la_current_user() : null;
		if ( ! $user ) return [ 'user' => null ];
		return [
			'user' => [
				'id'    => (int) $user->id,
				'name'  => $user->name,
				'email' => $user->email,
				'phone' => $user->phone,
			],
		];
	}

	/**
	 * POST /loveallah/v1/identity/signout — clears the token cookie.
	 * Doesn't delete the user row — they can sign back in with the
	 * same email to recover their history.
	 */
	public static function identity_signout( WP_REST_Request $req ) {
		if ( ! headers_sent() ) {
			setcookie( 'la_user_token', '', [
				'expires'  => time() - 3600,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			] );
		}
		unset( $_COOKIE['la_user_token'] );
		return [ 'ok' => true ];
	}

	// ─── Prayer-log endpoints ───

	/** Returns the list of prayer names this identity has marked prayed today. */
	public static function prayer_log_today( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) return [ 'prayed' => [] ];
		global $wpdb;
		$t = LA_DB::tables();
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT prayer FROM {$t['prayer_log']} WHERE identity = %s AND date = %s",
			$identity, gmdate( 'Y-m-d' )
		) );
		return [ 'prayed' => array_values( array_map( 'strval', $rows ) ) ];
	}

	/** Toggles a prayer's prayed state for today. Returns new state. */
	public static function prayer_log_toggle( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) {
			return new WP_Error( 'no_identity', 'Session required', [ 'status' => 400 ] );
		}
		$prayer = sanitize_text_field( (string) $req->get_param( 'prayer' ) );
		$allowed = [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ];
		if ( ! in_array( $prayer, $allowed, true ) ) {
			return new WP_Error( 'bad_prayer', 'Invalid prayer name', [ 'status' => 400 ] );
		}
		global $wpdb;
		$t = LA_DB::tables();
		$today = gmdate( 'Y-m-d' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t['prayer_log']} WHERE identity = %s AND date = %s AND prayer = %s",
			$identity, $today, $prayer
		) );
		if ( $existing ) {
			$wpdb->delete( $t['prayer_log'], [ 'id' => (int) $existing ] );
			$prayed = false;
		} else {
			$wpdb->insert( $t['prayer_log'], [
				'identity' => $identity, 'date' => $today, 'prayer' => $prayer,
				'prayed_at' => current_time( 'mysql' ),
			] );
			$prayed = true;
		}
		return [ 'prayer' => $prayer, 'prayed' => $prayed ];
	}

	// ─── Tasbeeh endpoints ───

	/** Per-phrase counts for today. */
	public static function tasbeeh_today( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) return [ 'counts' => new stdClass() ];
		global $wpdb;
		$t = LA_DB::tables();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT phrase, count FROM {$t['tasbeeh_log']} WHERE identity = %s AND date = %s",
			$identity, gmdate( 'Y-m-d' )
		), ARRAY_A );
		$out = [];
		foreach ( $rows as $r ) { $out[ $r['phrase'] ] = (int) $r['count']; }
		return [ 'counts' => $out ?: new stdClass() ];
	}

	/** Increments a phrase's daily count. Client throttles to ≤5 calls/sec. */
	public static function tasbeeh_increment( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) {
			return new WP_Error( 'no_identity', 'Session required', [ 'status' => 400 ] );
		}
		$phrase = sanitize_text_field( (string) $req->get_param( 'phrase' ) );
		$count  = max( 1, min( 100, (int) $req->get_param( 'count' ) ) ); // batch up to 100
		if ( ! in_array( $phrase, [ 'subhanallah', 'alhamdulillah', 'allahuakbar', 'laillaha', 'astaghfirullah' ], true ) ) {
			return new WP_Error( 'bad_phrase', 'Unknown phrase', [ 'status' => 400 ] );
		}
		global $wpdb;
		$t = LA_DB::tables();
		$today = gmdate( 'Y-m-d' );
		// Upsert: try insert, on duplicate update count
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$t['tasbeeh_log']} (identity, date, phrase, count)
			 VALUES (%s, %s, %s, %d)
			 ON DUPLICATE KEY UPDATE count = count + VALUES(count)",
			$identity, $today, $phrase, $count
		) );
		$new = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT count FROM {$t['tasbeeh_log']} WHERE identity = %s AND date = %s AND phrase = %s",
			$identity, $today, $phrase
		) );
		return [ 'phrase' => $phrase, 'count' => $new ];
	}

	/** List duas, optionally filtered by category. */
	public static function duas_list( WP_REST_Request $req ) {
		global $wpdb;
		$t = LA_DB::tables();
		$cat = sanitize_text_field( (string) $req->get_param( 'category' ) );
		if ( $cat ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$t['duas']} WHERE category = %s ORDER BY sort_order, id",
				$cat
			) );
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$t['duas']} ORDER BY sort_order, id" );
		}
		// Annotate with this identity's Ameen state
		$identity = self::identity_str( $req );
		if ( $identity && $rows ) {
			$ids = array_map( fn($r) => (int) $r->id, $rows );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$mine = $wpdb->get_col( $wpdb->prepare(
				"SELECT dua_id FROM {$t['dua_ameen']} WHERE identity = %s AND dua_id IN ($placeholders)",
				array_merge( [ $identity ], $ids )
			) );
			$mine_set = array_flip( array_map( 'intval', $mine ) );
			foreach ( $rows as $r ) {
				$r->ameen_by_me = isset( $mine_set[ (int) $r->id ] );
			}
		}
		return [ 'duas' => $rows ];
	}

	/** Toggle a dua's Ameen for this identity. Returns new state + count. */
	public static function dua_ameen( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) {
			return new WP_Error( 'no_identity', 'Session required', [ 'status' => 400 ] );
		}
		$dua_id = (int) $req['id'];
		global $wpdb;
		$t = LA_DB::tables();
		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t['dua_ameen']} WHERE dua_id = %d AND identity = %s",
			$dua_id, $identity
		) );
		if ( $exists ) {
			$wpdb->delete( $t['dua_ameen'], [ 'id' => $exists ] );
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['duas']} SET ameen_count = GREATEST(0, ameen_count - 1) WHERE id = %d", $dua_id ) );
			$ameen = false;
		} else {
			$wpdb->insert( $t['dua_ameen'], [
				'dua_id' => $dua_id, 'identity' => $identity,
				'created_at' => current_time( 'mysql' ),
			] );
			$wpdb->query( $wpdb->prepare( "UPDATE {$t['duas']} SET ameen_count = ameen_count + 1 WHERE id = %d", $dua_id ) );
			$ameen = true;
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ameen_count FROM {$t['duas']} WHERE id = %d", $dua_id ) );
		return [ 'dua_id' => $dua_id, 'ameen' => $ameen, 'count' => $count ];
	}

	/**
	 * Toggle an RSVP or favourite on a masjid event.
	 * Returns the new active state + the live count for that status.
	 */
	public static function event_toggle( WP_REST_Request $req ) {
		$identity = self::identity_str( $req );
		if ( ! $identity ) {
			return new WP_Error( 'no_identity', 'Session required', [ 'status' => 400 ] );
		}
		$event_id = (int) $req['id'];
		$status   = sanitize_key( (string) $req['status'] );
		if ( ! in_array( $status, [ 'rsvp', 'fav' ], true ) ) {
			return new WP_Error( 'bad_status', 'status must be rsvp or fav', [ 'status' => 400 ] );
		}

		global $wpdb;
		$t = LA_DB::tables();
		$count_col = $status === 'rsvp' ? 'rsvp_count' : 'fav_count';

		$exists = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t['event_rsvps']}
			 WHERE event_id = %d AND identity = %s AND status = %s",
			$event_id, $identity, $status
		) );
		if ( $exists ) {
			$wpdb->delete( $t['event_rsvps'], [ 'id' => $exists ] );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$t['events']} SET {$count_col} = GREATEST(0, {$count_col} - 1) WHERE id = %d",
				$event_id
			) );
			$active = false;
		} else {
			$wpdb->insert( $t['event_rsvps'], [
				'event_id' => $event_id, 'identity' => $identity, 'status' => $status,
			] );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$t['events']} SET {$count_col} = {$count_col} + 1 WHERE id = %d",
				$event_id
			) );
			$active = true;
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT {$count_col} FROM {$t['events']} WHERE id = %d",
			$event_id
		) );
		return [ 'event_id' => $event_id, 'status' => $status, 'active' => $active, 'count' => $count ];
	}

	/**
	 * Connect — list approved skill listings. Filters by category + free
	 * text search. Returns sorted by recency for now (we'll add affinity
	 * later when we have engagement signal).
	 */
	public static function skills_list( WP_REST_Request $req ) {
		global $wpdb;
		$t = LA_DB::tables();
		$category = sanitize_key( (string) $req->get_param( 'category' ) );
		$q        = trim( (string) $req->get_param( 'q' ) );
		$limit    = max( 1, min( 100, (int) $req->get_param( 'limit' ) ?: 30 ) );

		$where = "status = 'active'";
		$args  = [];
		if ( $category && $category !== 'all' ) {
			$where .= " AND category = %s";
			$args[] = $category;
		}
		if ( $q !== '' ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$where .= " AND ( title LIKE %s OR full_name LIKE %s OR city LIKE %s OR blurb LIKE %s )";
			$args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
		}

		$sql = "SELECT id, category, title, blurb, full_name, city, country,
		               contact_email, contact_whatsapp, contact_url,
		               price_from, price_unit, currency, photo_url,
		               views_count, contact_count, created_at
		        FROM {$t['skill_listings']}
		        WHERE {$where}
		        ORDER BY created_at DESC
		        LIMIT %d";
		$args[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		return [ 'listings' => $rows ];
	}

	/**
	 * Submit a new skill listing. Anonymous-allowed (identity recorded
	 * for moderation). Goes into 'pending' status until an admin approves.
	 */
	public static function skills_submit( WP_REST_Request $req ) {
		$body = $req->get_json_params();
		if ( ! is_array( $body ) ) $body = $req->get_params();

		$category = sanitize_key( (string) ( $body['category'] ?? '' ) );
		$title    = sanitize_text_field( (string) ( $body['title'] ?? '' ) );
		$blurb    = sanitize_textarea_field( (string) ( $body['blurb'] ?? '' ) );
		$name     = sanitize_text_field( (string) ( $body['full_name'] ?? '' ) );
		$city     = sanitize_text_field( (string) ( $body['city'] ?? '' ) );
		$email    = sanitize_email( (string) ( $body['contact_email'] ?? '' ) );
		$whats    = sanitize_text_field( (string) ( $body['contact_whatsapp'] ?? '' ) );
		$price    = (int) ( $body['price_from'] ?? 0 );
		$unit     = sanitize_key( (string) ( $body['price_unit'] ?? '' ) );

		if ( ! $category || ! $title || ! $name ) {
			return new WP_Error( 'missing', 'Category, title and your name are required.', [ 'status' => 400 ] );
		}
		if ( ! $email && ! $whats ) {
			return new WP_Error( 'missing_contact', 'At least one contact method (email or WhatsApp) is required.', [ 'status' => 400 ] );
		}

		[ $user_id, $session_id ] = self::identity( $req );
		$identity = self::identity_str( $req );

		global $wpdb;
		$t = LA_DB::tables();
		$ok = $wpdb->insert( $t['skill_listings'], [
			'user_id'          => $user_id,
			'identity'         => $identity ?: null,
			'category'         => $category,
			'title'            => $title,
			'blurb'            => $blurb,
			'full_name'        => $name,
			'city'             => $city,
			'country'          => null,
			'contact_email'    => $email ?: null,
			'contact_whatsapp' => $whats ?: null,
			'contact_url'      => null,
			'price_from'       => $price > 0 ? $price : null,
			'price_unit'       => $unit ?: null,
			'currency'         => 'GBP',
			'status'           => 'pending',
		] );
		if ( ! $ok ) {
			return new WP_Error( 'insert_failed', 'Could not save listing.', [ 'status' => 500 ] );
		}
		return [ 'ok' => true, 'id' => (int) $wpdb->insert_id, 'status' => 'pending' ];
	}

	/** Increment contact_count when a visitor taps the contact button. */
	public static function skills_contact( WP_REST_Request $req ) {
		$id = (int) $req['id'];
		global $wpdb;
		$t = LA_DB::tables();
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$t['skill_listings']} SET contact_count = contact_count + 1 WHERE id = %d AND status = 'active'",
			$id
		) );
		return [ 'ok' => true ];
	}

	/** Build the string identity used for prayer_log / tasbeeh_log rows. */
	private static function identity_str( WP_REST_Request $req ) : string {
		// Wave 64: prefer the email-based la_users row if the device
		// has a la_user_token cookie. That gives a STABLE identity
		// across sessions/devices so seen-tracking actually de-dupes
		// content. Without it, every cookie clear = fresh feed = same
		// videos repeat.
		if ( function_exists( 'la_current_user' ) ) {
			$user = la_current_user();
			if ( $user && ! empty( $user->id ) ) return 'e' . (int) $user->id;
		}
		[ $user_id, $session_id ] = self::identity( $req );
		if ( $user_id )    return 'u' . (int) $user_id;
		if ( $session_id ) return 's' . $session_id;
		return '';
	}

	private static function identity( WP_REST_Request $req ) : array {
		// Wave 64: if a la_user_token cookie identifies a signed-in
		// email-user, return their STABLE 'e{id}' identity as the
		// session_id. All existing tracking (seen-history, prayer log,
		// dhikr count, saves, likes) keys off session_id — so this one
		// override makes the whole app respect persistent identity
		// without touching any other code path.
		// Even after cookies expire, the user signs back in with the
		// same email and gets the same 'e{id}' → all their seen videos
		// stay excluded from the feed.
		if ( function_exists( 'la_current_user' ) ) {
			$user = la_current_user();
			if ( $user ) {
				return [ null, 'e' . (int) $user->id ];
			}
		}
		$user_id = get_current_user_id() ?: null;
		$session_id = sanitize_key( (string) $req->get_header( 'x-la-session' ) );
		if ( ! $session_id && isset( $_COOKIE['wordpress_la_session'] ) ) {
			$session_id = sanitize_key( $_COOKIE['wordpress_la_session'] );
		} elseif ( ! $session_id && isset( $_COOKIE['la_session'] ) ) {
			// Legacy cookie name — keep for one release while clients migrate
			$session_id = sanitize_key( $_COOKIE['la_session'] );
		}
		return [ $user_id, $session_id ?: null ];
	}

	/**
	 * Stop Varnish / Breeze / Cloudflare / Apache mod_cache from caching the
	 * response. The personalised feed endpoints (/feed, /feed/more) must
	 * compute fresh per user — otherwise every anonymous visitor with the
	 * same query string gets the FIRST user's cached batch, defeating
	 * seen-tracking, binge-exclusion, and per-session jitter entirely.
	 *
	 * The chain of headers below covers each layer:
	 *   nocache_headers()           — WordPress canonical "do not cache"
	 *   Cache-Control: no-store     — instructs every intermediary + browser
	 *   X-Breeze-Cache-Bypass: 1    — Cloudways Breeze opt-out
	 *   X-Cache-Bypass: 1           — generic page-cache opt-out
	 *   DONOTCACHEPAGE              — WP Super Cache / W3 Total Cache opt-out
	 *   Vary: Cookie, X-LA-Session  — even if a cache decides to store,
	 *                                  it'll segment by identity
	 */
	private static function bypass_cdn_cache() : void {
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Breeze-Cache-Bypass: 1' );
		header( 'X-Cache-Bypass: 1' );
		header( 'Vary: Cookie, X-LA-Session', false );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
	}
}
