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

			<?php // Wave 92: legibility scrim — soft black gradient behind the
			// creator/caption/nav so white text stays readable over any video
			// colour. Pointer-events:none so it doesn't intercept taps. ?>
			<div class="la-snap-scrim" aria-hidden="true"></div>

			<?php // Wave 92: right action rail — individual floating glass
			// circles, NOT a single container pill. Each is a 50px circle with
			// its own backdrop blur + hairline border. Accent appears only
			// when the action is active (liked / saved / following). ?>
			<div class="la-snap-rail" aria-label="Post actions">
				<button type="button" class="la-rail-btn la-rail-mute" data-action="toggle-mute" aria-label="Toggle sound">
					<svg class="la-mute-icon la-mute-on"  width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
					<svg class="la-mute-icon la-mute-off" width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/></svg>
				</button>
				<button type="button" class="la-rail-btn la-rail-like <?php echo ! empty( $card->_is_liked ) ? 'is-active' : ''; ?>" data-act="like" data-id="<?php echo (int) $card->id; ?>" aria-label="Like" aria-pressed="<?php echo ! empty( $card->_is_liked ) ? 'true' : 'false'; ?>">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="<?php echo ! empty( $card->_is_liked ) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
				</button>
				<?php $likes = (int) ( $card->likes_count ?? 0 ); ?>
				<span class="la-rail-count<?php echo $likes === 0 ? ' is-zero' : ''; ?>" data-likes><?php echo number_format( $likes ); ?></span>
				<button type="button" class="la-rail-btn la-rail-save <?php echo ! empty( $card->_is_saved ) ? 'is-active' : ''; ?>" data-act="save" data-id="<?php echo (int) $card->id; ?>" aria-label="Save" aria-pressed="<?php echo ! empty( $card->_is_saved ) ? 'true' : 'false'; ?>">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="<?php echo ! empty( $card->_is_saved ) ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
				</button>
				<?php
				// Save count: not currently tracked per-post in feed_posts.
				// Render an invisible placeholder so the rail stays vertically
				// aligned with the design spec layout. Hide via class — easy to
				// re-enable when the column lands.
				$saves = (int) ( $card->saves_count ?? 0 );
				?>
				<span class="la-rail-count<?php echo $saves === 0 ? ' is-zero' : ''; ?>" data-saves><?php echo number_format( $saves ); ?></span>
				<button type="button" class="la-rail-btn la-rail-share" data-act="share" data-id="<?php echo (int) $card->id; ?>" aria-label="Share">
					<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
				</button>
				<span class="la-rail-label">Share</span>
			</div>

			<?php // Wave 92: creator block at bottom-left, above the tab bar.
			// Avatar with accent ring + verified tick (if account_type=verified)
			// → display name + meta line → caption → Instagram chip.
			// Follow pill sits to the right of the name row in accent until
			// toggled, then becomes a neutral "Following" pill.
			$is_verified = ( $card->scholar_account_type ?? '' ) === 'verified';
			$display     = $card->scholar_display_name ?? 'Scholar';
			$bare_caption = trim( (string) ( $card->caption ?? '' ) );
			// Build IG handle guess: username from scholars table if present.
			$ig_handle = $card->scholar_username ?? '';
			?>
			<div class="la-snap-creator" data-card-creator>
				<div class="la-snap-creator-row">
					<div class="la-snap-avatar-wrap">
						<div class="la-snap-avatar"><?php echo esc_html( $initials ); ?></div>
						<?php if ( $is_verified ) : ?>
							<span class="la-snap-tick" aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 1.8 3-.4.6 2.9L20 8.4l-1.2 2.7L20 14l-2.4 1.5-.6 2.9-3-.4L12 20l-2.4-1.8-3 .4-.6-2.9L4 14.2l1.2-2.7L4 8.8l2.4-1.5.6-2.9 3 .4z"/><path d="M9 12l2 2 4-4" stroke="#fff" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</span>
						<?php endif; ?>
					</div>
					<div class="la-snap-creator-meta">
						<div class="la-snap-creator-name"><?php echo esc_html( $display ); ?></div>
						<?php if ( $bare_caption !== '' ) : ?>
							<div class="la-snap-creator-sub"><?php echo esc_html( mb_strimwidth( $bare_caption, 0, 40, '…' ) ); ?></div>
						<?php endif; ?>
					</div>
					<button type="button" class="la-snap-followpill" data-act="follow" data-scholar="<?php echo (int) ( $card->scholar_id ?? 0 ); ?>" aria-pressed="false">
						<span class="la-follow-label-plus">Follow</span>
						<span class="la-follow-label-check">Following</span>
					</button>
				</div>
				<?php if ( $ig_handle ) : ?>
					<a class="la-snap-ig" href="https://instagram.com/<?php echo esc_attr( $ig_handle ); ?>" target="_blank" rel="noopener" aria-label="Instagram @<?php echo esc_attr( $ig_handle ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
							<rect x="3" y="3" width="18" height="18" rx="5"/>
							<circle cx="12" cy="12" r="4"/>
							<circle cx="17.5" cy="6.5" r="1.1" fill="currentColor" stroke="none"/>
						</svg>
						<span>@<?php echo esc_html( $ig_handle ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}
}
