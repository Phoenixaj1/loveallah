<?php
/**
 * Masjid events — what's happening at the user's masjid this week.
 *
 * Events are seeded for the demo masjid; in production each masjid admin
 * will manage their own list via the admin dashboard.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Events {

	public static function upcoming( int $mosque_id, int $limit = 10 ) : array {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t['events']}
			 WHERE mosque_id = %d
			   AND starts_at >= DATE_SUB( NOW(), INTERVAL 2 HOUR )
			 ORDER BY starts_at ASC
			 LIMIT %d",
			$mosque_id, $limit
		) );
	}

	/** Seed example events for a mosque. Idempotent (skips if any exist). */
	public static function seed_for_mosque( int $mosque_id ) : int {
		global $wpdb;
		$t = LA_DB::tables();
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t['events']} WHERE mosque_id = %d",
			$mosque_id
		) );
		if ( $existing > 0 ) return 0;

		$now = current_time( 'U' );
		// Helper: next instance of day-of-week + time
		$next_dow = function( $dow, $hour, $min ) use ( $now ) {
			// 0=Sun..6=Sat
			$today_dow = (int) gmdate( 'w', $now );
			$days_ahead = ( $dow - $today_dow + 7 ) % 7;
			if ( $days_ahead === 0 ) {
				// Same weekday — check if time has passed today
				$today_h = (int) gmdate( 'H', $now );
				$today_m = (int) gmdate( 'i', $now );
				if ( $today_h * 60 + $today_m > $hour * 60 + $min ) $days_ahead = 7;
			}
			$ts = strtotime( gmdate( 'Y-m-d', $now + $days_ahead * 86400 ) . sprintf( ' %02d:%02d:00', $hour, $min ) );
			return gmdate( 'Y-m-d H:i:s', $ts );
		};

		$events = [
			[
				'title'       => 'Jumu\'ah Khutbah',
				'description' => 'Shaykh Yousaf delivers this week\'s khutbah, followed by Jumu\'ah salah. Doors open 12:45.',
				'starts_at'   => $next_dow( 5, 13, 15 ), // Friday 13:15
				'location'    => 'Main hall',
				'tag'         => 'jumuah',
			],
			[
				'title'       => 'Tafsir Halaqah — Surah Ya-Sin',
				'description' => 'Weekly tafsir class. Bring a Qur\'an. Sisters welcome (upstairs).',
				'starts_at'   => $next_dow( 2, 19, 30 ), // Tuesday 19:30
				'location'    => 'Library room',
				'tag'         => 'class',
			],
			[
				'title'       => 'Qur\'an Class for Kids',
				'description' => 'Ages 6-12. New term starting — limited spaces. Register with Sister Aisha.',
				'starts_at'   => $next_dow( 6, 10, 30 ), // Saturday 10:30
				'location'    => 'Madrasah block',
				'cta_label'   => 'Register',
				'cta_url'     => '#register',
				'tag'         => 'class',
			],
			[
				'title'       => 'Community Iftar',
				'description' => 'Open iftar this weekend. Please bring a dish to share. All welcome — neighbours encouraged.',
				'starts_at'   => $next_dow( 0, 20, 30 ), // Sunday 20:30
				'location'    => 'Community hall',
				'tag'         => 'community',
			],
		];

		$inserted = 0;
		foreach ( $events as $e ) {
			$wpdb->insert( $t['events'], array_merge( $e, [ 'mosque_id' => $mosque_id ] ) );
			$inserted++;
		}
		return $inserted;
	}
}
