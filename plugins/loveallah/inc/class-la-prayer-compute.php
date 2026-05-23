<?php
/**
 * Local prayer-times computation — no external API needed.
 *
 * Port of Hamid Zarrabi-Zadeh's PrayTimes algorithm (MIT licensed,
 * praytimes.org). Computes Fajr/Sunrise/Dhuhr/Asr/Maghrib/Isha from
 * latitude/longitude/date using standard astronomical formulas.
 *
 * Cloudways firewalls outbound to api.aladhan.com so we cannot rely
 * on the network — this gives us self-contained, deterministic times
 * for any location worldwide.
 *
 * Method defaults to ISNA (Fajr 15°, Isha 15°) which matches most
 * UK/US/Canada mosques. Asr uses Shafi'i (shadow = 1× object) by default.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Prayer_Compute {

	/** Calculation method angles. */
	const METHODS = [
		'ISNA'         => [ 'fajr' => 15.0, 'isha' => 15.0 ],
		'MWL'          => [ 'fajr' => 18.0, 'isha' => 17.0 ],
		'EGYPT'        => [ 'fajr' => 19.5, 'isha' => 17.5 ],
		'MAKKAH'       => [ 'fajr' => 18.5, 'isha' => '90 min' ],
		'KARACHI'      => [ 'fajr' => 18.0, 'isha' => 18.0 ],
		'TEHRAN'       => [ 'fajr' => 17.7, 'isha' => 14.0 ],
	];

	/**
	 * Compute today's prayer times for a location.
	 *
	 * @param float  $lat       Latitude in degrees.
	 * @param float  $lng       Longitude in degrees.
	 * @param string $timezone  IANA timezone (e.g. 'Europe/London'). Defaults to WP setting.
	 * @param string $method    Calculation method key (see METHODS).
	 * @param int    $asr_juristic 1 = Shafi'i (default), 2 = Hanafi.
	 * @return array Map of prayer name => 'HH:MM' string.
	 */
	public static function times_for( float $lat, float $lng, string $timezone = '', string $method = 'ISNA', int $asr_juristic = 1 ) : array {
		$tz = $timezone ?: ( get_option( 'timezone_string' ) ?: 'UTC' );
		try {
			$tz_obj = new DateTimeZone( $tz );
		} catch ( Exception $e ) {
			$tz_obj = new DateTimeZone( 'UTC' );
		}

		$now = new DateTime( 'now', $tz_obj );
		$year  = (int) $now->format( 'Y' );
		$month = (int) $now->format( 'n' );
		$day   = (int) $now->format( 'j' );

		$julian = self::julian_date( $year, $month, $day ) - $lng / ( 15.0 * 24.0 );

		$method_cfg = self::METHODS[ $method ] ?? self::METHODS['ISNA'];
		$fajr_angle = (float) $method_cfg['fajr'];
		$isha_angle = $method_cfg['isha'];

		// Tz offset in hours
		$tz_offset = $tz_obj->getOffset( $now ) / 3600.0;

		// Reference noon (Dhuhr) — compute then adjust by EoT
		$dhuhr_t = self::compute_mid_day( 12.0 / 24.0, $julian );

		// Fajr: sun is fajr_angle below horizon before sunrise
		$fajr_t   = self::compute_time( 180.0 - $fajr_angle, 5.0 / 24.0, $julian, $lat, $lng, $tz_offset );
		// Sunrise: sun on horizon (-0.833° for refraction)
		$sunrise_t = self::compute_time( 180.0 - 0.833, 6.0 / 24.0, $julian, $lat, $lng, $tz_offset );
		// Asr: shadow ratio
		$asr_t    = self::compute_asr( $asr_juristic, 13.0 / 24.0, $julian, $lat, $lng, $tz_offset );
		// Maghrib: sunset
		$maghrib_t = self::compute_time( 0.833, 18.0 / 24.0, $julian, $lat, $lng, $tz_offset );
		// Isha: sun is isha_angle below horizon after sunset
		if ( is_string( $isha_angle ) && strpos( $isha_angle, 'min' ) !== false ) {
			$mins = (int) trim( str_replace( 'min', '', $isha_angle ) );
			$isha_t = $maghrib_t + ( $mins / 60.0 );
		} else {
			$isha_t = self::compute_time( (float) $isha_angle, 18.0 / 24.0, $julian, $lat, $lng, $tz_offset );
		}

		// Convert local-day fractions to HH:MM
		return [
			'Fajr'    => self::frac_to_hhmm( $fajr_t + $tz_offset - $lng / 15.0 ),
			'Sunrise' => self::frac_to_hhmm( $sunrise_t + $tz_offset - $lng / 15.0 ),
			'Dhuhr'   => self::frac_to_hhmm( $dhuhr_t + $tz_offset - $lng / 15.0 ),
			'Asr'     => self::frac_to_hhmm( $asr_t + $tz_offset - $lng / 15.0 ),
			'Maghrib' => self::frac_to_hhmm( $maghrib_t + $tz_offset - $lng / 15.0 ),
			'Isha'    => self::frac_to_hhmm( $isha_t + $tz_offset - $lng / 15.0 ),
		];
	}

	// ────────────────────────────────────────────────────────────────────
	// Astronomical helpers (ported from praytimes.org JS reference impl)
	// ────────────────────────────────────────────────────────────────────

	private static function julian_date( int $year, int $month, int $day ) : float {
		if ( $month <= 2 ) { $year -= 1; $month += 12; }
		$a = floor( $year / 100 );
		$b = 2 - $a + floor( $a / 4 );
		return floor( 365.25 * ( $year + 4716 ) ) + floor( 30.6001 * ( $month + 1 ) ) + $day + $b - 1524.5;
	}

	/** Sun declination and equation of time at time t (fraction of day). */
	private static function sun_position( float $jd ) : array {
		$d = $jd - 2451545.0;
		$g = self::fix_angle( 357.529 + 0.98560028 * $d );
		$q = self::fix_angle( 280.459 + 0.98564736 * $d );
		$l = self::fix_angle( $q + 1.915 * self::sin_deg( $g ) + 0.020 * self::sin_deg( 2 * $g ) );
		$e = 23.439 - 0.00000036 * $d;
		$ra = self::arctan2_deg( self::cos_deg( $e ) * self::sin_deg( $l ), self::cos_deg( $l ) ) / 15.0;
		$decl = self::arcsin_deg( self::sin_deg( $e ) * self::sin_deg( $l ) );
		$eq_t = $q / 15.0 - self::fix_hour( $ra );
		return [ $decl, $eq_t ];
	}

	private static function compute_mid_day( float $t, float $jd ) : float {
		$pos = self::sun_position( $jd + $t );
		return self::fix_hour( 12.0 - $pos[1] );
	}

	private static function compute_time( float $angle, float $t, float $jd, float $lat, float $lng, float $tz ) : float {
		$decl = self::sun_position( $jd + $t )[0];
		$noon = self::compute_mid_day( $t, $jd );
		$cos_arg = ( -self::sin_deg( $angle ) - self::sin_deg( $decl ) * self::sin_deg( $lat ) ) / ( self::cos_deg( $decl ) * self::cos_deg( $lat ) );
		// Clamp for high latitudes
		if ( $cos_arg > 1 ) $cos_arg = 1;
		if ( $cos_arg < -1 ) $cos_arg = -1;
		$arc = self::arccos_deg( $cos_arg ) / 15.0;
		return $noon + ( $angle > 90 ? -$arc : $arc );
	}

	private static function compute_asr( int $juristic, float $t, float $jd, float $lat, float $lng, float $tz ) : float {
		$decl = self::sun_position( $jd + $t )[0];
		$factor = $juristic === 2 ? 2.0 : 1.0;
		$angle = -self::arccot_deg( $factor + self::tan_deg( abs( $lat - $decl ) ) );
		return self::compute_time( $angle, $t, $jd, $lat, $lng, $tz );
	}

	private static function frac_to_hhmm( float $hours ) : string {
		$hours = self::fix_hour( $hours );
		$h = floor( $hours );
		$m = round( ( $hours - $h ) * 60.0 );
		if ( $m >= 60 ) { $h += 1; $m = 0; }
		if ( $h >= 24 ) { $h -= 24; }
		return sprintf( '%02d:%02d', $h, $m );
	}

	private static function fix_angle( float $a ) : float {
		$a = $a - 360.0 * floor( $a / 360.0 );
		return $a < 0 ? $a + 360.0 : $a;
	}

	private static function fix_hour( float $h ) : float {
		$h = $h - 24.0 * floor( $h / 24.0 );
		return $h < 0 ? $h + 24.0 : $h;
	}

	private static function deg2rad( float $d ) : float { return $d * M_PI / 180.0; }
	private static function rad2deg( float $r ) : float { return $r * 180.0 / M_PI; }
	private static function sin_deg( float $d ) : float { return sin( self::deg2rad( $d ) ); }
	private static function cos_deg( float $d ) : float { return cos( self::deg2rad( $d ) ); }
	private static function tan_deg( float $d ) : float { return tan( self::deg2rad( $d ) ); }
	private static function arcsin_deg( float $x ) : float { return self::rad2deg( asin( $x ) ); }
	private static function arccos_deg( float $x ) : float { return self::rad2deg( acos( $x ) ); }
	private static function arctan2_deg( float $y, float $x ) : float { return self::rad2deg( atan2( $y, $x ) ); }
	private static function arccot_deg( float $x ) : float { return self::rad2deg( atan( 1.0 / $x ) ); }
}
