<?php
/**
 * Duas — left emoji sidebar with browsable categories.
 *
 * Mobile-first: vertical rail of emoji buttons on the left (one per
 * category), main pane on the right with scrollable cards. Tap an
 * emoji to switch categories instantly.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$t = LA_DB::tables();
$duas = $wpdb->get_results( "SELECT * FROM {$t['duas']} ORDER BY sort_order, id" );

// ─── Wave 109: Audio read-along map (slug → array of Qur'anic ayah keys) ───
// User feedback: "i have autism, i find it hard to just read i get bored.
// i need the read along too."
//
// For Qur'anic content we use everyayah.com's free Alafasy MP3s:
//   https://everyayah.com/data/Alafasy_128kbps/{surah:03}{ayah:03}.mp3
//
// Multi-ayah duas (full surahs, the 3 Quls combined, opening Baqarah)
// list each ayah key in order — the player chains them via the audio
// element's `onended` event so the recitation flows ayah-by-ayah without
// gaps. Single-ayah duas (Ayatul Kursi) get a one-element array.
//
// We only list Qur'anic items here. Hadith-derived duas (Jibreel's
// ruqyah, Allahumma Rabban-nas, kalimat tammah, sayyid-al-istighfar…)
// have no canonical reciter audio that's free + reliable, so those
// cards just don't render a Listen button. We'd rather show no button
// than a broken one.
$la_dua_audio = [
	// Ruqyah (Wave 108 entries)
	'ruqyah-fatiha'          => [ '001001','001002','001003','001004','001005','001006','001007' ],
	'ruqyah-ayatul-kursi'    => [ '002255' ],
	'ruqyah-last-baqarah'    => [ '002285','002286' ],
	'ruqyah-baqarah-opening' => [ '002001','002002','002003','002004','002005' ],
	'ruqyah-ikhlas'          => [ '112001','112002','112003','112004' ],
	'ruqyah-falaq'           => [ '113001','113002','113003','113004','113005' ],
	'ruqyah-nas'             => [ '114001','114002','114003','114004','114005','114006' ],

	// The 3 Quls also appear under Morning / Evening / Sleep — same audio
	// chain because they're the same Qur'an.
	'morning-three-quls'     => [ '112001','112002','112003','112004','113001','113002','113003','113004','113005','114001','114002','114003','114004','114005','114006' ],
	'evening-three-quls'     => [ '112001','112002','112003','112004','113001','113002','113003','113004','113005','114001','114002','114003','114004','114005','114006' ],
	'sleep-three-quls-blow'  => [ '112001','112002','112003','112004','113001','113002','113003','113004','113005','114001','114002','114003','114004','114005','114006' ],
];

// Group by category
$by_cat = [];
foreach ( $duas as $d ) {
	$by_cat[ $d->category ][] = $d;
}

// My Ameens for this identity
$user_id    = get_current_user_id() ?: null;
// Wave 68: stable identity if signed in.
$session_id = function_exists( 'la_tracking_session_id' ) ? la_tracking_session_id() : ( function_exists( 'la_get_or_set_session_id' ) ? la_get_or_set_session_id() : null );
$identity   = $user_id ? ( 'u' . (int) $user_id ) : ( $session_id ? ( ( strncmp( $session_id, 'e', 1 ) === 0 ) ? $session_id : 's' . $session_id ) : '' );
$my_ameen = [];
if ( $identity && $duas ) {
	$rows = $wpdb->get_col( $wpdb->prepare(
		"SELECT dua_id FROM {$t['dua_ameen']} WHERE identity = %s", $identity
	) );
	$my_ameen = array_flip( array_map( 'intval', $rows ) );
}

// Categories — ordered by daily relevance. Each: emoji, short label, long label.
// Wave 108: added Ruqyah — protective Qur'an + supplications against
// sihr (magic), ayn (evil eye), hasad (envy), and jinn interference.
// The category renders a knowledge intro card on top so the user
// learns the prophetic method, not just the words.
// Wave 115: each category now carries its own colour + monochrome SVG
// icon path. The rail renders coloured tiles (background = category
// colour, icon = white line SVG) to match the Claude-Design look the
// user pointed to. Emojis stay in the entry for screen-reader/title
// fallback but the visible icon is the SVG.
$cats = [
	'morning'    => [
		'emoji'  => '🌅', 'label' => 'Morning',   'sub' => 'After Fajr',
		'colour' => '#e8a956',
		'icon'   => '<path d="M17 18a5 5 0 0 0-10 0"/><line x1="12" y1="9" x2="12" y2="2"/><line x1="4.22" y1="10.22" x2="5.64" y2="11.64"/><line x1="1" y1="18" x2="3" y2="18"/><line x1="21" y1="18" x2="23" y2="18"/><line x1="18.36" y1="11.64" x2="19.78" y2="10.22"/><line x1="23" y1="22" x2="1" y2="22"/><polyline points="8 6 12 2 16 6"/>',
	],
	'evening'    => [
		'emoji'  => '🌙', 'label' => 'Evening',   'sub' => 'After Maghrib',
		'colour' => '#5b6dad',
		'icon'   => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
	],
	'ruqyah'     => [
		'emoji'  => '🛡️', 'label' => 'Ruqyah',   'sub' => 'Quranic protection',
		'colour' => '#3e8e6e',
		'icon'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
	],
	'worry'      => [
		'emoji'  => '💗', 'label' => 'Anxiety',   'sub' => 'When the heart is heavy',
		'colour' => '#c97694',
		'icon'   => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
	],
	'general'    => [
		'emoji'  => '⭐', 'label' => 'Daily',     'sub' => 'For every day',
		'colour' => '#d4af37',
		'icon'   => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
	],
	'waking'     => [
		'emoji'  => '☀️', 'label' => 'Waking',    'sub' => 'On opening your eyes',
		'colour' => '#e08549',
		'icon'   => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
	],
	'sleep'      => [
		'emoji'  => '✨', 'label' => 'Sleep',     'sub' => 'As you lay down',
		'colour' => '#8b7bc4',
		'icon'   => '<path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>',
	],
	'food'       => [
		'emoji'  => '🍽️', 'label' => 'Food',      'sub' => 'Before & after meals',
		'colour' => '#4a8c5e',
		'icon'   => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
	],
	'travel'     => [
		'emoji'  => '🛣️', 'label' => 'Travel',    'sub' => 'On the road',
		'colour' => '#3e9aa8',
		'icon'   => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
	],
	'sickness'   => [
		'emoji'  => '🤲', 'label' => 'Sickness',  'sub' => 'When health falters',
		'colour' => '#8b6cb8',
		'icon'   => '<path d="M9 11V6a2 2 0 0 1 4 0v5"/><path d="M9 11V4a2 2 0 0 1 4 0v7"/><path d="M13 11V5a2 2 0 0 1 4 0v6"/><path d="M17 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.5 0-4.5-1-6-2.5L2 17a2 2 0 0 1 3-2.5L8 17V6a2 2 0 0 1 4 0"/>',
	],
	'gratitude'  => [
		'emoji'  => '🌸', 'label' => 'Gratitude', 'sub' => 'For blessings',
		'colour' => '#dc6c8d',
		'icon'   => '<circle cx="12" cy="12" r="3"/><path d="M12 9a3 3 0 0 1 0-6 3 3 0 0 1 0 6z"/><path d="M12 21a3 3 0 0 1 0-6 3 3 0 0 1 0 6z"/><path d="M9 12a3 3 0 0 1-6 0 3 3 0 0 1 6 0z"/><path d="M21 12a3 3 0 0 1-6 0 3 3 0 0 1 6 0z"/>',
	],
];

// Filter to only categories that have duas
$active_cats = [];
foreach ( $cats as $key => $meta ) {
	if ( ! empty( $by_cat[ $key ] ) ) {
		$active_cats[ $key ] = $meta;
	}
}

$first_cat = array_key_first( $active_cats );

get_header();
?>
<main class="la-app la-app--duas-sidebar">

	<?php /* Wave 115: rail buttons are now coloured tiles (per Claude-
	     Design). Each category has its own colour + white SVG icon —
	     the tile becomes the visual identity for that category, the
	     label and count sit under it. */ ?>
	<aside class="la-duas-rail" aria-label="Dua categories">
		<?php $i = 0; foreach ( $active_cats as $key => $meta ) :
			$is_active_cat = ( $key === $first_cat );
		?>
			<button type="button"
				class="la-duas-rail-btn <?php echo $is_active_cat ? 'is-active' : ''; ?>"
				data-cat="<?php echo esc_attr( $key ); ?>"
				style="--cat-colour: <?php echo esc_attr( $meta['colour'] ?? '#888' ); ?>;"
				aria-label="<?php echo esc_attr( $meta['label'] ); ?>">
				<span class="la-duas-rail-tile" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $meta['icon'] ?? ''; ?></svg>
				</span>
				<span class="la-duas-rail-label"><?php echo esc_html( $meta['label'] ); ?></span>
				<span class="la-duas-rail-count"><?php echo count( $by_cat[ $key ] ); ?></span>
			</button>
		<?php $i++; endforeach; ?>
	</aside>

	<?php /* Wave 113: pane head dropped — user said "title at the top is way
	     too big". The category context (icon + name + sub-label) moved
	     down into the inline player at the bottom of the pane. The
	     progress strip is hidden too — its info ("3 of 8 read today")
	     now lives inline next to the player title as a small pip. */ ?>
	<!-- Right pane: one card visible at a time + inline player at bottom -->
	<div class="la-duas-pane">

		<!-- Card lists — one section per category, only active is visible -->
		<div class="la-duas-lists">
			<?php foreach ( $active_cats as $key => $meta ) : ?>
				<section class="la-duas-list <?php echo $key === $first_cat ? 'is-active' : ''; ?>" data-cat-section="<?php echo esc_attr( $key ); ?>">

					<?php // Wave 108: Ruqyah knowledge intro. Renders ONCE at the top
					// of the ruqyah section. Frames the prophetic protection — what
					// it addresses (sihr, ayn, hasad, jinn, heart hardness), the
					// method (cup, recite, blow, wipe), the timing (morning/evening/
					// sleep, on signs of harm), and the sincerity that it's Allah
					// who cures. The words are the means; the cure is His.
					//
					// Wave 113: the knowledge card is the FIRST visible card in
					// the Ruqyah category — user lands on the intro, taps next /
					// swipes to start reciting. Data attrs added so the player's
					// title row reads sensibly when this is the current card. ?>
					<?php if ( $key === 'ruqyah' ) : ?>
						<article class="la-dua la-dua--knowledge la-ruqyah-intro is-current"
							data-cat="ruqyah"
							data-title="Ruqyah — for what cannot be seen"
							data-audio-keys=""
							data-translit=""
							data-ameen-count="0"
							data-is-amened="0">
							<header class="la-ruqyah-intro-head">
								<div class="la-ruqyah-intro-eyebrow">The Prophet's Protection</div>
								<h2 class="la-ruqyah-intro-title">Ruqyah — for what cannot be seen</h2>
								<p class="la-ruqyah-intro-lead">Authentic Quranic recitation and prophetic supplication, used to seek Allah's protection and removal of spiritual harm. The cure is from Him alone. The words are the means.</p>
							</header>

							<div class="la-ruqyah-intro-pillars">
								<div class="la-ruqyah-pillar">
									<div class="la-ruqyah-pillar-glyph">①</div>
									<h3>What it addresses</h3>
									<ul class="la-ruqyah-pillar-list">
										<li><b>Sihr</b> — magic, witchcraft</li>
										<li><b>Ayn</b> — the evil eye, harm from envious glance</li>
										<li><b>Hasad</b> — envy from the heart of the envier</li>
										<li><b>Jinn</b> — interference, waswasah, oppression</li>
										<li><b>Qaswah</b> — hardness of heart, restlessness without cause</li>
									</ul>
								</div>

								<div class="la-ruqyah-pillar">
									<div class="la-ruqyah-pillar-glyph">②</div>
									<h3>The prophetic method</h3>
									<ol class="la-ruqyah-pillar-list">
										<li>Make intention (niyyah) for protection and cure</li>
										<li>Cup your hands together</li>
										<li>Recite the surahs &amp; supplications below into them</li>
										<li>Blow gently into the hands</li>
										<li>Wipe over the body — head &amp; face first, then as much as you can reach</li>
										<li>Repeat the wipe three times</li>
									</ol>
									<p class="la-ruqyah-pillar-cite">Bukhari 5017 · Aisha (RA) describing the Prophet ﷺ before sleep</p>
								</div>

								<div class="la-ruqyah-pillar">
									<div class="la-ruqyah-pillar-glyph">③</div>
									<h3>When</h3>
									<ul class="la-ruqyah-pillar-list">
										<li>Morning &amp; evening (after Fajr, after Maghrib)</li>
										<li>Before sleep</li>
										<li>When you sense affliction — heaviness, fatigue without cause, recurring bad dreams, sudden distress</li>
										<li>Over food, water, or olive oil for the sick</li>
									</ul>
								</div>

								<div class="la-ruqyah-pillar">
									<div class="la-ruqyah-pillar-glyph">④</div>
									<h3>Sincerity</h3>
									<p class="la-ruqyah-pillar-prose">"It is He who guides me through every step, and when I am ill, it is He who cures me." <span class="la-ruqyah-pillar-ref">(Quran 26:78–80)</span></p>
									<p class="la-ruqyah-pillar-prose">Do not fear; trust. The recitation itself softens and steadies the heart. Cure follows trust, not effort.</p>
								</div>
							</div>

							<footer class="la-ruqyah-intro-foot">
								<div class="la-ruqyah-intro-tag">Below: the surahs &amp; duas the Prophet ﷺ used &mdash; in order. Recite each with the method above.</div>
							</footer>
						</article>
					<?php endif; ?>

					<?php $dua_idx = 0; $cat_total = count( $by_cat[ $key ] ); foreach ( $by_cat[ $key ] as $d ) :
						$is_amened = isset( $my_ameen[ (int) $d->id ] );
						$dua_idx++;
						// Wave 113: only the first card in each category is rendered
						// as "current"; JS swaps which one is current as the user
						// taps prev/next or swipes. EXCEPTION: in Ruqyah, the
						// knowledge intro is the visible first card, so the first
						// dua here must NOT also claim is-current.
						$is_current = ( $dua_idx === 1 && $key !== 'ruqyah' );
						// Wave 113: also stash audio keys on the article so the
						// player can pick them up when this becomes the current
						// card. Empty if no Qur'anic audio — JS will fall back to
						// TTS for those.
						$audio_keys_attr = ! empty( $la_dua_audio[ $d->slug ] )
							? esc_attr( implode( ',', $la_dua_audio[ $d->slug ] ) )
							: '';
					?>
						<article class="la-dua <?php echo $is_current ? 'is-current' : ''; ?>"
							data-dua-id="<?php echo (int) $d->id; ?>"
							data-dua-slug="<?php echo esc_attr( $d->slug ); ?>"
							data-cat="<?php echo esc_attr( $key ); ?>"
							data-idx="<?php echo $dua_idx; ?>"
							data-total="<?php echo $cat_total; ?>"
							data-title="<?php echo esc_attr( $d->title ); ?>"
							data-translit="<?php echo esc_attr( $d->transliteration ?? '' ); ?>"
							data-audio-keys="<?php echo $audio_keys_attr; ?>"
							data-ameen-count="<?php echo (int) $d->ameen_count; ?>"
							data-is-amened="<?php echo $is_amened ? '1' : '0'; ?>">
							<?php // Wave 113: step pip removed from card — moves to the
							// bottom player's title row as "3 / 8" alongside the
							// category context. ?>

							<?php /* Wave 112: header now carries the gold source pill
							     right under the title (Claude-Design pattern). Source
							     was previously footer-level — moving it up gives the
							     dua an immediate context line (e.g. "BUKHARI 6306")
							     before the reciter starts. */ ?>
							<header class="la-dua-head">
								<h2 class="la-dua-title"><?php echo esc_html( $d->title ); ?></h2>
								<?php if ( ! empty( $d->source ) ) : ?>
									<span class="la-dua-source-pill"><?php echo esc_html( strtoupper( $d->source ) ); ?></span>
								<?php endif; ?>
								<?php if ( (int) $d->repeat_count > 1 ) : ?>
									<span class="la-dua-repeat" title="Recite this many times">×<?php echo (int) $d->repeat_count; ?></span>
								<?php endif; ?>
							</header>

							<?php /* Wave 112: interlinear render — each ayah/clause gets
							     its Arabic on top, transliteration directly below it,
							     so the user's eye doesn't have to scan an entire wall
							     of Arabic then re-scan a wall of translit. We split:
							        Arabic   on " ۞ "
							        Translit on " / "
							     If counts match and there are 2+ pieces, render
							     interlinear. Otherwise fall back to the original
							     two-blob layout (no data is lost — this just keeps
							     older duas without per-line separators readable). */ ?>
							<?php
							$ar_lines = array_values( array_filter( array_map( 'trim', explode( '۞', $d->arabic ?? '' ) ) ) );
							$tr_lines = array_values( array_filter( array_map( 'trim', explode( '/',  $d->transliteration ?? '' ) ) ) );
							$can_interlinear = ( count( $ar_lines ) >= 2 && count( $ar_lines ) === count( $tr_lines ) );
							?>
							<div class="la-dua-body">
								<?php if ( $can_interlinear ) : ?>
									<div class="la-dua-lines">
										<?php for ( $li = 0; $li < count( $ar_lines ); $li++ ) : ?>
											<div class="la-dua-line">
												<div class="la-dua-line-ar ar" dir="rtl" lang="ar"><?php echo esc_html( $ar_lines[ $li ] ); ?></div>
												<?php if ( ! empty( $tr_lines[ $li ] ) ) : ?>
													<div class="la-dua-line-tr"><?php echo esc_html( $tr_lines[ $li ] ); ?></div>
												<?php endif; ?>
											</div>
										<?php endfor; ?>
									</div>
								<?php else : ?>
									<div class="la-dua-arabic ar" dir="rtl" lang="ar"><?php echo esc_html( $d->arabic ); ?></div>
									<?php if ( ! empty( $d->transliteration ) ) : ?>
										<div class="la-dua-translit"><?php echo esc_html( $d->transliteration ); ?></div>
									<?php endif; ?>
								<?php endif; ?>

								<?php /* Wave 112: Meaning is now collapsible — the dua
								     itself (Arabic + translit) is the recitation surface;
								     the English meaning is one tap away when you want it.
								     Stops the meaning paragraph from dominating the card. */ ?>
								<?php if ( ! empty( $d->meaning ) ) : ?>
									<details class="la-dua-meaning-toggle">
										<summary class="la-dua-meaning-summary">
											<span>MEANING</span>
											<svg class="la-dua-meaning-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
										</summary>
										<p class="la-dua-meaning"><?php echo esc_html( $d->meaning ); ?></p>
									</details>
								<?php endif; ?>
							</div>

							<?php // Wave 113: card foot removed — Copy and Share dropped
							// per user, Listen + Ameen + Mark-as-read all moved into
							// the inline player at the bottom of the pane. The card
							// is now purely a reading surface: source pill, title,
							// interlinear lines, collapsible meaning. That's it.
							//
							// Hidden legacy buttons live below — the existing
							// loveallah.js Ameen / tick-day persistence handler
							// stays in charge. The player JS synthesizes clicks on
							// these so we don't have to re-implement the API calls. ?>
							<div class="la-dua-hidden-actions" hidden aria-hidden="true">
								<button type="button"
									class="la-dua-btn <?php echo $is_amened ? 'is-active' : ''; ?>"
									data-action="ameen"
									data-id="<?php echo (int) $d->id; ?>"
									aria-pressed="<?php echo $is_amened ? 'true' : 'false'; ?>"
									tabindex="-1">
									<span class="la-dua-btn-count" data-ameen-count><?php echo (int) $d->ameen_count; ?></span>
								</button>
								<button type="button"
									data-action="tick-day"
									data-id="<?php echo (int) $d->id; ?>"
									data-cat="<?php echo esc_attr( $key ); ?>"
									tabindex="-1"></button>
							</div>
						</article>
					<?php endforeach; ?>


				</section>
			<?php endforeach; ?>
		</div>

		<?php /* Wave 113: inline player — lives INSIDE the pane so it
		     sits over the card area only, not over the left rail.
		     Always present (per user: "should have play button on all
		     of the duas"). Title row carries the category context that
		     used to live in the pane-head (icon · name · sub) plus the
		     step pip (3 / 8). Controls row: prev · big gold play · next ·
		     speed · tick (mark-read) · ameen. Listen / Copy / Share
		     dropped from card footers; the player is the single action
		     surface. */ ?>
		<div class="la-duas-player la-duas-player--inline" data-duas-player>
			<div class="la-duas-player-title-row">
				<span class="la-duas-player-cat-icon" data-cat-icon><?php echo $active_cats[ $first_cat ]['emoji']; ?></span>
				<div class="la-duas-player-cat-text">
					<span class="la-duas-player-dua-title" data-duas-player-title><?php echo esc_html( $by_cat[ $first_cat ][0]->title ?? '' ); ?></span>
					<span class="la-duas-player-cat-sub">
						<span data-cat-title><?php echo esc_html( $active_cats[ $first_cat ]['label'] ); ?></span>
						<span class="dot">·</span>
						<span data-cat-sub><?php echo esc_html( $active_cats[ $first_cat ]['sub'] ); ?></span>
					</span>
				</div>
				<span class="la-duas-player-step">
					<span data-current-idx>1</span><span class="slash">/</span><span data-current-total><?php echo count( $by_cat[ $first_cat ] ); ?></span>
				</span>
			</div>

			<div class="la-duas-player-controls-row">
				<button type="button" class="la-duas-player-btn la-duas-player-btn--nav" data-duas-prev aria-label="Previous dua">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
				</button>
				<button type="button" class="la-duas-player-btn la-duas-player-btn--play" data-duas-player-toggle aria-label="Play or pause">
					<svg data-duas-player-play-icon width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="margin-left:2px"><path d="M9 6l8 6-8 6V6z"/></svg>
					<svg data-duas-player-pause-icon width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" hidden><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
				</button>
				<button type="button" class="la-duas-player-btn la-duas-player-btn--nav" data-duas-next aria-label="Next dua">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
				<button type="button" class="la-duas-player-btn la-duas-player-btn--speed" data-duas-player-speed-cycle aria-label="Playback speed">1×</button>
				<button type="button" class="la-duas-player-btn la-duas-player-btn--tick" data-duas-mark-read aria-label="Mark as read today">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
				</button>
				<button type="button" class="la-duas-player-btn la-duas-player-btn--ameen" data-duas-ameen aria-label="Ameen">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22c-3-2-5-4-5-7 0-3 1.5-4 3-4s2 1 2 1 1-1 2.5-1 2.5 1 2.5 4-2 5-5 7z"/></svg>
					<span class="la-duas-player-ameen-count" data-duas-player-ameen-count>0</span>
				</button>
			</div>

			<div class="la-duas-player-progress" aria-hidden="true">
				<div class="la-duas-player-progress-fill" data-duas-player-progress style="width:0%"></div>
			</div>
			<audio data-duas-player-audio preload="none"></audio>
		</div>

	</div><?php // close .la-duas-pane ?>

	<script id="la-duas-cats" type="application/json">
		<?php echo wp_json_encode( $active_cats ); ?>
	</script>
	<script id="la-duas-audio" type="application/json">
		<?php echo wp_json_encode( $la_dua_audio ); ?>
	</script>

	<script>
	/* Wave 113: Duas player + card-stepping model.

	   Major restructure per user feedback:
	     - One card visible at a time per category (no scroll-snap).
	     - Navigate with prev/next arrows in the player OR swipe L/R on
	       the card area.
	     - Player is INLINE inside the pane (not over the rail).
	     - The Listen/Copy/Share/Mark-as-read buttons are gone from the
	       card foot — all live in the player now.
	     - Play works on ALL duas: Qur'anic ones stream Alafasy from
	       everyayah.com; non-Qur'anic ones use the browser's Speech
	       Synthesis API reading the transliteration. The play button
	       is therefore always meaningful — never a dead button.

	   Read-along (Wave 110): when a Qur'anic dua is playing, the current
	   ayah lights up gold. In TTS mode there's no per-line timing so we
	   highlight the whole first line. */
	(function() {
		const player      = document.querySelector('[data-duas-player]');
		if ( ! player ) return;
		const cats        = JSON.parse( document.getElementById('la-duas-cats').textContent );
		const audioMap    = JSON.parse( document.getElementById('la-duas-audio').textContent );
		const audio       = player.querySelector('[data-duas-player-audio]');
		const titleEl     = player.querySelector('[data-duas-player-title]');
		const catIconEl   = player.querySelector('[data-cat-icon]');
		const catTitleEl  = player.querySelector('[data-cat-title]');
		const catSubEl    = player.querySelector('[data-cat-sub]');
		const idxEl       = player.querySelector('[data-current-idx]');
		const totalEl     = player.querySelector('[data-current-total]');
		const ameenCountEl= player.querySelector('[data-duas-player-ameen-count]');
		const playBtn     = player.querySelector('[data-duas-player-toggle]');
		const playIcon    = player.querySelector('[data-duas-player-play-icon]');
		const pauseIcon   = player.querySelector('[data-duas-player-pause-icon]');
		const prevBtn     = player.querySelector('[data-duas-prev]');
		const nextBtn     = player.querySelector('[data-duas-next]');
		const speedBtn    = player.querySelector('[data-duas-player-speed-cycle]');
		const tickBtn     = player.querySelector('[data-duas-mark-read]');
		const ameenBtn    = player.querySelector('[data-duas-ameen]');
		const progress    = player.querySelector('[data-duas-player-progress]');

		let queue = [];        // ayah keys still to play (after current)
		let allKeys = [];      // full ordered list for the active dua
		let cursor = 0;        // index into allKeys of the currently-playing ayah
		let currentKey = null;
		let playing = false;
		let mode    = null;    // 'audio' (everyayah MP3) | 'tts' (browser TTS)
		let speed = parseFloat( localStorage.getItem('la_duas_speed') || '1' ) || 1;
		const speedCycle = [ 0.75, 1, 1.25 ];

		/* Wave 110: read-along line state.
		   activeCard       = the .la-dua DOM node we've wrapped lines on
		   originalArHTML   = its arabic innerHTML before we replaced it
		   lineNodes        = the array of <span class="la-dua-arabic-line">
		   keyToLine        = parallel to allKeys; key index → line index
		   The wrap/restore pattern means we never permanently mutate the
		   page — switching dua or closing the player puts the original
		   text right back. */
		let activeCard       = null;
		let originalArHTML   = null;
		let lineNodes        = [];
		let keyToLine        = [];

		const urlFor = (key) => `https://everyayah.com/data/Alafasy_128kbps/${key}.mp3`;
		const ayahLabel = (key) => {
			const s = parseInt(key.substring(0,3), 10);
			const a = parseInt(key.substring(3), 10);
			return `${s}:${a}`;
		};

		/* Escape user-visible text for safe innerHTML insertion. */
		function escHTML(s) {
			return String(s).replace(/[&<>"']/g, c => ({
				'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
			}[c]));
		}

		/* Wave 110 → Wave 112: read-along finds lines in TWO shapes:
		     A) Interlinear (Wave 112+ markup): each ayah already has its
		        own .la-dua-line-ar element. Just collect them — no DOM
		        mutation needed, no innerHTML to restore.
		     B) Single-blob fallback (older duas without per-line breaks):
		        the original .la-dua-arabic exists; we split its text on
		        ۞ and wrap each segment in a span. We cache the original
		        innerHTML so closing the player restores the text exactly.
		   The activeMode flag tells restoreReadAlong which cleanup to do. */
		let activeMode = null;   // 'interlinear' | 'blob' | null
		function prepareReadAlong(card, keys) {
			if ( activeCard === card ) {
				keyToLine = buildKeyToLineMap(keys, lineNodes.length);
				return;
			}
			restoreReadAlong();
			if ( ! card ) return;

			// Mode A: interlinear
			const interlinear = card.querySelectorAll('.la-dua-line-ar');
			if ( interlinear.length >= 1 ) {
				lineNodes  = Array.from(interlinear);
				activeMode = 'interlinear';
				activeCard = card;
				keyToLine  = buildKeyToLineMap(keys, lineNodes.length);
				return;
			}

			// Mode B: single-blob fallback — split + wrap
			const ar = card.querySelector('.la-dua-arabic');
			if ( ! ar ) return;
			originalArHTML = ar.innerHTML;
			const text  = ar.textContent.trim();
			const parts = text.split('۞').map(s => s.trim()).filter(Boolean);
			ar.innerHTML = ( parts.length < 2 )
				? `<span class="la-dua-arabic-line">${escHTML(text)}</span>`
				: parts.map(p => `<span class="la-dua-arabic-line">${escHTML(p)}</span>`)
					.join(' <span class="la-dua-arabic-sep" aria-hidden="true">۞</span> ');
			lineNodes  = Array.from( ar.querySelectorAll('.la-dua-arabic-line') );
			activeMode = 'blob';
			activeCard = card;
			keyToLine  = buildKeyToLineMap(keys, lineNodes.length);
		}
		function restoreReadAlong() {
			if ( ! activeCard ) return;
			if ( activeMode === 'interlinear' ) {
				// No DOM mutation happened — just clear the highlight.
				lineNodes.forEach( l => l.classList.remove('is-active') );
			} else if ( activeMode === 'blob' ) {
				const ar = activeCard.querySelector('.la-dua-arabic');
				if ( ar && originalArHTML !== null ) ar.innerHTML = originalArHTML;
			}
			activeCard     = null;
			activeMode     = null;
			originalArHTML = null;
			lineNodes      = [];
			keyToLine      = [];
		}
		/* If keys.length === lineCount it's a 1:1 mapping (full surah,
		   one ayah per visible line). Otherwise group by surah prefix
		   (the 3 Quls combined is 15 keys → 3 lines, one per surah). */
		function buildKeyToLineMap(keys, lineCount) {
			if ( keys.length === lineCount ) {
				return keys.map((_, i) => i);
			}
			const surahs = [];
			return keys.map(k => {
				const s = k.substring(0, 3);
				let idx = surahs.indexOf(s);
				if ( idx === -1 ) { surahs.push(s); idx = surahs.length - 1; }
				return idx;
			});
		}
		function setActiveLine(lineIdx) {
			lineNodes.forEach( (l, i) => l.classList.toggle('is-active', i === lineIdx) );
			// Scroll the active line into view inside the card if it's offscreen
			const node = lineNodes[lineIdx];
			if ( node && node.scrollIntoView ) {
				try { node.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch(_) {}
			}
		}

		/* Wave 107b lesson: SVG `.hidden = bool` doesn't reflect to the
		   attribute. Use setAttribute / removeAttribute explicitly. */
		function setHidden(el, v) {
			if ( ! el ) return;
			if ( v ) el.setAttribute('hidden', '');
			else     el.removeAttribute('hidden');
		}
		function renderPlayingIcons() {
			setHidden( playIcon,    playing );
			setHidden( pauseIcon, ! playing );
		}

		function applySpeed(v) {
			speed = v;
			audio.playbackRate = v;
			localStorage.setItem('la_duas_speed', String(v));
			if ( speedBtn ) speedBtn.textContent = v + '×';
		}
		function cycleSpeed() {
			const i = speedCycle.indexOf(speed);
			applySpeed( speedCycle[ ( i + 1 ) % speedCycle.length ] );
		}

		/* ── Audio-mode (Qur'anic) playback ────────────────────────────
		   Same Wave 109/110 pipeline: walk through allKeys, swap src on
		   each ayah, highlight the line that ayah belongs to. */
		function playKey(key) {
			currentKey = key;
			audio.src = urlFor(key);
			audio.playbackRate = speed;
			const lineIdx = keyToLine[cursor];
			if ( typeof lineIdx === 'number' ) setActiveLine(lineIdx);
			audio.play().then(() => {
				playing = true; renderPlayingIcons();
			}).catch(() => {
				playing = false; renderPlayingIcons();
			});
		}

		/* ── TTS-mode (non-Qur'anic) playback ──────────────────────────
		   Browser SpeechSynthesis reads the ARABIC text itself using
		   the device's Arabic voice (iOS has Maged/Tarik built in;
		   Android has at least one ar-SA voice on most devices; macOS
		   Safari has Majed). If no Arabic voice is available, falls
		   back to English reading the transliteration so the play
		   button is never a dead button. */
		let ttsUtter = null;
		let ttsVoices = [];

		function loadVoices() {
			return new Promise( resolve => {
				if ( ! ( 'speechSynthesis' in window ) ) { return resolve([]); }
				const cur = speechSynthesis.getVoices();
				if ( cur && cur.length ) { ttsVoices = cur; return resolve(cur); }
				// Voices load async on first call in Chrome — wait for the event
				const t = setTimeout( () => {
					ttsVoices = speechSynthesis.getVoices() || [];
					resolve(ttsVoices);
				}, 800 );
				speechSynthesis.addEventListener( 'voiceschanged', () => {
					clearTimeout(t);
					ttsVoices = speechSynthesis.getVoices() || [];
					resolve(ttsVoices);
				}, { once: true } );
			});
		}

		function findArabicVoice() {
			if ( ! ttsVoices.length ) return null;
			// Prefer Saudi (most standard recitation pronunciation), then any Arabic
			return ttsVoices.find( v => v.lang && /^ar-SA/i.test(v.lang) )
				|| ttsVoices.find( v => v.lang && /^ar/i.test(v.lang) )
				|| null;
		}

		async function ttsSpeak( arabicText, translitFallback ) {
			if ( ! ( 'speechSynthesis' in window ) ) {
				playing = false; renderPlayingIcons();
				return;
			}
			try { speechSynthesis.cancel(); } catch(_){}
			await loadVoices();
			const arVoice = findArabicVoice();
			const text = ( arVoice && arabicText ) ? arabicText : ( translitFallback || arabicText || '' );
			if ( ! text ) { playing = false; renderPlayingIcons(); return; }

			ttsUtter = new SpeechSynthesisUtterance(text);
			// Arabic recitation wants a more measured pace — drop the rate
			// further so the speech doesn't blur the verse together.
			ttsUtter.rate = ( arVoice ? speed * 0.75 : speed * 0.9 );
			ttsUtter.pitch = 1;
			if ( arVoice ) {
				ttsUtter.voice = arVoice;
				ttsUtter.lang  = arVoice.lang;
			} else {
				ttsUtter.lang = 'en-US';
			}
			ttsUtter.onstart = () => { playing = true; renderPlayingIcons(); };
			ttsUtter.onend   = () => { playing = false; renderPlayingIcons(); progress.style.width = '100%'; };
			ttsUtter.onerror = (e) => {
				playing = false; renderPlayingIcons();
				// If Arabic failed (some Android browsers reject ar-SA) and
				// we have a transliteration fallback, try once more with EN.
				if ( arVoice && translitFallback && e?.error && /language|voice/i.test(e.error) ) {
					const fb = new SpeechSynthesisUtterance(translitFallback);
					fb.lang = 'en-US';
					fb.rate = speed * 0.9;
					try { speechSynthesis.speak(fb); } catch(_) {}
				}
			};
			speechSynthesis.speak(ttsUtter);
		}
		function ttsStop() {
			if ( 'speechSynthesis' in window ) {
				try { speechSynthesis.cancel(); } catch(_){}
			}
			ttsUtter = null;
		}
		// Warm the voices list so the first play doesn't lose time.
		loadVoices();

		/* ── Unified play-current ─────────────────────────────────────
		   Reads the active card, decides audio vs TTS based on whether
		   the slug has an audio-key chain, and starts playback. */
		function playCurrentCard() {
			const card = currentCard();
			if ( ! card ) return;
			stopPlayback(false);
			const slug = card.dataset.duaSlug || '';
			const keys = ( card.dataset.audioKeys || '' ).split(',').filter(Boolean);
			progress.style.width = '0%';
			if ( keys.length ) {
				// Audio mode — chain ayahs via .ended
				mode    = 'audio';
				allKeys = keys.slice();
				queue   = keys.slice(1);
				cursor  = 0;
				prepareReadAlong(card, keys);
				if ( lineNodes.length === 1 ) {
					// Single-line dua: highlight the whole thing
					setActiveLine(0);
				}
				playKey(keys[0]);
			} else {
				/* TTS mode — read the ARABIC text via the device's
				   Arabic voice. We grab the arabic from the rendered
				   card (handles both interlinear .la-dua-line-ar
				   markup and the .la-dua-arabic single-blob fallback).
				   Translit goes along as a fallback for devices with
				   no Arabic voice available. */
				mode = 'tts';
				let arabic = Array.from( card.querySelectorAll('.la-dua-line-ar') ).map( n => n.textContent.trim() ).join(' ');
				if ( ! arabic ) {
					const blob = card.querySelector('.la-dua-arabic');
					if ( blob ) arabic = blob.textContent.trim();
				}
				let translit = Array.from( card.querySelectorAll('.la-dua-line-tr') ).map( n => n.textContent.trim() ).join(' ');
				if ( ! translit ) translit = card.dataset.translit || '';
				prepareReadAlong(card, []);
				if ( lineNodes.length ) setActiveLine(0);
				ttsSpeak( arabic, translit );
			}
		}
		function stopPlayback(restore = true) {
			try { audio.pause(); } catch(_){}
			audio.removeAttribute('src');
			audio.load();
			ttsStop();
			queue = []; allKeys = []; cursor = 0; currentKey = null;
			playing = false; mode = null;
			renderPlayingIcons();
			progress.style.width = '0%';
			if ( restore ) restoreReadAlong();
		}

		// Audio events
		audio.addEventListener('ended', () => {
			if ( queue.length ) {
				cursor++;
				playKey( queue.shift() );
			} else {
				playing = false;
				renderPlayingIcons();
				progress.style.width = '100%';
				setTimeout( () => {
					lineNodes.forEach( l => l.classList.remove('is-active') );
				}, 1400 );
			}
		});
		audio.addEventListener('timeupdate', () => {
			if ( ! audio.duration ) return;
			progress.style.width = ( ( audio.currentTime / audio.duration ) * 100 ) + '%';
		});
		audio.addEventListener('pause', () => {
			if ( mode === 'audio' ) { playing = false; renderPlayingIcons(); }
		});
		audio.addEventListener('play', () => {
			if ( mode === 'audio' ) { playing = true; renderPlayingIcons(); }
		});

		/* ─────────────────────────────────────────────────────────────
		   CARD STEPPING — one card visible per category. The active
		   card has .is-current; the rest are display:none via CSS.
		   Prev/next + swipe move cursor within the current category.
		   ───────────────────────────────────────────────────────────── */
		function activeSection() {
			return document.querySelector('.la-duas-list.is-active');
		}
		function currentCards() {
			const sec = activeSection();
			return sec ? Array.from( sec.querySelectorAll('.la-dua') ) : [];
		}
		function currentCard() {
			return activeSection()?.querySelector('.la-dua.is-current') || currentCards()[0] || null;
		}
		function showCardAt(idx) {
			const cards = currentCards();
			if ( ! cards.length ) return;
			const safe = Math.max( 0, Math.min( cards.length - 1, idx ) );
			cards.forEach( (c, i) => c.classList.toggle('is-current', i === safe) );
			cards[safe]?.scrollIntoView?.({ block: 'start', behavior: 'instant' });
			renderPlayerForCurrent();
		}
		function navStep(delta) {
			const cards = currentCards();
			if ( ! cards.length ) return;
			const cur = cards.findIndex( c => c.classList.contains('is-current') );
			const next = Math.max( 0, Math.min( cards.length - 1, ( cur < 0 ? 0 : cur ) + delta ) );
			if ( next === cur ) return;
			// If currently playing, stop — user is moving on
			if ( playing ) stopPlayback();
			showCardAt(next);
		}
		function renderPlayerForCurrent() {
			const card = currentCard();
			const sec  = activeSection();
			if ( ! card || ! sec ) return;
			const catKey = sec.dataset.catSection;
			const cat    = cats[catKey] || {};
			titleEl.textContent      = card.dataset.title || '';
			if ( catIconEl  ) catIconEl.textContent  = cat.emoji || '';
			if ( catTitleEl ) catTitleEl.textContent = cat.label || '';
			if ( catSubEl   ) catSubEl.textContent   = cat.sub || '';
			const idx = Array.from( sec.querySelectorAll('.la-dua') ).indexOf(card) + 1;
			const tot = sec.querySelectorAll('.la-dua').length;
			if ( idxEl   ) idxEl.textContent   = String(idx);
			if ( totalEl ) totalEl.textContent = String(tot);
			if ( ameenCountEl ) ameenCountEl.textContent = card.dataset.ameenCount || '0';
			if ( ameenBtn ) ameenBtn.classList.toggle( 'is-active', card.dataset.isAmened === '1' );
		}

		// Player UI wiring
		playBtn.addEventListener('click', () => {
			if ( playing ) {
				if ( mode === 'audio' ) audio.pause();
				else if ( mode === 'tts' ) ttsStop(), (playing = false), renderPlayingIcons();
			} else {
				playCurrentCard();
			}
		});
		prevBtn?.addEventListener('click', () => navStep(-1));
		nextBtn?.addEventListener('click', () => navStep(+1));
		speedBtn?.addEventListener('click', cycleSpeed);
		tickBtn?.addEventListener('click', () => {
			const card = currentCard();
			if ( ! card ) return;
			// Synthesize click on the hidden legacy tick button — the
			// existing loveallah.js handler does the localStorage write
			// + progress repaint + .is-ticked class swap.
			const legacyTick = card.querySelector('[data-action="tick-day"]');
			if ( legacyTick ) legacyTick.click();
			tickBtn.classList.add('is-just-ticked');
			setTimeout(() => tickBtn.classList.remove('is-just-ticked'), 600);
			// Auto-advance to next dua after a brief beat
			setTimeout(() => navStep(+1), 450);
		});
		ameenBtn?.addEventListener('click', () => {
			const card = currentCard();
			if ( ! card ) return;
			// Synthesize click on hidden legacy ameen button — existing
			// loveallah.js handler hits ${LA.apiRoot}duas/{id}/ameen and
			// updates the count. We then re-sync the player display from
			// the legacy button's data + count element.
			const legacyAmeen = card.querySelector('[data-action="ameen"]');
			if ( legacyAmeen ) {
				legacyAmeen.click();
				// Let the legacy handler's async fetch run, then read state
				setTimeout(() => {
					const wasActive = legacyAmeen.classList.contains('is-active');
					card.dataset.isAmened = wasActive ? '1' : '0';
					const countEl = legacyAmeen.querySelector('[data-ameen-count]');
					if ( countEl ) {
						card.dataset.ameenCount = countEl.textContent;
						if ( ameenCountEl ) ameenCountEl.textContent = countEl.textContent;
					}
					ameenBtn.classList.toggle('is-active', wasActive);
				}, 50);
			}
		});

		// Swipe gestures on the card area — left swipe = next, right = prev
		(function attachSwipe() {
			const area = document.querySelector('.la-duas-lists');
			if ( ! area ) return;
			let sx = 0, sy = 0, active = false;
			area.addEventListener('pointerdown', (e) => {
				sx = e.clientX; sy = e.clientY; active = true;
			});
			area.addEventListener('pointerup', (e) => {
				if ( ! active ) return;
				active = false;
				const dx = e.clientX - sx, dy = e.clientY - sy;
				// Horizontal-dominant gesture only — leave vertical to native scroll
				if ( Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.6 ) {
					navStep( dx < 0 ? +1 : -1 );
				}
			});
			area.addEventListener('pointercancel', () => { active = false; });
		})();

		// Re-render player meta when category changes — observe the
		// existing rail-button click that swaps .is-active sections.
		document.querySelectorAll('[data-cat]').forEach( railBtn => {
			railBtn.addEventListener('click', () => {
				setTimeout(() => {
					// Reset card cursor to first in the newly-active category
					if ( playing ) stopPlayback();
					const cards = currentCards();
					cards.forEach( (c, i) => c.classList.toggle('is-current', i === 0) );
					renderPlayerForCurrent();
				}, 0);
			});
		});

		// Initial paint
		applySpeed(speed);
		renderPlayingIcons();
		renderPlayerForCurrent();
	})();
	</script>

</main>
<?php
get_footer();
