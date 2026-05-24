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
		// If the masjid has a compute-config (e.g. Hanafi Asr), feed it
		// to LA_Prayer_Compute. Otherwise the default Shafi'i path applies.
		$cfg = ! empty( $mosque->prayer_compute_config_json )
			? json_decode( $mosque->prayer_compute_config_json, true )
			: [];
		$method        = isset( $cfg['method'] )        ? (string) $cfg['method']        : 'ISNA';
		$asr_juristic  = isset( $cfg['asr_juristic'] )  ? (int)    $cfg['asr_juristic']  : 1;
		return self::for_lat_lng(
			(float) $mosque->latitude,
			(float) $mosque->longitude,
			(int)   $mosque->id,
			'',
			$method,
			$asr_juristic
		);
	}

	/**
	 * Apply the masjid's jamaat-offset JSON to a set of begin times.
	 *
	 * Each prayer entry in $offsets is either:
	 *   { "type": "offset", "minutes": N }  → begin + N minutes
	 *   { "type": "fixed",  "time":   "HH:MM" } → that exact clock time
	 *
	 * Returns an array shaped like the begin times (Fajr/Dhuhr/Asr/Maghrib/Isha
	 * → "HH:MM") so the masjid page can render Begin vs Jamaat side by side.
	 *
	 * Defensive: silently ignores malformed entries and falls back to the begin
	 * time so the row never disappears.
	 */
	public static function apply_jamaat_offsets( array $begin_times, $mosque ) : array {
		if ( empty( $mosque->jamaat_offsets_json ) ) return [];
		$offsets = json_decode( $mosque->jamaat_offsets_json, true );
		if ( ! is_array( $offsets ) ) return [];

		$out = [];
		foreach ( [ 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] as $name ) {
			$begin = $begin_times[ $name ] ?? '';
			if ( ! $begin ) continue;
			$cfg = $offsets[ $name ] ?? null;
			if ( ! is_array( $cfg ) ) { $out[ $name ] = $begin; continue; }

			$type = $cfg['type'] ?? '';
			if ( $type === 'fixed' && ! empty( $cfg['time'] ) ) {
				$out[ $name ] = substr( (string) $cfg['time'], 0, 5 );
			} elseif ( $type === 'offset' && isset( $cfg['minutes'] ) ) {
				$out[ $name ] = self::add_minutes( $begin, (int) $cfg['minutes'] );
			} else {
				$out[ $name ] = $begin;
			}
		}
		return $out;
	}

	private static function add_minutes( string $hhmm, int $delta ) : string {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', $hhmm, $m ) ) return $hhmm;
		$total = ( ( (int) $m[1] ) * 60 ) + ( (int) $m[2] ) + $delta;
		// Wrap into 0..1439 in case offsets push us past midnight.
		$total = ( ( $total % 1440 ) + 1440 ) % 1440;
		return sprintf( '%02d:%02d', intdiv( $total, 60 ), $total % 60 );
	}

	/**
	 * Compute prayer times for any lat/lng — used by the global geo strip
	 * (visitor's own location) and for_mosque() (a specific masjid's GPS).
	 *
	 * $method = 'ISNA' (Fajr/Isha 15°), 'MWL' (Fajr 18°/Isha 17°), etc.
	 * $asr_juristic = 1 (Shafi'i, default) or 2 (Hanafi).
	 */
	public static function for_lat_lng( float $lat, float $lng, ?int $mosque_id = null, string $timezone = '', string $method = 'ISNA', int $asr_juristic = 1 ) : array {
		$today = gmdate( 'd-m-Y' );
		$cache_key = 'la_prayer_' . md5( $lat . '|' . $lng . '|' . $today . '|' . $timezone . '|' . $method . '|' . $asr_juristic );
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// PRIMARY: local astronomical computation (no network, always works).
		if ( class_exists( 'LA_Prayer_Compute' ) ) {
			$timings = LA_Prayer_Compute::times_for( $lat, $lng, $timezone, $method, $asr_juristic );
			if ( ! empty( $timings['Dhuhr'] ) ) {
				set_transient( $cache_key, $timings, 12 * HOUR_IN_SECONDS );
				return $timings;
			}
		}

		// LEGACY: try Aladhan API as a sanity-check / mosque-imam-corrected
		// fallback (firewalled on Cloudways, so usually skipped).
		$fail_key = $cache_key . '_fail';
		if ( get_transient( $fail_key ) ) {
			return $mosque_id ? self::db_fallback( $mosque_id ) : [];
		}

		$url = sprintf(
			'https://api.aladhan.com/v1/timings/%s?latitude=%F&longitude=%F&method=2&school=0',
			$today, $lat, $lng
		);
		$response = wp_remote_get( $url, [ 'timeout' => 5, 'sslverify' => true ] );

		if ( is_wp_error( $response ) ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return $mosque_id ? self::db_fallback( $mosque_id ) : [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['data']['timings'] ) ) {
			set_transient( $fail_key, 1, HOUR_IN_SECONDS );
			return $mosque_id ? self::db_fallback( $mosque_id ) : [];
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
