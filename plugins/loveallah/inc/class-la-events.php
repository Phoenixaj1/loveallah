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
				'starts_at'   => $next_dow( 5, 13, 15 ),
				'location'    => 'Main hall',
				'tag'         => 'jumuah',
				'poster_gradient' => 'linear-gradient(160deg, #2C1338 0%, #6B1846 50%, #ED1C6C 100%)',
			],
			[
				'title'       => 'Tafsir Halaqah — Surah Ya-Sin',
				'description' => 'Weekly tafsir class. Bring a Qur\'an. Sisters welcome (upstairs).',
				'starts_at'   => $next_dow( 2, 19, 30 ),
				'location'    => 'Library room',
				'tag'         => 'class',
				'poster_gradient' => 'linear-gradient(160deg, #1a3a5c 0%, #2d6ca2 60%, #4FC3F7 100%)',
			],
			[
				'title'       => 'Qur\'an Class for Kids',
				'description' => 'Ages 6-12. New term starting — limited spaces. Register with Sister Aisha.',
				'starts_at'   => $next_dow( 6, 10, 30 ),
				'location'    => 'Madrasah block',
				'cta_label'   => 'Register',
				'cta_url'     => '#register',
				'tag'         => 'class',
				'poster_gradient' => 'linear-gradient(160deg, #4a3300 0%, #b8860b 60%, #ffd700 100%)',
			],
			[
				'title'       => 'Community Iftar',
				'description' => 'Open iftar this weekend. Please bring a dish to share. All welcome — neighbours encouraged.',
				'starts_at'   => $next_dow( 0, 20, 30 ),
				'location'    => 'Community hall',
				'tag'         => 'community',
				'poster_gradient' => 'linear-gradient(160deg, #1a0033 0%, #4b0082 50%, #ffd700 100%)',
			],
			[
				'title'       => 'Sisters\' Halaqah',
				'description' => 'Monthly sisters-only circle. Topic: "The hereafter and the soul" — Ustadha Rabia leading.',
				'starts_at'   => $next_dow( 6, 14, 0 ),
				'location'    => 'Upstairs hall',
				'tag'         => 'sisters',
				'poster_gradient' => 'linear-gradient(160deg, #5a1845 0%, #c2185b 60%, #ff8a80 100%)',
			],
			[
				'title'       => 'Reverts Meet & Greet',
				'description' => 'For new Muslims and those exploring Islam. Tea, biscuits and Q&A with Imam Yousaf.',
				'starts_at'   => $next_dow( 3, 18, 0 ),
				'location'    => 'Community room',
				'tag'         => 'reverts',
				'poster_gradient' => 'linear-gradient(160deg, #0f5132 0%, #198754 60%, #75d39e 100%)',
			],
			[
				'title'       => 'Janazah Prayer Services',
				'description' => 'Janazah prayers are held after each obligatory prayer when needed. Contact the masjid office to arrange ghusl + burial.',
				'starts_at'   => $next_dow( 1, 12, 0 ),
				'location'    => 'Main hall',
				'tag'         => 'service',
				'poster_gradient' => 'linear-gradient(160deg, #1c1c1c 0%, #424242 60%, #757575 100%)',
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
