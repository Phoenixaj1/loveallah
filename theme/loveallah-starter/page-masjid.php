<?php
/**
 * Masjid page — local masjid hub.
 *
 * Top to bottom on mobile:
 *   1. Hero (masjid name + address)
 *   2. Prayer times — single horizontal row
 *   3. Jumuah block — large card with khutbah time (highlighted Fridays)
 *   4. Events — movie-poster horizontal slider with RSVP / fav buttons
 *   5. Services & announcements
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$la_mosque  = la_chosen_mosque();
// Resolve IANA timezone — WP setting if it's a region name, else Europe/London
// (our home market). UTC offsets like "+00:00" don't survive PHP DateTime so
// we fall back to a sensible default. Matches header.php's logic.
$la_tz_raw  = wp_timezone_string();
$la_tz_msj  = ( $la_tz_raw && ! preg_match( '/^[+\-]?\d{1,2}:?\d{0,2}$/', $la_tz_raw ) && $la_tz_raw !== 'UTC' )
	? $la_tz_raw
	: 'Europe/London';

// Wave 55: use the masjid's compute config (Hanafi Asr for ArRahma).
// If no compute config → defaults to Shafi'i / ISNA.
if ( $la_mosque ) {
	$la_cfg          = ! empty( $la_mosque->prayer_compute_config_json )
		? json_decode( $la_mosque->prayer_compute_config_json, true )
		: [];
	$la_method       = isset( $la_cfg['method'] )       ? (string) $la_cfg['method']       : 'ISNA';
	$la_asr_juristic = isset( $la_cfg['asr_juristic'] ) ? (int)    $la_cfg['asr_juristic'] : 1;
	$la_timings      = LA_Prayer_Times::for_lat_lng(
		(float) $la_mosque->latitude,
		(float) $la_mosque->longitude,
		(int)   $la_mosque->id,
		$la_tz_msj,
		$la_method,
		$la_asr_juristic
	);
	// Jamaat times — derived from begin times + masjid's stored offsets.
	// Empty array if the masjid hasn't configured any offsets.
	$la_jamaat = LA_Prayer_Times::apply_jamaat_offsets( $la_timings, $la_mosque );
} else {
	$la_timings = [];
	$la_jamaat  = [];
}

$la_next    = $la_timings ? LA_Prayer_Times::next_prayer( $la_timings ) : [];
$la_events  = $la_mosque ? LA_Events::upcoming( (int) $la_mosque->id, 12 ) : [];

// Jumuah time — masjid-managed if set, otherwise default to Dhuhr time.
// Strip seconds from the DB time column ("13:30:00" → "13:30") so the
// big Jumuah card matches the rest of the page's HH:MM formatting.
$la_jumuah_time = $la_mosque->jumuah_time ?? '';
if ( $la_jumuah_time ) {
	$la_jumuah_time = substr( $la_jumuah_time, 0, 5 );
}
if ( ! $la_jumuah_time && ! empty( $la_timings['Dhuhr'] ) ) {
	$la_jumuah_time = $la_timings['Dhuhr'];
}
$la_jumuah_lang = $la_mosque->jumuah_khutbah_lang ?? '';

// Is today Friday? Highlight the Jumuah block.
try {
	$tz_obj = new DateTimeZone( $la_tz_msj );
	$la_is_friday = ( (int) ( new DateTime( 'now', $tz_obj ) )->format( 'N' ) === 5 );
} catch ( Throwable $e ) { $la_is_friday = false; }

// RSVP / favourite state — read once for all visible events
$la_user_id    = get_current_user_id() ?: null;
$la_session_id = function_exists( 'la_get_or_set_session_id' ) ? la_get_or_set_session_id() : null;
$la_identity   = $la_user_id ? ( 'u' . (int) $la_user_id ) : ( $la_session_id ? ( 's' . $la_session_id ) : '' );
$la_my_rsvps = [];
$la_my_favs  = [];
if ( $la_identity && $la_events ) {
	global $wpdb;
	$t  = LA_DB::tables();
	$ids = array_map( fn( $e ) => (int) $e->id, $la_events );
	if ( $ids ) {
		$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_id, status FROM {$t['event_rsvps']}
			 WHERE identity = %s AND event_id IN ( $ph )",
			array_merge( [ $la_identity ], $ids )
		) );
		foreach ( $rows as $r ) {
			if ( $r->status === 'rsvp' ) $la_my_rsvps[ (int) $r->event_id ] = true;
			if ( $r->status === 'fav' )  $la_my_favs[ (int) $r->event_id ] = true;
		}
	}
}

get_header();
?>
<main class="la-app la-app--page la-app--masjid">

	<?php if ( ! $la_mosque ) : ?>
		<section class="la-page-empty">
			<h1>Choose your masjid</h1>
			<p>Tap the location pin in the header to find your closest masjid.</p>
		</section>
	<?php else : ?>

		<!-- HERO -->
		<header class="la-mhero">
			<div class="la-mhero-tag">Your masjid</div>
			<h1 class="la-mhero-name"><?php echo esc_html( $la_mosque->name ); ?></h1>
			<p class="la-mhero-loc">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s-7-7-7-12a7 7 0 0 1 14 0c0 5-7 12-7 12z"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
				<?php echo esc_html( trim( ( $la_mosque->address ?? '' ) . ', ' . ( $la_mosque->city ?? '' ), ', ' ) ); ?>
			</p>
		</header>

		<!-- PRAYER TIMES — Begin + Jamaat side by side (Wave 55) -->
		<?php if ( $la_timings ) : ?>
			<section class="la-msection la-msection--prayers">
				<?php if ( ! empty( $la_next['name'] ) ) : ?>
					<div class="la-mprayer-next" data-next-time="<?php echo esc_attr( $la_next['time'] ); ?>">
						<span class="la-mprayer-next-label">Next prayer</span>
						<span class="la-mprayer-next-name"><?php echo esc_html( $la_next['name'] ); ?></span>
						<span class="la-mprayer-next-eta" data-countdown>—</span>
					</div>
				<?php endif; ?>

				<?php
				// If the masjid has published jamaat offsets we show two values
				// per prayer (Begin · Jamaat). Otherwise just begin times.
				$la_has_jamaat = ! empty( $la_jamaat );
				?>
				<div class="la-mprayer-row <?php echo $la_has_jamaat ? 'has-jamaat' : ''; ?>">
					<?php foreach ( [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] as $name ) :
						if ( empty( $la_timings[ $name ] ) ) continue;
						$is_next = ( ! empty( $la_next['name'] ) && $la_next['name'] === $name );
						// Sunrise has no jamaat (no congregational prayer at sunrise).
						$jamaat_t = ( $name !== 'Sunrise' && ! empty( $la_jamaat[ $name ] ) ) ? $la_jamaat[ $name ] : '';
					?>
						<div class="la-mprayer-cell <?php echo $is_next ? 'is-next' : ''; ?>">
							<span class="la-mprayer-cell-name"><?php echo esc_html( $name ); ?></span>
							<span class="la-mprayer-cell-time"><?php echo esc_html( $la_timings[ $name ] ); ?></span>
							<?php if ( $jamaat_t ) : ?>
								<span class="la-mprayer-cell-jamaat" aria-label="Jamaat time">
									<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
									<?php echo esc_html( $jamaat_t ); ?>
								</span>
							<?php elseif ( $la_has_jamaat && $name === 'Sunrise' ) : ?>
								<span class="la-mprayer-cell-jamaat la-mprayer-cell-jamaat--dash" aria-hidden="true">—</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $la_has_jamaat ) : ?>
					<div class="la-mprayer-legend">
						<span class="la-mprayer-legend-key"><span class="la-mprayer-legend-dot"></span>Begin</span>
						<span class="la-mprayer-legend-key"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>Jamaat</span>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<!-- JUMUAH — prominent block, highlighted on Fridays -->
		<?php if ( $la_jumuah_time ) : ?>
			<section class="la-mjumuah <?php echo $la_is_friday ? 'is-today' : ''; ?>">
				<div class="la-mjumuah-inner">
					<div class="la-mjumuah-icon" aria-hidden="true">🕌</div>
					<div class="la-mjumuah-body">
						<div class="la-mjumuah-label">
							<?php echo $la_is_friday ? esc_html__( 'Today · Jumuah', 'loveallah' ) : esc_html__( 'This Friday · Jumuah', 'loveallah' ); ?>
						</div>
						<div class="la-mjumuah-time"><?php echo esc_html( $la_jumuah_time ); ?></div>
						<?php if ( $la_jumuah_lang ) : ?>
							<div class="la-mjumuah-lang"><?php echo esc_html( sprintf( __( 'Khutbah in %s', 'loveallah' ), $la_jumuah_lang ) ); ?></div>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $la_mosque->second_jumuah_time ) ) : ?>
						<div class="la-mjumuah-second">
							<div class="la-mjumuah-second-label"><?php esc_html_e( '2nd jamaah', 'loveallah' ); ?></div>
							<div class="la-mjumuah-second-time"><?php echo esc_html( $la_mosque->second_jumuah_time ); ?></div>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<!-- EVENTS — movie-poster horizontal slider -->
		<section class="la-msection la-msection--events">
			<header class="la-msection-head">
				<h2><?php esc_html_e( 'Upcoming events', 'loveallah' ); ?></h2>
				<?php if ( count( $la_events ) > 0 ) : ?>
					<span class="la-msection-count"><?php echo count( $la_events ); ?></span>
				<?php endif; ?>
			</header>
			<?php if ( empty( $la_events ) ) : ?>
				<p class="la-mempty"><?php esc_html_e( 'No events scheduled yet.', 'loveallah' ); ?></p>
			<?php else : ?>
				<div class="la-event-rail" data-event-rail>
					<?php foreach ( $la_events as $e ) :
						$dt = strtotime( $e->starts_at );
						$day = gmdate( 'd', $dt );
						$month = strtoupper( gmdate( 'M', $dt ) );
						$weekday = gmdate( 'D', $dt );
						$time = gmdate( 'H:i', $dt );
						$is_rsvp = isset( $la_my_rsvps[ (int) $e->id ] );
						$is_fav  = isset( $la_my_favs[ (int) $e->id ] );
						$bg_style = '';
						if ( ! empty( $e->image_url ) ) {
							$bg_style = 'background-image:url(' . esc_url( $e->image_url ) . ');background-size:cover;background-position:center;';
						} elseif ( ! empty( $e->poster_gradient ) ) {
							$bg_style = 'background:' . esc_attr( $e->poster_gradient ) . ';';
						} else {
							$bg_style = 'background:linear-gradient(160deg, #2C1338, #6B1846, #ED1C6C);';
						}
					?>
						<article class="la-event-poster" data-event-id="<?php echo (int) $e->id; ?>" style="<?php echo $bg_style; ?>">
							<!-- Top row: tag chip + favourite heart -->
							<div class="la-event-poster-top">
								<?php if ( ! empty( $e->tag ) ) : ?>
									<span class="la-event-poster-tag"><?php echo esc_html( ucfirst( $e->tag ) ); ?></span>
								<?php endif; ?>
								<button type="button"
									class="la-event-fav <?php echo $is_fav ? 'is-active' : ''; ?>"
									data-action="event-fav"
									data-id="<?php echo (int) $e->id; ?>"
									aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
									aria-label="Favourite">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $is_fav ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
								</button>
							</div>

							<!-- Spacer pushes content to bottom -->
							<div class="la-event-poster-fill"></div>

							<!-- Bottom: date pill + title + meta + RSVP -->
							<div class="la-event-poster-meta">
								<div class="la-event-poster-date">
									<span class="la-event-poster-day"><?php echo esc_html( $day ); ?></span>
									<span class="la-event-poster-month"><?php echo esc_html( $month ); ?></span>
								</div>
								<h3 class="la-event-poster-title"><?php echo esc_html( $e->title ); ?></h3>
								<div class="la-event-poster-when">
									<?php echo esc_html( $weekday . ' · ' . $time ); ?>
									<?php if ( ! empty( $e->location ) ) : ?>
										<span> · <?php echo esc_html( $e->location ); ?></span>
									<?php endif; ?>
								</div>
								<button type="button"
									class="la-event-rsvp <?php echo $is_rsvp ? 'is-active' : ''; ?>"
									data-action="event-rsvp"
									data-id="<?php echo (int) $e->id; ?>"
									aria-pressed="<?php echo $is_rsvp ? 'true' : 'false'; ?>">
									<span class="la-event-rsvp-on"><?php echo $is_rsvp ? '✓ Going' : 'RSVP'; ?></span>
									<span class="la-event-rsvp-count" data-rsvp-count><?php echo (int) ( $e->rsvp_count ?? 0 ); ?></span>
								</button>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<!-- SERVICES -->
		<section class="la-msection">
			<header class="la-msection-head">
				<h2>Services</h2>
			</header>
			<div class="la-mservices">
				<?php $services = [
					[ 'icon' => '📖', 'name' => 'Qur\'an classes', 'sub' => 'Kids & adults' ],
					[ 'icon' => '🤲', 'name' => 'Nikkah & marriage', 'sub' => 'Book a date' ],
					[ 'icon' => '🕌', 'name' => 'Funeral (Janazah)', 'sub' => 'Ghusl + burial' ],
					[ 'icon' => '📿', 'name' => 'Reverts', 'sub' => 'Shahadah & support' ],
					[ 'icon' => '🌙', 'name' => 'Ramadan programs', 'sub' => 'Iftar, taraweeh, i\'tikaaf' ],
					[ 'icon' => '🤝', 'name' => 'Counselling', 'sub' => 'Confidential, with the imam' ],
				]; foreach ( $services as $s ) : ?>
					<div class="la-mservice">
						<span class="la-mservice-icon" aria-hidden="true"><?php echo $s['icon']; ?></span>
						<div>
							<div class="la-mservice-name"><?php echo esc_html( $s['name'] ); ?></div>
							<div class="la-mservice-sub"><?php echo esc_html( $s['sub'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="la-mhint">Services shown are placeholders — each masjid manages their own list in their dashboard.</p>
		</section>

		<!-- ANNOUNCEMENTS -->
		<section class="la-msection">
			<header class="la-msection-head">
				<h2>Announcements</h2>
			</header>
			<div class="la-mannouncements">
				<article class="la-mannouncement">
					<div class="la-mannouncement-meta">Imam Yousaf · Yesterday</div>
					<p>JazakAllah khair to everyone who attended the new sisters' halaqah on Saturday. Next one is in 2 weeks insha'Allah.</p>
				</article>
				<article class="la-mannouncement">
					<div class="la-mannouncement-meta">Masjid Committee · 3 days ago</div>
					<p>Roof repairs complete. Thank you for your patience and du'as. Donations toward the building fund are still welcome.</p>
				</article>
			</div>
			<p class="la-mhint">Announcements are pushed by your masjid to your notifications bell.</p>
		</section>

	<?php endif; ?>

</main>
<?php get_footer();
