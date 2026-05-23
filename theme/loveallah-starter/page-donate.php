<?php
/**
 * Donate — split-cause sadaqah hub.
 *
 * Six poster-style cause cards. Tap any → routes to YourNiyyah where
 * the donation is split between vetted charity partners for that cause.
 * No card processing happens here — we hand off to YourNiyyah which
 * handles the payment, the receipt, and the cross-charity split.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The six core causes. Each link lands on YourNiyyah with utm tracking
// so we can measure conversion per cause back to the source app.
$la_causes = [
	[
		'slug'    => 'palestine',
		'emoji'   => '🍉',
		'name'    => 'Palestine',
		'tag'     => 'Emergency',
		'blurb'   => "Food, medical aid and shelter for Gaza and the West Bank — split across vetted on-the-ground partners.",
		'url'     => 'https://yourniyyah.com/donate/palestine/?utm_source=loveallah&utm_medium=donate_tab&utm_campaign=cause_card',
		'gradient'=> 'linear-gradient(160deg, #0f5132 0%, #198754 50%, #ed1c6c 100%)',
	],
	[
		'slug'    => 'orphans',
		'emoji'   => '🤍',
		'name'    => 'Sponsor an Orphan',
		'tag'     => 'Monthly',
		'blurb'   => "Cover schooling, food and clothing for an orphan child. Updates from the partner charity each quarter.",
		'url'     => 'https://yourniyyah.com/donate/orphan/?utm_source=loveallah&utm_medium=donate_tab&utm_campaign=cause_card',
		'gradient'=> 'linear-gradient(160deg, #1b3a57 0%, #2d6ca2 60%, #4FC3F7 100%)',
	],
	[
		'slug'    => 'water',
		'emoji'   => '💧',
		'name'    => 'Build a Water Well',
		'tag'     => 'Sadaqah Jariyah',
		'blurb'   => "A continuing charity — every drink from the well counts on your scale long after the donation.",
		'url'     => 'https://yourniyyah.com/donate/water/?utm_source=loveallah&utm_medium=donate_tab&utm_campaign=cause_card',
		'gradient'=> 'linear-gradient(160deg, #003459 0%, #00a8e8 60%, #00d4ff 100%)',
	],
	[
		'slug'    => 'masjid',
		'emoji'   => '🕌',
		'name'    => 'Support a Masjid',
		'tag'     => 'Local',
		'blurb'   => "Help a masjid near you with bills, prayer mats, or programmes. Selected from our verified directory.",
		'url'     => 'https://yourniyyah.com/donate/masjid/?utm_source=loveallah&utm_medium=donate_tab&utm_campaign=cause_card',
		'gradient'=> 'linear-gradient(160deg, #2C1338 0%, #6B1846 50%, #ED1C6C 100%)',
	],
	[
		'slug'    => 'zakat',
		'emoji'   => '⚖️',
		'name'    => 'Pay Your Zakat',
		'tag'     => 'Obligation',
		'blurb'   => "Calculate what you owe with our zakat tool, then disburse it across qualifying recipients in 8 categories.",
		'url'     => 'https://yourniyyah.com/zakat-calculator/?utm_source=loveallah&utm_medium=donate_tab&utm_campaign=cause_card',
		'gradient'=> 'linear-gradient(160deg, #4a3300 0%, #b8860b 60%, #ffd700 100%)',
	],
	[
		'slug'    => 'ramadan',
		'emoji'   => '🌙',
		'name'    => 'Ramadan Iftar',
		'tag'     => 'Seasonal',
		'blurb'   => "Provide iftar meals during Ramadan. The Prophet ﷺ said whoever feeds a fasting person earns the same reward.",
		'url'     => 'https://yourniyyah.com/donate/ramadan/?utm_source=loveallah&utm_medium=donate_tab&utm_campaign=cause_card',
		'gradient'=> 'linear-gradient(160deg, #1a0033 0%, #4b0082 50%, #ffd700 100%)',
	],
];

get_header();
?>
<main class="la-app la-app--donate">

	<header class="la-donate-head">
		<div class="la-donate-eyebrow"><?php esc_html_e( "It's only loaned — give it back beautifully", 'loveallah' ); ?></div>
		<h1 class="la-donate-title"><?php esc_html_e( 'Sadaqah, split across trusted hands', 'loveallah' ); ?></h1>
		<p class="la-donate-sub">
			<?php esc_html_e( "Pick a cause. We hand off to YourNiyyah where your donation is split between vetted charity partners for that cause — full transparency, no platform fee.", 'loveallah' ); ?>
		</p>
	</header>

	<div class="la-donate-grid">
		<?php foreach ( $la_causes as $c ) : ?>
			<a class="la-donate-card" href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener" style="background:<?php echo esc_attr( $c['gradient'] ); ?>">
				<div class="la-donate-card-top">
					<span class="la-donate-card-emoji" aria-hidden="true"><?php echo $c['emoji']; ?></span>
					<span class="la-donate-card-tag"><?php echo esc_html( $c['tag'] ); ?></span>
				</div>
				<div class="la-donate-card-body">
					<h2 class="la-donate-card-name"><?php echo esc_html( $c['name'] ); ?></h2>
					<p class="la-donate-card-blurb"><?php echo esc_html( $c['blurb'] ); ?></p>
				</div>
				<div class="la-donate-card-cta">
					<span><?php esc_html_e( 'Give', 'loveallah' ); ?></span>
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</div>
			</a>
		<?php endforeach; ?>
	</div>

	<section class="la-donate-trust">
		<div class="la-donate-trust-inner">
			<div class="la-donate-trust-icon" aria-hidden="true">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
			</div>
			<div class="la-donate-trust-text">
				<strong><?php esc_html_e( '0% platform fee', 'loveallah' ); ?></strong>
				<span><?php esc_html_e( '100% of your donation reaches our charity partners. We are funded by the masjids and brands inside the app, not by your sadaqah.', 'loveallah' ); ?></span>
			</div>
		</div>
	</section>

</main>
<?php
get_footer();
