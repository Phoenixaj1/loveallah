<?php if ( ! defined( 'ABSPATH' ) ) exit;

$la_cur = '';
if ( is_front_page() )              $la_cur = 'feed';
elseif ( is_page( 'dhikr' ) )       $la_cur = 'dhikr';
elseif ( is_page( 'nasheed' ) )     $la_cur = 'nasheed';
elseif ( is_page( 'mindfulness' ) ) $la_cur = 'mindfulness';
elseif ( is_page( 'connect' ) )     $la_cur = 'connect';
?>

<nav class="la-tabbar la-tabbar--5" aria-label="Primary">
	<a class="la-tab <?php echo $la_cur === 'feed' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php echo $la_cur === 'feed' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 5h18v2H3zm0 5h18v9H3zm2 2v5h6v-5zm8 0v2h6v-2zm0 3v2h6v-2z"/></svg>
		<span><?php esc_html_e( 'Feed', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'dhikr' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/dhikr' ) ); ?>" <?php echo $la_cur === 'dhikr' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="4" r="1.6"/><circle cx="12" cy="20" r="1.6"/><circle cx="4" cy="12" r="1.6"/><circle cx="20" cy="12" r="1.6"/><circle cx="6.3" cy="6.3" r="1.4"/><circle cx="17.7" cy="6.3" r="1.4"/><circle cx="6.3" cy="17.7" r="1.4"/><circle cx="17.7" cy="17.7" r="1.4"/></svg>
		<span><?php esc_html_e( 'Dhikr', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'nasheed' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/nasheed' ) ); ?>" <?php echo $la_cur === 'nasheed' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 18V6l12-2v12"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
		<span><?php esc_html_e( 'Nasheed', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'mindfulness' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/mindfulness' ) ); ?>" <?php echo $la_cur === 'mindfulness' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="12" cy="12" r="2"/></svg>
		<span><?php esc_html_e( 'Mindful', 'loveallah' ); ?></span>
	</a>
	<a class="la-tab <?php echo $la_cur === 'connect' ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/connect' ) ); ?>" <?php echo $la_cur === 'connect' ? 'aria-current="page"' : ''; ?>>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="8" r="2.5"/><path d="M3 18a6 6 0 0 1 12 0v2H3zM14 17.5a5 5 0 0 1 7-.5v2.5h-7z"/></svg>
		<span><?php esc_html_e( 'Connect', 'loveallah' ); ?></span>
	</a>
</nav>

<?php wp_footer(); ?>
</body>
</html>
