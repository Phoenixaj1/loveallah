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
$cats = [
	'morning'    => [ 'emoji' => '🌅', 'label' => 'Morning',    'sub' => 'After Fajr' ],
	'evening'    => [ 'emoji' => '🌙', 'label' => 'Evening',    'sub' => 'After Maghrib' ],
	'ruqyah'     => [ 'emoji' => '🛡️', 'label' => 'Ruqyah',     'sub' => 'Quranic protection' ],
	'worry'      => [ 'emoji' => '💗', 'label' => 'Anxiety',    'sub' => 'When the heart is heavy' ],
	'general'    => [ 'emoji' => '⭐', 'label' => 'Daily',      'sub' => 'For every day' ],
	'waking'     => [ 'emoji' => '☀️', 'label' => 'Waking',     'sub' => 'On opening your eyes' ],
	'sleep'      => [ 'emoji' => '✨', 'label' => 'Sleep',      'sub' => 'As you lay down' ],
	'food'       => [ 'emoji' => '🍽️', 'label' => 'Food',       'sub' => 'Before & after meals' ],
	'travel'     => [ 'emoji' => '🛣️', 'label' => 'Travel',     'sub' => 'On the road' ],
	'sickness'   => [ 'emoji' => '🤲', 'label' => 'Sickness',   'sub' => 'When health falters' ],
	'gratitude'  => [ 'emoji' => '🌸', 'label' => 'Gratitude',  'sub' => 'For blessings' ],
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

	<!-- Left sidebar: emoji column. Each is a button that switches the right pane. -->
	<aside class="la-duas-rail" aria-label="Dua categories">
		<?php $i = 0; foreach ( $active_cats as $key => $meta ) : ?>
			<button type="button"
				class="la-duas-rail-btn <?php echo $key === $first_cat ? 'is-active' : ''; ?>"
				data-cat="<?php echo esc_attr( $key ); ?>"
				aria-label="<?php echo esc_attr( $meta['label'] ); ?>">
				<span class="la-duas-rail-emoji" aria-hidden="true"><?php echo $meta['emoji']; ?></span>
				<span class="la-duas-rail-label"><?php echo esc_html( $meta['label'] ); ?></span>
				<span class="la-duas-rail-count"><?php echo count( $by_cat[ $key ] ); ?></span>
			</button>
		<?php $i++; endforeach; ?>
	</aside>

	<!-- Right pane: header + scrollable dua cards for the active category -->
	<div class="la-duas-pane">

		<!-- Active category header — updates via JS -->
		<header class="la-duas-pane-head">
			<div class="la-duas-pane-icon" data-cat-icon><?php echo $active_cats[ $first_cat ]['emoji']; ?></div>
			<div class="la-duas-pane-meta">
				<h1 class="la-duas-pane-title" data-cat-title><?php echo esc_html( $active_cats[ $first_cat ]['label'] ); ?></h1>
				<p class="la-duas-pane-sub" data-cat-sub><?php echo esc_html( $active_cats[ $first_cat ]['sub'] ); ?></p>
			</div>
		</header>

		<!-- Per-category progress bar — resets daily. JS reads localStorage
		     keyed by date so taps survive page reloads but new day = fresh. -->
		<div class="la-duas-progress" aria-label="Today's progress in this category">
			<div class="la-duas-progress-track">
				<div class="la-duas-progress-fill" data-cat-progress-bar style="width:0%"></div>
			</div>
			<div class="la-duas-progress-meta">
				<span class="la-duas-progress-text" data-cat-progress-text>0 of 0 read today</span>
				<button type="button" class="la-duas-progress-reset" data-action="reset-day" title="Reset today's ticks" aria-label="Reset today's ticks">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
				</button>
			</div>
		</div>

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
					if ( $key === 'ruqyah' ) : ?>
						<article class="la-dua la-dua--knowledge la-ruqyah-intro">
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
					?>
						<article class="la-dua" data-dua-id="<?php echo (int) $d->id; ?>" data-cat="<?php echo esc_attr( $key ); ?>" data-idx="<?php echo $dua_idx; ?>" data-total="<?php echo $cat_total; ?>">
							<!-- Step counter — 'Dua 3 of 8' so the user knows where they are -->
							<div class="la-dua-step">
								<span class="la-dua-step-num"><?php echo $dua_idx; ?></span>
								<span class="la-dua-step-of">of <?php echo $cat_total; ?></span>
							</div>

							<!-- Header — title + repeat-count chip -->
							<header class="la-dua-head">
								<h2 class="la-dua-title"><?php echo esc_html( $d->title ); ?></h2>
								<?php if ( (int) $d->repeat_count > 1 ) : ?>
									<span class="la-dua-repeat" title="Recite this many times"><?php echo (int) $d->repeat_count; ?>×</span>
								<?php endif; ?>
							</header>

							<!-- Body — Arabic + translit + meaning, centred for a meditative read -->
							<div class="la-dua-body">
								<div class="la-dua-arabic" dir="rtl" lang="ar"><?php echo esc_html( $d->arabic ); ?></div>
								<?php if ( ! empty( $d->transliteration ) ) : ?>
									<div class="la-dua-translit"><?php echo esc_html( $d->transliteration ); ?></div>
								<?php endif; ?>
								<?php if ( ! empty( $d->meaning ) ) : ?>
									<p class="la-dua-meaning"><?php echo esc_html( $d->meaning ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $d->source ) ) : ?>
									<div class="la-dua-source">
										<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
										<?php echo esc_html( $d->source ); ?>
									</div>
								<?php endif; ?>
							</div>

							<!-- Footer — small Ameen/Copy/Share + LARGE 'Mark as read' CTA -->
							<footer class="la-dua-foot">
								<div class="la-dua-actions">
									<?php // Wave 109: Listen button — only on duas that have a
									// Qur'anic audio chain in $la_dua_audio. Tapping mounts the
									// floating player above the tab nav and starts playing. ?>
									<?php if ( ! empty( $la_dua_audio[ $d->slug ] ) ) :
										$audio_keys_attr = esc_attr( implode( ',', $la_dua_audio[ $d->slug ] ) );
									?>
										<button type="button"
											class="la-dua-btn la-dua-btn--listen"
											data-action="listen"
											data-id="<?php echo (int) $d->id; ?>"
											data-title="<?php echo esc_attr( $d->title ); ?>"
											data-audio-keys="<?php echo $audio_keys_attr; ?>"
											aria-label="Listen — Mishary Al-Afasy">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M18.5 5.5a9 9 0 0 1 0 13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
											<span>Listen</span>
										</button>
									<?php endif; ?>
									<button type="button"
										class="la-dua-btn <?php echo $is_amened ? 'is-active' : ''; ?>"
										data-action="ameen"
										data-id="<?php echo (int) $d->id; ?>"
										aria-pressed="<?php echo $is_amened ? 'true' : 'false'; ?>"
										aria-label="Ameen">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22c-3-2-5-4-5-7 0-3 1.5-4 3-4s2 1 2 1 1-1 2.5-1 2.5 1 2.5 4-2 5-5 7z"/></svg>
										<span>Ameen</span>
										<span class="la-dua-btn-count" data-ameen-count><?php echo (int) $d->ameen_count; ?></span>
									</button>
									<button type="button" class="la-dua-btn" data-action="save" data-id="<?php echo (int) $d->id; ?>" aria-label="Copy">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
										<span>Copy</span>
									</button>
									<button type="button" class="la-dua-btn" data-action="share" data-id="<?php echo (int) $d->id; ?>" aria-label="Share">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
										<span>Share</span>
									</button>
								</div>

								<!-- Prominent 'Mark as read today' CTA. Tap → tick + auto-advance to next card. -->
								<button type="button" class="la-dua-complete"
									data-action="tick-day"
									data-id="<?php echo (int) $d->id; ?>"
									data-cat="<?php echo esc_attr( $key ); ?>"
									aria-label="Mark as read today">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
									<span class="la-dua-complete-label">Mark as read today</span>
									<span class="la-dua-complete-done">Read · next →</span>
								</button>
							</footer>
						</article>
					<?php endforeach; ?>


				</section>
			<?php endforeach; ?>
		</div>

	</div>

	<script id="la-duas-cats" type="application/json">
		<?php echo wp_json_encode( $active_cats ); ?>
	</script>

	<?php /* Wave 109: Floating audio player — dark glass pill that
	     sits above the bottom tab nav. Mounts hidden, becomes visible
	     when any Listen button is tapped. Uses HTML5 <audio> with
	     everyayah.com Alafasy MP3s; multi-ayah duas chain via onended.
	     Speed control 0.75× / 1× / 1.25× via audio.playbackRate. */ ?>
	<div class="la-duas-player" data-duas-player hidden>
		<div class="la-duas-player-inner">
			<div class="la-duas-player-meta">
				<svg class="la-duas-player-glyph" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
				<div class="la-duas-player-text">
					<div class="la-duas-player-title" data-duas-player-title>—</div>
					<div class="la-duas-player-by">Mishary Al-Afasy · <span data-duas-player-ayah>—</span></div>
				</div>
			</div>
			<div class="la-duas-player-controls">
				<button type="button" class="la-duas-player-btn la-duas-player-btn--primary" data-duas-player-toggle aria-label="Play or pause">
					<svg data-duas-player-play-icon width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" hidden><path d="M9 6l8 6-8 6V6z"/></svg>
					<svg data-duas-player-pause-icon width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
				</button>
				<div class="la-duas-player-speed" data-duas-player-speed role="group" aria-label="Playback speed">
					<button type="button" class="la-duas-speed-opt" data-speed="0.75">0.75×</button>
					<button type="button" class="la-duas-speed-opt is-active" data-speed="1">1×</button>
					<button type="button" class="la-duas-speed-opt" data-speed="1.25">1.25×</button>
				</div>
				<button type="button" class="la-duas-player-btn" data-duas-player-close aria-label="Close player">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
				</button>
			</div>
		</div>
		<div class="la-duas-player-progress" aria-hidden="true">
			<div class="la-duas-player-progress-fill" data-duas-player-progress style="width:0%"></div>
		</div>
		<audio data-duas-player-audio preload="none"></audio>
	</div>

	<script>
	/* Wave 109: floating audio player for the Duas page.
	   Read-along uses everyayah.com Alafasy MP3s. Multi-ayah duas chain
	   via the HTMLAudioElement's onended event so the recitation flows
	   ayah-by-ayah without gaps. We track playback so the gold play
	   icon swaps to pause + the thin progress bar at the bottom fills.

	   The user explicitly mentioned this is autism-friendly — reading
	   alone gets boring. Hearing it spoken alongside the text keeps
	   attention on the verse. (Wave 110 will add line-by-line highlight.) */
	(function() {
		const player    = document.querySelector('[data-duas-player]');
		if ( ! player ) return;
		const audio     = player.querySelector('[data-duas-player-audio]');
		const titleEl   = player.querySelector('[data-duas-player-title]');
		const ayahEl    = player.querySelector('[data-duas-player-ayah]');
		const playBtn   = player.querySelector('[data-duas-player-toggle]');
		const playIcon  = player.querySelector('[data-duas-player-play-icon]');
		const pauseIcon = player.querySelector('[data-duas-player-pause-icon]');
		const closeBtn  = player.querySelector('[data-duas-player-close]');
		const speedRow  = player.querySelector('[data-duas-player-speed]');
		const progress  = player.querySelector('[data-duas-player-progress]');
		const speedOpts = player.querySelectorAll('[data-speed]');

		let queue = [];        // ayah keys still to play (after current)
		let allKeys = [];      // full ordered list for this dua (for line-map)
		let cursor = 0;        // index into allKeys of the currently-playing ayah
		let currentKey = null;
		let playing = false;
		let speed = parseFloat( localStorage.getItem('la_duas_speed') || '1' ) || 1;

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

		/* Split the arabic text into per-ayah lines using ۞ as separator
		   (matches the convention we use across the seed). Wrap each in
		   a span we can later highlight. If there's no ۞, the whole text
		   becomes one line — still highlights as a whole on playback. */
		function prepareReadAlong(card, keys) {
			if ( activeCard === card ) {
				// Already wrapped — just rebuild the key→line map
				keyToLine = buildKeyToLineMap(keys, lineNodes.length);
				return;
			}
			restoreReadAlong();   // unwrap any previous card
			if ( ! card ) return;
			const ar = card.querySelector('.la-dua-arabic');
			if ( ! ar ) return;
			originalArHTML = ar.innerHTML;
			const text = ar.textContent.trim();
			const parts = text.split('۞').map(s => s.trim()).filter(Boolean);
			let html;
			if ( parts.length < 2 ) {
				html = `<span class="la-dua-arabic-line">${escHTML(text)}</span>`;
			} else {
				html = parts.map(p => `<span class="la-dua-arabic-line">${escHTML(p)}</span>`)
					.join(' <span class="la-dua-arabic-sep" aria-hidden="true">۞</span> ');
			}
			ar.innerHTML = html;
			lineNodes = Array.from( ar.querySelectorAll('.la-dua-arabic-line') );
			activeCard = card;
			keyToLine = buildKeyToLineMap(keys, lineNodes.length);
		}
		function restoreReadAlong() {
			if ( ! activeCard ) return;
			const ar = activeCard.querySelector('.la-dua-arabic');
			if ( ar && originalArHTML !== null ) ar.innerHTML = originalArHTML;
			activeCard     = null;
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
			speedOpts.forEach( o => o.classList.toggle( 'is-active', parseFloat(o.dataset.speed) === v ) );
		}
		function playKey(key) {
			currentKey = key;
			ayahEl.textContent = ayahLabel(key);
			audio.src = urlFor(key);
			audio.playbackRate = speed;
			// Wave 110: highlight the line this key belongs to before
			// playback starts, so the eye is already on the verse when
			// the recitation hits speak.
			const lineIdx = keyToLine[cursor];
			if ( typeof lineIdx === 'number' ) setActiveLine(lineIdx);
			audio.play().then(() => {
				playing = true;
				renderPlayingIcons();
			}).catch( e => {
				// Likely autoplay policy or network — leave paused, user can tap again
				playing = false;
				renderPlayingIcons();
			});
		}
		function startQueue(title, keys, card) {
			titleEl.textContent = title || '';
			allKeys = keys.slice();
			queue   = keys.slice(1);
			cursor  = 0;
			// Wave 110: wrap the card's arabic in per-line spans BEFORE
			// the first key fires, so setActiveLine has nodes to toggle.
			prepareReadAlong(card, keys);
			player.hidden = false;
			player.classList.add('is-open');
			applySpeed(speed);
			playKey(keys[0]);
		}
		function stopAll() {
			try { audio.pause(); } catch(_){}
			audio.removeAttribute('src');
			audio.load();
			queue       = [];
			allKeys     = [];
			cursor      = 0;
			currentKey  = null;
			playing     = false;
			renderPlayingIcons();
			progress.style.width = '0%';
			player.classList.remove('is-open');
			player.hidden = true;
			restoreReadAlong();   // Wave 110: unwrap arabic spans
		}

		// Audio events
		audio.addEventListener('ended', () => {
			if ( queue.length ) {
				cursor++;                     // Wave 110: advance the line cursor
				playKey( queue.shift() );
			} else {
				playing = false;
				renderPlayingIcons();
				progress.style.width = '100%';
				// Hold the last-line highlight visible for a beat — visual
				// confirmation of completion — then fade it.
				setTimeout( () => {
					lineNodes.forEach( l => l.classList.remove('is-active') );
				}, 1400 );
			}
		});
		audio.addEventListener('timeupdate', () => {
			if ( ! audio.duration ) return;
			progress.style.width = ( ( audio.currentTime / audio.duration ) * 100 ) + '%';
		});
		audio.addEventListener('pause', () => { playing = false; renderPlayingIcons(); });
		audio.addEventListener('play',  () => { playing = true;  renderPlayingIcons(); });

		// UI wiring
		playBtn.addEventListener('click', () => {
			if ( ! currentKey ) return;
			if ( playing ) audio.pause(); else audio.play();
		});
		closeBtn.addEventListener('click', stopAll);
		speedOpts.forEach( o => o.addEventListener('click', () => applySpeed( parseFloat( o.dataset.speed ) )) );

		// Delegated Listen-button handler — survives category swaps
		document.addEventListener('click', (e) => {
			const btn = e.target.closest('[data-action="listen"]');
			if ( ! btn ) return;
			e.preventDefault();
			const title = btn.dataset.title || 'Recitation';
			const keys  = ( btn.dataset.audioKeys || '' ).split(',').filter(Boolean);
			if ( ! keys.length ) return;
			const card  = btn.closest('.la-dua');   // Wave 110: pass the card so we can wrap its arabic
			startQueue(title, keys, card);
		});

		// Initialise persistent speed pill state
		applySpeed(speed);
		renderPlayingIcons();
	})();
	</script>

</main>
<?php
get_footer();
