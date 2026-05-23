<?php
/**
 * Dhikr — heart-polishing contemplation.
 *
 * Not a counter, not a game. A breath-paced silent practice rooted in
 * the Sufi sciences: dhikr is the polish for the heart's rust, the
 * means by which the slave's heart finds rest (Quran 13:28).
 *
 * UX:  Landing (phrase + duration + mode) → breath circle session →
 *      reflection completion.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The dhikr phrases — ordered from the highest to the everyday.
// La ilaha illa Allah is the primary dhikr of the Sufi orders (the kalimah),
// the testimony itself and the means by which the heart is unlocked.
$la_phrases = [
	[
		'key'       => 'kalimah',
		'arabic'    => 'لَا إِلَهَ إِلَّا ٱللَّٰه',
		'translit'  => 'Lā ilāha illa-llāh',
		'meaning'   => 'There is no god but Allah',
		'inhale'    => 'Lā ilāha',
		'exhale'    => 'illa-llāh',
		'note'      => 'The kalimah — the testimony and the highest dhikr',
		'breath_s'  => 8,
	],
	[
		'key'       => 'allah',
		'arabic'    => 'ٱللَّٰه',
		'translit'  => 'Allāh',
		'meaning'   => 'The Divine Name',
		'inhale'    => 'Al-',
		'exhale'    => 'lāh',
		'note'      => 'The singular Name — the dhikr of the gnostics',
		'breath_s'  => 8,
	],
	[
		'key'       => 'subhanallah',
		'arabic'    => 'سُبْحَانَ ٱللَّٰه',
		'translit'  => 'Subḥān Allāh',
		'meaning'   => 'Glory be to Allah',
		'inhale'    => 'Subḥān',
		'exhale'    => 'Allāh',
		'note'      => 'Glorification — the dhikr of declaring Allah free from any imperfection',
		'breath_s'  => 7,
	],
	[
		'key'       => 'alhamdulillah',
		'arabic'    => 'ٱلْحَمْدُ لِلَّٰه',
		'translit'  => 'Alḥamdulillāh',
		'meaning'   => 'All praise is for Allah',
		'inhale'    => 'Alḥamdu',
		'exhale'    => 'lillāh',
		'note'      => 'Gratitude — the dhikr that fills the scales',
		'breath_s'  => 7,
	],
	[
		'key'       => 'allahuakbar',
		'arabic'    => 'ٱللَّٰهُ أَكْبَر',
		'translit'  => 'Allāhu akbar',
		'meaning'   => 'Allah is greater',
		'inhale'    => 'Allāhu',
		'exhale'    => 'akbar',
		'note'      => 'Magnification — the dhikr that puts every other concern in its place',
		'breath_s'  => 7,
	],
	[
		'key'       => 'astaghfirullah',
		'arabic'    => 'أَسْتَغْفِرُ ٱللَّٰه',
		'translit'  => 'Astaghfirullāh',
		'meaning'   => 'I seek forgiveness of Allah',
		'inhale'    => 'Astaghfi',
		'exhale'    => 'rullāh',
		'note'      => 'The polish — the Prophet ﷺ sought forgiveness 70+ times a day',
		'breath_s'  => 8,
	],
	[
		'key'       => 'salawat',
		'arabic'    => 'صَلَّى ٱللَّٰهُ عَلَيْهِ وَسَلَّم',
		'translit'  => 'Ṣalla-llāhu ʿalayhi wa sallam',
		'meaning'   => 'Peace and blessings upon the Prophet ﷺ',
		'inhale'    => 'Ṣalla-llāhu',
		'exhale'    => 'ʿalayhi wa sallam',
		'note'      => 'Salawat — every blessing on him returns to you tenfold',
		'breath_s'  => 9,
	],
];

// Durations (minutes)
$la_durations = [ 3, 7, 11, 21 ];

// Modes — the stations of dhikr in Sufi sciences
$la_modes = [
	[ 'key' => 'lisani', 'label' => 'Tongue', 'desc' => 'Audible, with recitation playing' ],
	[ 'key' => 'qalbi',  'label' => 'Heart',  'desc' => 'Silent, breath only — the dhikr enters the heart' ],
	[ 'key' => 'sirri',  'label' => 'Secret', 'desc' => 'No Arabic shown — pure presence' ],
];

// Wisdom — load + pick three (one for landing, rest rotate during session)
$la_wisdom = [];
$wisdom_path = LA_DIR . 'inc/data/dhikr-wisdom.json';
if ( file_exists( $wisdom_path ) ) {
	$la_wisdom = json_decode( file_get_contents( $wisdom_path ), true ) ?: [];
}
$la_wisdom_landing = $la_wisdom ? $la_wisdom[ array_rand( $la_wisdom ) ] : null;

get_header();
?>
<main class="la-app la-app--dhikr-meditate" data-dhikr-app>

	<!-- ─── LANDING — choose phrase + duration + mode ─── -->
	<section class="la-dhikr-landing" data-dhikr-scene="landing">

		<!-- Wisdom hero — rotates on each page load -->
		<div class="la-dhikr-wisdom" data-dhikr-wisdom>
			<?php if ( $la_wisdom_landing ) : ?>
				<blockquote class="la-dhikr-wisdom-quote">"<?php echo esc_html( $la_wisdom_landing['quote'] ); ?>"</blockquote>
				<cite class="la-dhikr-wisdom-cite">— <?php echo esc_html( $la_wisdom_landing['speaker'] ); ?> · <span><?php echo esc_html( $la_wisdom_landing['source'] ); ?></span></cite>
			<?php endif; ?>
		</div>

		<!-- Phrase selector — small horizontal scroll of cards -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Choose your dhikr</h2>
			<div class="la-dhikr-phrase-list" data-phrase-list role="radiogroup" aria-label="Dhikr phrase">
				<?php foreach ( $la_phrases as $i => $p ) : ?>
					<button type="button"
						class="la-dhikr-phrase-card <?php echo $i === 0 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 0 ? 'true' : 'false'; ?>"
						data-phrase-key="<?php echo esc_attr( $p['key'] ); ?>"
						data-phrase='<?php echo esc_attr( wp_json_encode( $p ) ); ?>'>
						<span class="la-dhikr-phrase-arabic" dir="rtl" lang="ar"><?php echo esc_html( $p['arabic'] ); ?></span>
						<span class="la-dhikr-phrase-translit"><?php echo esc_html( $p['translit'] ); ?></span>
						<span class="la-dhikr-phrase-note"><?php echo esc_html( $p['note'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Duration -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Duration</h2>
			<div class="la-dhikr-duration-row" data-duration-list role="radiogroup" aria-label="Duration">
				<?php foreach ( $la_durations as $i => $m ) : ?>
					<button type="button"
						class="la-dhikr-duration-pill <?php echo $i === 1 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 1 ? 'true' : 'false'; ?>"
						data-duration="<?php echo (int) $m; ?>">
						<?php echo (int) $m; ?> <span>min</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Mode (station of dhikr) -->
		<div class="la-dhikr-section">
			<h2 class="la-dhikr-section-label">Station</h2>
			<div class="la-dhikr-mode-list" data-mode-list role="radiogroup" aria-label="Mode">
				<?php foreach ( $la_modes as $i => $m ) : ?>
					<button type="button"
						class="la-dhikr-mode-card <?php echo $i === 1 ? 'is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $i === 1 ? 'true' : 'false'; ?>"
						data-mode="<?php echo esc_attr( $m['key'] ); ?>">
						<strong><?php echo esc_html( $m['label'] ); ?></strong>
						<span><?php echo esc_html( $m['desc'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<button type="button" class="la-dhikr-begin" data-action="begin-dhikr">
			<span>Begin</span>
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l8 6-8 6V6z" fill="currentColor"/></svg>
		</button>

		<p class="la-dhikr-landing-footnote">
			Dhikr is the polish of the heart. Let the breath guide you in.
		</p>
	</section>

	<!-- ─── ACTIVE SESSION — the heart pulses with the breath ─── -->
	<section class="la-dhikr-session" data-dhikr-scene="session" hidden>

		<!-- Slim header — phrase + countdown -->
		<div class="la-dhikr-session-head">
			<button type="button" class="la-dhikr-back" data-action="end-session" aria-label="End session">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
			</button>
			<div class="la-dhikr-session-phrase" data-active-phrase>—</div>
			<div class="la-dhikr-session-timer" data-active-timer>—:—</div>
		</div>

		<!-- The breath circle: expands on inhale, contracts on exhale.
		     CSS animation drives the visual; JS drives the phrase text. -->
		<div class="la-breath" data-breath-ring>
			<div class="la-breath-glow"></div>
			<div class="la-breath-circle">
				<div class="la-breath-inner">
					<div class="la-breath-arabic" data-breath-arabic dir="rtl" lang="ar">—</div>
					<div class="la-breath-cue" data-breath-cue>Settle</div>
				</div>
			</div>
		</div>

		<!-- Soft guidance below — rotates -->
		<div class="la-dhikr-guidance" data-dhikr-guidance>—</div>

		<!-- Progress arc -->
		<div class="la-dhikr-progress" aria-hidden="true">
			<div class="la-dhikr-progress-fill" data-progress-fill></div>
		</div>
	</section>

	<!-- ─── COMPLETION — reflection, not celebration ─── -->
	<section class="la-dhikr-complete" data-dhikr-scene="complete" hidden>
		<div class="la-dhikr-complete-glow"></div>
		<div class="la-dhikr-complete-inner">
			<div class="la-dhikr-complete-arabic" dir="rtl" lang="ar">وَلَذِكْرُ ٱللَّهِ أَكْبَرُ</div>
			<div class="la-dhikr-complete-translit">Wa la dhikru-llāhi akbar</div>
			<div class="la-dhikr-complete-meaning">"And the remembrance of Allah is greater" — Quran 29:45</div>
			<div class="la-dhikr-complete-actions">
				<button type="button" class="la-dhikr-secondary" data-action="reset-session">Settle longer</button>
				<button type="button" class="la-dhikr-primary" data-action="return-home">Return</button>
			</div>
		</div>
	</section>

	<!-- ─── Data embedded for JS (phrases + wisdom) ─── -->
	<script id="la-dhikr-config" type="application/json">
		<?php echo wp_json_encode( [ 'phrases' => $la_phrases, 'wisdom' => $la_wisdom ] ); ?>
	</script>

</main>
<?php
get_footer();
