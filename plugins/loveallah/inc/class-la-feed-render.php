<?php
/**
 * Shared card rendering — used by both front-page.php (initial render)
 * and the /feed/more REST endpoint (AJAX load more).
 *
 * Keeping the markup in one place means infinite scroll cards look
 * exactly the same as the first paint.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_FeedRender {

	/** Render a single card to HTML string */
	public static function card( $card ) : string {
		ob_start();
		$type = $card->_card_type ?? '';
		if ( $type === 'dhikr' )       self::dhikr_card( $card );
		elseif ( $type === 'signup' )  self::signup_card( $card );
		else                           self::content_card( $card );
		return ob_get_clean();
	}

	/** Email-capture interstitial card */
	public static function signup_card( $card ) : void {
		?>
		<article class="la-snap la-snap--signup" data-card-type="signup" data-position="<?php echo (int) ( $card->_position ?? 0 ); ?>" tabindex="0">
			<div class="la-snap-inner">
				<div class="la-snap-overline"><?php echo esc_html( $card->overline ?? '' ); ?></div>
				<h2 class="la-snap-signup-title"><?php echo esc_html( $card->title ?? '' ); ?></h2>
				<p class="la-snap-signup-body"><?php echo esc_html( $card->body ?? '' ); ?></p>
				<form class="la-signup-form" data-signup-form>
					<input type="email" name="email" class="la-signup-email" placeholder="you@example.com" required autocomplete="email" inputmode="email">
					<button type="submit" class="la-signup-submit">
						<span><?php esc_html_e( 'Continue', 'loveallah' ); ?></span>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
					</button>
				</form>
				<p class="la-signup-fine"><?php esc_html_e( 'No spam. Unsubscribe anytime.', 'loveallah' ); ?></p>
				<button class="la-signup-skip" type="button" data-signup-skip><?php esc_html_e( 'Skip for now', 'loveallah' ); ?></button>
			</div>
		</article>
		<?php
	}

	public static function dhikr_card( $card ) : void {
		$d = $card->dhikr;
		?>
		<article class="la-snap la-snap--dhikr"
			data-card-type="dhikr"
			data-dhikr-index="<?php echo (int) $card->_dhikr_index; ?>"
			data-dhikr-step="<?php echo (int) $card->step; ?>"
			data-dhikr-final="<?php echo $card->step === $card->total ? '1' : '0'; ?>"
			tabindex="0">
			<div class="la-snap-inner">
				<div class="la-snap-overline">Today's remembrance · <?php echo (int) $card->step; ?>/<?php echo (int) $card->total; ?></div>
				<div class="la-snap-arabic" lang="ar" dir="rtl"><?php echo esc_html( $d->arabic_text ); ?></div>
				<div class="la-snap-translit"><?php echo esc_html( $d->transliteration ); ?></div>
				<div class="la-snap-translation">“<?php echo esc_html( $d->translation ); ?>”</div>
				<?php if ( ! empty( $d->body ) ) : ?>
					<p class="la-snap-body"><?php echo esc_html( $d->body ); ?></p>
				<?php endif; ?>
				<div class="la-snap-swipe">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M12 19V5M5 12l7-7 7 7"/>
					</svg>
					<span>Swipe up to remember</span>
				</div>
				<div class="la-snap-done-mark" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
					<span>Remembered</span>
				</div>
			</div>
		</article>
		<?php
	}

	public static function content_card( $card ) : void {
		$is_verified = ( $card->scholar_account_type ?? '' ) === 'verified';
		$scholar_label_cls = $is_verified ? 'is-verified' : 'is-curated';
		$scholar_label = $is_verified ? 'Verified' : 'Curated';

		$initials = '';
		if ( ! empty( $card->scholar_display_name ) ) {
			$parts = preg_split( '/\s+/', $card->scholar_display_name );
			$initials = strtoupper( substr( $parts[0], 0, 1 ) . substr( end( $parts ), 0, 1 ) );
		}

		// YouTube ID for thumbnail backdrop
		$video_id = '';
		if ( ! empty( $card->video_url ) && preg_match( '#(?:embed/|v=|youtu\.be/)([a-zA-Z0-9_-]{11})#', $card->video_url, $m ) ) {
			$video_id = $m[1];
		}
		$thumb_url = $video_id ? "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg" : '';
		?>
		<article class="la-snap la-snap--content"
			data-card-type="content"
			data-post-id="<?php echo (int) $card->id; ?>"
			data-scholar-id="<?php echo (int) ( $card->scholar_id ?? 0 ); ?>"
			data-content-type="<?php echo esc_attr( $card->type ?? 'video' ); ?>"
			tabindex="0">
			<?php if ( $thumb_url ) : ?>
				<div class="la-snap-backdrop" style="background-image:url(<?php echo esc_url( $thumb_url ); ?>)" aria-hidden="true"></div>
			<?php else : ?>
				<div class="la-snap-backdrop la-snap-backdrop--gradient" aria-hidden="true"></div>
			<?php endif; ?>
			<div class="la-snap-video">
				<div class="la-snap-iframe-wrap">
					<iframe class="la-snap-iframe"
						data-src="<?php echo esc_url( $card->video_url ); ?>"
						src="about:blank"
						loading="lazy"
						allow="autoplay; encrypted-media"
						allowfullscreen></iframe>
				</div>
			</div>

			<div class="la-snap-overlay">
				<div class="la-snap-scholar">
					<div class="la-snap-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></div>
					<div class="la-snap-scholar-meta">
						<div class="la-snap-scholar-name"><?php echo esc_html( $card->scholar_display_name ?? 'Scholar' ); ?></div>
						<div class="la-snap-scholar-label <?php echo esc_attr( $scholar_label_cls ); ?>">
							<?php if ( $is_verified ) : ?>
								<svg width="11" height="11" viewBox="0 0 24 24" fill="#4FC3F7" aria-hidden="true"><path d="M12 2l2.4 1.8 3-.4.6 2.9L20 8.4l-1.2 2.7L20 14l-2.4 1.5-.6 2.9-3-.4L12 20l-2.4-1.8-3 .4-.6-2.9L4 14.2l1.2-2.7L4 8.8l2.4-1.5.6-2.9 3 .4z"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php endif; ?>
							<?php echo esc_html( $scholar_label ); ?>
						</div>
					</div>
				</div>
				<?php // Title + caption intentionally removed — YouTube's own
				// player chrome already shows the title, and our overlay was
				// redundant + crowded the scholar attribution. ?>
			</div>

			<div class="la-snap-actions" aria-label="Post actions">
				<button type="button" class="la-snap-action la-snap-mute" data-action="toggle-mute" aria-label="Toggle sound">
					<svg class="la-mute-icon la-mute-on" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
					<svg class="la-mute-icon la-mute-off" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
				</button>
				<button type="button" class="la-snap-action <?php echo ! empty( $card->_is_liked ) ? 'is-active' : ''; ?>" data-act="like" data-id="<?php echo (int) $card->id; ?>" aria-label="Like" aria-pressed="<?php echo ! empty( $card->_is_liked ) ? 'true' : 'false'; ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
					<span class="la-snap-action-count" data-likes><?php echo (int) ( $card->likes_count ?? 0 ); ?></span>
				</button>
				<button type="button" class="la-snap-action <?php echo ! empty( $card->_is_saved ) ? 'is-active' : ''; ?>" data-act="save" data-id="<?php echo (int) $card->id; ?>" aria-label="Save" aria-pressed="<?php echo ! empty( $card->_is_saved ) ? 'true' : 'false'; ?>">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="<?php echo ! empty( $card->_is_saved ) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
					<span class="la-snap-action-count"><?php echo ! empty( $card->_is_saved ) ? 'Saved' : 'Save'; ?></span>
				</button>
				<button type="button" class="la-snap-action" data-act="share" data-id="<?php echo (int) $card->id; ?>" aria-label="Share">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
					<span class="la-snap-action-count">Share</span>
				</button>
			</div>
		</article>
		<?php
	}
}
