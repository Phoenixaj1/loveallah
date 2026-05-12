<?php
/**
 * Ummah page — connect with Muslims offering skilled services.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main class="la-app la-app--page">

	<header class="la-mhero">
		<div class="la-mhero-tag">The Ummah</div>
		<h1 class="la-mhero-name">Find your people</h1>
		<p class="la-mhero-loc">Muslims offering skills, knowledge & help</p>
	</header>

	<section class="la-msection">
		<div class="la-search-bar">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<input type="search" placeholder="Search by name or skill…" disabled>
		</div>
		<div class="la-chip-row">
			<?php $skills = [ 'All', 'Doctors', 'Lawyers', 'Plumbers', 'Teachers', 'Tutors', 'Carpenters', 'Designers', 'Developers', 'Drivers', 'Accountants', 'Therapists' ];
			foreach ( $skills as $i => $s ) : ?>
				<button class="la-chip <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button"><?php echo esc_html( $s ); ?></button>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="la-msection">
		<header class="la-msection-head">
			<h2>People near you</h2>
			<span class="la-msection-count">Coming soon</span>
		</header>

		<div class="la-coming-soon">
			<div class="la-coming-soon-icon">🤝</div>
			<h3>A directory of trust</h3>
			<p>The ummah is meant to lean on each other. Find vetted Muslims offering services in your area — every connection backed by community reviews.</p>
			<a class="la-pill-btn" href="#join">Join the directory</a>
		</div>

		<div class="la-people-skeleton" aria-hidden="true">
			<?php for ( $i = 0; $i < 3; $i++ ) : ?>
				<div class="la-person-skel">
					<div class="la-person-avatar-skel"></div>
					<div class="la-person-lines">
						<div class="la-person-line"></div>
						<div class="la-person-line" style="width:50%"></div>
						<div class="la-person-line" style="width:75%; height: 8px;"></div>
					</div>
				</div>
			<?php endfor; ?>
		</div>
	</section>

	<section class="la-msection">
		<div class="la-impact">
			<div class="la-impact-icon">💚</div>
			<div class="la-impact-body">
				<h3>Made for connection</h3>
				<p>Search by skill, distance or masjid. Send a niyyah-respectful message. No DMs without context, no spam. This is for real help between real Muslims.</p>
			</div>
		</div>
	</section>

</main>
<?php get_footer();
