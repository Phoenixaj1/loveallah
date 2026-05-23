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

		register_rest_route( self::NS, '/feed/(?P<id>\d+)/(?P<action>like|save|share|view)', [
			'methods'  => 'POST',
			'callback' => [ __CLASS__, 'feed_interaction' ],
			'permission_callback' => [ __CLASS__, 'check_nonce' ],
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

		register_rest_route( self::NS, '/mosques/nearest', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'nearest_mosques' ],
			'permission_callback' => '__return_true',
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

		$posts = LA_Feed::recent( 20, $mosque_id );
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
		[ $user_id, $session_id ] = self::identity( $req );
		$page  = max( 0, (int) $req->get_param( 'page' ) );
		$limit = min( 20, max( 5, (int) ( $req->get_param( 'limit' ) ?: 10 ) ) );
		$type  = sanitize_key( (string) $req->get_param( 'type' ) ) ?: null;

		$cards = LA_Algorithm::for_user( $user_id, $session_id, $limit, $page, $type );
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
		$ok = LA_Feed::record_interaction( $id, $action, $user_id, $session_id );
		return [ 'ok' => (bool) $ok ];
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

	/** Build the string identity used for prayer_log / tasbeeh_log rows. */
	private static function identity_str( WP_REST_Request $req ) : string {
		[ $user_id, $session_id ] = self::identity( $req );
		if ( $user_id )    return 'u' . (int) $user_id;
		if ( $session_id ) return 's' . $session_id;
		return '';
	}

	private static function identity( WP_REST_Request $req ) : array {
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
}
