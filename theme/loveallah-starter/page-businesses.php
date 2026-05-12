<?php
/**
 * Business directory page — Muslim businesses.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main class="la-app la-app--page">

	<header class="la-mhero">
		<div class="la-mhero-tag">Discover</div>
		<h1 class="la-mhero-name">Muslim Businesses</h1>
		<p class="la-mhero-loc">Halal-first directory · all profits to the ummah</p>
	</header>

	<section class="la-msection">
		<div class="la-search-bar">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<input type="search" placeholder="Search businesses or services…" disabled>
		</div>
		<div class="la-chip-row">
			<?php $cats = [ 'All', 'Food', 'Clothing', 'Halal Meat', 'Tradesmen', 'Beauty', 'Travel', 'Finance', 'Legal', 'Tech', 'Estate' ];
			foreach ( $cats as $i => $c ) : ?>
				<button class="la-chip <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button"><?php echo esc_html( $c ); ?></button>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="la-msection">
		<header class="la-msection-head">
			<h2>Featured</h2>
			<span class="la-msection-count">Coming soon</span>
		</header>
		<div class="la-coming-soon">
			<div class="la-coming-soon-icon">🏛️</div>
			<h3>We're curating this carefully</h3>
			<p>Businesses on Love Allah will be hand-verified for halal commitment and customer reputation. Listings open soon.</p>
			<a class="la-pill-btn" href="#list-yours">List your business</a>
		</div>

		<div class="la-business-skeleton" aria-hidden="true">
			<?php for ( $i = 0; $i < 4; $i++ ) : ?>
				<div class="la-biz-card-skel">
					<div class="la-biz-skel-img"></div>
					<div class="la-biz-skel-lines">
						<div class="la-biz-skel-line"></div>
						<div class="la-biz-skel-line" style="width:60%"></div>
					</div>
				</div>
			<?php endfor; ?>
		</div>
	</section>

	<section class="la-msection">
		<div class="la-impact">
			<div class="la-impact-icon">🤲</div>
			<div class="la-impact-body">
				<h3>How this funds the ummah</h3>
				<p>Every advertising pound flows through YourNiyyah and is split between partner charities, Islamic activities, and the masjids that sign up. Buying from a listed business directly supports both the merchant and the ummah.</p>
			</div>
		</div>
	</section>

</main>
<?php get_footer();
