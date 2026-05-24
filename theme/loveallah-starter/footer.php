<?php if ( ! defined( 'ABSPATH' ) ) exit;

$la_cur = '';
if ( is_front_page() )              $la_cur = 'feed';
elseif ( is_page( 'dhikr' ) )       $la_cur = 'dhikr';
elseif ( is_page( 'duas' ) )        $la_cur = 'duas';
elseif ( is_page( 'donate' ) )      $la_cur = 'donate';
elseif ( is_page( 'masjid' ) )      $la_cur = 'masjid';
elseif ( is_page( 'connect' ) )     $la_cur = 'connect';
?>

<nav class="la-tabbar la-tabbar--6" aria-label="Primary">
	<a class="la-tab <?php echo $la_cur === 'feed' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php echo $la_cur === 'feed' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 5h18v2H3zm0 5h18v9H3zm2 2v5h6v-5zm8 0v2h6v-2zm0 3v2h6v-2z"/></svg>
		<span><?php esc_html_e( 'Feed', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'dhikr' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/dhikr' ) ); ?>" <?php echo $la_cur === 'dhikr' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="4" r="1.6"/><circle cx="12" cy="20" r="1.6"/><circle cx="4" cy="12" r="1.6"/><circle cx="20" cy="12" r="1.6"/><circle cx="6.3" cy="6.3" r="1.4"/><circle cx="17.7" cy="6.3" r="1.4"/><circle cx="6.3" cy="17.7" r="1.4"/><circle cx="17.7" cy="17.7" r="1.4"/></svg>
		<span><?php esc_html_e( 'Dhikr', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'duas' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/duas' ) ); ?>" <?php echo $la_cur === 'duas' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22c-3-2-5-4-5-7 0-3 1.5-4 3-4s2 1 2 1 1-1 2.5-1 2.5 1 2.5 4-2 5-5 7z" fill="currentColor" stroke="none"/><path d="M12 5c0-1.5 1-3 2.5-3M12 5c0-1.5-1-3-2.5-3" stroke-linecap="round"/></svg>
		<span><?php esc_html_e( 'Dua', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'donate' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/donate' ) ); ?>" <?php echo $la_cur === 'donate' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="currentColor" stroke="none" opacity="0.95"/><path d="M9 12.5l1.5 1.5L15 9.5" stroke="#fff" stroke-width="2.2" fill="none"/></svg>
		<span><?php esc_html_e( 'Donate', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'masjid' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/masjid' ) ); ?>" <?php echo $la_cur === 'masjid' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.2 2 1 .6V8h2v2h1V8h2v3h1v11h-3v-5h-2v5H8v-5H6v5H3V11h1V8h2v2h1V8h2v-3.4l1-.6L12 2z"/></svg>
		<span><?php esc_html_e( 'Masjid', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'connect' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/connect' ) ); ?>" <?php echo $la_cur === 'connect' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="8" r="2.5"/><path d="M3 18a6 6 0 0 1 12 0v2H3zM14 17.5a5 5 0 0 1 7-.5v2.5h-7z"/></svg>
		<span><?php esc_html_e( 'Connect', 'loveallah' ); ?></span>
	</a>
</nav>

<?php
// Wave 64: passwordless sign-in sheet — appears as a centred modal,
// triggered by any [data-action="signin"] click anywhere on the site.
// Renders once globally so every page can open it without duplication.
$la_current_user = function_exists( 'la_current_user' ) ? la_current_user() : null;
?>
<div class="la-signin-sheet" data-signin-sheet hidden role="dialog" aria-modal="true" aria-labelledby="la-signin-title">
	<button type="button" class="la-signin-backdrop" data-signin-close aria-label="Close"></button>
	<form class="la-signin-panel" data-signin-form>
		<button type="button" class="la-signin-x" data-signin-close aria-label="Close">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
		</button>

		<div class="la-signin-icon" aria-hidden="true">
			<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="currentColor" fill-opacity="0.20" stroke-opacity="1"/></svg>
		</div>
		<h2 class="la-signin-title" id="la-signin-title">Save your journey</h2>
		<p class="la-signin-sub">Just your email + phone. No password. So the feed remembers what you've seen, your prayer streak survives, and your masjid stays pinned — across devices and time.</p>

		<label class="la-signin-field">
			<span class="la-signin-label">Email</span>
			<input type="email" name="email" data-signin-email required autocomplete="email" placeholder="you@example.com" inputmode="email">
		</label>
		<label class="la-signin-field">
			<span class="la-signin-label">Mobile</span>
			<input type="tel" name="phone" data-signin-phone required autocomplete="tel" placeholder="07700 900123" inputmode="tel">
		</label>
		<label class="la-signin-field la-signin-field--optional">
			<span class="la-signin-label">Name <em>(optional)</em></span>
			<input type="text" name="name" data-signin-name autocomplete="name" placeholder="What should we call you?">
		</label>

		<div class="la-signin-error" data-signin-error hidden></div>

		<button type="submit" class="la-signin-submit" data-signin-submit>
			<span class="la-signin-submit-label">Continue</span>
			<svg class="la-signin-submit-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
		</button>

		<p class="la-signin-fineprint">We won't spam. You'll only hear from us with reminders you'd actually want — Friday salawat, Ramadan prep, your masjid's events.</p>
	</form>
</div>

<script type="application/json" id="la-current-user">
<?php echo wp_json_encode( $la_current_user ? [
	'id'    => (int) $la_current_user->id,
	'name'  => $la_current_user->name,
	'email' => $la_current_user->email,
] : null ); ?>
</script>

<?php wp_footer(); ?>
</body>
</html>
