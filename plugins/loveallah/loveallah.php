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

define( 'LA_VERSION',  '0.70.0' );
define( 'LA_DB_VERSION', 38 );
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
require_once LA_DIR . 'inc/class-la-curation.php';
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
	// Hourly sync — round-robin 8 scholars per tick via LA_YouTube::cron_tick().
	// Cheap & scalable: scales with channel count, not with frequency.
	if ( ! wp_next_scheduled( 'la_youtube_sync' ) ) {
		wp_schedule_event( time() + 60, 'la_one_hour', 'la_youtube_sync' );
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

// ── Clip permalinks (Wave 36) ─────────────────────────────────────
// /clip/{id}/ — viral share URL. Routes to the homepage with the
// la_clip query var set, which:
//   1) Tells la_render_feed_main() to prepend that exact post as the
//      first card (so the recipient lands directly on the shared clip).
//   2) Triggers OG meta tag emission in <head> so WhatsApp/Telegram/X
//      show a rich preview card (thumbnail + scholar + caption) when
//      the link is pasted into a chat.
add_action( 'init', function() {
	add_rewrite_rule( '^clip/([0-9]+)/?$', 'index.php?la_clip=$matches[1]', 'top' );

	// Flush rewrites once after the rule is added (cheap idempotent check).
	// Wave 86: bumped the option key so the new /privacy + /terms routes in
	// LA_PWA also get registered on the next page load following deploy.
	if ( get_option( 'la_rewrite_flush_v2' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'la_rewrite_flush_v2', '1' );
	}
}, 6 );
add_filter( 'query_vars', function( $vars ) {
	$vars[] = 'la_clip';
	return $vars;
} );
// When /clip/{id}/ is hit, force the homepage to render (otherwise WP
// thinks there's no matching post and shows a 404).
add_action( 'pre_get_posts', function( $q ) {
	if ( ! $q->is_main_query() ) return;
	$clip = (int) $q->get( 'la_clip' );
	if ( ! $clip ) return;
	$q->is_home     = true;
	$q->is_404      = false;
	$q->is_archive  = false;
	$q->is_singular = false;
	$q->is_page     = false;
} );
// OG / Twitter meta for shared clip previews. Keep titles short so they
// don't get truncated in WhatsApp's preview rendering.
add_action( 'wp_head', function() {
	$clip = (int) get_query_var( 'la_clip' );
	if ( ! $clip ) return;
	$post = LA_Feed::get_by_id( $clip );
	if ( ! $post ) return;
	$scholar = class_exists( 'LA_Scholars' ) ? LA_Scholars::get_by_id( (int) $post->scholar_id ) : null;
	$url     = home_url( "/clip/{$clip}/" );
	$title   = trim( ( $scholar->display_name ?? 'Love Allah' ) . ( ! empty( $post->title ) ? ' — ' . $post->title : '' ) );
	$desc    = $post->caption ?: 'A reminder from Love Allah · prayer times, daily dhikr, curated Islamic content.';
	$image   = $post->thumbnail_url ?: '';
	echo "\n<!-- Love Allah clip OG (la_clip={$clip}) -->\n";
	echo '<meta property="og:type"        content="video.other">' . "\n";
	echo '<meta property="og:site_name"   content="Love Allah">' . "\n";
	echo '<meta property="og:url"         content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:title"       content="' . esc_attr( mb_substr( $title, 0, 120 ) ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( mb_substr( $desc,  0, 200 ) ) . '">' . "\n";
	if ( $image ) {
		echo '<meta property="og:image"       content="' . esc_url( $image ) . '">' . "\n";
		echo '<meta property="og:image:width"  content="480">' . "\n";
		echo '<meta property="og:image:height" content="360">' . "\n";
	}
	echo '<meta name="twitter:card"  content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( mb_substr( $title, 0, 120 ) ) . '">' . "\n";
	if ( $image ) {
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
	}
}, 1 );

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
// Hourly cron + round-robin (process N scholars per tick by last_synced_at ASC)
// gives us "always-fresh content" without hammering yt-dlp. With ~55 channels and
// 8 per tick, every scholar syncs ~every 7 hours; new uploads surface within an
// hour at the top of the feed thanks to the freshness boost in the algorithm.
add_action( 'la_youtube_sync', [ 'LA_YouTube', 'cron_tick' ] );
add_filter( 'cron_schedules', function( $s ) {
	$s['la_one_hour']  = [ 'interval' => HOUR_IN_SECONDS,     'display' => __( 'Every hour (Love Allah)', 'loveallah' ) ];
	// Kept registered so any legacy stored event still resolves (we unschedule
	// it below, but a stale row in cron options could try to dispatch once).
	$s['la_six_hours'] = [ 'interval' => 6 * HOUR_IN_SECONDS, 'display' => __( 'Every 6 hours (Love Allah, legacy)', 'loveallah' ) ];
	return $s;
} );
add_action( 'plugins_loaded', function() {
	if ( ! get_option( 'la_yt_sync_enabled', 1 ) ) return;

	// One-time migration: if we previously scheduled the 6-hourly variant,
	// unschedule it so we don't end up with two parallel sync events.
	$next = wp_next_scheduled( 'la_youtube_sync' );
	if ( $next ) {
		$schedule = wp_get_schedule( 'la_youtube_sync' );
		if ( $schedule === 'la_six_hours' ) {
			wp_clear_scheduled_hook( 'la_youtube_sync' );
			$next = false;
		}
	}
	if ( ! $next ) {
		wp_schedule_event( time() + 60, 'la_one_hour', 'la_youtube_sync' );
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
