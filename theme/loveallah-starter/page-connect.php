<?php
/**
 * Connect — skills marketplace + patron tiers.
 *
 * Primary: browse + post community skill listings. Tradespeople,
 * doctors, tutors, lawyers etc list their services. A small platform
 * revenue share funds Islamic projects via YourNiyyah.
 *
 * Secondary: patron tiers (verified badges, subscription support).
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();
$la_categories = [
	'all'        => [ 'emoji' => '🤝', 'label' => 'All' ],
	'medical'    => [ 'emoji' => '🩺', 'label' => 'Medical' ],
	'tutoring'   => [ 'emoji' => '📚', 'label' => 'Tutoring' ],
	'legal'      => [ 'emoji' => '⚖️', 'label' => 'Legal' ],
	'trades'     => [ 'emoji' => '🔧', 'label' => 'Trades' ],
	'design'     => [ 'emoji' => '🎨', 'label' => 'Design' ],
	'tech'       => [ 'emoji' => '💻', 'label' => 'Tech' ],
	'fitness'    => [ 'emoji' => '💪', 'label' => 'Fitness' ],
	'wellness'   => [ 'emoji' => '🌿', 'label' => 'Wellness' ],
	'finance'    => [ 'emoji' => '📊', 'label' => 'Finance' ],
	'food'       => [ 'emoji' => '🍽️', 'label' => 'Food' ],
	'travel'     => [ 'emoji' => '✈️', 'label' => 'Travel' ],
	'other'      => [ 'emoji' => '⭐', 'label' => 'Other' ],
];

// Initial server-side list (no JS needed for first paint)
$la_listings = $wpdb->get_results(
	"SELECT id, category, title, blurb, full_name, city, country,
	        contact_email, contact_whatsapp, contact_url,
	        price_from, price_unit, currency, photo_url,
	        views_count, contact_count
	 FROM {$t['skill_listings']}
	 WHERE status = 'active'
	 ORDER BY created_at DESC
	 LIMIT 30"
);

get_header();
?>
<main class="la-app la-app--page la-app--connect">

	<header class="la-mhero">
		<div class="la-mhero-tag"><?php esc_html_e( 'Connect with the Ummah', 'loveallah' ); ?></div>
		<h1 class="la-mhero-name"><?php esc_html_e( 'The skills market — by Muslims, for Muslims.', 'loveallah' ); ?></h1>
		<p class="la-mhero-loc">
			<?php esc_html_e( 'Find a trusted plumber, tutor, doctor or designer. Every listing supports Islamic projects through a small revenue share.', 'loveallah' ); ?>
		</p>
	</header>

	<!-- ─── MARKETPLACE ─── -->
	<section class="la-msection">
		<header class="la-msection-head">
			<h2><?php esc_html_e( 'Browse skills', 'loveallah' ); ?></h2>
			<span class="la-msection-count" data-listing-count><?php echo count( $la_listings ); ?></span>
		</header>

		<!-- Search -->
		<div class="la-search-bar">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
			<input type="search" placeholder="<?php esc_attr_e( 'Search by skill, name or city', 'loveallah' ); ?>" data-skill-search>
		</div>

		<!-- Category chips -->
		<div class="la-chip-row" data-skill-chips>
			<?php $i = 0; foreach ( $la_categories as $key => $cat ) : ?>
				<button class="la-chip <?php echo $i === 0 ? 'is-active' : ''; ?>" type="button" data-cat="<?php echo esc_attr( $key ); ?>">
					<?php echo $cat['emoji']; ?> <?php echo esc_html( $cat['label'] ); ?>
				</button>
			<?php $i++; endforeach; ?>
		</div>

		<!-- Listings grid -->
		<div class="la-skills-grid" data-skills-grid>
			<?php if ( empty( $la_listings ) ) : ?>
				<div class="la-mempty">
					<?php esc_html_e( 'No listings yet. Be the first — post your service below.', 'loveallah' ); ?>
				</div>
			<?php else : foreach ( $la_listings as $l ) :
				$cat = $la_categories[ $l->category ] ?? $la_categories['other'];
				$contact_label = $l->contact_whatsapp ? 'WhatsApp' : 'Email';
				$contact_href  = $l->contact_whatsapp
					? 'https://wa.me/' . preg_replace( '/\D/', '', $l->contact_whatsapp )
					: 'mailto:' . $l->contact_email;
				?>
				<article class="la-skill" data-cat="<?php echo esc_attr( $l->category ); ?>" data-q="<?php echo esc_attr( strtolower( $l->title . ' ' . $l->full_name . ' ' . $l->city ) ); ?>">
					<div class="la-skill-head">
						<span class="la-skill-cat-emoji" aria-hidden="true"><?php echo $cat['emoji']; ?></span>
						<div class="la-skill-cat-label"><?php echo esc_html( $cat['label'] ); ?></div>
						<?php if ( $l->price_from ) : ?>
							<div class="la-skill-price">
								<span class="la-skill-price-from">from</span>
								<?php echo esc_html( $l->currency ?: 'GBP' === 'GBP' ? '£' : '$' ); ?><?php echo (int) $l->price_from; ?>
								<?php if ( $l->price_unit ) : ?>
									<span class="la-skill-price-unit">/<?php echo esc_html( $l->price_unit ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					<h3 class="la-skill-title"><?php echo esc_html( $l->title ); ?></h3>
					<div class="la-skill-by">
						<span class="la-skill-name"><?php echo esc_html( $l->full_name ); ?></span>
						<?php if ( $l->city ) : ?>
							<span class="la-skill-city">
								<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s-7-7-7-12a7 7 0 0 1 14 0c0 5-7 12-7 12z"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
								<?php echo esc_html( $l->city ); ?>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( $l->blurb ) : ?>
						<p class="la-skill-blurb"><?php echo esc_html( $l->blurb ); ?></p>
					<?php endif; ?>
					<a class="la-skill-contact" href="<?php echo esc_url( $contact_href ); ?>" target="_blank" rel="noopener" data-action="skill-contact" data-id="<?php echo (int) $l->id; ?>">
						<span><?php echo esc_html( 'Message via ' . $contact_label ); ?></span>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
					</a>
				</article>
			<?php endforeach; endif; ?>
		</div>
	</section>

	<!-- ─── POST YOUR SKILL ─── -->
	<section class="la-msection la-msection--listing-form" id="post-skill">
		<header class="la-msection-head">
			<h2><?php esc_html_e( 'List your skill', 'loveallah' ); ?></h2>
		</header>
		<p class="la-msection-intro">
			<?php esc_html_e( "It's free to list. We review every submission within 24h before it goes live. A small share of revenue from contacts you make through Love Allah funds Islamic projects — you keep the rest.", 'loveallah' ); ?>
		</p>

		<form class="la-skill-form" data-skill-form>
			<div class="la-skill-form-row">
				<label>
					<span><?php esc_html_e( 'Your name', 'loveallah' ); ?> *</span>
					<input type="text" name="full_name" required maxlength="120" autocomplete="name">
				</label>
				<label>
					<span><?php esc_html_e( 'City', 'loveallah' ); ?></span>
					<input type="text" name="city" maxlength="120" autocomplete="address-level2">
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Service title', 'loveallah' ); ?> *</span>
				<input type="text" name="title" required maxlength="180" placeholder="e.g. Qur'an + Tajweed online">
			</label>
			<label>
				<span><?php esc_html_e( 'Category', 'loveallah' ); ?> *</span>
				<select name="category" required>
					<option value="">Choose…</option>
					<?php foreach ( $la_categories as $key => $cat ) :
						if ( $key === 'all' ) continue; ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo $cat['emoji']; ?> <?php echo esc_html( $cat['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Short description', 'loveallah' ); ?></span>
				<textarea name="blurb" rows="3" maxlength="500" placeholder="What you offer, who it's for, qualifications…"></textarea>
			</label>
			<div class="la-skill-form-row">
				<label>
					<span><?php esc_html_e( 'From price (£)', 'loveallah' ); ?></span>
					<input type="number" name="price_from" min="0" max="9999" inputmode="numeric">
				</label>
				<label>
					<span><?php esc_html_e( 'Per', 'loveallah' ); ?></span>
					<select name="price_unit">
						<option value="">—</option>
						<option value="hour">hour</option>
						<option value="session">session</option>
						<option value="visit">visit</option>
						<option value="consultation">consultation</option>
						<option value="project">project</option>
						<option value="month">month</option>
					</select>
				</label>
			</div>
			<div class="la-skill-form-row">
				<label>
					<span><?php esc_html_e( 'Email', 'loveallah' ); ?></span>
					<input type="email" name="contact_email" maxlength="190" autocomplete="email">
				</label>
				<label>
					<span><?php esc_html_e( 'WhatsApp (international)', 'loveallah' ); ?></span>
					<input type="tel" name="contact_whatsapp" placeholder="+44…" maxlength="40">
				</label>
			</div>
			<p class="la-skill-form-fine">
				<?php esc_html_e( 'We need at least one contact method (email or WhatsApp). Your details are only shown to visitors who tap "Message".', 'loveallah' ); ?>
			</p>
			<button type="submit" class="la-pill-btn la-skill-form-submit">
				<?php esc_html_e( 'Submit for review', 'loveallah' ); ?>
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
			</button>
			<div class="la-skill-form-msg" data-form-msg hidden></div>
		</form>
	</section>

	<!-- ─── IMPACT ─── -->
	<section class="la-msection">
		<div class="la-impact">
			<div class="la-impact-icon">🤲</div>
			<div class="la-impact-body">
				<h3><?php esc_html_e( 'Where the money goes', 'loveallah' ); ?></h3>
				<p>
					<?php esc_html_e( "Love Allah Connect takes 0% from listings themselves. When a contact converts to a paid job, providers can opt to contribute 2.5% (the zakat rate) back to the platform — those contributions flow through YourNiyyah and fund:", 'loveallah' ); ?>
				</p>
				<ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#5A5A6E;line-height:1.6;">
					<li><?php esc_html_e( 'Masjid building + maintenance grants', 'loveallah' ); ?></li>
					<li><?php esc_html_e( 'Youth dawah programs', 'loveallah' ); ?></li>
					<li><?php esc_html_e( 'Quran + Islamic studies education', 'loveallah' ); ?></li>
					<li><?php esc_html_e( 'Vetted partner charities (UK Charity Commission registered)', 'loveallah' ); ?></li>
				</ul>
				<p style="margin-top:10px;font-size:12px;color:#8A8A9E;">
					<?php esc_html_e( 'A transparent ledger is published quarterly so you can see exactly where it went.', 'loveallah' ); ?>
				</p>
			</div>
		</div>
	</section>

</main>
<?php get_footer();
