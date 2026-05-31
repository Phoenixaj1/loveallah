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

</main>
<?php
get_footer();
