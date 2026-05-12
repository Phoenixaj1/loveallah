<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$la_mosque  = function_exists( 'la_chosen_mosque' ) ? la_chosen_mosque() : null;
$la_timings = ( $la_mosque && class_exists( 'LA_Prayer_Times' ) ) ? LA_Prayer_Times::for_mosque( $la_mosque ) : [];
$la_next    = $la_timings ? LA_Prayer_Times::next_prayer( $la_timings ) : [];
$la_streak  = function_exists( 'la_unlock_state_for_view' ) ? la_unlock_state_for_view()['streak'] : 0;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="<?php echo esc_attr( $la_mosque->branding_color_primary ?? '#ED1C6C' ); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700;800;900&family=Noto+Naskh+Arabic:wght@400;700&display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<header class="la-header"
		<?php if ( $la_mosque && ! empty( $la_next['time'] ) ) : ?>
			data-next-time="<?php echo esc_attr( $la_next['time'] ); ?>"
			data-next-name="<?php echo esc_attr( $la_next['name'] ); ?>"
		<?php endif; ?>>

		<!-- ROW 1: masjid label + actions -->
		<div class="la-header-row la-header-row--top">
			<?php if ( $la_mosque ) : ?>
				<div class="la-prayer-label">
					<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 4v6c0 5-3.5 9-7 10-3.5-1-7-5-7-10V6l7-4z"/></svg>
					<span class="la-prayer-label-text"><?php echo esc_html( $la_mosque->name ); ?></span>
				</div>
			<?php else : ?>
				<div class="la-prayer-label"></div>
			<?php endif; ?>

			<div class="la-header-actions">
				<?php if ( $la_streak >= 1 ) : ?>
					<div class="la-streak-pill" title="<?php echo esc_attr( $la_streak ); ?>-day remembrance streak">
						<span class="la-streak-icon">🤲</span>
						<span class="la-streak-num"><?php echo (int) $la_streak; ?></span>
					</div>
				<?php endif; ?>
				<button class="la-icon-btn" type="button" aria-label="Search the feed" data-action="search">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
				</button>
				<button class="la-icon-btn la-icon-btn--bell" type="button" aria-label="Notifications" data-action="notifications">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 16v-5a6 6 0 1 0-12 0v5l-2 2v1h16v-1l-2-2z"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>
					<span class="la-icon-btn-dot" aria-hidden="true"></span>
				</button>
				<button class="la-icon-btn" type="button" aria-label="Upcoming masjid events" data-action="events">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></svg>
				</button>
				<button class="la-icon-btn" type="button" aria-label="Find closest masjid" data-action="locate">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-7-7-7-12a7 7 0 0 1 14 0c0 5-7 12-7 12z"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
				</button>
			</div>
		</div>

		<!-- ROW 2: brand + 5 prayer cells -->
		<div class="la-header-row la-header-row--bottom">
			<a class="la-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Love Allah home">
				<svg class="la-brand-mark" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path d="M12 21s-7-4.5-9.5-9C.5 8 3 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 4 0 6.5 4 4.5 8C19 16.5 12 21 12 21z" fill="currentColor"/>
					<path d="M14.5 9.5a3.5 3.5 0 1 1-3 5.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round" fill="none"/>
				</svg>
				<span class="la-brand-text">
					<span class="la-brand-word">Love</span><span class="la-brand-allah">Allah</span>
				</span>
			</a>

			<?php if ( $la_mosque && $la_timings ) : ?>
				<div class="la-prayer-bar-row" aria-label="Prayer times at <?php echo esc_attr( $la_mosque->name ); ?>">
					<?php foreach ( [ 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ] as $name ) :
						if ( empty( $la_timings[ $name ] ) ) continue;
						$is_next = ( ! empty( $la_next['name'] ) && $la_next['name'] === $name );
					?>
						<div class="la-prayer-cell <?php echo $is_next ? 'is-next' : ''; ?>" title="<?php echo esc_attr( $name . ' ' . $la_timings[ $name ] ); ?>">
							<span class="la-prayer-cell-name"><?php echo esc_html( $name ); ?></span>
							<span class="la-prayer-cell-time"><?php echo esc_html( $la_timings[ $name ] ); ?></span>
							<?php if ( $is_next ) : ?>
								<span class="la-prayer-cell-eta" data-countdown>—</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</header>
