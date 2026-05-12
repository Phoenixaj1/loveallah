<?php
/**
 * Prayer times — Aladhan API with transient cache + DB fallback.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Prayer_Times {

	public static function for_mosque( $mosque ) : array {
		if ( ! $mosque || empty( $mosque->latitude ) || empty( $mosque->longitude ) ) {
			return [];
		}

		$lat = (float) $mosque->latitude;
		$lng = (float) $mosque->longitude;
		$today = gmdate( 'd-m-Y' );

		$cache_key = 'la_aladhan_' . md5( $lat . $lng . $today );
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$fail_key = $cache_key . '_fail';
		if ( get_transient( $fail_key ) ) {
			return self::db_fallback( (int) $mosque->id );
		}

		$url = sprintf(
			'https://api.aladhan.com/v1/timings/%s?latitude=%F&longitude=%F&method=2&school=0',
			$today, $lat, $lng
		);
		$response = wp_remote_get( $url, [ 'timeout' => 5, 'sslverify' => true ] );

		if ( is_wp_error( $response ) ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return self::db_fallback( (int) $mosque->id );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['data']['timings'] ) ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return self::db_fallback( (int) $mosque->id );
		}

		$timings = [
			'Fajr'    => self::clean( $body['data']['timings']['Fajr'] ?? '' ),
			'Sunrise' => self::clean( $body['data']['timings']['Sunrise'] ?? '' ),
			'Dhuhr'   => self::clean( $body['data']['timings']['Dhuhr'] ?? '' ),
			'Asr'     => self::clean( $body['data']['timings']['Asr'] ?? '' ),
			'Maghrib' => self::clean( $body['data']['timings']['Maghrib'] ?? '' ),
			'Isha'    => self::clean( $body['data']['timings']['Isha'] ?? '' ),
		];

		set_transient( $cache_key, $timings, 6 * HOUR_IN_SECONDS );
		return $timings;
	}

	private static function clean( string $t ) : string {
		return preg_replace( '/\s*\(.+\)\s*/', '', $t );
	}

	private static function db_fallback( int $mosque_id ) : array {
		global $wpdb;
		$t = LA_DB::tables();
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['prayer_times']} WHERE mosque_id = %d AND date = %s LIMIT 1",
			$mosque_id, gmdate( 'Y-m-d' )
		) );
		if ( ! $row ) return [];
		return [
			'Fajr'    => substr( $row->fajr ?? '', 0, 5 ),
			'Sunrise' => substr( $row->sunrise ?? '', 0, 5 ),
			'Dhuhr'   => substr( $row->dhuhr ?? '', 0, 5 ),
			'Asr'     => substr( $row->asr ?? '', 0, 5 ),
			'Maghrib' => substr( $row->maghrib ?? '', 0, 5 ),
			'Isha'    => substr( $row->isha ?? '', 0, 5 ),
		];
	}

	public static function next_prayer( array $timings ) : array {
		$now = current_time( 'H:i' );
		$order = [ 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ];
		foreach ( $order as $name ) {
			if ( ! empty( $timings[ $name ] ) && $timings[ $name ] > $now ) {
				return [ 'name' => $name, 'time' => $timings[ $name ] ];
			}
		}
		return [ 'name' => 'Fajr', 'time' => $timings['Fajr'] ?? '' ];
	}
}
