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

		register_rest_route( self::NS, '/mosques/nearest', [
			'methods'  => 'GET',
			'callback' => [ __CLASS__, 'nearest_mosques' ],
			'permission_callback' => '__return_true',
		] );
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
