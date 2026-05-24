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

			<div class="la-stats" style="margin-bottom:12px;">
				<?php self::stat_card( __( 'Channels due (>6h)', 'loveallah' ), $due_count ); ?>
				<?php self::stat_card( __( 'Ingested last 24h',  'loveallah' ), $fresh_24h ); ?>
				<?php self::stat_card( __( 'Cron schedule',      'loveallah' ), $schedule === 'la_one_hour' ? '1 hr' : esc_html( $schedule ) ); ?>
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
		$rows = $wpdb->get_results( "SELECT * FROM {$t['scholars']} ORDER BY display_name ASC" );
		?>
		<div class="wrap la-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Scholars', 'loveallah' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=loveallah-scholars&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add new', 'loveallah' ); ?></a>
			<hr class="wp-header-end">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Username', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Type', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'YouTube channel', 'loveallah' ); ?></th>
					<th><?php esc_html_e( 'Last synced', 'loveallah' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $s ) :
					$edit_url = admin_url( 'admin.php?page=loveallah-scholars&action=edit&id=' . (int) $s->id );
					$del_url  = wp_nonce_url( admin_url( 'admin-post.php?action=la_delete&type=scholar&id=' . (int) $s->id ), 'la_delete_scholar_' . $s->id );
				?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $s->display_name ); ?></a></strong></td>
						<td><code>@<?php echo esc_html( $s->username ); ?></code></td>
						<td><?php echo esc_html( $s->account_type ); ?></td>
						<td><?php echo $s->youtube_channel_id ? '<code>' . esc_html( $s->youtube_channel_id ) . '</code>' : '—'; ?></td>
						<td><?php echo $s->last_synced_at ? esc_html( human_time_diff( strtotime( $s->last_synced_at ) ) . ' ago' ) : '—'; ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'loveallah' ); ?></a> ·
							<a href="<?php echo esc_url( $del_url ); ?>" style="color:#a00;" onclick="return confirm('<?php esc_attr_e( 'Delete this scholar? Their posts stay but become orphaned.', 'loveallah' ); ?>');"><?php esc_html_e( 'Delete', 'loveallah' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><em><?php esc_html_e( 'No scholars yet.', 'loveallah' ); ?></em></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php self::admin_css();
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
		if ( $id ) {
			$wpdb->update( $t['scholars'], $data, [ 'id' => $id ] );
		} else {
			$wpdb->insert( $t['scholars'], $data );
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
				.totals { font-size: 18px; font-weight: 700; color: #F4D982; margin-top: 20px; padding: 14px 18px; background: rgba(232, 199, 111, 0.10); border-radius: 10px; }
				a { color: #F4D982; }
			</style>
		</head><body>
		<h1>🚀 Catch-up sync running</h1>
		<p>Each pass pulls 15 priority channels. We'll stop early when every channel has ≥ <?php echo (int) LA_YouTube::CATCH_UP_THRESHOLD; ?> videos. <strong>This page keeps writing as work progresses — leave it open OR close it (the sync continues server-side).</strong></p>
		<?php
		flush();

		$MAX_PASSES = 25;
		$total_synced   = 0;
		$total_inserted = 0;
		$start_at       = microtime( true );

		for ( $i = 0; $i < $MAX_PASSES; $i++ ) {
			$res = LA_YouTube::sync_next_batch();
			$total_synced   += (int) $res['synced'];
			$total_inserted += (int) $res['inserted'];

			// Persist per-pass so the main admin dashboard reflects progress
			update_option( 'la_yt_last_tick', [
				'at'       => current_time( 'mysql' ),
				'synced'   => (int) $res['synced'],
				'inserted' => (int) $res['inserted'],
				'errors'   => array_slice( (array) $res['errors'], 0, 5 ),
			], false );

			$elapsed = (int) ( microtime( true ) - $start_at );
			printf(
				'<div class="pass">Pass %d/%d · %d channels checked · %d new posts · running total: %d new · elapsed %ds</div>',
				$i + 1, $MAX_PASSES, (int) $res['synced'], (int) $res['inserted'], $total_inserted, $elapsed
			);
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
		global $wpdb;
		$t = LA_DB::tables();
		$total_videos    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['feed_posts']}" );
		$total_channels  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['scholars']} WHERE source_url IS NOT NULL AND source_url <> ''" );
		$elapsed = (int) ( microtime( true ) - $start_at );

		printf(
			'<div class="totals">✅ Done in %ds<br>Catch-up added <strong>%d new videos</strong> across %d channel-checks.<br>Catalog now has <strong>%d videos</strong> across %d channels.</div>',
			$elapsed, $total_inserted, $total_synced, $total_videos, $total_channels
		);
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
