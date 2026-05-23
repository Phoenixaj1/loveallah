<?php
/**
 * Plugin Name: Love Allah
 * Description: Sacred ritual app — prayer times + daily affirmation dhikr unlock a curated Islamic feed. Each masjid hosts and brands their own congregation's experience. Ad revenue funds the ummah via YourNiyyah.
 * Version:     0.3.0
 * Author:      Love Allah
 * Author URI:  https://loveallah.app
 * Text Domain: loveallah
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LA_VERSION',  '0.9.9' );
define( 'LA_DB_VERSION', 13 );
define( 'LA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LA_URL', plugin_dir_url( __FILE__ ) );
define( 'LA_FILE', __FILE__ );

// ── Class loader ──
require_once LA_DIR . 'inc/class-la-db.php';
require_once LA_DIR . 'inc/class-la-caps.php';
require_once LA_DIR . 'inc/class-la-mosques.php';
require_once LA_DIR . 'inc/class-la-prayer-compute.php';
require_once LA_DIR . 'inc/class-la-prayer-times.php';
require_once LA_DIR . 'inc/class-la-content.php';
require_once LA_DIR . 'inc/class-la-scholars.php';
require_once LA_DIR . 'inc/class-la-feed.php';
require_once LA_DIR . 'inc/class-la-unlock.php';
require_once LA_DIR . 'inc/class-la-api.php';
require_once LA_DIR . 'inc/class-la-algorithm.php';
require_once LA_DIR . 'inc/class-la-feed-render.php';
require_once LA_DIR . 'inc/class-la-youtube.php';
require_once LA_DIR . 'inc/class-la-events.php';
require_once LA_DIR . 'inc/class-la-pwa.php';
require_once LA_DIR . 'inc/class-la-admin.php';
require_once LA_DIR . 'inc/class-la-cli.php';
require_once LA_DIR . 'inc/ornaments.php';
require_once LA_DIR . 'inc/helpers.php';

// ── Activation / Deactivation lifecycle ──
register_activation_hook( __FILE__, function() {
	LA_DB::install();
	LA_Caps::install();
	LA_PWA::on_activate();
	if ( ! wp_next_scheduled( 'la_youtube_sync' ) ) {
		wp_schedule_event( time() + 60, 'la_six_hours', 'la_youtube_sync' );
	}
} );
register_deactivation_hook( __FILE__, function() {
	wp_clear_scheduled_hook( 'la_youtube_sync' );
} );

// ── i18n ──
add_action( 'init', function() {
	load_plugin_textdomain( 'loveallah', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 1 );

// ── Boot ──
add_action( 'plugins_loaded', function() {
	LA_DB::maybe_upgrade();

	if ( get_option( 'la_seeded' ) !== '1' ) {
		LA_DB::seed();
		update_option( 'la_seeded', '1' );
	}
} );

// Page creation needs $wp_rewrite (init priority 10), so we hook later
add_action( 'init', function() {
	$expected_version = '4';
	if ( get_option( 'la_pages_created' ) === $expected_version ) return;
	$pages = [
		'dhikr'       => 'Dhikr',
		'mindfulness' => 'Mindfulness',
		'connect'     => 'Connect',
		'masjid'      => 'Masjid',
		'saved'       => 'Saved',
		'duas'        => 'Duas',
		'donate'      => 'Donate',
		// 'nasheed' deliberately removed — feed is scholars + qaris only.
		// Existing /nasheed page on production redirects to /feed via
		// theme/page-nasheed.php for back-compat with shared links.
	];
	foreach ( $pages as $slug => $title ) {
		if ( ! get_page_by_path( $slug ) ) {
			wp_insert_post( [
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '',
			] );
		}
	}
	update_option( 'la_pages_created', $expected_version );
	flush_rewrite_rules();
}, 20 );

// ── REST API ──
add_action( 'rest_api_init', [ 'LA_API', 'register_routes' ] );

// ── Admin UI ──
LA_Admin::register();

// ── PWA (manifest + service worker + meta tags) ──
LA_PWA::register();

// ── WP-CLI ──
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	LA_CLI::register();
}

// ── YouTube sync schedule ──
add_action( 'la_youtube_sync', [ 'LA_YouTube', 'cron_tick' ] );
add_filter( 'cron_schedules', function( $s ) {
	$s['la_six_hours'] = [ 'interval' => 6 * HOUR_IN_SECONDS, 'display' => __( 'Every 6 hours (Love Allah)', 'loveallah' ) ];
	return $s;
} );
add_action( 'plugins_loaded', function() {
	if ( get_option( 'la_yt_sync_enabled', 1 ) && ! wp_next_scheduled( 'la_youtube_sync' ) ) {
		wp_schedule_event( time() + 60, 'la_six_hours', 'la_youtube_sync' );
	}
}, 20 );

// ── Frontend asset enqueue (with filemtime versioning) ──
add_action( 'wp_enqueue_scripts', function() {
	$css_path = LA_DIR . 'assets/css/loveallah.css';
	$js_path  = LA_DIR . 'assets/js/loveallah.js';
	$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : LA_VERSION;
	$js_ver   = file_exists( $js_path )  ? filemtime( $js_path )  : LA_VERSION;

	wp_enqueue_style(  'loveallah', LA_URL . 'assets/css/loveallah.css', [], $css_ver );
	wp_enqueue_script( 'loveallah', LA_URL . 'assets/js/loveallah.js',  [], $js_ver, true );

	wp_localize_script( 'loveallah', 'LA', [
		'apiRoot'   => esc_url_raw( rest_url( 'loveallah/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'sessionId' => la_get_or_set_session_id(),
		'pluginUrl' => esc_url_raw( LA_URL ),  // for audio/asset paths
		'i18n'      => [
			'remembered'   => __( 'Remembered',   'loveallah' ),
			'feed_open'    => __( 'The feed is open', 'loveallah' ),
			'today_done'   => __( "Today's remembrance is complete", 'loveallah' ),
			'loading'      => __( 'Loading…', 'loveallah' ),
		],
	] );
} );

// ── Optional: clear caches when content changes ──
add_action( 'la_after_feed_sync', function() {
	wp_cache_delete( 'la_ranked_content', 'loveallah' );
} );

/**
 * Public-facing helpers — DO NOT remove these declarations.
 * Theme templates depend on them.
 */
// la_chosen_mosque() / la_get_or_set_session_id() / la_greeting() / la_unlock_state_for_view()
// All defined in helpers.php
