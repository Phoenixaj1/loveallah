<?php
/**
 * Dhikr Witness — feed of actual dhikr circles + chant-along counter.
 *
 * Filtered strictly to `type=dhikr` content. We cannot predict what the
 * sheikh is reciting in any given clip (it might be Subhanallah, it
 * might be Salawat, it might be La ilaha illa Allah) — so there is no
 * pre-set count target. The user just chants along with whatever's on
 * screen and taps a counter for their own tally.
 *
 * Wave 43: previously fell back to qirat (Quran recitation) when the
 * dhikr pool was small. That polluted the feed with content that is
 * NOT dhikr. Removed — show pure dhikr only, with a graceful empty
 * state if no content has been ingested yet.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id() ?: null;
$session_id = la_get_or_set_session_id();

// Pure dhikr content only — no qirat / lecture fallback.
$cards = LA_Algorithm::for_user( $user_id, $session_id, 20, 0, 'dhikr' );

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

	<!-- Floating session tally — just counts the user's taps, no target.
	     What's playing on screen might be any dhikr; we can't predict and
	     don't try. The tap is the user's own remembrance, counted. -->
	<div class="la-witness-hud" data-witness-hud>
		<a href="<?php echo esc_url( home_url( '/dhikr/' ) ); ?>" class="la-witness-back-link" aria-label="Back to dhikr modes">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
		</a>
		<div class="la-witness-hud-tally">
			<div class="la-witness-hud-count" data-witness-count>0</div>
			<div class="la-witness-hud-label">chants this session</div>
		</div>
	</div>

	<?php if ( ! empty( $cards ) ) : ?>
		<!-- Feed of dhikr-only content. Vertical-snap so each clip is one
		     full-viewport item. The user chants along with whatever the
		     sheikh is doing — Subhanallah, Salawat, La ilaha — taking
		     guidance from the audio and tapping the +1 each time. -->
		<div class="la-feed-snap la-feed-snap--witness" data-feed data-witness>
			<?php foreach ( $cards as $card ) {
				echo LA_FeedRender::card( $card );
			} ?>

			<div class="la-feed-loader" data-feed-loader hidden>
				<div class="la-feed-loader-spinner" aria-hidden="true"></div>
				<span><?php esc_html_e( 'Loading more', 'loveallah' ); ?></span>
			</div>
			<div class="la-feed-sentinel" data-feed-sentinel aria-hidden="true"></div>
		</div>

		<!-- BIG tap-to-count button. Each tap = one chant logged for this
		     session. No target, no celebration sheet, no surprise modals
		     — just a steady tally that grows with the user. -->
		<button class="la-witness-tap" type="button" data-witness-tap aria-label="Tap to count">
			<span class="la-witness-tap-plus">+1</span>
			<span class="la-witness-tap-label">Chant along</span>
		</button>

	<?php else : ?>
		<!-- No dhikr-typed content ingested yet. We don't fall back to qirat
		     because that's Quran recitation, not dhikr — different worship.
		     Suggest Pulse / Solitude / Names instead. -->
		<section class="la-witness-empty">
			<div class="la-witness-empty-inner">
				<div class="la-witness-empty-glyph" aria-hidden="true">◯</div>
				<h2 class="la-witness-empty-title">Gathering dhikr circles</h2>
				<p class="la-witness-empty-body">
					We're pulling halaqa and tasbih recordings in. Until they arrive,
					try one of the other paths — your remembrance counts in any of them.
				</p>
				<div class="la-witness-empty-actions">
					<a href="<?php echo esc_url( home_url( '/dhikr/?mode=pulse' ) ); ?>" class="la-witness-empty-btn la-witness-empty-btn--primary">Try Pulse</a>
					<a href="<?php echo esc_url( home_url( '/dhikr/?mode=solitude' ) ); ?>" class="la-witness-empty-btn">Solitude</a>
					<a href="<?php echo esc_url( home_url( '/dhikr/?mode=names' ) ); ?>" class="la-witness-empty-btn">Names</a>
				</div>
			</div>
		</section>
	<?php endif; ?>

</main>
