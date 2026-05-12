<?php
/**
 * Mosque queries.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Mosques {

	public static function get_by_slug( string $slug ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['mosques']} WHERE slug = %s LIMIT 1",
			$slug
		) );
	}

	public static function get_by_id( int $id ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['mosques']} WHERE id = %d LIMIT 1",
			$id
		) );
	}

	public static function nearest( float $lat, float $lng, int $limit = 5 ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT *,
				( 6371 * acos(
					cos( radians(%f) ) * cos( radians( latitude ) ) *
					cos( radians( longitude ) - radians(%f) ) +
					sin( radians(%f) ) * sin( radians( latitude ) )
				) ) AS distance_km
			FROM {$t['mosques']}
			WHERE latitude IS NOT NULL AND longitude IS NOT NULL
			ORDER BY distance_km ASC
			LIMIT %d",
			$lat, $lng, $lat, $limit
		) );
	}

	public static function all( int $limit = 100 ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t['mosques']} ORDER BY name ASC LIMIT %d",
			$limit
		) );
	}

	public static function default_mosque() {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_row( "SELECT * FROM {$t['mosques']} ORDER BY id ASC LIMIT 1" );
	}
}
