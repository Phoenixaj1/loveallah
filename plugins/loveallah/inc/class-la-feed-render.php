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
			data-duration-sec="<?php echo (int) ( $card->duration_sec ?? 30 ); ?>"
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

			<?php // Wave 90b: scholar overlay (avatar + name + Curated/Verified
			// badge) removed at user request — "remove the channel name we
			// add at the bottom with the curated thing". Feed cards now show
			// pure video without our attribution chrome layered on top.
			// YouTube's own player chrome shows the channel name during
			// playback (in the top-left of the iframe) so attribution is
			// preserved without the extra chrome.
			// To restore: see git history pre-c248114 for the .la-snap-scholar
			// block + .la-snap-overlay wrapper. ?>

			<div class="la-snap-actions" aria-label="Post actions">
				<!-- Wave 36: wrapped in a frosted pill so the column reads as a
				     single UI element instead of four floating icons. Icon-only
				     by default; like count appears only when > 0. -->
				<div class="la-snap-actions-pill">
					<button type="button" class="la-snap-action la-snap-mute" data-action="toggle-mute" aria-label="Toggle sound">
						<svg class="la-mute-icon la-mute-on"  width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
						<svg class="la-mute-icon la-mute-off" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
					</button>
					<button type="button" class="la-snap-action <?php echo ! empty( $card->_is_liked ) ? 'is-active' : ''; ?>" data-act="like" data-id="<?php echo (int) $card->id; ?>" aria-label="Like" aria-pressed="<?php echo ! empty( $card->_is_liked ) ? 'true' : 'false'; ?>">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
						<?php $likes = (int) ( $card->likes_count ?? 0 ); ?>
						<span class="la-snap-action-count<?php echo $likes === 0 ? ' is-zero' : ''; ?>" data-likes><?php echo $likes; ?></span>
					</button>
					<button type="button" class="la-snap-action <?php echo ! empty( $card->_is_saved ) ? 'is-active' : ''; ?>" data-act="save" data-id="<?php echo (int) $card->id; ?>" aria-label="Save" aria-pressed="<?php echo ! empty( $card->_is_saved ) ? 'true' : 'false'; ?>">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="<?php echo ! empty( $card->_is_saved ) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
					</button>
					<!-- Share → WhatsApp first (uses Web Share API on mobile,
					     which surfaces WhatsApp at the top of the native sheet;
					     falls back to wa.me deep link). The shared URL is the
					     clip permalink which carries Open Graph tags for a
					     rich WhatsApp preview. -->
					<button type="button" class="la-snap-action la-snap-share" data-act="share" data-id="<?php echo (int) $card->id; ?>" aria-label="Share to WhatsApp">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.05 4.91A9.82 9.82 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.45 9.91-9.91 0-2.65-1.03-5.14-2.91-7.01Zm-7.01 15.24h-.01a8.23 8.23 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.24 8.24 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24a8.18 8.18 0 0 1 5.83 2.42 8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.23 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.81-.78.97-.14.17-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.39.11-.51.11-.11.25-.29.37-.43.12-.15.16-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43-.14 0-.31-.01-.47-.01-.17 0-.43.06-.66.31-.23.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.57.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.16-.48-.29Z"/></svg>
					</button>
				</div>
			</div>
		</article>
		<?php
	}
}
