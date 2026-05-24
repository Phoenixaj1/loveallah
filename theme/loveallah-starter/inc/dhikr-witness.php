<?php
/**
 * Dhikr Witness — feed of dhikr content + tap-along counter.
 *
 * Reuses LA_Algorithm to pull `type=dhikr` posts (plus qari recitations
 * that include tahajjud / tasbeeh). Each card carries a big "+1" tap
 * button that increments a session counter — the user chants along
 * with the scholar on screen and taps each repetition.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id() ?: null;
$session_id = la_get_or_set_session_id();

// Pull dhikr-type content for the witness feed. Falls back to qirat
// (recitations) if no dhikr-typed posts have arrived yet — most qari
// recitations include extended tasbeeh segments useful to follow along.
$cards = LA_Algorithm::for_user( $user_id, $session_id, 20, 0, 'dhikr' );
if ( count( $cards ) < 5 ) {
	$qirat = LA_Algorithm::for_user( $user_id, $session_id, 15, 0, 'qirat' );
	foreach ( $qirat as $q ) $cards[] = $q;
}

// Decorate saved/liked state for the right rail
$post_ids = [];
foreach ( $cards as $c ) {
	if ( ( $c->_card_type ?? '' ) === 'content' && ! empty( $c->id ) ) $post_ids[] = (int) $c->id;
}
$saved_map = LA_Feed::active_actions_for( $user_id, $session_id, $post_ids, 'save' );
$liked_map = LA_Feed::active_actions_for( $user_id, $session_id, $post_ids, 'like' );
foreach ( $cards as $c ) {
	if ( ( $c->_card_type ?? '' ) === 'content' && ! empty( $c->id ) ) {
		$c->_is_saved = isset( $saved_map[ (int) $c->id ] );
		$c->_is_liked = isset( $liked_map[ (int) $c->id ] );
	}
}
?>
<main class="la-app la-app--witness">

	<!-- Floating count + target HUD (updates as user taps +1) -->
	<div class="la-witness-hud" data-witness-hud>
		<div class="la-witness-back">
			<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-witness-back-link" aria-label="Back to dhikr modes">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			</a>
		</div>
		<div class="la-witness-hud-progress">
			<div class="la-witness-hud-count" data-witness-count>0</div>
			<div class="la-witness-hud-target">of <span data-witness-target>33</span></div>
		</div>
		<select class="la-witness-target-pick" data-witness-target-pick aria-label="Sunnah count target">
			<option value="33">33</option>
			<option value="100">100</option>
			<option value="300">300</option>
		</select>
	</div>

	<!-- Feed -->
	<div class="la-feed-snap la-feed-snap--witness" data-feed data-witness>
		<?php foreach ( $cards as $card ) {
			echo LA_FeedRender::card( $card );
		} ?>

		<?php if ( empty( $cards ) ) : ?>
			<article class="la-snap la-snap--empty">
				<div class="la-snap-inner">
					<h3>No dhikr content yet</h3>
					<p>We're pulling more in. Try Solitude or Pulse in the meantime — they're ready now.</p>
					<a class="la-dhikr-primary" href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" style="margin-top:18px;">Back</a>
				</div>
			</article>
		<?php endif; ?>

		<div class="la-feed-loader" data-feed-loader hidden>
			<div class="la-feed-loader-spinner" aria-hidden="true"></div>
			<span><?php esc_html_e( 'Loading more', 'loveallah' ); ?></span>
		</div>
		<div class="la-feed-sentinel" data-feed-sentinel aria-hidden="true"></div>
	</div>

	<!-- BIG tap-to-count button anchored to the bottom of the viewport -->
	<button class="la-witness-tap" type="button" data-witness-tap aria-label="Tap to count dhikr">
		<span class="la-witness-tap-plus">+1</span>
		<span class="la-witness-tap-label">Tap as you chant</span>
	</button>

	<!-- Milestone celebration (shows on hitting target) -->
	<div class="la-witness-celebrate" data-witness-celebrate hidden>
		<div class="la-witness-celebrate-inner">
			<div class="la-witness-celebrate-arabic" dir="rtl" lang="ar">سُبْحَانَ ٱللَّٰه</div>
			<div class="la-witness-celebrate-text">Remembered</div>
			<button type="button" class="la-witness-celebrate-btn" data-witness-celebrate-close>Continue</button>
		</div>
	</div>

</main>
