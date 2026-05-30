<?php
/**
 * WP Admin UI — Love Allah platform management.
 *
 * Top-level menu: Love Allah
 *   ├─ Dashboard (stats overview)
 *   ├─ Scholars  (CRUD)
 *   ├─ Mosques   (CRUD)
 *   ├─ Events    (CRUD)
 *   ├─ Content   (feed posts — read-only, plus YT sync trigger)
 *   ├─ Subscribers (read-only)
 *   └─ Settings  (Settings API)
 *
 * All pages capability-gated by `manage_loveallah`.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Admin {

	const SLUG = 'loveallah';

	public static function register() : void {
		add_action( 'admin_menu',         [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init',         [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_post_la_save_scholar', [ __CLASS__, 'handle_save_scholar' ] );
		add_action( 'admin_post_la_save_mosque',  [ __CLASS__, 'handle_save_mosque' ] );
		add_action( 'admin_post_la_save_event',   [ __CLASS__, 'handle_save_event' ] );
		add_action( 'admin_post_la_delete',       [ __CLASS__, 'handle_delete' ] );
		add_action( 'admin_post_la_yt_sync',         [ __CLASS__, 'handle_yt_sync' ] );
		add_action( 'admin_post_la_yt_sync_batch',   [ __CLASS__, 'handle_yt_sync_batch' ] );
		add_action( 'admin_post_la_yt_sync_catchup', [ __CLASS__, 'handle_yt_sync_catchup' ] );
		// Wave 70: bulk re-tag scholar content type + dhikr-video CRUD
		add_action( 'admin_post_la_scholar_set_type',   [ __CLASS__, 'handle_scholar_set_type' ] );
		add_action( 'admin_post_la_scholar_set_status', [ __CLASS__, 'handle_scholar_set_status' ] );
		add_action( 'admin_post_la_scholar_sync_now',   [ __CLASS__, 'handle_scholar_sync_now' ] );
		add_action( 'admin_post_la_dhikr_save',         [ __CLASS__, 'handle_dhikr_save' ] );
		add_action( 'admin_post_la_dhikr_delete',       [ __CLASS__, 'handle_dhikr_delete' ] );
		add_action( 'admin_notices',      [ __CLASS__, 'flash_notice' ] );
	}

	// ────────────────────────────────────────────────────────────
	// MENU
	// ────────────────────────────────────────────────────────────
	public static function register_menu() : void {
		add_menu_page(
			__( 'Love Allah', 'loveallah' ),
			__( 'Love Allah', 'loveallah' ),
			LA_Caps::CAP_PLATFORM,
			self::SLUG,
			[ __CLASS__, 'page_dashboard' ],
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="black"><path d="M12 21s-7-4.5-9.5-9C.5 8 3 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 4 0 6.5 4 4.5 8C19 16.5 12 21 12 21z"/></svg>' ),
			30
		);
		add_submenu_page( self::SLUG, __( 'Dashboard',  'loveallah' ), __( 'Dashboard',  'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG,                  [ __CLASS__, 'page_dashboard'  ] );
		add_submenu_page( self::SLUG, __( 'Scholars',   'loveallah' ), __( 'Scholars',   'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-scholars',   [ __CLASS__, 'page_scholars'   ] );
		add_submenu_page( self::SLUG, __( 'Scholars audit', 'loveallah' ), __( 'Scholars audit', 'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-scholars-audit', [ __CLASS__, 'page_scholars_audit' ] );
		add_submenu_page( self::SLUG, __( 'Dhikr videos', 'loveallah' ), __( 'Dhikr videos', 'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-dhikr',      [ __CLASS__, 'page_dhikr_videos' ] );
		add_submenu_page( self::SLUG, __( 'Mosques',    'loveallah' ), __( 'Mosques',    'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-mosques',    [ __CLASS__, 'page_mosques'    ] );
		add_submenu_page( self::SLUG, __( 'Events',     'loveallah' ), __( 'Events',     'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-events',     [ __CLASS__, 'page_events'     ] );
		add_submenu_page( self::SLUG, __( 'Content',    'loveallah' ), __( 'Content',    'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-content',    [ __CLASS__, 'page_content'    ] );
		add_submenu_page( self::SLUG, __( 'Subscribers','loveallah' ), __( 'Subscribers','loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-subscribers',[ __CLASS__, 'page_subscribers'] );
		add_submenu_page( self::SLUG, __( 'Email captures','loveallah' ), __( 'Email captures','loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-emails', [ __CLASS__, 'page_email_captures' ] );
		add_submenu_page( self::SLUG, __( 'Settings',   'loveallah' ), __( 'Settings',   'loveallah' ), LA_Caps::CAP_PLATFORM, self::SLUG . '-settings',   [ __CLASS__, 'page_settings'   ] );
	}

	// ────────────────────────────────────────────────────────────
	// SETTINGS API
	// ────────────────────────────────────────────────────────────
	public static function register_settings() : void {
		register_setting( 'loveallah_settings', 'la_brand_color',          [ 'sanitize_callback' => 'sanitize_hex_color' ] );
		register_setting( 'loveallah_settings', 'la_default_mosque_slug',  [ 'sanitize_callback' => 'sanitize_title' ] );
		register_setting( 'loveallah_settings', 'la_yt_sync_enabled',      [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'loveallah_settings', 'la_yt_per_sync',          [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'loveallah_settings', 'la_yt_max_duration',      [ 'sanitize_callback' => 'absint' ] );
		// Wave 87b: YouTube Data API v3 key — used as fallback for
		// channels where /shorts page scrape returns empty (Mufti
		// Menk-tier creators whose pages YouTube only serves as a
		// JS-shell to our Cloudways IP). Free tier = 10k units/day.
		register_setting( 'loveallah_settings', 'la_yt_api_key',           [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'loveallah_settings', 'la_required_dhikr',       [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'loveallah_settings', 'la_recency_window_days',  [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'loveallah_settings', 'la_android_sha256',      [ 'sanitize_callback' => 'sanitize_text_field' ] );
		register_setting( 'loveallah_settings', 'la_android_package',     [ 'sanitize_callback' => 'sanitize_text_field' ] );
	}

	// ────────────────────────────────────────────────────────────
	// DASHBOARD
	// ────────────────────────────────────────────────────────────
	public static function page_dashboard() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		global $wpdb;
		$t = LA_DB::tables();
		$scholars     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['scholars']}" );
		$mosques      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['mosques']}" );
		$feed_posts   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']}" );
		$feed_recent  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']} WHERE published_at >= DATE_SUB( NOW(), INTERVAL 7 DAY )" );
		$events       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['events']} WHERE starts_at >= NOW()" );
		$subscribers  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['subscribers']} WHERE unsubscribed_at IS NULL" );
		$last_sync    = $wpdb->get_var( "SELECT MAX(last_synced_at) FROM {$t['scholars']}" );
		$next_cron    = wp_next_scheduled( 'la_youtube_sync' );
		$schedule     = wp_get_schedule( 'la_youtube_sync' ) ?: 'unscheduled';
		$last_tick    = (array) ( get_option( 'la_yt_last_tick' ) ?: [] );
		$due_count    = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$t['scholars']}
			 WHERE source_url IS NOT NULL AND source_url <> ''
			   AND ( last_synced_at IS NULL OR last_synced_at < DATE_SUB(NOW(), INTERVAL 6 HOUR) )"
		);
		$fresh_24h    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']} WHERE created_at >= DATE_SUB( NOW(), INTERVAL 24 HOUR )" );
		?>
		<div class="wrap la-admin">
			<h1><?php esc_html_e( 'Love Allah · Dashboard', 'loveallah' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Sacred ritual app · feed-first · masjid-routed · ads fund the ummah.', 'loveallah' ); ?></p>

			<div class="la-stats">
				<?php self::stat_card( __( 'Scholars', 'loveallah' ), $scholars, 'admin.php?page=loveallah-scholars' ); ?>
				<?php self::stat_card( __( 'Mosques',  'loveallah' ), $mosques,  'admin.php?page=loveallah-mosques' ); ?>
				<?php self::stat_card( __( 'Feed posts (total)', 'loveallah' ), $feed_posts, 'admin.php?page=loveallah-content' ); ?>
				<?php self::stat_card( __( 'Fresh posts (7d)',   'loveallah' ), $feed_recent, 'admin.php?page=loveallah-content' ); ?>
				<?php self::stat_card( __( 'Upcoming events',    'loveallah' ), $events, 'admin.php?page=loveallah-events' ); ?>
				<?php self::stat_card( __( 'Subscribers',        'loveallah' ), $subscribers, 'admin.php?page=loveallah-subscribers' ); ?>
			</div>

			<h2 style="margin-top:32px;"><?php esc_html_e( 'YouTube sync', 'loveallah' ); ?></h2>

			<?php
			// Wave 87c: YouTube Data API v3 quota burn today.
			// Calls counter is incremented in LA_YouTube::yt_api_v3_shorts(),
			// resets automatically at UTC midnight via the date-stamped option key.
			$yt_api_calls = (int) get_option( 'la_yt_api_calls_today_' . gmdate( 'Ymd' ), 0 );
			$yt_api_units = $yt_api_calls * 101; // search.list (100) + videos.list (1)
			$yt_api_cap   = 90; // hard daily cap defined in yt_api_v3_shorts
			?>
			<div class="la-stats" style="margin-bottom:12px;">
				<?php self::stat_card( __( 'Channels due (>6h)', 'loveallah' ), $due_count ); ?>
				<?php self::stat_card( __( 'Ingested last 24h',  'loveallah' ), $fresh_24h ); ?>
				<?php self::stat_card( __( 'Cron schedule',      'loveallah' ), $schedule === 'la_one_hour' ? '1 hr' : esc_html( $schedule ) ); ?>
				<?php self::stat_card( __( 'API calls today', 'loveallah' ), $yt_api_calls . ' / ' . $yt_api_cap ); ?>
				<?php self::stat_card( __( 'API quota burned', 'loveallah' ), $yt_api_units . ' / 10,000' ); ?>
			</div>

			<p>
				<?php
				if ( $last_sync ) {
					/* translators: %s: time ago string */
					printf( esc_html__( 'Most recent scholar sync: %s', 'loveallah' ), esc_html( human_time_diff( strtotime( $last_sync ) ) . ' ago' ) );
				} else {
					esc_html_e( 'Never synced yet.', 'loveallah' );
				}
				echo ' · ';
				if ( $next_cron ) {
					/* translators: %s: time until next cron */
					printf( esc_html__( 'Next cron tick in %s', 'loveallah' ), esc_html( human_time_diff( time(), $next_cron ) ) );
				}
				?>
			</p>

			<?php if ( ! empty( $last_tick['at'] ) ) : ?>
				<p style="color:#5e6c84; font-size:13px;">
					<?php
					printf(
						/* translators: 1: time ago, 2: scholars count, 3: posts count */
						esc_html__( 'Last cron tick: %1$s · checked %2$d channels · inserted %3$d new posts', 'loveallah' ),
						esc_html( human_time_diff( strtotime( $last_tick['at'] ) ) . ' ago' ),
						(int) ( $last_tick['synced']   ?? 0 ),
						(int) ( $last_tick['inserted'] ?? 0 )
					);
					if ( ! empty( $last_tick['errors'] ) ) {
						echo ' · <span style="color:#a94442;">' . esc_html( count( $last_tick['errors'] ) ) . ' channel(s) had errors</span>';
					}
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px; display:inline-block;">
				<?php wp_nonce_field( 'la_yt_sync_batch' ); ?>
				<input type="hidden" name="action" value="la_yt_sync_batch">
				<?php submit_button( __( 'Sync next batch (fast)', 'loveallah' ), 'primary', '', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px; display:inline-block; margin-left:8px;">
				<?php wp_nonce_field( 'la_yt_sync_catchup' ); ?>
				<input type="hidden" name="action" value="la_yt_sync_catchup">
				<?php submit_button( __( '🚀 Pull in MORE videos now (catch-up)', 'loveallah' ), 'primary', '', false, [ 'style' => 'background:#1A8A7B; border-color:#1A8A7B;' ] ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px; display:inline-block; margin-left:8px;">
				<?php wp_nonce_field( 'la_yt_sync' ); ?>
				<input type="hidden" name="action" value="la_yt_sync">
				<?php submit_button( __( 'Sync full roster (slow)', 'loveallah' ), 'secondary', '', false ); ?>
			</form>
			<p class="description" style="margin-top:8px;">
				<strong><?php esc_html_e( '🚀 Catch-up', 'loveallah' ); ?>:</strong>
				<?php esc_html_e( 'Aggressively backfills the catalog — keeps running batches in priority order (never-synced → undersized → rest) until every channel has at least 20 videos OR ~25 passes done. Takes 5-15 min. The browser may time out but the work continues server-side. Refresh this page in 10-15 min to see the final count.', 'loveallah' ); ?>
				<br>
				<strong><?php esc_html_e( 'Batch', 'loveallah' ); ?>:</strong>
				<?php esc_html_e( 'Syncs the next 15 oldest channels (matches one hourly cron tick). Done in seconds.', 'loveallah' ); ?>
				<br>
				<strong><?php esc_html_e( 'Full roster', 'loveallah' ); ?>:</strong>
				<?php esc_html_e( 'Single sequential pass over all 255+ channels. Slow and synchronous — use only if catch-up does not converge.', 'loveallah' ); ?>
			</p>

			<h2 style="margin-top:32px;"><?php esc_html_e( 'Quick actions', 'loveallah' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars&action=add' ) ); ?>"><?php esc_html_e( 'Add scholar', 'loveallah' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-events&action=add' ) ); ?>"><?php esc_html_e( 'Add event', 'loveallah' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-mosques&action=add' ) ); ?>"><?php esc_html_e( 'Add mosque', 'loveallah' ); ?></a>
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View site →', 'loveallah' ); ?></a>
			</p>
		</div>
		<?php self::admin_css();
	}

	private static function stat_card( string $label, $value, string $link = '' ) : void {
		?>
		<div class="la-stat-card">
			<div class="la-stat-label"><?php echo esc_html( $label ); ?></div>
			<div class="la-stat-value"><?php echo esc_html( number_format( (int) $value ) ); ?></div>
			<?php if ( $link ) : ?>
				<a class="la-stat-link" href="<?php echo esc_url( admin_url( $link ) ); ?>"><?php esc_html_e( 'Manage →', 'loveallah' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	// ────────────────────────────────────────────────────────────
	// SCHOLARS AUDIT (Wave 87i)
	//
	// Iterates every scholar row and probes channels.list?forHandle for
	// each handle parsed out of source_url. Surfaces three failure modes:
	//   1. API returns NO items for the handle → handle is invalid / channel
	//      doesn't exist (rare, but real — typos or YouTube-banned channels).
	//   2. API returns the channel but lifetime videoCount = 0 → channel is
	//      dormant or wrong (Mufti Menk's @muftimenk handle hit this).
	//   3. API title doesn't match our display_name → handle points at a
	//      DIFFERENT person / org entirely (worst case — needs replacement).
	//
	// Cost: 1 quota unit per scholar × ~399 scholars = ~399 units, ~4% of
	// the 10k daily quota. Safe to run once a week as a catalog health check.
	//
	// Streams output progressively so the page stays responsive across the
	// ~80 seconds it takes to probe all 399 handles.
	// ────────────────────────────────────────────────────────────
	public static function page_scholars_audit() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );

		$api_key = (string) get_option( 'la_yt_api_key', '' );
		if ( $api_key === '' ) {
			echo '<div class="wrap"><h1>Scholars audit</h1>';
			echo '<div class="notice notice-error"><p><strong>YouTube Data API v3 key not configured.</strong> ';
			echo 'Add it on the <a href="' . esc_url( admin_url( 'admin.php?page=loveallah-settings' ) ) . '">Settings page</a> first — the audit needs the API to authoritatively resolve handle → channel.</p></div>';
			echo '</div>';
			return;
		}

		global $wpdb;
		$t = LA_DB::tables();
		// Optional filter: ?filter=problems shows only suspicious rows
		$filter = sanitize_key( $_GET['filter'] ?? '' );
		// Optional pagination so users can run partial audits without burning
		// the full ~80s in one shot. Default: probe all.
		$limit  = max( 1, min( 500, (int) ( $_GET['limit'] ?? 500 ) ) );
		$offset = max( 0, (int) ( $_GET['offset'] ?? 0 ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, display_name, source_url, youtube_channel_id
			 FROM {$t['scholars']}
			 WHERE COALESCE(is_active, 1) = 1
			 ORDER BY id ASC
			 LIMIT %d OFFSET %d",
			$limit, $offset
		) );

		ignore_user_abort( true );
		@set_time_limit( 0 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Accel-Buffering: no' );
		echo str_repeat( ' ', 1024 );
		?>
		<!doctype html>
		<html><head><meta charset="utf-8">
		<title>Scholars audit — Love Allah</title>
		<style>
			body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #1A0D26; color: #F8ECD0; padding: 32px; max-width: 1280px; margin: 0 auto; line-height: 1.5; }
			h1 { color: #F4D982; font-weight: 800; }
			.summary { padding: 12px 16px; background: rgba(255,255,255,0.06); border-left: 3px solid #C9A961; margin: 12px 0; border-radius: 6px; }
			table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
			th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.10); vertical-align: top; }
			th { background: rgba(255,255,255,0.08); color: #F4D982; font-weight: 700; position: sticky; top: 0; }
			tr.problem-fatal   { background: rgba(248,113,113,0.12); }
			tr.problem-warn    { background: rgba(250,204,21,0.10); }
			tr.problem-mismatch{ background: rgba(248,113,113,0.18); }
			tr.ok              { opacity: 0.85; }
			.badge { display: inline-block; font-size: 10px; padding: 2px 6px; border-radius: 999px; font-weight: 700; letter-spacing: 0.04em; }
			.badge-ok    { background: #166534; color: #bbf7d0; }
			.badge-warn  { background: #854d0e; color: #fde68a; }
			.badge-fatal { background: #7f1d1d; color: #fecaca; }
			code { background: rgba(0,0,0,0.30); padding: 1px 5px; border-radius: 3px; font-size: 12px; }
			a { color: #F4D982; }
			.handle { font-family: SFMono-Regular, Consolas, monospace; }
			.actions a { margin-right: 8px; }
			.controls { padding: 12px 16px; background: rgba(255,255,255,0.04); border-radius: 6px; margin-bottom: 16px; }
		</style>
		</head><body>
		<h1>🔬 Scholars audit</h1>
		<div class="controls">
			Filter: <a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars-audit' ) ); ?>">all</a>
			· <a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars-audit&filter=problems' ) ); ?>"><strong>problems only</strong></a>
			&nbsp;|&nbsp;
			Range: <code>?offset=<?php echo (int) $offset; ?>&limit=<?php echo (int) $limit; ?></code> &nbsp;
			(<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars-audit&offset=' . ( $offset + $limit ) . '&limit=' . $limit ) ); ?>">next batch →</a>)
		</div>
		<div class="summary">Probing <?php echo count( $rows ); ?> scholars. Each row = 1 API quota unit. Streaming live results below…</div>
		<table>
		<thead>
		<tr>
			<th>#</th>
			<th>Scholar (our DB)</th>
			<th>Handle</th>
			<th>API status</th>
			<th>Title (per YouTube)</th>
			<th>Lifetime videos</th>
			<th>Status</th>
			<th>Actions</th>
		</tr>
		</thead>
		<tbody>
		<?php
		flush();

		$stats = [ 'total' => 0, 'ok' => 0, 'no_match' => 0, 'dormant' => 0, 'title_mismatch' => 0, 'no_handle' => 0 ];

		foreach ( $rows as $row ) {
			$stats['total']++;

			// Parse @handle out of source_url. Channels using /channel/UCxxx
			// instead of @handle can't be audited via this endpoint (the
			// channels.list?forHandle param requires a real handle string).
			$handle = '';
			if ( preg_match( '#/@([A-Za-z0-9._-]+)#', (string) $row->source_url, $m ) ) {
				$handle = $m[1];
			}

			if ( $handle === '' ) {
				$stats['no_handle']++;
				$row_class = $filter === 'problems' ? '' : 'problem-warn';
				$show = ( $filter !== 'problems' || true ); // no-handle = always problem
				if ( $filter === 'problems' || $filter === '' ) {
					echo self::audit_row_html( $stats['total'], $row, $handle, [
						'class'  => 'problem-warn',
						'title'  => '',
						'count'  => 0,
						'status' => 'no @handle',
						'badge'  => '<span class="badge badge-warn">NO HANDLE</span>',
					] );
					flush();
				}
				continue;
			}

			// 1 quota unit per call. Sleep briefly to be polite.
			$ch_url = add_query_arg( [
				'key'       => $api_key,
				'forHandle' => '@' . $handle,
				'part'      => 'id,snippet,statistics',
			], 'https://www.googleapis.com/youtube/v3/channels' );
			$res = wp_remote_get( $ch_url, [ 'timeout' => 12 ] );

			$result = [ 'class' => 'ok', 'title' => '', 'count' => 0, 'status' => '', 'badge' => '' ];
			if ( is_wp_error( $res ) ) {
				$result['status'] = 'wp_error: ' . $res->get_error_message();
				$result['badge']  = '<span class="badge badge-warn">NETWORK</span>';
				$result['class']  = 'problem-warn';
			} else {
				$body = (string) wp_remote_retrieve_body( $res );
				$json = json_decode( $body, true );
				if ( ! is_array( $json ) || empty( $json['items'][0] ) ) {
					$result['status'] = isset( $json['error']['message'] ) ? substr( $json['error']['message'], 0, 80 ) : 'no items';
					$result['badge']  = '<span class="badge badge-fatal">NO MATCH</span>';
					$result['class']  = 'problem-fatal';
					$stats['no_match']++;
				} else {
					$item = $json['items'][0];
					$result['title'] = (string) ( $item['snippet']['title'] ?? '' );
					$result['count'] = (int) ( $item['statistics']['videoCount'] ?? 0 );
					$result['resolved_id'] = (string) ( $item['id'] ?? '' );

					// Title fuzzy-match against our display_name. Normalise both
					// (lowercase, strip punctuation/honorifics) before comparing.
					$norm = function( $s ) {
						$s = mb_strtolower( $s );
						$s = preg_replace( '#\b(sh|sheikh|dr|mufti|imam|ustadh|sheikh|qari|hafiz|brother)\.?\s+#', '', $s );
						$s = preg_replace( '#[^a-z0-9 ]+#', '', $s );
						$s = trim( preg_replace( '#\s+#', ' ', $s ) );
						return $s;
					};
					$n_our = $norm( $row->display_name );
					$n_yt  = $norm( $result['title'] );
					$title_matches = ( $n_our && $n_yt ) && ( $n_our === $n_yt || strpos( $n_yt, $n_our ) !== false || strpos( $n_our, $n_yt ) !== false );

					if ( $result['count'] === 0 ) {
						$result['status'] = 'channel exists but ZERO lifetime videos';
						$result['badge']  = '<span class="badge badge-fatal">DORMANT</span>';
						$result['class']  = 'problem-fatal';
						$stats['dormant']++;
					} elseif ( ! $title_matches ) {
						$result['status'] = 'API title does not match our display_name';
						$result['badge']  = '<span class="badge badge-fatal">WRONG PERSON?</span>';
						$result['class']  = 'problem-mismatch';
						$stats['title_mismatch']++;
					} else {
						$result['status'] = sprintf( '%s lifetime videos', number_format( $result['count'] ) );
						$result['badge']  = '<span class="badge badge-ok">OK</span>';
						$stats['ok']++;
					}
				}
			}

			$is_problem = $result['class'] !== 'ok';
			if ( $filter === 'problems' && ! $is_problem ) continue;

			echo self::audit_row_html( $stats['total'], $row, $handle, $result );
			flush();
		}
		?>
		</tbody>
		</table>
		<div class="summary">
			<strong>Done.</strong>
			Total: <?php echo (int) $stats['total']; ?>
			· OK: <?php echo (int) $stats['ok']; ?>
			· Dormant (0 videos): <?php echo (int) $stats['dormant']; ?>
			· Wrong person?: <?php echo (int) $stats['title_mismatch']; ?>
			· No match: <?php echo (int) $stats['no_match']; ?>
			· No @handle: <?php echo (int) $stats['no_handle']; ?>
		</div>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars' ) ); ?>">← Back to scholars</a></p>
		</body></html>
		<?php
		exit;
	}

	/**
	 * One row of the scholars-audit table. Kept as a helper so the main loop
	 * can stream rows progressively without holding the whole HTML in memory.
	 */
	private static function audit_row_html( int $n, $row, string $handle, array $result ) : string {
		$row_class = $result['class'];
		$edit_url  = admin_url( 'admin.php?page=loveallah-scholars&action=edit&id=' . (int) $row->id );
		$source    = (string) $row->source_url;
		$handle_md = $handle ? '<span class="handle">@' . esc_html( $handle ) . '</span>' : '<em>(no @handle in URL)</em>';
		return sprintf(
			'<tr class="%s"><td>%d</td><td><strong>%s</strong><br><small>id=%d</small></td><td>%s<br><a href="%s" target="_blank" rel="noopener">YouTube ↗</a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="actions"><a href="%s">Edit</a></td></tr>' . "\n",
			esc_attr( $row_class ),
			$n,
			esc_html( $row->display_name ),
			(int) $row->id,
			$handle_md,
			esc_url( $source ),
			esc_html( $result['status'] ?? '' ),
			esc_html( $result['title'] ?? '' ),
			number_format( (int) ( $result['count'] ?? 0 ) ),
			$result['badge'] ?? '',
			esc_url( $edit_url )
		);
	}

	// ────────────────────────────────────────────────────────────
	// SCHOLARS
	// ────────────────────────────────────────────────────────────
	public static function page_scholars() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( $action === 'add' || $action === 'edit' ) {
			self::form_scholar( $action === 'edit' ? (int) ( $_GET['id'] ?? 0 ) : 0 );
		} else {
			self::list_scholars();
		}
	}

	private static function list_scholars() : void {
		global $wpdb;
		$t = LA_DB::tables();

		// Wave 70/71: filters + search + per-channel metrics + status
		$filter_type   = sanitize_key( $_GET['type'] ?? '' );
		$filter_status = sanitize_key( $_GET['status'] ?? 'active' ); // default: only show active
		$search        = sanitize_text_field( $_GET['q'] ?? '' );
		$orderby       = sanitize_key( $_GET['orderby'] ?? 'videos' );
		$valid_order   = [ 'name', 'videos', 'views_30d', 'last_sync' ];
		if ( ! in_array( $orderby, $valid_order, true ) ) $orderby = 'videos';

		// Single query: scholars LEFT JOIN aggregated counts + 30-day views
		$where  = "WHERE 1=1";
		$args   = [];
		if ( $filter_type ) {
			$where .= " AND s.default_content_type = %s";
			$args[] = $filter_type;
		}
		// Wave 71 hotfix: only apply status filter if column exists
		$has_status_col = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = %s
			   AND COLUMN_NAME = 'status'",
			$t['scholars']
		) );
		if ( $has_status_col && $filter_status && $filter_status !== 'all' ) {
			if ( $filter_status === 'active' ) {
				$where .= " AND ( s.status IS NULL OR s.status = '' OR s.status = 'active' )";
			} else {
				$where .= " AND s.status = %s";
				$args[] = $filter_status;
			}
		}
		if ( $search ) {
			$where .= " AND (s.display_name LIKE %s OR s.username LIKE %s)";
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$sql = "
			SELECT s.*,
			       COALESCE(p.video_count, 0)  AS video_count,
			       COALESCE(v.views_30d,   0)  AS views_30d
			FROM {$t['scholars']} s
			LEFT JOIN (
			  SELECT scholar_id, COUNT(*) AS video_count
			  FROM {$t['feed_posts']}
			  GROUP BY scholar_id
			) p ON p.scholar_id = s.id
			LEFT JOIN (
			  SELECT fp.scholar_id, COUNT(*) AS views_30d
			  FROM {$t['feed_interactions']} fi
			  JOIN {$t['feed_posts']} fp ON fp.id = fi.post_id
			  WHERE fi.action = 'view'
			    AND fi.occurred_at >= DATE_SUB( NOW(), INTERVAL 30 DAY )
			  GROUP BY fp.scholar_id
			) v ON v.scholar_id = s.id
			$where
			ORDER BY ";
		switch ( $orderby ) {
			case 'name':      $sql .= "s.display_name ASC"; break;
			case 'views_30d': $sql .= "views_30d DESC, video_count DESC"; break;
			case 'last_sync': $sql .= "s.last_synced_at DESC, s.display_name ASC"; break;
			case 'videos':
			default:          $sql .= "video_count DESC, s.display_name ASC"; break;
		}

		$rows = $args
			? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
			: $wpdb->get_results( $sql );

		// Category counts for the filter chips (always show all categories)
		$type_counts = [];
		foreach ( $wpdb->get_results(
			"SELECT default_content_type AS t, COUNT(*) AS c
			 FROM {$t['scholars']}
			 GROUP BY default_content_type"
		) as $r ) {
			$type_counts[ $r->t ?: 'unset' ] = (int) $r->c;
		}

		$type_options = [
			''            => __( 'All categories', 'loveallah' ),
			'reminder'    => __( 'Reminder / short lecture', 'loveallah' ),
			'nasheed'     => __( 'Nasheed', 'loveallah' ),
			'dhikr'       => __( 'Dhikr', 'loveallah' ),
			'mindfulness' => __( 'Mindfulness', 'loveallah' ),
			'qirat'       => __( "Qira'at", 'loveallah' ),
			'lecture'     => __( 'Long-form lecture', 'loveallah' ),
		];

		$total_videos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']}" );
		$total_views_30d = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$t['feed_interactions']}
			 WHERE action = 'view' AND occurred_at >= DATE_SUB( NOW(), INTERVAL 30 DAY )"
		);
		?>
		<div class="wrap la-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Scholars', 'loveallah' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'loveallah' ); ?></a>
			<hr class="wp-header-end">

			<div class="la-stat-row" style="display:flex; gap:14px; margin:14px 0 18px;">
				<div style="padding:10px 14px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
					<div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Channels</div>
					<div style="font-size:22px; font-weight:800;"><?php echo number_format( count( $rows ) ); ?></div>
				</div>
				<div style="padding:10px 14px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
					<div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Videos (total)</div>
					<div style="font-size:22px; font-weight:800;"><?php echo number_format( $total_videos ); ?></div>
				</div>
				<div style="padding:10px 14px; background:#fff; border:1px solid #ccd0d4; border-radius:8px;">
					<div style="font-size:11px; color:#666; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">Views (30 d)</div>
					<div style="font-size:22px; font-weight:800;"><?php echo number_format( $total_views_30d ); ?></div>
				</div>
			</div>

			<form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
				<input type="hidden" name="page" value="loveallah-scholars">
				<label>
					<span style="font-weight:600; margin-right:4px;">Category:</span>
					<select name="type" onchange="this.form.submit()">
						<?php foreach ( $type_options as $val => $label ) :
							$cnt = $type_counts[ $val ?: '' ] ?? null; ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_type, $val ); ?>>
								<?php echo esc_html( $label ); ?><?php echo ( $val && isset( $type_counts[ $val ] ) ) ? ' (' . (int) $type_counts[ $val ] . ')' : ''; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name or username…" style="min-width:220px;">
				<label>
					<span style="font-weight:600; margin-right:4px;">Status:</span>
					<select name="status" onchange="this.form.submit()">
						<option value="active"   <?php selected( $filter_status, 'active' ); ?>>Active only</option>
						<option value="hidden"   <?php selected( $filter_status, 'hidden' ); ?>>Hidden</option>
						<option value="archived" <?php selected( $filter_status, 'archived' ); ?>>Archived</option>
						<option value="all"      <?php selected( $filter_status, 'all' ); ?>>All statuses</option>
					</select>
				</label>
				<label>
					<span style="font-weight:600; margin-right:4px;">Sort:</span>
					<select name="orderby" onchange="this.form.submit()">
						<option value="videos"    <?php selected( $orderby, 'videos' ); ?>>Most videos</option>
						<option value="views_30d" <?php selected( $orderby, 'views_30d' ); ?>>Most viewed (30 d)</option>
						<option value="last_sync" <?php selected( $orderby, 'last_sync' ); ?>>Recently synced</option>
						<option value="name"      <?php selected( $orderby, 'name' ); ?>>Name (A→Z)</option>
					</select>
				</label>
				<button class="button" type="submit">Apply</button>
				<?php if ( $filter_type || $search || $filter_status !== 'active' ) : ?>
					<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars' ) ); ?>">Clear filters</a>
				<?php endif; ?>
			</form>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Channel', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Category', 'loveallah' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Videos', 'loveallah' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Views (30d)', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Last synced', 'loveallah' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $s ) :
					$edit_url = admin_url( 'admin.php?page=loveallah-scholars&action=edit&id=' . (int) $s->id );
					$del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=la_delete&type=scholar&id=' . (int) $s->id ), 'la_delete_scholar_' . $s->id );
					$verified_badge = $s->account_type === 'verified' ? ' <span style="color:#1A8A7B;" title="Verified — partnered">✓</span>' : '';
					$status = $s->status ?? 'active';
					if ( $status === '' ) $status = 'active';
					$is_hidden = ( $status === 'hidden' || $status === 'archived' );
					$row_dim_style = $is_hidden ? 'opacity:0.55;' : '';
				?>
					<tr style="<?php echo esc_attr( $row_dim_style ); ?>">
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $s->display_name ); ?></a></strong><?php echo $verified_badge; ?>
							<?php if ( $status === 'hidden' ) : ?>
								<span style="background:#fef3c7; color:#92400e; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; margin-left:6px; text-transform:uppercase; letter-spacing:0.05em;">Hidden</span>
							<?php elseif ( $status === 'archived' ) : ?>
								<span style="background:#fee2e2; color:#991b1b; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; margin-left:6px; text-transform:uppercase; letter-spacing:0.05em;">Archived</span>
							<?php endif; ?>
							<br>
							<code style="font-size:11px; color:#666;">@<?php echo esc_html( $s->username ); ?></code>
							<?php if ( $s->source_url ) : ?>
								· <a href="<?php echo esc_url( $s->source_url ); ?>" target="_blank" style="font-size:11px;">YouTube ↗</a>
							<?php endif; ?>
							<?php if ( ! empty( $s->last_sync_error ) ) : ?>
								<div style="margin-top:4px; font-size:11px; color:#a00; max-width:340px;" title="<?php echo esc_attr( $s->last_sync_error ); ?>">⚠ <?php echo esc_html( mb_substr( $s->last_sync_error, 0, 80 ) ); ?></div>
							<?php endif; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
								<?php wp_nonce_field( 'la_scholar_set_type_' . $s->id ); ?>
								<input type="hidden" name="action" value="la_scholar_set_type">
								<input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
								<select name="default_content_type" onchange="this.form.submit()" style="min-width:140px;">
									<?php foreach ( [
										'reminder', 'nasheed', 'dhikr', 'mindfulness', 'qirat', 'lecture'
									] as $val ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s->default_content_type, $val ); ?>>
											<?php echo esc_html( ucfirst( $val ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</form>
						</td>
						<td style="text-align:right; font-variant-numeric:tabular-nums; font-weight:600;">
							<?php echo number_format( (int) $s->video_count ); ?>
						</td>
						<td style="text-align:right; font-variant-numeric:tabular-nums;">
							<?php echo number_format( (int) $s->views_30d ); ?>
						</td>
						<td style="font-size:12px; color:#666;">
							<?php echo $s->last_synced_at ? esc_html( human_time_diff( strtotime( $s->last_synced_at ) ) . ' ago' ) : '<span style="color:#a00;">never</span>'; ?>
						</td>
						<td style="white-space:nowrap;">
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'loveallah' ); ?></a>
							<?php
							// Wave 78: per-scholar "Sync now" — runs sync_scholar()
							// immediately in deep mode and streams the result so
							// the admin can verify a specific channel pulls.
							$sync_url = wp_nonce_url(
								admin_url( 'admin-post.php?action=la_scholar_sync_now&id=' . (int) $s->id ),
								'la_scholar_sync_now_' . $s->id
							);
							?>
							· <a href="<?php echo esc_url( $sync_url ); ?>" style="color:#1A8A7B; font-weight:700;" title="<?php esc_attr_e( 'Fetch the latest videos from this channel right now (RSS + scrape, up to ~30 videos)', 'loveallah' ); ?>"><?php esc_html_e( 'Sync now', 'loveallah' ); ?></a>
							<?php
							// Hide/show toggle — single-click to flip status
							$next_status = $is_hidden ? 'active' : 'hidden';
							$toggle_label = $is_hidden ? __( 'Show', 'loveallah' ) : __( 'Hide', 'loveallah' );
							$toggle_color = $is_hidden ? '#1A8A7B' : '#b45309';
							$toggle_url = wp_nonce_url(
								admin_url( 'admin-post.php?action=la_scholar_set_status&id=' . (int) $s->id . '&status=' . $next_status ),
								'la_scholar_set_status_' . $s->id
							);
							?>
							· <a href="<?php echo esc_url( $toggle_url ); ?>" style="color:<?php echo esc_attr( $toggle_color ); ?>; font-weight:700;" title="<?php echo $is_hidden ? esc_attr__( 'Re-enable this scholar — content appears in feeds again', 'loveallah' ) : esc_attr__( 'Hide all content from this scholar — feed excludes them', 'loveallah' ); ?>"><?php echo esc_html( $toggle_label ); ?></a>
							· <a href="<?php echo esc_url( $del_url ); ?>" style="color:#a00;" onclick="return confirm('<?php esc_attr_e( 'Delete this scholar permanently? Their posts become orphaned. For temporary blocks use Hide instead.', 'loveallah' ); ?>');"><?php esc_html_e( 'Delete', 'loveallah' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><em><?php esc_html_e( 'No scholars match — try clearing filters.', 'loveallah' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php self::admin_css();
	}

	// Wave 71: hide/show/archive a scholar with one click
	public static function handle_scholar_set_status() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'la_scholar_set_status_' . $id );
		$status = sanitize_key( $_GET['status'] ?? 'active' );
		$allowed = [ 'active', 'hidden', 'archived' ];
		if ( $id && in_array( $status, $allowed, true ) ) {
			global $wpdb;
			$t = LA_DB::tables();
			$wpdb->update( $t['scholars'], [ 'status' => $status ], [ 'id' => $id ] );
			$label = $status === 'hidden' ? __( 'hidden from feeds', 'loveallah' )
			       : ( $status === 'archived' ? __( 'archived', 'loveallah' )
			       : __( 're-enabled', 'loveallah' ) );
			set_transient( 'la_admin_notice', sprintf( __( 'Channel %s.', 'loveallah' ), $label ), 10 );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=loveallah-scholars' ) );
		exit;
	}

	/**
	 * Wave 78: Force-sync a single scholar right now. Streams progress.
	 * Used when the priority queue hasn't gotten around to a high-value
	 * channel yet, or when verifying a fix on a specific scholar's
	 * source_url. Runs in deep mode so up to 500 videos get pulled.
	 */
	public static function handle_scholar_sync_now() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'la_scholar_sync_now_' . $id );

		global $wpdb;
		$t = LA_DB::tables();
		$scholar = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['scholars']} WHERE id = %d LIMIT 1",
			$id
		) );
		if ( ! $scholar ) wp_die( 'Scholar not found' );

		ignore_user_abort( true );
		@set_time_limit( 120 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Accel-Buffering: no' );
		echo str_repeat( ' ', 1024 );
		flush();

		$before = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t['feed_posts']} WHERE scholar_id = %d",
			$id
		) );
		?>
		<!doctype html>
		<html><head><meta charset="utf-8">
		<title>Syncing <?php echo esc_html( $scholar->display_name ); ?>…</title>
		<style>
			body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #1A0D26; color: #F8ECD0; padding: 32px; max-width: 720px; margin: 0 auto; line-height: 1.5; }
			h1 { color: #F4D982; font-weight: 800; }
			.box { padding: 14px 18px; background: rgba(255,255,255,0.06); border-left: 3px solid #C9A961; margin: 12px 0; border-radius: 6px; font-variant-numeric: tabular-nums; }
			.done { border-left-color: #4ade80; font-size: 16px; font-weight: 700; }
			.err  { border-left-color: #f87171; }
			a { color: #F4D982; }
			code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px; font-size: 12px; }
		</style>
		</head><body>
		<h1>⚡ Sync now: <?php echo esc_html( $scholar->display_name ); ?></h1>
		<div class="box">Source: <code><?php echo esc_html( $scholar->source_url ); ?></code></div>
		<div class="box">Videos before: <strong><?php echo $before; ?></strong></div>
		<?php
		// Wave 78d: pre-run probe — exposes RSS + scrape state so we know
		// WHY a channel returns nothing before sync_scholar's verdict.
		$probe = LA_YouTube::debug_probe( $scholar );
		?>
		<div class="box">
			<strong>🔬 Probe</strong><br>
			cached channel_id: <code><?php echo esc_html( $probe['cached_cid'] ?: '(none)' ); ?></code><br>
			<?php if ( ! $probe['cached_cid'] && $probe['resolved_cid'] ) : ?>
				resolved channel_id: <code><?php echo esc_html( $probe['resolved_cid'] ); ?></code><br>
			<?php endif; ?>
			RSS: status=<code><?php echo esc_html( $probe['rss_status'] ); ?></code>
			· body=<?php echo (int) $probe['rss_body_len']; ?>B
			· entries=<strong><?php echo (int) $probe['rss_entries']; ?></strong><br>
			<?php if ( ! empty( $probe['invidious_tries'] ) ) : ?>
				Invidious:<br>
				<?php foreach ( (array) $probe['invidious_tries'] as $line ) : ?>
					&nbsp;&nbsp;<code><?php echo esc_html( $line ); ?></code><br>
				<?php endforeach; ?>
			<?php endif; ?>
			Scrape URL: <code><?php echo esc_html( $probe['scrape_url'] ); ?></code><br>
			Scrape: status=<code><?php echo esc_html( $probe['scrape_status'] ); ?></code>
			· body=<?php echo (int) $probe['scrape_body_len']; ?>B
			· UC-hits=<?php echo (int) $probe['scrape_uc_hits']; ?>
			· vidId-hits=<strong><?php echo (int) $probe['scrape_vid_hits']; ?></strong>
			· mobile-fallback=<?php echo $probe['scrape_mobile'] ? 'YES' : 'no'; ?><br>
			page-title: <code><?php echo esc_html( $probe['title'] ?? '' ); ?></code><br>
			has-ytInitialData=<?php echo ! empty( $probe['has_yt_data'] ) ? 'yes' : 'NO'; ?>
			· has-ytcfg=<?php echo ! empty( $probe['has_ytcfg'] ) ? 'yes' : 'NO'; ?>
			· /watch?v hits=<strong><?php echo (int) ( $probe['watch_v_hits'] ?? 0 ); ?></strong>
			· /shorts/ hits=<strong><?php echo (int) ( $probe['shorts_hits'] ?? 0 ); ?></strong><br>
			escaped-vidId hits=<?php echo (int) ( $probe['esc_vid_hits'] ?? 0 ); ?>
			· richItemRenderer=<?php echo (int) ( $probe['rich_item_hits'] ?? 0 ); ?>
			· videoRenderer=<?php echo (int) ( $probe['video_renderer'] ?? 0 ); ?>
			· captcha=<?php echo ! empty( $probe['captcha'] ) ? 'YES' : 'no'; ?>
			· consent-redirect=<?php echo ! empty( $probe['consent_page'] ) ? 'YES' : 'no'; ?>
		</div>
		<?php
		// Wave 87e — surface the shorts-only + API v3 diagnostics so we can
		// see what the new pipeline branches actually return for this channel.
		?>
		<div class="box">
			<strong>🩺 Shorts-only probe (Wave 87e)</strong><br>
			shorts_only_flag: <strong><?php echo (int) ( $probe['shorts_only_flag'] ?? 0 ); ?></strong>
			<?php if ( empty( $probe['shorts_only_flag'] ) ) : ?>
				<span style="color:#f87171"> ← legacy path active; flip to 1 to enable Shorts-only ingest</span>
			<?php endif; ?>
			<br>
			<?php if ( ! empty( $probe['shorts_page_url'] ) ) : ?>
				Shorts URL: <code><?php echo esc_html( $probe['shorts_page_url'] ); ?></code><br>
				Shorts page: status=<code><?php echo esc_html( $probe['shorts_page_status'] ?? '' ); ?></code>
				· body=<?php echo (int) ( $probe['shorts_page_body_len'] ?? 0 ); ?>B
				· videoId-hits=<strong><?php echo (int) ( $probe['shorts_page_vid_hits'] ?? 0 ); ?></strong>
				· mobile-shell=<?php echo ! empty( $probe['shorts_page_mobile'] ) ? 'YES' : 'no'; ?><br>
			<?php endif; ?>
			YouTube API v3 key set: <?php echo ! empty( $probe['api_v3_key_set'] ) ? 'YES' : 'NO'; ?><br>
			<?php if ( isset( $probe['api_v3_resolved_cid'] ) ) : ?>
				channels.list?forHandle: status=<code><?php echo esc_html( $probe['api_v3_channels_status'] ?? '' ); ?></code>
				· resolved id=<code><?php echo esc_html( $probe['api_v3_resolved_cid'] ); ?></code>
				· title=<code><?php echo esc_html( $probe['api_v3_resolved_title'] ?? '' ); ?></code>
				· lifetime-videos=<strong><?php echo (int) ( $probe['api_v3_resolved_videos'] ?? 0 ); ?></strong>
				<?php if ( empty( $probe['api_v3_cid_matches'] ) ) : ?>
					<span style="color:#f87171"> ← <strong>MISMATCH with cached id</strong> — scholar row needs re-resolution</span>
				<?php else : ?>
					<span style="color:#4ade80"> ← matches cached id ✓</span>
				<?php endif; ?>
				<br>
			<?php elseif ( isset( $probe['api_v3_channels_error'] ) ) : ?>
				channels.list?forHandle: <span style="color:#f87171">error: <code><?php echo esc_html( $probe['api_v3_channels_error'] ); ?></code></span><br>
			<?php endif; ?>
			<?php if ( isset( $probe['api_v3_search_status'] ) ) : ?>
				search.list: status=<code><?php echo esc_html( $probe['api_v3_search_status'] ); ?></code>
				· items=<strong><?php echo (int) ( $probe['api_v3_search_items'] ?? 0 ); ?></strong>
				<?php if ( ! empty( $probe['api_v3_search_error'] ) ) : ?>
					· <span style="color:#f87171">error: <code><?php echo esc_html( $probe['api_v3_search_error'] ); ?></code></span>
				<?php endif; ?>
				<br>
				videos.list shorts (≤61s): <strong><?php echo (int) ( $probe['api_v3_shorts_count'] ?? 0 ); ?></strong>
			<?php endif; ?>
		</div>
		<?php
		flush();
		$start = microtime( true );
		$result = [];
		try {
			$result = LA_YouTube::sync_scholar( $scholar, [ 'deep' => true ] );
		} catch ( Throwable $e ) {
			$result = [ 'inserted' => 0, 'reason' => 'exception: ' . $e->getMessage() ];
		}
		$after = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t['feed_posts']} WHERE scholar_id = %d",
			$id
		) );
		$elapsed = number_format( microtime( true ) - $start, 1 );
		$cls = ( ! empty( $result['inserted'] ) ) ? 'done' : 'err';
		?>
		<div class="box <?php echo $cls; ?>">
			Inserted: <strong><?php echo (int) ( $result['inserted'] ?? 0 ); ?></strong>
			· Fetched: <?php echo (int) ( $result['fetched'] ?? 0 ); ?>
			· Via: <?php echo esc_html( $result['via'] ?? '—' ); ?>
			· Elapsed: <?php echo esc_html( $elapsed ); ?>s
		</div>
		<div class="box">Total videos now: <strong><?php echo $after; ?></strong> (was <?php echo $before; ?>)</div>
		<?php if ( ! empty( $result['reason'] ) ) : ?>
			<div class="box err">Reason: <code><?php echo esc_html( $result['reason'] ); ?></code></div>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars' ) ); ?>">← Back to scholars</a></p>
		</body></html>
		<?php
		exit;
	}

	// Wave 70: inline category re-tag (dropdown on the scholars list)
	public static function handle_scholar_set_type() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		$id = (int) ( $_POST['id'] ?? 0 );
		check_admin_referer( 'la_scholar_set_type_' . $id );
		$type = sanitize_key( $_POST['default_content_type'] ?? '' );
		$allowed = [ 'reminder', 'nasheed', 'dhikr', 'mindfulness', 'qirat', 'lecture' ];
		if ( $id && in_array( $type, $allowed, true ) ) {
			global $wpdb;
			$t = LA_DB::tables();
			$wpdb->update( $t['scholars'], [ 'default_content_type' => $type ], [ 'id' => $id ] );
			set_transient( 'la_admin_notice', __( 'Channel category updated.', 'loveallah' ), 10 );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=loveallah-scholars' ) );
		exit;
	}

	// ────────────────────────────────────────────────────────────
	// WAVE 70: Dhikr videos admin (Witness mode curated content)
	// ────────────────────────────────────────────────────────────
	public static function page_dhikr_videos() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( $action === 'add' || $action === 'edit' ) {
			self::form_dhikr_video( (int) ( $_GET['id'] ?? 0 ) );
			return;
		}
		self::list_dhikr_videos();
	}

	private static function list_dhikr_videos() : void {
		global $wpdb;
		$t = LA_DB::tables();
		$rows = $wpdb->get_results( "SELECT * FROM {$t['dhikr_videos']} ORDER BY sort_order ASC, id ASC" );
		?>
		<div class="wrap la-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Dhikr videos', 'loveallah' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-dhikr&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'loveallah' ); ?></a>
			<hr class="wp-header-end">
			<p class="description" style="margin:14px 0;">
				<?php esc_html_e( "These are the curated videos shown in Dhikr → Witness mode. Hand-picked dhikr loops (kalimah, salawat, takbir, etc.) — not algorithmic. Each row maps to one YouTube video. Lower sort_order shows first.", 'loveallah' ); ?>
			</p>
			<table class="widefat striped">
				<thead><tr>
					<th style="width:60px;">#</th>
					<th><?php esc_html_e( 'Thumbnail', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Title', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Scholar / channel', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Phrase', 'loveallah' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Duration', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'YouTube ID', 'loveallah' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $v ) :
					$edit_url = admin_url( 'admin.php?page=loveallah-dhikr&action=edit&id=' . (int) $v->id );
					$del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=la_dhikr_delete&id=' . (int) $v->id ), 'la_dhikr_delete_' . $v->id );
					$thumb = 'https://i.ytimg.com/vi/' . urlencode( $v->youtube_id ) . '/default.jpg';
					$watch = 'https://www.youtube.com/watch?v=' . urlencode( $v->youtube_id );
					$dur_m = (int) ( $v->duration_sec / 60 );
				?>
					<tr>
						<td><?php echo (int) $v->sort_order; ?></td>
						<td><a href="<?php echo esc_url( $watch ); ?>" target="_blank"><img src="<?php echo esc_url( $thumb ); ?>" width="80" height="60" style="border-radius:4px;"></a></td>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $v->title ); ?></a></strong></td>
						<td><?php echo esc_html( $v->scholar_name ?: '—' ); ?><?php echo $v->channel_handle ? '<br><small style="color:#666;">@' . esc_html( $v->channel_handle ) . '</small>' : ''; ?></td>
						<td><code><?php echo esc_html( $v->phrase ?: '—' ); ?></code></td>
						<td style="text-align:right; font-variant-numeric:tabular-nums;"><?php echo $dur_m ? $dur_m . 'm' : '—'; ?></td>
						<td><code><?php echo esc_html( $v->youtube_id ); ?></code></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'loveallah' ); ?></a> ·
							<a href="<?php echo esc_url( $watch ); ?>" target="_blank"><?php esc_html_e( 'Preview', 'loveallah' ); ?></a> ·
							<a href="<?php echo esc_url( $del_url ); ?>" style="color:#a00;" onclick="return confirm('Delete this dhikr video?');"><?php esc_html_e( 'Delete', 'loveallah' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8"><em><?php esc_html_e( 'No dhikr videos yet. Add one to populate the Witness feed.', 'loveallah' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php self::admin_css();
	}

	private static function form_dhikr_video( int $id ) : void {
		global $wpdb;
		$t = LA_DB::tables();
		$v = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['dhikr_videos']} WHERE id = %d", $id ) ) : null;
		?>
		<div class="wrap la-admin">
			<h1><?php echo $v ? esc_html__( 'Edit dhikr video', 'loveallah' ) : esc_html__( 'Add dhikr video', 'loveallah' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'la_dhikr_save' ); ?>
				<input type="hidden" name="action" value="la_dhikr_save">
				<input type="hidden" name="id" value="<?php echo (int) ( $v->id ?? 0 ); ?>">
				<table class="form-table"><tbody>
					<tr><th><label for="youtube_id">YouTube ID *</label></th>
						<td><input class="regular-text" required type="text" id="youtube_id" name="youtube_id" value="<?php echo esc_attr( $v->youtube_id ?? '' ); ?>" placeholder="e.g. psN1gCbTgLc">
						<p class="description"><?php esc_html_e( 'The 11-character ID from the YouTube URL (after watch?v=).', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="title">Title *</label></th>
						<td><input class="regular-text" required type="text" id="title" name="title" value="<?php echo esc_attr( $v->title ?? '' ); ?>"></td></tr>
					<tr><th><label for="scholar_name">Scholar / reciter</label></th>
						<td><input class="regular-text" type="text" id="scholar_name" name="scholar_name" value="<?php echo esc_attr( $v->scholar_name ?? '' ); ?>" placeholder="e.g. Shaykh Hasan Ali"></td></tr>
					<tr><th><label for="channel_handle">Channel handle</label></th>
						<td><input class="regular-text" type="text" id="channel_handle" name="channel_handle" value="<?php echo esc_attr( $v->channel_handle ?? '' ); ?>" placeholder="e.g. Alfalaah"></td></tr>
					<tr><th><label for="phrase">Phrase tag</label></th>
						<td><select id="phrase" name="phrase">
							<?php $phrases = [
								''               => '— Any / unspecified —',
								'la_ilaha'       => 'La ilaha illa Allah',
								'subhanallah'    => 'Subhanallah',
								'alhamdulillah'  => 'Alhamdulillah',
								'allahuakbar'    => 'Allahu Akbar',
								'astaghfirullah' => 'Astaghfirullah',
								'salawat'        => 'Salawat',
								'mixed'          => 'Mixed dhikr',
							]; foreach ( $phrases as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( ( $v->phrase ?? '' ), $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used for filtering inside Witness mode (future feature).', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="duration_sec">Duration (seconds)</label></th>
						<td><input class="small-text" type="number" id="duration_sec" name="duration_sec" value="<?php echo (int) ( $v->duration_sec ?? 0 ); ?>" min="0">
						<p class="description"><?php esc_html_e( 'e.g. 3600 for a 1-hour loop.', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="sort_order">Sort order</label></th>
						<td><input class="small-text" type="number" id="sort_order" name="sort_order" value="<?php echo (int) ( $v->sort_order ?? 0 ); ?>">
						<p class="description"><?php esc_html_e( 'Lower numbers show first in the Witness feed. Use 10, 20, 30… to leave space for inserts.', 'loveallah' ); ?></p></td></tr>
				</tbody></table>
				<?php submit_button( $v ? __( 'Update dhikr video', 'loveallah' ) : __( 'Add dhikr video', 'loveallah' ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-dhikr' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'loveallah' ); ?></a>
			</form>
		</div>
		<?php self::admin_css();
	}

	public static function handle_dhikr_save() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		check_admin_referer( 'la_dhikr_save' );
		global $wpdb;
		$t = LA_DB::tables();
		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = [
			'youtube_id'     => preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $_POST['youtube_id'] ?? '' ) ),
			'title'          => sanitize_text_field( $_POST['title'] ?? '' ),
			'scholar_name'   => sanitize_text_field( $_POST['scholar_name'] ?? '' ) ?: null,
			'channel_handle' => sanitize_text_field( $_POST['channel_handle'] ?? '' ) ?: null,
			'phrase'         => sanitize_key( $_POST['phrase'] ?? '' ) ?: null,
			'duration_sec'   => max( 0, (int) ( $_POST['duration_sec'] ?? 0 ) ),
			'sort_order'     => (int) ( $_POST['sort_order'] ?? 0 ),
		];
		if ( ! $data['youtube_id'] || ! $data['title'] ) {
			set_transient( 'la_admin_notice', __( 'YouTube ID and title are required.', 'loveallah' ), 10 );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=loveallah-dhikr' ) );
			exit;
		}
		if ( $id ) {
			$wpdb->update( $t['dhikr_videos'], $data, [ 'id' => $id ] );
			set_transient( 'la_admin_notice', __( 'Dhikr video updated.', 'loveallah' ), 10 );
		} else {
			$wpdb->insert( $t['dhikr_videos'], $data );
			set_transient( 'la_admin_notice', __( 'Dhikr video added — appears in Witness mode immediately.', 'loveallah' ), 10 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=loveallah-dhikr' ) );
		exit;
	}

	public static function handle_dhikr_delete() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		$id = (int) ( $_GET['id'] ?? 0 );
		check_admin_referer( 'la_dhikr_delete_' . $id );
		if ( $id ) {
			global $wpdb;
			$t = LA_DB::tables();
			$wpdb->delete( $t['dhikr_videos'], [ 'id' => $id ] );
			set_transient( 'la_admin_notice', __( 'Dhikr video deleted.', 'loveallah' ), 10 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=loveallah-dhikr' ) );
		exit;
	}

	private static function form_scholar( int $id ) : void {
		global $wpdb;
		$t = LA_DB::tables();
		$s = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['scholars']} WHERE id = %d", $id ) ) : null;
		?>
		<div class="wrap la-admin">
			<h1><?php echo $s ? esc_html__( 'Edit scholar', 'loveallah' ) : esc_html__( 'Add scholar', 'loveallah' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'la_save_scholar' ); ?>
				<input type="hidden" name="action" value="la_save_scholar">
				<input type="hidden" name="id" value="<?php echo (int) ( $s->id ?? 0 ); ?>">
				<table class="form-table"><tbody>
					<tr><th><label for="display_name"><?php esc_html_e( 'Display name', 'loveallah' ); ?> *</label></th>
						<td><input class="regular-text" required type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $s->display_name ?? '' ); ?>"></td></tr>
					<tr><th><label for="username"><?php esc_html_e( 'Username (slug)', 'loveallah' ); ?> *</label></th>
						<td><input class="regular-text" required type="text" id="username" name="username" value="<?php echo esc_attr( $s->username ?? '' ); ?>">
						<p class="description"><?php esc_html_e( 'Lowercase, no spaces. Used in URLs (e.g. /scholar/muftimenk).', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="account_type"><?php esc_html_e( 'Account type', 'loveallah' ); ?></label></th>
						<td><select id="account_type" name="account_type">
							<option value="curated" <?php selected( ( $s->account_type ?? '' ), 'curated' ); ?>><?php esc_html_e( 'Curated 📚 (reposted with attribution)', 'loveallah' ); ?></option>
							<option value="verified" <?php selected( ( $s->account_type ?? '' ), 'verified' ); ?>><?php esc_html_e( 'Verified ✓ (partnered)', 'loveallah' ); ?></option>
						</select></td></tr>
					<tr><th><label for="source_url"><?php esc_html_e( 'YouTube channel URL', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="url" id="source_url" name="source_url" value="<?php echo esc_attr( $s->source_url ?? '' ); ?>" placeholder="https://www.youtube.com/@handle">
						<p class="description"><?php esc_html_e( 'Channel ID resolves automatically on first sync.', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="default_content_type"><?php esc_html_e( 'Default content type', 'loveallah' ); ?></label></th>
						<td><select id="default_content_type" name="default_content_type">
							<?php $cur_ct = $s->default_content_type ?? 'reminder';
							$ct_options = [
								'reminder'    => __( 'Reminder / Short lecture (default)', 'loveallah' ),
								'nasheed'     => __( 'Nasheed', 'loveallah' ),
								'dhikr'       => __( 'Dhikr (remembrance recitation)', 'loveallah' ),
								'mindfulness' => __( 'Mindfulness (ambient · focus · work)', 'loveallah' ),
								'qirat'       => __( "Qira'at (Qur'an recitation)", 'loveallah' ),
								'lecture'     => __( 'Long-form lecture', 'loveallah' ),
							];
							foreach ( $ct_options as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cur_ct, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'New posts auto-synced from this channel will get this type. Drives the feed filter chips.', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="bio"><?php esc_html_e( 'Bio', 'loveallah' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="bio" name="bio"><?php echo esc_textarea( $s->bio ?? '' ); ?></textarea></td></tr>
					<tr><th><label for="associated_charity"><?php esc_html_e( 'Associated charity', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="associated_charity" name="associated_charity" value="<?php echo esc_attr( $s->associated_charity ?? '' ); ?>"></td></tr>
					<?php // Wave 73: 365-day deep import. Only offered for NEW
					      // scholars — existing ones use the catch-up button
					      // on the dashboard. Defaults to ON since it's the
					      // whole point of adding a new channel. ?>
					<?php if ( ! $s ) : ?>
					<tr><th><label for="deep_import"><?php esc_html_e( 'Import last 365 days now', 'loveallah' ); ?></label></th>
						<td>
							<label style="font-weight:600;">
								<input type="checkbox" id="deep_import" name="deep_import" value="1" checked>
								<?php esc_html_e( 'Pull up to 500 latest videos from this channel immediately after saving', 'loveallah' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Runs yt-dlp deep mode in the background. You\'ll see live progress on the next screen. Safe to close the tab — the import keeps running.', 'loveallah' ); ?></p>
						</td></tr>
					<?php endif; ?>
				</tbody></table>
				<?php submit_button( $s ? __( 'Update scholar', 'loveallah' ) : __( 'Create scholar', 'loveallah' ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'loveallah' ); ?></a>
			</form>
		</div>
		<?php self::admin_css();
	}

	public static function handle_save_scholar() : void {
		check_admin_referer( 'la_save_scholar' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		global $wpdb;
		$t = LA_DB::tables();

		$allowed_types = [ 'reminder', 'nasheed', 'dhikr', 'mindfulness', 'qirat', 'lecture' ];
		$data = [
			'display_name'         => sanitize_text_field( $_POST['display_name'] ?? '' ),
			'username'             => sanitize_title( $_POST['username'] ?? '' ),
			'account_type'         => in_array( $_POST['account_type'] ?? '', [ 'curated', 'verified' ], true ) ? $_POST['account_type'] : 'curated',
			'source_url'           => esc_url_raw( $_POST['source_url'] ?? '' ),
			'bio'                  => sanitize_textarea_field( $_POST['bio'] ?? '' ),
			'associated_charity'   => sanitize_text_field( $_POST['associated_charity'] ?? '' ),
			'default_content_type' => in_array( $_POST['default_content_type'] ?? '', $allowed_types, true ) ? $_POST['default_content_type'] : 'reminder',
		];

		$id = (int) ( $_POST['id'] ?? 0 );

		// Wave 87f — if source_url changes, the cached youtube_channel_id is
		// stale (points at the OLD handle's channel). Clear it so the next
		// sync re-resolves the new URL → channelId via the API/scrape.
		// Without this, changing /@muftimenk → /@muftimenkofficial still
		// queries the old channel's stats and returns no Shorts.
		if ( $id ) {
			$old_src = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT source_url FROM {$t['scholars']} WHERE id = %d",
				$id
			) );
			if ( $old_src && $old_src !== $data['source_url'] ) {
				$data['youtube_channel_id'] = '';
			}
		}

		if ( $id ) {
			$wpdb->update( $t['scholars'], $data, [ 'id' => $id ] );
		} else {
			$wpdb->insert( $t['scholars'], $data );
			$id = (int) $wpdb->insert_id;
		}

		// Wave 73: "Import last 365 days" — when ticked, immediately run
		// a DEEP sync_scholar for this channel (up to 500 latest videos
		// pulled via yt-dlp, all that pass the orientation/duration
		// filters get inserted). Streams progress and stays alive
		// regardless of browser close.
		$do_deep = ! empty( $_POST['deep_import'] );
		if ( $do_deep && $id ) {
			ignore_user_abort( true );
			@set_time_limit( 0 );
			$scholar = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['scholars']} WHERE id = %d", $id ) );
			$display = $scholar->display_name ?? 'channel';

			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-Accel-Buffering: no' );
			echo str_repeat( ' ', 1024 );
			flush();
			?>
			<!doctype html>
			<html><head><meta charset="utf-8"><title>Importing — Love Allah</title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #1A0D26; color: #F8ECD0; padding: 32px; max-width: 720px; margin: 0 auto; line-height: 1.5; }
				h1 { color: #F4D982; font-weight: 800; }
				.row { padding: 10px 14px; background: rgba(255,255,255,0.06); border-left: 3px solid #C9A961; margin: 8px 0; border-radius: 6px; }
				.done { border-left-color: #4ade80; }
				.totals { font-size: 18px; font-weight: 700; color: #F4D982; margin-top: 20px; padding: 14px 18px; background: rgba(232,199,111,0.10); border-radius: 10px; }
				a { color: #F4D982; }
			</style>
			</head><body>
			<h1>📚 365-day import: <?php echo esc_html( $display ); ?></h1>
			<p>Pulling up to <?php echo (int) LA_YouTube::DEEP_PULL_LIMIT; ?> latest videos from this channel. Filtering by orientation + duration. <strong>Safe to close this tab — the import keeps running.</strong></p>
			<?php
			echo '<div class="row">Starting deep yt-dlp pull (this can take 1-3 minutes for prolific channels)…</div>';
			flush();
			$start = microtime( true );
			try {
				$r = LA_YouTube::sync_scholar( $scholar, [ 'deep' => true ] );
				$elapsed = (int) ( microtime( true ) - $start );
				$inserted = (int) ( $r['inserted'] ?? 0 );
				$fetched  = (int) ( $r['fetched']  ?? 0 );
				$reason   = $r['reason'] ?? '';
				printf(
					'<div class="row done">✓ yt-dlp returned %d videos · %d ingested into the catalog · elapsed %ds</div>',
					$fetched, $inserted, $elapsed
				);
				if ( $reason ) {
					printf( '<div class="row">Note: %s</div>', esc_html( $reason ) );
				}
				printf(
					'<div class="totals">Done! Added <strong>%d new videos</strong> from %s in %ds.</div>',
					$inserted, esc_html( $display ), $elapsed
				);
			} catch ( Throwable $e ) {
				printf( '<div class="row" style="border-left-color:#ef4444;">Error: %s</div>', esc_html( $e->getMessage() ) );
			}
			printf(
				'<p style="margin-top:20px;"><a href="%s">← Back to scholars</a> · <a href="%s">Add another</a></p>',
				esc_url( admin_url( 'admin.php?page=loveallah-scholars' ) ),
				esc_url( admin_url( 'admin.php?page=loveallah-scholars&action=add' ) )
			);
			echo '</body></html>';
			exit;
		}

		set_transient( 'la_admin_notice', __( 'Scholar saved.', 'loveallah' ), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=loveallah-scholars' ) );
		exit;
	}

	// ────────────────────────────────────────────────────────────
	// MOSQUES
	// ────────────────────────────────────────────────────────────
	public static function page_mosques() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( $action === 'add' || $action === 'edit' ) {
			self::form_mosque( $action === 'edit' ? (int) ( $_GET['id'] ?? 0 ) : 0 );
		} else {
			global $wpdb;
			$t = LA_DB::tables();
			$rows = $wpdb->get_results( "SELECT * FROM {$t['mosques']} ORDER BY name ASC" );
			?>
			<div class="wrap la-admin">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Mosques', 'loveallah' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-mosques&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'loveallah' ); ?></a>
				<hr class="wp-header-end">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Name', 'loveallah' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'loveallah' ); ?></th>
						<th><?php esc_html_e( 'City', 'loveallah' ); ?></th>
						<th><?php esc_html_e( 'Claimed', 'loveallah' ); ?></th>
						<th><?php esc_html_e( 'Brand', 'loveallah' ); ?></th>
						<th></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $m ) :
						$edit_url = admin_url( 'admin.php?page=loveallah-mosques&action=edit&id=' . (int) $m->id );
					?>
						<tr>
							<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $m->name ); ?></a></strong></td>
							<td><code><?php echo esc_html( $m->slug ); ?></code></td>
							<td><?php echo esc_html( $m->city ?? '' ); ?></td>
							<td><?php echo $m->claimed_user_id ? '✓' : '—'; ?></td>
							<td><span style="display:inline-block;width:18px;height:18px;background:<?php echo esc_attr( $m->branding_color_primary ?? '#ED1C6C' ); ?>;border-radius:50%;vertical-align:middle;"></span></td>
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'loveallah' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php self::admin_css();
		}
	}

	private static function form_mosque( int $id ) : void {
		global $wpdb;
		$t = LA_DB::tables();
		$m = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['mosques']} WHERE id = %d", $id ) ) : null;
		?>
		<div class="wrap la-admin">
			<h1><?php echo $m ? esc_html__( 'Edit mosque', 'loveallah' ) : esc_html__( 'Add mosque', 'loveallah' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'la_save_mosque' ); ?>
				<input type="hidden" name="action" value="la_save_mosque">
				<input type="hidden" name="id" value="<?php echo (int) ( $m->id ?? 0 ); ?>">
				<table class="form-table"><tbody>
					<tr><th><label for="name"><?php esc_html_e( 'Name', 'loveallah' ); ?> *</label></th>
						<td><input class="regular-text" required type="text" id="name" name="name" value="<?php echo esc_attr( $m->name ?? '' ); ?>"></td></tr>
					<tr><th><label for="slug"><?php esc_html_e( 'URL slug', 'loveallah' ); ?> *</label></th>
						<td><input class="regular-text" required type="text" id="slug" name="slug" value="<?php echo esc_attr( $m->slug ?? '' ); ?>"></td></tr>
					<tr><th><label for="address"><?php esc_html_e( 'Address', 'loveallah' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="address" name="address"><?php echo esc_textarea( $m->address ?? '' ); ?></textarea></td></tr>
					<tr><th><label for="city"><?php esc_html_e( 'City', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="city" name="city" value="<?php echo esc_attr( $m->city ?? '' ); ?>"></td></tr>
					<tr><th><label for="country"><?php esc_html_e( 'Country', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="country" name="country" value="<?php echo esc_attr( $m->country ?? '' ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Coordinates', 'loveallah' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Latitude', 'loveallah' ); ?>:</label>
							<input type="number" step="any" name="latitude" value="<?php echo esc_attr( $m->latitude ?? '' ); ?>" style="width:140px;">
							<label style="margin-left:12px;"><?php esc_html_e( 'Longitude', 'loveallah' ); ?>:</label>
							<input type="number" step="any" name="longitude" value="<?php echo esc_attr( $m->longitude ?? '' ); ?>" style="width:140px;">
						</td></tr>
					<tr><th><label for="branding_color_primary"><?php esc_html_e( 'Brand color', 'loveallah' ); ?></label></th>
						<td><input type="color" id="branding_color_primary" name="branding_color_primary" value="<?php echo esc_attr( $m->branding_color_primary ?? '#ED1C6C' ); ?>">
						<p class="description"><?php esc_html_e( 'Drives the meta theme-color tag and (Phase 2) the masjid theming.', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="claimed_user_id"><?php esc_html_e( 'Claimed by user', 'loveallah' ); ?></label></th>
						<td><?php
							wp_dropdown_users( [
								'name'              => 'claimed_user_id',
								'selected'          => (int) ( $m->claimed_user_id ?? 0 ),
								'show_option_none'  => __( '— Unclaimed —', 'loveallah' ),
								'option_none_value' => 0,
								'role__in'          => [ 'administrator', 'loveallah_masjid_admin' ],
							] );
						?></td></tr>
				</tbody></table>
				<?php submit_button( $m ? __( 'Update mosque', 'loveallah' ) : __( 'Create mosque', 'loveallah' ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-mosques' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'loveallah' ); ?></a>
			</form>
		</div>
		<?php self::admin_css();
	}

	public static function handle_save_mosque() : void {
		check_admin_referer( 'la_save_mosque' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		global $wpdb;
		$t = LA_DB::tables();
		$data = [
			'name'                   => sanitize_text_field( $_POST['name'] ?? '' ),
			'slug'                   => sanitize_title( $_POST['slug'] ?? '' ),
			'address'                => sanitize_textarea_field( $_POST['address'] ?? '' ),
			'city'                   => sanitize_text_field( $_POST['city'] ?? '' ),
			'country'                => sanitize_text_field( $_POST['country'] ?? '' ),
			'latitude'               => is_numeric( $_POST['latitude'] ?? '' )  ? (float) $_POST['latitude']  : null,
			'longitude'              => is_numeric( $_POST['longitude'] ?? '' ) ? (float) $_POST['longitude'] : null,
			'branding_color_primary' => sanitize_hex_color( $_POST['branding_color_primary'] ?? '#ED1C6C' ),
			'claimed_user_id'        => ( (int) ( $_POST['claimed_user_id'] ?? 0 ) ) ?: null,
		];
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( $id ) $wpdb->update( $t['mosques'], $data, [ 'id' => $id ] );
		else      $wpdb->insert( $t['mosques'], $data );
		set_transient( 'la_admin_notice', __( 'Mosque saved.', 'loveallah' ), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=loveallah-mosques' ) );
		exit;
	}

	// ────────────────────────────────────────────────────────────
	// EVENTS
	// ────────────────────────────────────────────────────────────
	public static function page_events() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( $action === 'add' || $action === 'edit' ) {
			self::form_event( $action === 'edit' ? (int) ( $_GET['id'] ?? 0 ) : 0 );
			return;
		}
		global $wpdb;
		$t = LA_DB::tables();
		$rows = $wpdb->get_results( "SELECT e.*, m.name AS mosque_name FROM {$t['events']} e LEFT JOIN {$t['mosques']} m ON m.id = e.mosque_id ORDER BY e.starts_at DESC" );
		?>
		<div class="wrap la-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Events', 'loveallah' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-events&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'loveallah' ); ?></a>
			<hr class="wp-header-end">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Title', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Mosque', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'When', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Location', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Tag', 'loveallah' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $e ) :
					$edit_url = admin_url( 'admin.php?page=loveallah-events&action=edit&id=' . (int) $e->id );
					$del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=la_delete&type=event&id=' . (int) $e->id ), 'la_delete_event_' . $e->id );
				?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $e->title ); ?></a></strong></td>
						<td><?php echo esc_html( $e->mosque_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $e->starts_at ); ?></td>
						<td><?php echo esc_html( $e->location ?? '—' ); ?></td>
						<td><?php echo $e->tag ? '<span class="la-pill">' . esc_html( $e->tag ) . '</span>' : '—'; ?></td>
						<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'loveallah' ); ?></a> · <a href="<?php echo esc_url( $del_url ); ?>" style="color:#a00;" onclick="return confirm('<?php esc_attr_e( 'Delete this event?', 'loveallah' ); ?>');"><?php esc_html_e( 'Delete', 'loveallah' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><em><?php esc_html_e( 'No events yet.', 'loveallah' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php self::admin_css();
	}

	private static function form_event( int $id ) : void {
		global $wpdb;
		$t = LA_DB::tables();
		$e = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['events']} WHERE id = %d", $id ) ) : null;
		$mosques = $wpdb->get_results( "SELECT id, name FROM {$t['mosques']} ORDER BY name ASC" );
		?>
		<div class="wrap la-admin">
			<h1><?php echo $e ? esc_html__( 'Edit event', 'loveallah' ) : esc_html__( 'Add event', 'loveallah' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'la_save_event' ); ?>
				<input type="hidden" name="action" value="la_save_event">
				<input type="hidden" name="id" value="<?php echo (int) ( $e->id ?? 0 ); ?>">
				<table class="form-table"><tbody>
					<tr><th><label for="title"><?php esc_html_e( 'Title', 'loveallah' ); ?> *</label></th>
						<td><input class="regular-text" required type="text" id="title" name="title" value="<?php echo esc_attr( $e->title ?? '' ); ?>"></td></tr>
					<tr><th><label for="mosque_id"><?php esc_html_e( 'Mosque', 'loveallah' ); ?> *</label></th>
						<td><select id="mosque_id" name="mosque_id" required>
							<option value="">— <?php esc_html_e( 'Select…', 'loveallah' ); ?> —</option>
							<?php foreach ( $mosques as $m ) : ?>
								<option value="<?php echo (int) $m->id; ?>" <?php selected( (int) ( $e->mosque_id ?? 0 ), (int) $m->id ); ?>><?php echo esc_html( $m->name ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
					<tr><th><label for="starts_at"><?php esc_html_e( 'Starts at', 'loveallah' ); ?> *</label></th>
						<td><input type="datetime-local" id="starts_at" name="starts_at" required value="<?php echo esc_attr( $e ? str_replace( ' ', 'T', substr( $e->starts_at, 0, 16 ) ) : '' ); ?>"></td></tr>
					<tr><th><label for="location"><?php esc_html_e( 'Location', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="location" name="location" value="<?php echo esc_attr( $e->location ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Main hall, Library, etc.', 'loveallah' ); ?>"></td></tr>
					<tr><th><label for="description"><?php esc_html_e( 'Description', 'loveallah' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="description" name="description"><?php echo esc_textarea( $e->description ?? '' ); ?></textarea></td></tr>
					<tr><th><label for="tag"><?php esc_html_e( 'Tag', 'loveallah' ); ?></label></th>
						<td><select id="tag" name="tag">
							<option value=""><?php esc_html_e( '— None —', 'loveallah' ); ?></option>
							<?php foreach ( [ 'jumuah','class','community','ramadan','eid','seminar','janazah' ] as $tg ) : ?>
								<option value="<?php echo esc_attr( $tg ); ?>" <?php selected( $e->tag ?? '', $tg ); ?>><?php echo esc_html( $tg ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
				</tbody></table>
				<?php submit_button( $e ? __( 'Update event', 'loveallah' ) : __( 'Create event', 'loveallah' ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-events' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'loveallah' ); ?></a>
			</form>
		</div>
		<?php self::admin_css();
	}

	public static function handle_save_event() : void {
		check_admin_referer( 'la_save_event' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		global $wpdb;
		$t = LA_DB::tables();
		$starts = sanitize_text_field( $_POST['starts_at'] ?? '' );
		$starts = $starts ? str_replace( 'T', ' ', $starts ) . ':00' : null;

		$data = [
			'mosque_id'   => (int) ( $_POST['mosque_id'] ?? 0 ),
			'title'       => sanitize_text_field( $_POST['title'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'starts_at'   => $starts,
			'location'    => sanitize_text_field( $_POST['location'] ?? '' ),
			'tag'         => sanitize_key( $_POST['tag'] ?? '' ),
		];
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( $id ) $wpdb->update( $t['events'], $data, [ 'id' => $id ] );
		else      $wpdb->insert( $t['events'], $data );
		set_transient( 'la_admin_notice', __( 'Event saved.', 'loveallah' ), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=loveallah-events' ) );
		exit;
	}

	// ────────────────────────────────────────────────────────────
	// CONTENT (read-only, plus YT sync)
	// ────────────────────────────────────────────────────────────
	public static function page_content() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		global $wpdb;
		$t = LA_DB::tables();
		$rows = $wpdb->get_results( "SELECT p.*, s.display_name AS scholar_name FROM {$t['feed_posts']} p LEFT JOIN {$t['scholars']} s ON s.id = p.scholar_id ORDER BY p.published_at DESC LIMIT 100" );
		?>
		<div class="wrap la-admin">
			<h1><?php esc_html_e( 'Feed content', 'loveallah' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Latest 100 feed posts. Content is ingested via yt-dlp from scholar channels — manage scholars to control what gets synced.', 'loveallah' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:14px 0;">
				<?php wp_nonce_field( 'la_yt_sync' ); ?>
				<input type="hidden" name="action" value="la_yt_sync">
				<?php submit_button( __( 'Sync YouTube now', 'loveallah' ), 'primary', '', false ); ?>
			</form>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Title', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Scholar', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Type', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Duration', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Published', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Likes', 'loveallah' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $p ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( $p->original_source_url ); ?>" target="_blank"><?php echo esc_html( $p->title ); ?></a></td>
						<td><?php echo esc_html( $p->scholar_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $p->type ); ?></td>
						<td><?php echo (int) $p->duration_sec . 's'; ?></td>
						<td><?php echo esc_html( $p->published_at ); ?></td>
						<td><?php echo (int) $p->likes_count; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php self::admin_css();
	}

	// ────────────────────────────────────────────────────────────
	// SUBSCRIBERS
	// ────────────────────────────────────────────────────────────
	public static function page_subscribers() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		global $wpdb;
		$t = LA_DB::tables();
		$rows = $wpdb->get_results( "SELECT s.*, m.name AS mosque_name FROM {$t['subscribers']} s LEFT JOIN {$t['mosques']} m ON m.id = s.mosque_id ORDER BY s.subscribed_at DESC LIMIT 200" );
		?>
		<div class="wrap la-admin">
			<h1><?php esc_html_e( 'Subscribers', 'loveallah' ); ?></h1>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Email', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Mosque', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Push', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Subscribed', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Status', 'loveallah' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $s ) : ?>
					<tr>
						<td><?php echo esc_html( $s->email ?? '—' ); ?></td>
						<td><?php echo esc_html( $s->mosque_name ?? '—' ); ?></td>
						<td><?php echo $s->push_endpoint ? '✓' : '—'; ?></td>
						<td><?php echo esc_html( $s->subscribed_at ); ?></td>
						<td><?php echo $s->unsubscribed_at ? '<span style="color:#a00;">' . esc_html__( 'Unsubscribed', 'loveallah' ) . '</span>' : '<span style="color:#2a7e2a;">' . esc_html__( 'Active', 'loveallah' ) . '</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?><tr><td colspan="5"><em><?php esc_html_e( 'No subscribers yet.', 'loveallah' ); ?></em></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php self::admin_css();
	}

	// ────────────────────────────────────────────────────────────
	// EMAIL CAPTURES
	// ────────────────────────────────────────────────────────────
	public static function page_email_captures() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		global $wpdb;
		$t = LA_DB::tables();
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['email_captures']}" );
		$today  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['email_captures']} WHERE captured_at >= CURDATE()" );
		$week   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['email_captures']} WHERE captured_at >= DATE_SUB( NOW(), INTERVAL 7 DAY )" );
		$rows   = $wpdb->get_results( "SELECT * FROM {$t['email_captures']} ORDER BY captured_at DESC LIMIT 200" );
		?>
		<div class="wrap la-admin">
			<h1><?php esc_html_e( 'Email captures', 'loveallah' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Emails captured from the feed signup card. These are the people most likely to convert to patrons.', 'loveallah' ); ?></p>
			<div class="la-stats" style="margin-top:20px;">
				<?php self::stat_card( __( 'All-time', 'loveallah' ), $total ); ?>
				<?php self::stat_card( __( 'Today',    'loveallah' ), $today ); ?>
				<?php self::stat_card( __( 'Last 7 days', 'loveallah' ), $week ); ?>
			</div>
			<p style="margin-top:24px;">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-emails&export=csv&_wpnonce=' . wp_create_nonce( 'la_export_emails' ) ) ); ?>"><?php esc_html_e( 'Export CSV', 'loveallah' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Email', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Source', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'User', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Captured', 'loveallah' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r->email ); ?></strong></td>
						<td><?php echo esc_html( $r->source ); ?></td>
						<td><?php echo $r->user_id ? esc_html( get_userdata( $r->user_id )->user_login ?? '' ) : '<em>anon</em>'; ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $r->captured_at ) ) ); ?> ago</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="4"><em><?php esc_html_e( 'No captures yet. The feed will start collecting from anonymous visitors.', 'loveallah' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		// CSV export inline
		if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'la_export_emails' ) ) {
			while ( ob_get_level() ) ob_end_clean();
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="loveallah-emails-' . date( 'Y-m-d' ) . '.csv"' );
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, [ 'email', 'source', 'user_id', 'session_id', 'captured_at' ] );
			$all = $wpdb->get_results( "SELECT email, source, user_id, session_id, captured_at FROM {$t['email_captures']} ORDER BY captured_at DESC" );
			foreach ( $all as $r ) fputcsv( $out, [ $r->email, $r->source, $r->user_id, $r->session_id, $r->captured_at ] );
			fclose( $out );
			exit;
		}
		self::admin_css();
	}

	// ────────────────────────────────────────────────────────────
	// SETTINGS
	// ────────────────────────────────────────────────────────────
	public static function page_settings() : void {
		if ( ! LA_Caps::can_manage_platform() ) wp_die( __( 'Forbidden', 'loveallah' ) );
		?>
		<div class="wrap la-admin">
			<h1><?php esc_html_e( 'Settings', 'loveallah' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'loveallah_settings' ); ?>
				<h2><?php esc_html_e( 'Brand', 'loveallah' ); ?></h2>
				<table class="form-table"><tbody>
					<tr><th><label for="la_brand_color"><?php esc_html_e( 'Primary brand color', 'loveallah' ); ?></label></th>
						<td><input type="color" id="la_brand_color" name="la_brand_color" value="<?php echo esc_attr( get_option( 'la_brand_color', '#ED1C6C' ) ); ?>"></td></tr>
					<tr><th><label for="la_default_mosque_slug"><?php esc_html_e( 'Default mosque slug', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="la_default_mosque_slug" name="la_default_mosque_slug" value="<?php echo esc_attr( get_option( 'la_default_mosque_slug', '' ) ); ?>" placeholder="central-jamia-masjid-birmingham">
						<p class="description"><?php esc_html_e( 'Used when a visitor has not chosen one.', 'loveallah' ); ?></p></td></tr>
				</tbody></table>

				<h2><?php esc_html_e( 'YouTube sync', 'loveallah' ); ?></h2>
				<table class="form-table"><tbody>
					<tr><th><label for="la_yt_sync_enabled"><?php esc_html_e( 'Sync enabled', 'loveallah' ); ?></label></th>
						<td><input type="checkbox" id="la_yt_sync_enabled" name="la_yt_sync_enabled" value="1" <?php checked( (int) get_option( 'la_yt_sync_enabled', 1 ), 1 ); ?>></td></tr>
					<tr><th><label for="la_yt_per_sync"><?php esc_html_e( 'Posts per scholar per run', 'loveallah' ); ?></label></th>
						<td><input type="number" id="la_yt_per_sync" name="la_yt_per_sync" value="<?php echo esc_attr( get_option( 'la_yt_per_sync', 15 ) ); ?>" min="5" max="50" style="width:100px;"></td></tr>
					<tr><th><label for="la_yt_max_duration"><?php esc_html_e( 'Max duration (seconds)', 'loveallah' ); ?></label></th>
						<td><input type="number" id="la_yt_max_duration" name="la_yt_max_duration" value="<?php echo esc_attr( get_option( 'la_yt_max_duration', 180 ) ); ?>" min="30" max="900" style="width:100px;">
						<p class="description"><?php esc_html_e( 'Videos longer than this are rejected as not-Shorts.', 'loveallah' ); ?></p></td></tr>
					<tr><th><label for="la_yt_api_key"><?php esc_html_e( 'YouTube Data API v3 key', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="password" id="la_yt_api_key" name="la_yt_api_key" value="<?php echo esc_attr( get_option( 'la_yt_api_key', '' ) ); ?>" autocomplete="off" placeholder="AIza...">
						<p class="description"><?php esc_html_e( 'Wave 87b: used when channel /shorts page scrape returns empty (Mufti Menk-tier creators whose pages YouTube only serves as a JS-shell to our IP). Free 10k quota/day. Create at console.cloud.google.com → APIs &amp; Services → Credentials and restrict to YouTube Data API v3.', 'loveallah' ); ?></p></td></tr>
				</tbody></table>

				<h2><?php esc_html_e( 'Content', 'loveallah' ); ?></h2>
				<table class="form-table"><tbody>
					<tr><th><label for="la_required_dhikr"><?php esc_html_e( 'Dhikr per day', 'loveallah' ); ?></label></th>
						<td><input type="number" id="la_required_dhikr" name="la_required_dhikr" value="<?php echo esc_attr( get_option( 'la_required_dhikr', 5 ) ); ?>" min="1" max="10" style="width:80px;"></td></tr>
					<tr><th><label for="la_recency_window_days"><?php esc_html_e( 'Feed recency window (days)', 'loveallah' ); ?></label></th>
						<td><input type="number" id="la_recency_window_days" name="la_recency_window_days" value="<?php echo esc_attr( get_option( 'la_recency_window_days', 60 ) ); ?>" min="7" max="365" style="width:100px;"></td></tr>
				</tbody></table>

				<h2><?php esc_html_e( 'Android app (TWA)', 'loveallah' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Required for Android Digital Asset Links. Get the SHA-256 fingerprint from bubblewrap after building.', 'loveallah' ); ?></p>
				<table class="form-table"><tbody>
					<tr><th><label for="la_android_package"><?php esc_html_e( 'Android package ID', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="la_android_package" name="la_android_package" value="<?php echo esc_attr( get_option( 'la_android_package', 'app.loveallah.app' ) ); ?>"></td></tr>
					<tr><th><label for="la_android_sha256"><?php esc_html_e( 'Signing key SHA-256 fingerprint', 'loveallah' ); ?></label></th>
						<td><input class="regular-text" type="text" id="la_android_sha256" name="la_android_sha256" value="<?php echo esc_attr( get_option( 'la_android_sha256', '' ) ); ?>" placeholder="AA:BB:CC:DD:...:FF">
						<p class="description"><?php esc_html_e( 'Surfaced at /.well-known/assetlinks.json so Android trusts this PWA.', 'loveallah' ); ?></p></td></tr>
				</tbody></table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php self::admin_css();
	}

	// ────────────────────────────────────────────────────────────
	// HELPERS
	// ────────────────────────────────────────────────────────────
	public static function handle_delete() : void {
		$type = sanitize_key( $_GET['type'] ?? '' );
		$id   = (int) ( $_GET['id'] ?? 0 );
		if ( ! $type || ! $id ) wp_die( 'Bad request' );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'la_delete_' . $type . '_' . $id ) ) wp_die( 'Bad nonce' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );

		$map = [ 'scholar' => 'scholars', 'mosque' => 'mosques', 'event' => 'events' ];
		if ( ! isset( $map[ $type ] ) ) wp_die( 'Bad type' );

		global $wpdb;
		$t = LA_DB::tables();
		$wpdb->delete( $t[ $map[ $type ] ], [ 'id' => $id ] );
		set_transient( 'la_admin_notice', __( 'Deleted.', 'loveallah' ), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=loveallah-' . $map[ $type ] ) );
		exit;
	}

	public static function handle_yt_sync() : void {
		check_admin_referer( 'la_yt_sync' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		// Lift execution caps — full sync across 55+ channels in one request
		// can run 5+ minutes when yt-dlp is slow on cold IPs.
		@set_time_limit( 0 );
		$result = LA_YouTube::sync_all();
		set_transient( 'la_admin_notice', sprintf(
			/* translators: 1: synced count, 2: inserted count */
			__( 'Full sync complete: %1$d channels checked, %2$d new posts.', 'loveallah' ),
			$result['synced'], $result['inserted']
		), 30 );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=loveallah' ) );
		exit;
	}

	/**
	 * Same code path as the hourly cron — process the next batch of
	 * oldest-synced channels. Fast (8 channels × ~3s each = ~25s typical),
	 * safe to invoke from the browser without hitting timeouts.
	 */
	public static function handle_yt_sync_batch() : void {
		check_admin_referer( 'la_yt_sync_batch' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );
		$result = LA_YouTube::sync_next_batch();
		// Mirror cron_tick — persist last-tick stats so the dashboard reflects
		// the manual run (otherwise the panel still shows the old cron numbers).
		update_option( 'la_yt_last_tick', [
			'at'       => current_time( 'mysql' ),
			'synced'   => (int) $result['synced'],
			'inserted' => (int) $result['inserted'],
			'errors'   => array_slice( (array) $result['errors'], 0, 5 ),
		], false );
		set_transient( 'la_admin_notice', sprintf(
			/* translators: 1: synced count, 2: inserted count */
			__( 'Batch sync: %1$d channels checked, %2$d new posts.', 'loveallah' ),
			$result['synced'], $result['inserted']
		), 30 );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=loveallah' ) );
		exit;
	}

	/**
	 * Wave 69: aggressive catch-up sync from the admin. Runs up to
	 * MAX_PASSES of sync_next_batch() — each pass picks priority
	 * channels (never-synced → undersized → rest) and pulls them deep
	 * (CATCH_UP_PULL=100 latest for undersized channels). Stops early
	 * once every channel has ≥ CATCH_UP_THRESHOLD videos in the DB.
	 *
	 * UX:
	 *   - Renders an immediate "running" status page
	 *   - Flushes output so the user sees progress in real time
	 *   - ignore_user_abort(true) so the work keeps going even if the
	 *     browser closes / times out
	 *   - set_time_limit(0) so PHP doesn't kill the process
	 *   - Persists per-pass stats to la_yt_last_tick so the dashboard
	 *     keeps updating
	 */
	public static function handle_yt_sync_catchup() : void {
		check_admin_referer( 'la_yt_sync_catchup' );
		if ( ! LA_Caps::can_manage_platform() ) wp_die( 'Forbidden' );

		// Don't kill the work if the browser closes
		ignore_user_abort( true );
		@set_time_limit( 0 );

		// Stream a status page so the user has something to look at
		// while the catch-up runs.
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Accel-Buffering: no' );  // disable nginx buffering
		echo str_repeat( ' ', 1024 );        // force initial flush
		flush();

		?>
		<!doctype html>
		<html><head>
			<meta charset="utf-8">
			<title>Catch-up sync running…</title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #1A0D26; color: #F8ECD0; padding: 32px; max-width: 720px; margin: 0 auto; line-height: 1.5; }
				h1 { color: #F4D982; font-weight: 800; }
				.pass { padding: 10px 14px; background: rgba(255,255,255,0.06); border-left: 3px solid #C9A961; margin: 8px 0; border-radius: 6px; font-variant-numeric: tabular-nums; }
				.done { border-left-color: #4ade80; }
				.pass.err { border-left-color: #f87171; }
				.diag { background: rgba(244, 217, 130, 0.08); border: 1px solid rgba(244, 217, 130, 0.3); padding: 14px 18px; margin: 14px 0; border-radius: 8px; font-size: 13px; }
				.diag h2 { margin: 0 0 8px 0; font-size: 14px; color: #F4D982; letter-spacing: 0.04em; text-transform: uppercase; }
				.diag pre { white-space: pre-wrap; word-break: break-word; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px; margin: 6px 0; font-size: 12px; }
				.diag .row { font-family: ui-monospace, monospace; font-size: 12px; opacity: 0.85; }
				.totals { font-size: 18px; font-weight: 700; color: #F4D982; margin-top: 20px; padding: 14px 18px; background: rgba(232, 199, 111, 0.10); border-radius: 10px; }
				a { color: #F4D982; }
			</style>
		</head><body>
		<h1>🚀 Catch-up sync running</h1>
		<p>Each pass pulls 15 priority channels. We'll stop early when every channel has ≥ <?php echo (int) LA_YouTube::CATCH_UP_THRESHOLD; ?> videos. <strong>This page keeps writing as work progresses — leave it open OR close it (the sync continues server-side).</strong></p>
		<?php
		flush();

		// ── Wave 74 PRE-FLIGHT DIAGNOSTICS ──────────────────────────
		// Before we run any passes, dump enough state to definitively
		// tell what's wrong if sync_next_batch keeps returning 0 rows.
		global $wpdb;
		$t = LA_DB::tables();
		$diag_total_scholars = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['scholars']} WHERE source_url IS NOT NULL AND source_url <> ''" );
		$diag_has_status_col = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
			$t['scholars']
		) );
		$diag_status_breakdown = $diag_has_status_col
			? $wpdb->get_results( "SELECT COALESCE(NULLIF(status,''),'(empty/null)') AS s, COUNT(*) AS n FROM {$t['scholars']} GROUP BY s ORDER BY n DESC" )
			: [];
		$diag_status_where = $diag_has_status_col
			? " AND ( s.status IS NULL OR s.status = '' OR s.status = 'active' )"
			: '';
		$diag_priority_sql = $wpdb->prepare(
			"SELECT s.id, s.username, s.last_synced_at, COALESCE(p.video_count, 0) AS video_count
			 FROM {$t['scholars']} s
			 LEFT JOIN (
			   SELECT scholar_id, COUNT(*) AS video_count
			   FROM {$t['feed_posts']}
			   GROUP BY scholar_id
			 ) p ON p.scholar_id = s.id
			 WHERE s.source_url IS NOT NULL AND s.source_url <> ''
			   {$diag_status_where}
			 ORDER BY
			   (s.last_synced_at IS NULL) DESC,
			   (COALESCE(p.video_count, 0) < %d) DESC,
			   COALESCE(p.video_count, 0) ASC,
			   s.last_synced_at ASC,
			   s.id ASC
			 LIMIT 5",
			LA_YouTube::CATCH_UP_THRESHOLD
		);
		$diag_sample = $wpdb->get_results( $diag_priority_sql );
		$diag_sample_count = is_array( $diag_sample ) ? count( $diag_sample ) : 0;
		$diag_last_error = $wpdb->last_error;
		// Wave 74: shell availability check — Cloudways disabled escapeshellcmd
		// in their default policy which silently killed every yt-dlp call. We
		// polyfill those now, but confirm shell_exec itself still works.
		$diag_disabled_funcs = (string) ini_get( 'disable_functions' );
		$diag_shell_exec     = function_exists( 'shell_exec' );
		$diag_escape_cmd     = function_exists( 'escapeshellcmd' );
		$diag_escape_arg     = function_exists( 'escapeshellarg' );
		$diag_shell_smoke    = '';
		if ( $diag_shell_exec ) {
			$smoke = @shell_exec( 'echo loveallah_shell_ok 2>&1' );
			$diag_shell_smoke = trim( (string) $smoke ) === 'loveallah_shell_ok' ? 'WORKS' : ( 'returned: ' . substr( (string) $smoke, 0, 40 ) );
		}
		echo '<div class="diag"><h2>🔬 Pre-flight diagnostics</h2>';
		echo '<div>shell_exec available: <strong>' . ( $diag_shell_exec ? 'YES' : 'NO (sync impossible)' ) . '</strong></div>';
		if ( $diag_shell_exec ) {
			echo '<div>shell_exec smoke test: <strong>' . esc_html( $diag_shell_smoke ) . '</strong></div>';
		}
		echo '<div>escapeshellcmd available: <strong>' . ( $diag_escape_cmd ? 'YES' : 'NO (using polyfill)' ) . '</strong></div>';
		echo '<div>escapeshellarg available: <strong>' . ( $diag_escape_arg ? 'YES' : 'NO (using polyfill)' ) . '</strong></div>';
		if ( $diag_disabled_funcs ) {
			echo '<div style="opacity:0.7; font-size:11px;">disable_functions: <code>' . esc_html( $diag_disabled_funcs ) . '</code></div>';
		}
		echo '<div>Total scholars with source_url: <strong>' . $diag_total_scholars . '</strong></div>';
		echo '<div>Status column exists: <strong>' . ( $diag_has_status_col ? 'YES' : 'NO' ) . '</strong></div>';
		if ( $diag_has_status_col ) {
			echo '<div>Status breakdown:</div>';
			foreach ( $diag_status_breakdown as $sb ) {
				echo '<div class="row">&nbsp;&nbsp;' . esc_html( $sb->s ) . ' → ' . (int) $sb->n . '</div>';
			}
		}
		echo '<div>Priority query returned: <strong>' . $diag_sample_count . ' row(s)</strong></div>';
		if ( $diag_last_error ) {
			echo '<div style="color:#f87171">Last SQL error: <code>' . esc_html( $diag_last_error ) . '</code></div>';
		}
		if ( $diag_sample_count > 0 ) {
			echo '<div>Sample (top-priority) rows:</div>';
			foreach ( $diag_sample as $r ) {
				echo '<div class="row">&nbsp;&nbsp;#' . (int) $r->id . ' ' . esc_html( $r->username )
					. ' · videos=' . (int) $r->video_count
					. ' · last_synced=' . esc_html( $r->last_synced_at ?? 'NULL' )
					. '</div>';
			}
		}
		echo '<div style="margin-top:8px; opacity:0.7">Prepared SQL:</div>';
		echo '<pre>' . esc_html( $diag_priority_sql ) . '</pre>';
		echo '</div>';
		flush();

		$MAX_PASSES = 25;
		$total_synced   = 0;
		$total_inserted = 0;
		$all_errors     = [];
		$start_at       = microtime( true );

		for ( $i = 0; $i < $MAX_PASSES; $i++ ) {
			$res = LA_YouTube::sync_next_batch();
			$total_synced   += (int) $res['synced'];
			$total_inserted += (int) $res['inserted'];
			if ( ! empty( $res['errors'] ) ) {
				$all_errors = array_merge( $all_errors, (array) $res['errors'] );
			}

			// Persist per-pass so the main admin dashboard reflects progress
			update_option( 'la_yt_last_tick', [
				'at'       => current_time( 'mysql' ),
				'synced'   => (int) $res['synced'],
				'inserted' => (int) $res['inserted'],
				'errors'   => array_slice( (array) $res['errors'], 0, 5 ),
			], false );

			$elapsed = (int) ( microtime( true ) - $start_at );
			$err_cls = ( (int) $res['synced'] === 0 ) ? ' err' : '';
			printf(
				'<div class="pass%s">Pass %d/%d · %d channels checked · %d new posts · running total: %d new · elapsed %ds</div>',
				$err_cls, $i + 1, $MAX_PASSES, (int) $res['synced'], (int) $res['inserted'], $total_inserted, $elapsed
			);
			// First few errors from this pass surface inline so we can see WHY
			// sync_scholar returned a reason / threw.
			if ( ! empty( $res['errors'] ) ) {
				foreach ( array_slice( (array) $res['errors'], 0, 3 ) as $err_line ) {
					echo '<div class="pass err" style="margin-left:14px; font-size:12px;">↳ ' . esc_html( $err_line ) . '</div>';
				}
			}
			flush();

			// Early-exit check: every channel meets the threshold?
			if ( $res['inserted'] === 0 && $i > 4 ) {
				global $wpdb;
				$t = LA_DB::tables();
				$undersized = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$t['scholars']} s
					 LEFT JOIN ( SELECT scholar_id, COUNT(*) AS c FROM {$t['feed_posts']} GROUP BY scholar_id ) p
					   ON p.scholar_id = s.id
					 WHERE COALESCE(p.c, 0) < %d
					   AND s.source_url IS NOT NULL AND s.source_url <> ''",
					LA_YouTube::CATCH_UP_THRESHOLD
				) );
				if ( $undersized === 0 ) {
					echo '<div class="pass done">✓ All channels now have at least ' . (int) LA_YouTube::CATCH_UP_THRESHOLD . ' videos — stopping early.</div>';
					flush();
					break;
				}
			}

			// Small pause between batches so we don't slam yt-dlp
			usleep( 1500 * 1000 );
		}

		// Final stats
		$total_videos    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']}" );
		$total_channels  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['scholars']} WHERE source_url IS NOT NULL AND source_url <> ''" );
		$elapsed = (int) ( microtime( true ) - $start_at );

		printf(
			'<div class="totals">✅ Done in %ds<br>Catch-up added <strong>%d new videos</strong> across %d channel-checks.<br>Catalog now has <strong>%d videos</strong> across %d channels.</div>',
			$elapsed, $total_inserted, $total_synced, $total_videos, $total_channels
		);

		// Wave 74: if there were errors, show a roll-up so we can see WHY
		// the catch-up didn't produce results. The per-pass list only shows
		// the first 3 per pass, this dumps the unique reasons across all passes.
		if ( ! empty( $all_errors ) ) {
			$counts = [];
			foreach ( $all_errors as $line ) {
				// Group by the "reason" portion after the colon
				$reason = trim( preg_replace( '/^[^:]+:\s*/', '', $line ) );
				$counts[ $reason ] = ( $counts[ $reason ] ?? 0 ) + 1;
			}
			arsort( $counts );
			echo '<div class="diag"><h2>⚠️ Errors / reasons (' . count( $all_errors ) . ' total)</h2>';
			foreach ( $counts as $reason => $n ) {
				echo '<div class="row">' . (int) $n . '× — ' . esc_html( $reason ) . '</div>';
			}
			echo '</div>';
		}

		printf(
			'<p style="margin-top:20px;"><a href="%s">← Back to Love Allah admin</a></p>',
			esc_url( admin_url( 'admin.php?page=loveallah' ) )
		);
		echo '</body></html>';
		exit;
	}

	public static function flash_notice() : void {
		$msg = get_transient( 'la_admin_notice' );
		if ( ! $msg ) return;
		delete_transient( 'la_admin_notice' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private static function admin_css() : void {
		?>
		<style>
		.la-admin h1 { color: #1A1A2E; }
		.la-admin .description { color: #5A5A6E; }
		.la-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-top: 22px; }
		.la-stat-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 18px; }
		.la-stat-label { font-size: 12px; font-weight: 700; color: #5A5A6E; letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 6px; }
		.la-stat-value { font-size: 32px; font-weight: 900; color: #1A1A2E; line-height: 1; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
		.la-stat-link { display: inline-block; margin-top: 10px; font-size: 12px; color: #ED1C6C; text-decoration: none; font-weight: 700; }
		.la-pill { display: inline-block; padding: 2px 8px; background: #FFEAF2; color: #c91560; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
		</style>
		<?php
	}
}
