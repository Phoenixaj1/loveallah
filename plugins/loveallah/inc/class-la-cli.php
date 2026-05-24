<?php
/**
 * WP-CLI commands for Love Allah.
 *
 * Usage:
 *   wp loveallah sync         — trigger YouTube sync now
 *   wp loveallah stats        — show platform counts
 *   wp loveallah scholar list — list scholars
 *   wp loveallah scholar add  --name="..." --username="..." --source="..."
 *   wp loveallah seed-events <mosque_id>
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) return;

class LA_CLI {

	public static function register() : void {
		WP_CLI::add_command( 'loveallah sync',        [ __CLASS__, 'sync' ] );
		WP_CLI::add_command( 'loveallah stats',       [ __CLASS__, 'stats' ] );
		WP_CLI::add_command( 'loveallah scholar',     [ __CLASS__, 'scholar' ] );
		WP_CLI::add_command( 'loveallah seed-events', [ __CLASS__, 'seed_events' ] );
		WP_CLI::add_command( 'loveallah purge',       [ __CLASS__, 'purge' ] );
	}

	/**
	 * Trigger YouTube sync.
	 *
	 * ## OPTIONS
	 *
	 * [--mode=<mode>]
	 * : 'all' (default) — every scholar in one go.
	 *   'batch' — process one BATCH_PER_TICK batch (~15 scholars).
	 *   'catchup' — repeatedly call sync_next_batch() until either every
	 *               scholar has ≥ CATCH_UP_THRESHOLD videos OR the
	 *               configured max passes is hit. The "backfill the
	 *               catalog" mode for a fresh launch.
	 *
	 * [--passes=<n>]
	 * : (catchup mode only) Max passes through the queue. Default 30.
	 *
	 * [--sleep=<seconds>]
	 * : Pause this long between batches in catchup mode. Default 2.
	 *
	 * ## EXAMPLES
	 *
	 *     wp loveallah sync                       # all scholars, one go
	 *     wp loveallah sync --mode=batch          # one batch (15 scholars)
	 *     wp loveallah sync --mode=catchup        # backfill until full
	 *     wp loveallah sync --mode=catchup --passes=50 --sleep=3
	 */
	public static function sync( $args, $assoc ) : void {
		$mode   = $assoc['mode']   ?? 'all';
		$passes = max( 1, (int) ( $assoc['passes'] ?? 30 ) );
		$sleep  = max( 0, (int) ( $assoc['sleep']  ?? 2 ) );

		if ( $mode === 'batch' ) {
			WP_CLI::log( 'Running one batch (BATCH_PER_TICK scholars)…' );
			$res = LA_YouTube::sync_next_batch();
			WP_CLI::success( sprintf( '%d scholars synced · %d new posts', $res['synced'], $res['inserted'] ) );
			if ( ! empty( $res['errors'] ) ) {
				foreach ( $res['errors'] as $e ) WP_CLI::warning( $e );
			}
			return;
		}

		if ( $mode === 'catchup' ) {
			WP_CLI::log( "Catch-up mode: up to {$passes} passes, {$sleep}s between batches…" );
			$total_synced   = 0;
			$total_inserted = 0;
			for ( $i = 0; $i < $passes; $i++ ) {
				$res = LA_YouTube::sync_next_batch();
				$total_synced   += (int) $res['synced'];
				$total_inserted += (int) $res['inserted'];
				WP_CLI::log( sprintf(
					'  pass %d/%d: %d checked, %d new · running total: %d new posts',
					$i + 1, $passes, $res['synced'], $res['inserted'], $total_inserted
				) );
				// If a pass added zero AND every channel now has the catch-
				// up threshold, we're done.
				if ( $res['inserted'] === 0 && $i > 5 ) {
					global $wpdb;
					$t = LA_DB::tables();
					$undersized = (int) $wpdb->get_var( $wpdb->prepare(
						"SELECT COUNT(*) FROM {$t['scholars']} s
						 LEFT JOIN ( SELECT scholar_id, COUNT(*) AS c FROM {$t['feed_posts']} GROUP BY scholar_id ) p
						   ON p.scholar_id = s.id
						 WHERE COALESCE(p.c, 0) < %d
						   AND s.source_url IS NOT NULL AND s.source_url <> ''",
						LA_YouTube::CATCH_UP_THRESHOLD
					) );
					if ( $undersized === 0 ) {
						WP_CLI::log( '  every channel meets the catch-up threshold — stopping.' );
						break;
					}
				}
				if ( $sleep > 0 && $i < $passes - 1 ) sleep( $sleep );
			}
			WP_CLI::success( sprintf(
				'Catch-up complete: %d total channels checked across passes, %d new posts.',
				$total_synced, $total_inserted
			) );
			return;
		}

		// default: full single-pass sync
		WP_CLI::log( 'Running yt-dlp sync for all scholars…' );
		$res = LA_YouTube::sync_all();
		WP_CLI::success( sprintf( '%d scholars synced · %d new posts', $res['synced'], $res['inserted'] ) );
		if ( ! empty( $res['errors'] ) ) {
			foreach ( $res['errors'] as $e ) WP_CLI::warning( $e );
		}
	}

	/**
	 * Show platform stats.
	 */
	public static function stats( $args, $assoc ) : void {
		global $wpdb;
		$t = LA_DB::tables();
		$rows = [
			[ 'metric' => 'Scholars',           'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['scholars']}" ) ],
			[ 'metric' => 'Mosques',            'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['mosques']}" ) ],
			[ 'metric' => 'Feed posts (total)', 'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']}" ) ],
			[ 'metric' => 'Feed posts (7d)',    'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']} WHERE published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ) ],
			[ 'metric' => 'Upcoming events',    'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['events']} WHERE starts_at >= NOW()" ) ],
			[ 'metric' => 'Subscribers',        'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['subscribers']} WHERE unsubscribed_at IS NULL" ) ],
			[ 'metric' => 'Feed interactions',  'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_interactions']}" ) ],
		];
		WP_CLI\Utils\format_items( 'table', $rows, [ 'metric', 'count' ] );
	}

	/**
	 * Scholar subcommands.
	 *
	 * <subcommand>: list | add | delete
	 *
	 * Options for `add`:
	 *   --name=<display name>
	 *   --username=<slug>
	 *   --source=<youtube channel url>
	 *   [--type=curated|verified]
	 *
	 * Options for `delete`:
	 *   --username=<slug>
	 */
	public static function scholar( $args, $assoc ) : void {
		$sub = $args[0] ?? 'list';
		global $wpdb;
		$t = LA_DB::tables();

		if ( $sub === 'list' ) {
			$rows = $wpdb->get_results( "SELECT id, username, display_name, account_type, youtube_channel_id, last_synced_at FROM {$t['scholars']} ORDER BY display_name ASC", ARRAY_A );
			WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'username', 'display_name', 'account_type', 'youtube_channel_id', 'last_synced_at' ] );
			return;
		}

		if ( $sub === 'add' ) {
			if ( empty( $assoc['name'] ) || empty( $assoc['username'] ) ) {
				WP_CLI::error( '--name and --username are required' );
			}
			$wpdb->insert( $t['scholars'], [
				'display_name' => sanitize_text_field( $assoc['name'] ),
				'username'     => sanitize_title( $assoc['username'] ),
				'source_url'   => esc_url_raw( $assoc['source'] ?? '' ),
				'account_type' => in_array( $assoc['type'] ?? 'curated', [ 'curated', 'verified' ], true ) ? $assoc['type'] : 'curated',
				'bio'          => sanitize_textarea_field( $assoc['bio'] ?? '' ),
			] );
			WP_CLI::success( 'Scholar added · ID ' . $wpdb->insert_id );
			return;
		}

		if ( $sub === 'delete' ) {
			if ( empty( $assoc['username'] ) ) WP_CLI::error( '--username required' );
			$n = $wpdb->delete( $t['scholars'], [ 'username' => sanitize_title( $assoc['username'] ) ] );
			$n ? WP_CLI::success( 'Deleted.' ) : WP_CLI::error( 'Not found.' );
			return;
		}

		WP_CLI::error( 'Unknown subcommand. Use: list | add | delete' );
	}

	/** Seed example events for a mosque */
	public static function seed_events( $args, $assoc ) : void {
		$mosque_id = (int) ( $args[0] ?? 0 );
		if ( ! $mosque_id ) {
			$m = LA_Mosques::default_mosque();
			$mosque_id = $m ? (int) $m->id : 0;
		}
		if ( ! $mosque_id ) WP_CLI::error( 'No mosque ID and no default mosque exists.' );

		$n = LA_Events::seed_for_mosque( $mosque_id );
		WP_CLI::success( "Seeded {$n} events for mosque #{$mosque_id}." );
	}

	/** Danger: wipe feed posts (keeps everything else) */
	public static function purge( $args, $assoc ) : void {
		$what = $args[0] ?? '';
		if ( $what !== 'feed' ) {
			WP_CLI::error( 'Usage: wp loveallah purge feed' );
		}
		WP_CLI::confirm( 'Delete ALL feed posts and interactions?' );
		global $wpdb;
		$t = LA_DB::tables();
		$wpdb->query( "DELETE FROM {$t['feed_posts']}" );
		$wpdb->query( "DELETE FROM {$t['feed_interactions']}" );
		WP_CLI::success( 'Purged.' );
	}
}
