<?php
/**
 * Dhikr + affirmation content.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Content {

	public static function today_dhikr() : array {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_results(
			"SELECT * FROM {$t['content']}
			 WHERE type = 'dhikr' AND ( mosque_id IS NULL )
			 ORDER BY display_order ASC LIMIT 5"
		);
	}

	public static function affirmations() : array {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_results(
			"SELECT * FROM {$t['content']}
			 WHERE type = 'affirmation' AND ( mosque_id IS NULL )
			 ORDER BY display_order ASC"
		);
	}

	public static function mosque_has_content( int $mosque_id ) : bool {
		global $wpdb;
		$t = LA_DB::tables();
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t['content']} WHERE mosque_id = %d",
			$mosque_id
		) );
		return $count > 0;
	}
}
