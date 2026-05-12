<?php
/**
 * Scholar accounts — verified (partnered) + curated (attributed reposts).
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Scholars {

	public static function get_by_id( int $id ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['scholars']} WHERE id = %d LIMIT 1",
			$id
		) );
	}

	public static function get_by_username( string $username ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['scholars']} WHERE username = %s LIMIT 1",
			$username
		) );
	}

	public static function all() : array {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_results( "SELECT * FROM {$t['scholars']} ORDER BY display_name ASC" );
	}

	public static function label( $scholar ) : string {
		if ( ! $scholar ) return '';
		return $scholar->account_type === 'verified' ? 'Verified ✓' : 'Curated 📚';
	}

	public static function attribution( $scholar ) : string {
		if ( ! $scholar ) return '';
		if ( $scholar->account_type === 'verified' ) {
			return 'Posted with permission';
		}
		$src = $scholar->source_url ?? '';
		return sprintf( 'Curated by Love Allah · original from %s', esc_html( $scholar->display_name ) );
	}
}
