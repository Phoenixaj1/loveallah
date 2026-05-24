<?php
/**
 * Public opcache reset trigger — standalone entry point.
 *
 * BACKGROUND:
 *   PHP opcache caches compiled bytecode. When a deploy updates a .php
 *   file but opcache has the old version in memory, the OLD code runs
 *   until either PHP-FPM restarts or opcache_reset() is called.
 *
 *   Cloudways production has `opcache.validate_timestamps=0` (performance
 *   default) so mtime changes don't auto-invalidate cached entries. The
 *   auto-deploy pulls new files but doesn't always reset opcache.
 *
 * WHY A STANDALONE FILE:
 *   class-la-api.php is itself opcached. Adding a new REST route there
 *   wouldn't take effect — the cached version of class-la-api.php still
 *   has the OLD list of routes. A brand-new file (this one) has no
 *   opcache entry → PHP reads it fresh from disk on first request →
 *   the opcache_reset() inside executes → all subsequent requests get
 *   fresh bytecode from disk.
 *
 * USAGE:
 *   https://loveallah.app/wp-content/plugins/loveallah/cache-bust.php?token=la-bust-2026
 *
 *   Token check is cosmetic friction, not real security — the cost of
 *   abuse is "opcache gets reset again" which is harmless. The token
 *   keeps random scanners from triggering it incidentally.
 *
 * @package LoveAllah
 */

$expected_token = 'la-bust-2026';
$got_token = $_GET['token'] ?? '';
if ( $got_token !== $expected_token ) {
	http_response_code( 403 );
	header( 'Content-Type: text/plain' );
	echo "Missing or invalid token.\n";
	echo "Usage: ?token=la-bust-2026\n";
	exit;
}

// Reset opcache before bootstrapping WordPress — that way the WP load
// path itself gets fresh bytecode from disk.
$opcache_result = function_exists( 'opcache_reset' ) ? @opcache_reset() : null;

// Now bootstrap WordPress so we can also clear its caches and force-run
// the DB migration in case Wave 48's opcache_reset call inside
// maybe_upgrade hasn't fired yet (because the old maybe_upgrade is
// still in cache).
$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
if ( file_exists( $wp_load ) ) {
	require_once $wp_load;
}

$db_version_before = function_exists( 'get_option' ) ? get_option( 'la_db_version', 0 ) : 'unknown';

// Force migration to re-run by rewinding the stored version. Then call
// maybe_upgrade explicitly — which now (with fresh opcache) executes
// the latest seed_scholars + purge_unsafe_scholars + opcache_reset code.
if ( class_exists( 'LA_DB' ) ) {
	update_option( 'la_db_version', 0 );
	LA_DB::maybe_upgrade();
	// Reset opcache AGAIN after migration in case it loaded more stale code
	if ( function_exists( 'opcache_reset' ) ) @opcache_reset();
}

if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();

$db_version_after = function_exists( 'get_option' ) ? get_option( 'la_db_version', 0 ) : 'unknown';

header( 'Content-Type: text/plain' );
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
echo "Cache bust complete\n";
echo "opcache_reset(): " . var_export( $opcache_result, true ) . "\n";
echo "la_db_version before: {$db_version_before}\n";
echo "la_db_version after:  {$db_version_after}\n";
echo "Time: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
echo "\nReload the dhikr witness page now — fresh bytecode should be live.\n";
