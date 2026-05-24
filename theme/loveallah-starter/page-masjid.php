<?php
/**
 * Masjid page — local masjids list (Wave 56).
 *
 * The tab is a discovery surface, not a single-masjid detail view:
 *   1. Hero strip: "Local masjids" + visitor's city (if known)
 *   2. List of nearby masjids, GPS-sorted by distance
 *   3. Each card: name (prominent), distance, address, next prayer +
 *      jamaat time, favourite star
 *   4. Favourited masjids pin to the top of the list
 *   5. Tapping a card expands inline to show full prayer times + the
 *      masjid's upcoming events
 *
 * SSR-first: we render an initial list from server (default mosque +
 * the rest by name) so the page is never blank before JS hydrates.
 * Once JS gets GPS, it re-fetches /masjids/nearby and replaces the
 * list with a distance-sorted version.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$la_t = LA_DB::tables();

// Visitor identity for favourites — same scheme as elsewhere.
$la_user_id    = get_current_user_id() ?: null;
$la_session_id = function_exists( 'la_get_or_set_session_id' ) ? la_get_or_set_session_id() : null;
$la_identity   = $la_user_id ? ( 'u' . (int) $la_user_id ) : ( $la_session_id ? ( 's' . $la_session_id ) : '' );

// SSR list: ALL mosques sorted by name. JS replaces this with a GPS
// distance-sorted list once it has coordinates.
$la_mosques_ssr = $wpdb->get_results( "SELECT * FROM {$la_t['mosques']} ORDER BY name ASC LIMIT 20" );

// Favourite set for this identity (initial render)
$la_fav_ids = [];
if ( $la_identity ) {
	$la_fav_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT mosque_id FROM {$la_t['masjid_favourites']} WHERE identity = %s",
		$la_identity
	) ) );
}

// Friendly timezone fallback for SSR — matches header.php's resolution.
$la_tz_raw = wp_timezone_string();
$la_tz     = ( $la_tz_raw && ! preg_match( '/^[+\-]?\d{1,2}:?\d{0,2}$/', $la_tz_raw ) && $la_tz_raw !== 'UTC' )
	? $la_tz_raw
	: 'Europe/London';

/**
 * Build the card payload (HTML rendered inline + a JSON blob in a
 * data-attr so JS can update without a full re-render). Each card
 * shows just the next prayer's begin + jamaat times; the expand
 * panel shows the full daily schedule.
 */
function la_masjid_card_html( $m, bool $is_fav, bool $expanded = false ) {
	$timings = LA_Prayer_Times::for_mosque( $m );
	$jamaat  = LA_Prayer_Times::apply_jamaat_offsets( $timings, $m );
	$next    = $timings ? LA_Prayer_Times::next_prayer( $timings ) : [];
	$next_name = $next['name']   ?? '';
	$next_begin = $next_name && isset( $timings[ $next_name ] ) ? $timings[ $next_name ] : '';
	$next_jamaat = $next_name && isset( $jamaat[ $next_name ] ) ? $jamaat[ $next_name ] : '';
	$jumuah_time = ! empty( $m->jumuah_time ) ? substr( $m->jumuah_time, 0, 5 ) : '';
	$brand = $m->branding_color_primary ?? '';
	$address = trim( ( $m->address ?? '' ) . ( ! empty( $m->city ) ? ', ' . $m->city : '' ), ', ' );

	ob_start();
	?>
	<article class="la-masjid-card<?php echo $is_fav ? ' is-favourite' : ''; ?><?php echo $expanded ? ' is-expanded' : ''; ?>"
		data-masjid-card
		data-mosque-id="<?php echo (int) $m->id; ?>"
		data-mosque-slug="<?php echo esc_attr( $m->slug ); ?>"
		<?php if ( $brand ) : ?>style="--masjid-brand: <?php echo esc_attr( $brand ); ?>;"<?php endif; ?>>

		<button type="button" class="la-masjid-card-head" data-card-toggle aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>">
			<div class="la-masjid-card-glyph" aria-hidden="true">🕌</div>
			<div class="la-masjid-card-main">
				<h3 class="la-masjid-card-name"><?php echo esc_html( $m->name ); ?></h3>
				<div class="la-masjid-card-meta">
					<span class="la-masjid-card-dist" data-card-dist>—</span>
					<span class="la-masjid-card-addr"><?php echo esc_html( $address ?: 'Birmingham' ); ?></span>
				</div>
				<?php if ( $next_name && $next_jamaat ) : ?>
					<div class="la-masjid-card-next">
						<span class="la-masjid-card-next-name"><?php echo esc_html( $next_name ); ?></span>
						<span class="la-masjid-card-next-jamaat"><?php echo esc_html( $next_jamaat ); ?> jamaat</span>
						<?php if ( $next_begin && $next_begin !== $next_jamaat ) : ?>
							<span class="la-masjid-card-next-begin">· begins <?php echo esc_html( $next_begin ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<span class="la-masjid-card-fav <?php echo $is_fav ? 'is-on' : ''; ?>"
				data-fav-btn
				role="button"
				aria-label="<?php echo $is_fav ? esc_attr__( 'Remove favourite', 'loveallah' ) : esc_attr__( 'Favourite this masjid', 'loveallah' ); ?>"
				aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="<?php echo $is_fav ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
			</span>
		</button>

		<div class="la-masjid-card-body" data-card-body <?php echo $expanded ? '' : 'hidden'; ?>>
			<?php if ( $timings ) : ?>
				<div class="la-masjid-card-prayers">
					<?php foreach ( [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] as $p ) :
						if ( empty( $timings[ $p ] ) ) continue;
						$is_next = $next_name === $p;
						$j = ( $p !== 'Sunrise' && ! empty( $jamaat[ $p ] ) ) ? $jamaat[ $p ] : '';
					?>
						<div class="la-masjid-card-prayer <?php echo $is_next ? 'is-next' : ''; ?>">
							<span class="la-masjid-card-prayer-name"><?php echo esc_html( $p ); ?></span>
							<span class="la-masjid-card-prayer-begin"><?php echo esc_html( $timings[ $p ] ); ?></span>
							<?php if ( $j ) : ?>
								<span class="la-masjid-card-prayer-jamaat"><?php echo esc_html( $j ); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $jumuah_time ) : ?>
				<div class="la-masjid-card-jumuah">
					<span class="la-masjid-card-jumuah-icon" aria-hidden="true">🕌</span>
					<span class="la-masjid-card-jumuah-label">Jumu'ah</span>
					<span class="la-masjid-card-jumuah-time"><?php echo esc_html( $jumuah_time ); ?></span>
					<?php if ( ! empty( $m->jumuah_khutbah_lang ) ) : ?>
						<span class="la-masjid-card-jumuah-lang"><?php echo esc_html( $m->jumuah_khutbah_lang ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			// Upcoming events for this masjid (first 3 only — keep card compact)
			$events = class_exists( 'LA_Events' ) ? LA_Events::upcoming( (int) $m->id, 3 ) : [];
			if ( $events ) : ?>
				<div class="la-masjid-card-events">
					<div class="la-masjid-card-events-head">Upcoming events</div>
					<?php foreach ( $events as $e ) :
						$dt = strtotime( $e->starts_at );
					?>
						<div class="la-masjid-card-event">
							<div class="la-masjid-card-event-date">
								<span class="la-masjid-card-event-day"><?php echo esc_html( gmdate( 'd', $dt ) ); ?></span>
								<span class="la-masjid-card-event-month"><?php echo esc_html( strtoupper( gmdate( 'M', $dt ) ) ); ?></span>
							</div>
							<div class="la-masjid-card-event-meta">
								<div class="la-masjid-card-event-title"><?php echo esc_html( $e->title ); ?></div>
								<div class="la-masjid-card-event-when"><?php echo esc_html( gmdate( 'D · H:i', $dt ) ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

// Build initial card HTML. Favourites pinned first.
$la_cards_fav = [];
$la_cards_rest = [];
foreach ( $la_mosques_ssr as $m ) {
	$is_fav = in_array( (int) $m->id, $la_fav_ids, true );
	$html = la_masjid_card_html( $m, $is_fav, false );
	if ( $is_fav ) $la_cards_fav[]  = $html;
	else           $la_cards_rest[] = $html;
}

get_header();
?>
<main class="la-app la-app--page la-app--masjid-list" data-masjid-list>

	<header class="la-masjid-list-head">
		<div class="la-masjid-list-eyebrow">Local masjids</div>
		<h1 class="la-masjid-list-title" data-list-title>Masjids near you</h1>
		<p class="la-masjid-list-sub" data-list-sub>Tap the star to favourite — your chosen masjids pin to the top.</p>
		<button type="button" class="la-masjid-list-gps" data-gps-button hidden>
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v3"/><path d="M12 20v3"/><path d="M4.22 4.22l2.12 2.12"/><path d="M17.66 17.66l2.12 2.12"/><path d="M1 12h3"/><path d="M20 12h3"/><path d="M4.22 19.78l2.12-2.12"/><path d="M17.66 6.34l2.12-2.12"/></svg>
			Use my location
		</button>
	</header>

	<section class="la-masjid-list-section" data-favourites-section <?php echo empty( $la_cards_fav ) ? 'hidden' : ''; ?>>
		<header class="la-masjid-list-section-head">
			<span class="la-masjid-list-section-icon" aria-hidden="true">⭐</span>
			<h2>Your masjids</h2>
		</header>
		<div class="la-masjid-list-cards" data-favourites-list>
			<?php echo implode( '', $la_cards_fav ); ?>
		</div>
	</section>

	<section class="la-masjid-list-section" data-nearby-section>
		<header class="la-masjid-list-section-head">
			<svg class="la-masjid-list-section-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-12a7 7 0 0 1 14 0c0 5-7 12-7 12z"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
			<h2 data-nearby-heading>All masjids</h2>
		</header>
		<div class="la-masjid-list-cards" data-nearby-list>
			<?php echo implode( '', $la_cards_rest ); ?>
		</div>
		<div class="la-masjid-list-loading" data-list-loading hidden>
			<div class="la-masjid-list-spinner" aria-hidden="true"></div>
			<span>Finding masjids near you…</span>
		</div>
		<p class="la-masjid-list-empty" data-list-empty hidden>No masjids found nearby. Try again later, or share your location to expand the search.</p>
	</section>

	<p class="la-masjid-list-footnote">
		Imam at a masjid? <a href="<?php echo esc_url( home_url( '/connect/' ) ); ?>">Get in touch</a> to claim your listing — keep your prayer times and events updated yourself.
	</p>

</main>
<?php get_footer();
