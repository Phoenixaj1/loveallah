<?php
/**
 * Connect page — patron subscriptions, verified badges, people directory.
 *
 * Subscription revenue flows through YourNiyyah to:
 *   • Masjids that have signed up
 *   • Partner charities
 *   • Charitable Islamic activities
 *
 * Each tier grants a different verified-style badge across the platform.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main class="la-app la-app--page">

	<header class="la-mhero">
		<div class="la-mhero-tag"><?php esc_html_e( 'Connect with the Ummah', 'loveallah' ); ?></div>
		<h1 class="la-mhero-name"><?php esc_html_e( 'Stand with your people.', 'loveallah' ); ?></h1>
		<p class="la-mhero-loc"><?php esc_html_e( 'Subscribe · get verified · support masjids and charity', 'loveallah' ); ?></p>
	</header>

	<!-- PATRON TIERS -->
	<section class="la-msection">
		<header class="la-msection-head">
			<h2><?php esc_html_e( 'Become a patron', 'loveallah' ); ?></h2>
			<span class="la-msection-count"><?php esc_html_e( 'All revenue → charity', 'loveallah' ); ?></span>
		</header>
		<p class="description" style="margin:0 0 18px;color:#5A5A6E;font-size:14px;">
			<?php esc_html_e( 'Your subscription is sadaqah jaariyah. Every penny flows through YourNiyyah and is split between your masjid, partner charities, and Islamic projects. You also get a verified badge visible across the platform.', 'loveallah' ); ?>
		</p>

		<div class="la-tier-grid">
			<!-- Supporter -->
			<article class="la-tier la-tier--supporter">
				<div class="la-tier-badge-wrap" aria-hidden="true">
					<svg class="la-badge la-badge--supporter" width="42" height="42" viewBox="0 0 24 24"><path d="M12 2l2.4 1.8 3-.4.6 2.9L20 8.4l-1.2 2.7L20 14l-2.4 1.5-.6 2.9-3-.4L12 20l-2.4-1.8-3 .4-.6-2.9L4 14.2l1.2-2.7L4 8.8l2.4-1.5.6-2.9 3 .4z" fill="#25D366"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<div class="la-tier-name"><?php esc_html_e( 'Supporter', 'loveallah' ); ?></div>
				<div class="la-tier-price">£3<span>/mo</span></div>
				<ul class="la-tier-perks">
					<li><span class="la-tier-tick" style="color:#25D366;">✓</span> <?php esc_html_e( 'Green verified badge', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#25D366;">✓</span> <?php esc_html_e( 'Searchable profile', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#25D366;">✓</span> <?php esc_html_e( 'Funds your masjid', 'loveallah' ); ?></li>
				</ul>
				<button class="la-tier-btn la-tier-btn--supporter" type="button"><?php esc_html_e( 'Notify me', 'loveallah' ); ?></button>
			</article>

			<!-- Guardian -->
			<article class="la-tier la-tier--guardian">
				<div class="la-tier-badge-wrap" aria-hidden="true">
					<svg class="la-badge la-badge--guardian" width="42" height="42" viewBox="0 0 24 24"><path d="M12 2l2.4 1.8 3-.4.6 2.9L20 8.4l-1.2 2.7L20 14l-2.4 1.5-.6 2.9-3-.4L12 20l-2.4-1.8-3 .4-.6-2.9L4 14.2l1.2-2.7L4 8.8l2.4-1.5.6-2.9 3 .4z" fill="#00ADEF"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<div class="la-tier-name"><?php esc_html_e( 'Guardian', 'loveallah' ); ?></div>
				<div class="la-tier-price">£10<span>/mo</span></div>
				<ul class="la-tier-perks">
					<li><span class="la-tier-tick" style="color:#00ADEF;">✓</span> <?php esc_html_e( 'Blue verified badge', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#00ADEF;">✓</span> <?php esc_html_e( 'Featured in search', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#00ADEF;">✓</span> <?php esc_html_e( 'Direct masjid invoice', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#00ADEF;">✓</span> <?php esc_html_e( 'Priority support', 'loveallah' ); ?></li>
				</ul>
				<button class="la-tier-btn la-tier-btn--guardian" type="button"><?php esc_html_e( 'Notify me', 'loveallah' ); ?></button>
			</article>

			<!-- Champion -->
			<article class="la-tier la-tier--champion la-tier--popular">
				<span class="la-tier-flag"><?php esc_html_e( 'Most chosen', 'loveallah' ); ?></span>
				<div class="la-tier-badge-wrap" aria-hidden="true">
					<svg class="la-badge la-badge--champion" width="42" height="42" viewBox="0 0 24 24"><path d="M12 2l2.4 1.8 3-.4.6 2.9L20 8.4l-1.2 2.7L20 14l-2.4 1.5-.6 2.9-3-.4L12 20l-2.4-1.8-3 .4-.6-2.9L4 14.2l1.2-2.7L4 8.8l2.4-1.5.6-2.9 3 .4z" fill="#C8A54E"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<div class="la-tier-name"><?php esc_html_e( 'Champion', 'loveallah' ); ?></div>
				<div class="la-tier-price">£25<span>/mo</span></div>
				<ul class="la-tier-perks">
					<li><span class="la-tier-tick" style="color:#C8A54E;">✓</span> <?php esc_html_e( 'Gold verified badge', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#C8A54E;">✓</span> <?php esc_html_e( 'Top of category listings', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#C8A54E;">✓</span> <?php esc_html_e( 'Sadaqah jaariyah ledger', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#C8A54E;">✓</span> <?php esc_html_e( 'Boosted reach in feed', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#C8A54E;">✓</span> <?php esc_html_e( 'Annual impact report', 'loveallah' ); ?></li>
				</ul>
				<button class="la-tier-btn la-tier-btn--champion" type="button"><?php esc_html_e( 'Notify me', 'loveallah' ); ?></button>
			</article>

			<!-- Visionary -->
			<article class="la-tier la-tier--visionary">
				<div class="la-tier-badge-wrap" aria-hidden="true">
					<svg class="la-badge la-badge--visionary" width="42" height="42" viewBox="0 0 24 24"><path d="M12 1l3 7h7l-5.5 5L18 21l-6-4-6 4 1.5-8L2 8h7z" fill="#8B45F1"/></svg>
				</div>
				<div class="la-tier-name"><?php esc_html_e( 'Visionary', 'loveallah' ); ?></div>
				<div class="la-tier-price">£100<span>/mo</span></div>
				<ul class="la-tier-perks">
					<li><span class="la-tier-tick" style="color:#8B45F1;">★</span> <?php esc_html_e( 'Purple star badge', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#8B45F1;">★</span> <?php esc_html_e( 'Name in masjid mihrab credits', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#8B45F1;">★</span> <?php esc_html_e( 'Personal scholar Q&A access', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#8B45F1;">★</span> <?php esc_html_e( 'Direct charity allocation choice', 'loveallah' ); ?></li>
					<li><span class="la-tier-tick" style="color:#8B45F1;">★</span> <?php esc_html_e( 'Every Champion perk', 'loveallah' ); ?></li>
				</ul>
				<button class="la-tier-btn la-tier-btn--visionary" type="button"><?php esc_html_e( 'Notify me', 'loveallah' ); ?></button>
			</article>
		</div>
	</section>

	<!-- OFFER SERVICES -->
	<section class="la-msection">
		<header class="la-msection-head">
			<h2><?php esc_html_e( 'Offer your skill', 'loveallah' ); ?></h2>
		</header>
		<div class="la-cta-card">
			<div class="la-cta-emoji">🤝</div>
			<div>
				<h3><?php esc_html_e( 'List your skill, help your ummah', 'loveallah' ); ?></h3>
				<p><?php esc_html_e( 'Doctor, plumber, tutor, designer — whatever you do, the ummah needs you. List your services, get found, and every payment supports both you and the charitable cause.', 'loveallah' ); ?></p>
				<a class="la-pill-btn" href="#join-directory"><?php esc_html_e( 'Join the directory', 'loveallah' ); ?> →</a>
			</div>
		</div>
	</section>

	<!-- FIND PEOPLE -->
	<section class="la-msection">
		<header class="la-msection-head">
			<h2><?php esc_html_e( 'Find your people', 'loveallah' ); ?></h2>
			<span class="la-msection-count"><?php esc_html_e( 'Coming soon', 'loveallah' ); ?></span>
		</header>
		<div class="la-search-bar">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<input type="search" placeholder="<?php esc_attr_e( 'Search by name, skill or city…', 'loveallah' ); ?>" disabled>
		</div>
		<div class="la-chip-row">
			<?php $skills = [ 'All', 'Doctors', 'Lawyers', 'Plumbers', 'Teachers', 'Tutors', 'Carpenters', 'Designers', 'Developers', 'Drivers', 'Accountants', 'Therapists', 'Tradesmen', 'Travel' ];
			foreach ( $skills as $i => $s ) : ?>
				<button class="la-chip <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button"><?php echo esc_html( $s ); ?></button>
			<?php endforeach; ?>
		</div>
		<div class="la-people-skeleton" aria-hidden="true">
			<?php for ( $i = 0; $i < 4; $i++ ) : ?>
				<div class="la-person-skel">
					<div class="la-person-avatar-skel"></div>
					<div class="la-person-lines">
						<div class="la-person-line"></div>
						<div class="la-person-line" style="width:55%"></div>
						<div class="la-person-line" style="width:70%; height: 8px;"></div>
					</div>
				</div>
			<?php endfor; ?>
		</div>
	</section>

	<!-- IMPACT -->
	<section class="la-msection">
		<div class="la-impact">
			<div class="la-impact-icon">🤲</div>
			<div class="la-impact-body">
				<h3><?php esc_html_e( 'Where the money goes', 'loveallah' ); ?></h3>
				<p><?php esc_html_e( 'Every subscription pound flows through YourNiyyah, which distributes it across:', 'loveallah' ); ?></p>
				<ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#5A5A6E;line-height:1.6;">
					<li><?php esc_html_e( 'Masjids that have joined Love Allah (your local one gets a direct cut)', 'loveallah' ); ?></li>
					<li><?php esc_html_e( 'Vetted partner charities (UK Charity Commission registered)', 'loveallah' ); ?></li>
					<li><?php esc_html_e( 'Islamic projects — youth programs, dawah, education', 'loveallah' ); ?></li>
				</ul>
				<p style="margin-top:10px;font-size:12px;color:#8A8A9E;"><?php esc_html_e( 'A transparent ledger will be visible to every patron.', 'loveallah' ); ?></p>
			</div>
		</div>
	</section>

</main>
<?php get_footer();
