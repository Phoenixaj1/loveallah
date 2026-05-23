<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Resolve the visitor's location for the prayer-times strip.
// Priority: explicit cookie (set after Geolocation accept) → home mosque
// (Birmingham default) → London fallback.
$la_geo_lat  = 52.4567; $la_geo_lng = -1.8606; $la_geo_label = 'Birmingham';
if ( ! empty( $_COOKIE['wordpress_la_geo'] ) ) {
	$cookie = sanitize_text_field( wp_unslash( $_COOKIE['wordpress_la_geo'] ) );
	$parts  = explode( '|', $cookie );
	if ( count( $parts ) >= 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) ) {
		$la_geo_lat = (float) $parts[0];
		$la_geo_lng = (float) $parts[1];
		if ( ! empty( $parts[2] ) ) $la_geo_label = sanitize_text_field( $parts[2] );
	}
}

// Resolve a sensible IANA timezone for SSR: WP setting if it's a real
// region name (not a UTC offset like '+00:00'), else Europe/London.
$la_tz_raw = wp_timezone_string();
$la_tz = ( $la_tz_raw && ! preg_match( '/^[+\-]?\d{1,2}:?\d{0,2}$/', $la_tz_raw ) && $la_tz_raw !== 'UTC' )
	? $la_tz_raw
	: 'Europe/London';  // safe default for our home market; JS re-fetches with browser tz on hydration
$la_timings = class_exists( 'LA_Prayer_Times' )
	? LA_Prayer_Times::for_lat_lng( $la_geo_lat, $la_geo_lng, null, $la_tz )
	: [];
$la_next   = $la_timings ? LA_Prayer_Times::next_prayer( $la_timings ) : [];

// Which prayers has this visitor already marked prayed today?
$la_prayed = [];
if ( class_exists( 'LA_DB' ) && function_exists( 'la_get_or_set_session_id' ) ) {
	global $wpdb;
	$t = LA_DB::tables();
	$_uid = get_current_user_id();
	$_sid = la_get_or_set_session_id();
	$identity = $_uid ? ( 'u' . (int) $_uid ) : ( $_sid ? ( 's' . $_sid ) : '' );
	if ( $identity && ! empty( $t['prayer_log'] ) ) {
		$la_prayed = $wpdb->get_col( $wpdb->prepare(
			"SELECT prayer FROM {$t['prayer_log']} WHERE identity = %s AND date = %s",
			$identity, gmdate( 'Y-m-d' )
		) );
	}
}
$la_prayed_count = is_array( $la_prayed ) ? count( array_intersect( $la_prayed, [ 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] ) ) : 0;
$la_streak = function_exists( 'la_unlock_state_for_view' ) ? la_unlock_state_for_view()['streak'] : 0;

// Hijri date — uses the modern Islamic calendar bundled in PHP's IntlDateFormatter.
// IntlDateFormatter only accepts IANA timezone names, not UTC offsets like '+00:00'.
$la_hijri_label = '';
if ( class_exists( 'IntlDateFormatter' ) ) {
	$la_hijri_tz = $la_tz;
	if ( ! $la_hijri_tz || preg_match( '/^[+\-]?\d{2}:?\d{2}$/', $la_hijri_tz ) ) {
		$la_hijri_tz = 'UTC';
	}
	try {
		$fmt = new IntlDateFormatter(
			'en@calendar=islamic-umalqura',
			IntlDateFormatter::LONG, IntlDateFormatter::NONE,
			$la_hijri_tz, IntlDateFormatter::TRADITIONAL, 'd MMM y'
		);
		$la_hijri_label = $fmt->format( new DateTime( 'now', new DateTimeZone( $la_hijri_tz ) ) );
		$la_hijri_label = str_replace( ' AH', '', $la_hijri_label );
	} catch ( Throwable $e ) {
		$la_hijri_label = ''; // silently skip on error — page must always render
	}
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#ED1C6C">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700;800;900&family=Noto+Naskh+Arabic:wght@400;700&display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<header class="la-header"
		data-geo-lat="<?php echo esc_attr( $la_geo_lat ); ?>"
		data-geo-lng="<?php echo esc_attr( $la_geo_lng ); ?>"
		<?php if ( ! empty( $la_next['time'] ) ) : ?>
			data-next-time="<?php echo esc_attr( $la_next['time'] ); ?>"
			data-next-name="<?php echo esc_attr( $la_next['name'] ); ?>"
		<?php endif; ?>>

		<!-- Compact single-row header: brand + 5 prayer cells + actions.
		     Mobile-first: collapses to just the NEXT prayer cell + countdown below 600px. -->
		<div class="la-header-row la-header-row--main">
			<a class="la-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Love Allah home">
				<svg class="la-brand-mark" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M12 21s-7-4.5-9.5-9C.5 8 3 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 4 0 6.5 4 4.5 8C19 16.5 12 21 12 21z" fill="currentColor"/>
					<path d="M14.5 9.5a3.5 3.5 0 1 1-3 5.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round" fill="none"/>
				</svg>
				<span class="la-brand-text">
					<span class="la-brand-word">Love</span><span class="la-brand-allah">Allah</span>
				</span>
			</a>

			<?php if ( $la_timings ) : ?>
				<div class="la-prayer-bar-row" data-prayer-bar aria-label="Your prayer times — tap to mark prayed">
					<?php foreach ( [ 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] as $name ) :
						if ( empty( $la_timings[ $name ] ) ) continue;
						$is_next   = ( ! empty( $la_next['name'] ) && $la_next['name'] === $name );
						$is_prayed = in_array( $name, $la_prayed, true );
					?>
						<button type="button"
							class="la-prayer-cell <?php echo $is_next ? 'is-next' : ''; ?> <?php echo $is_prayed ? 'is-prayed' : ''; ?>"
							data-prayer-name="<?php echo esc_attr( $name ); ?>"
							data-action="toggle-prayed"
							aria-pressed="<?php echo $is_prayed ? 'true' : 'false'; ?>"
							title="<?php echo esc_attr( $name . ' ' . $la_timings[ $name ] . ' — tap to ' . ( $is_prayed ? 'unmark' : 'mark prayed' ) ); ?>">
							<span class="la-prayer-cell-name"><?php echo esc_html( $name ); ?></span>
							<span class="la-prayer-cell-time"><?php echo esc_html( $la_timings[ $name ] ); ?></span>
							<?php if ( $is_next && ! $is_prayed ) : ?>
								<span class="la-prayer-cell-eta" data-countdown>—</span>
							<?php endif; ?>
							<svg class="la-prayer-cell-check" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="la-header-actions">
				<?php if ( $la_hijri_label ) : ?>
					<div class="la-hijri-pill" title="Today, Hijri" aria-label="Hijri date <?php echo esc_attr( $la_hijri_label ); ?>">
						<span class="la-hijri-text"><?php echo esc_html( $la_hijri_label ); ?></span>
					</div>
				<?php endif; ?>
				<?php if ( $la_streak >= 1 ) : ?>
					<div class="la-streak-pill" title="<?php echo esc_attr( $la_streak ); ?>-day remembrance streak">
						<span class="la-streak-icon">🤲</span>
						<span class="la-streak-num"><?php echo (int) $la_streak; ?></span>
					</div>
				<?php endif; ?>
				<a class="la-icon-btn" href="<?php echo esc_url( home_url( '/saved/' ) ); ?>" aria-label="Saved videos — your library" title="Your saved videos">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
				</a>
				<button class="la-icon-btn la-geo-btn" type="button" aria-label="Use my location for prayer times" data-action="use-geo" title="<?php echo esc_attr( $la_geo_label ); ?> · tap to use my location">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-12a7 7 0 0 1 14 0c0 5-7 12-7 12z"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
				</button>
				<button class="la-icon-btn" type="button" aria-label="Upcoming masjid events" data-action="events">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg>
				</button>
			</div>
		</div>
	</header>
