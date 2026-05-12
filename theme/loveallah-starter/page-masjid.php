<?php
/**
 * Masjid page — the user's local masjid hub.
 * Events, prayer times, services, announcements.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$la_mosque  = la_chosen_mosque();
$la_timings = $la_mosque ? LA_Prayer_Times::for_mosque( $la_mosque ) : [];
$la_next    = $la_timings ? LA_Prayer_Times::next_prayer( $la_timings ) : [];
$la_events  = $la_mosque ? LA_Events::upcoming( (int) $la_mosque->id, 6 ) : [];

get_header();
?>
<main class="la-app la-app--page">

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

		<!-- PRAYER TIMES -->
		<section class="la-msection">
			<header class="la-msection-head">
				<h2>Prayer times</h2>
				<?php if ( ! empty( $la_next['name'] ) ) : ?>
					<span class="la-mnext" data-next-time="<?php echo esc_attr( $la_next['time'] ); ?>">
						<?php echo esc_html( $la_next['name'] ); ?> · <span data-countdown>—</span>
					</span>
				<?php endif; ?>
			</header>
			<?php if ( $la_timings ) : ?>
				<div class="la-mprayer-grid">
					<?php foreach ( [ 'Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] as $name ) :
						if ( empty( $la_timings[ $name ] ) ) continue;
						$is_next = ( ! empty( $la_next['name'] ) && $la_next['name'] === $name );
					?>
						<div class="la-mprayer <?php echo $is_next ? 'is-next' : ''; ?>">
							<span class="la-mprayer-name"><?php echo esc_html( $name ); ?></span>
							<span class="la-mprayer-time"><?php echo esc_html( $la_timings[ $name ] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<!-- EVENTS -->
		<section class="la-msection">
			<header class="la-msection-head">
				<h2>Upcoming events</h2>
				<?php if ( count( $la_events ) > 0 ) : ?>
					<span class="la-msection-count"><?php echo count( $la_events ); ?></span>
				<?php endif; ?>
			</header>
			<?php if ( empty( $la_events ) ) : ?>
				<p class="la-mempty">No events scheduled yet.</p>
			<?php else : ?>
				<div class="la-mevents">
					<?php foreach ( $la_events as $e ) :
						$dt = strtotime( $e->starts_at );
						$day = gmdate( 'd', $dt );
						$month = gmdate( 'M', $dt );
						$weekday = gmdate( 'D', $dt );
						$time = gmdate( 'H:i', $dt );
					?>
						<article class="la-event">
							<div class="la-event-date">
								<div class="la-event-day"><?php echo esc_html( $day ); ?></div>
								<div class="la-event-month"><?php echo esc_html( $month ); ?></div>
							</div>
							<div class="la-event-body">
								<?php if ( ! empty( $e->tag ) ) : ?>
									<span class="la-event-tag"><?php echo esc_html( $e->tag ); ?></span>
								<?php endif; ?>
								<h3 class="la-event-title"><?php echo esc_html( $e->title ); ?></h3>
								<div class="la-event-meta">
									<span>🕒 <?php echo esc_html( $weekday . ' · ' . $time ); ?></span>
									<?php if ( ! empty( $e->location ) ) : ?>
										<span>📍 <?php echo esc_html( $e->location ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( ! empty( $e->description ) ) : ?>
									<p class="la-event-desc"><?php echo esc_html( $e->description ); ?></p>
								<?php endif; ?>
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
