<?php
/**
 * YouTube ingestion via yt-dlp — Shorts + Videos.
 *
 * Strategy:
 *   1. yt-dlp --flat-playlist  →  fast list of video IDs from /shorts tab
 *   2. For each NEW video (not in DB), yt-dlp single-video fetch  →  duration, upload_date, description
 *   3. Filter to videos ≤ max_duration_for(type)
 *   4. Insert with full metadata, dedup by source URL
 *
 * SCALABLE HOURLY CRON (Wave 28):
 *   Runs every hour via `la_youtube_sync` action. Each tick processes
 *   only BATCH_PER_TICK scholars, ordered by `last_synced_at ASC NULLS FIRST`.
 *   With ~55 channels and 8 per tick, every channel still syncs every ~7 hours,
 *   but newly-published content appears at the top of the feed within an hour
 *   thanks to the +200 freshness boost in LA_Algorithm. No single tick ever
 *   hammers yt-dlp with 55 sequential subprocess calls — that pattern would
 *   hit Cloudways' shell timeout and silently break the cron.
 *
 * Requires yt-dlp binary at /usr/local/bin/yt-dlp (production Dockerfile
 * should install Python 3 + yt-dlp).
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_YouTube {

	// POOL CAPACITY (Wave 30):
	// A power user consuming 3 hours of 30-second reels per day = ~360 unique
	// videos/day. Even at our 56 channels × 15 = 840 cap, they'd exhaust the
	// pool in ~2.3 days. Bumping MAX_PER_SYNC to 30 gives 56 × 30 = 1,680
	// shorts — about 4.6 days for a power user before everything is seen.
	// Combined with the hourly cron's fresh-uploads stream, this should keep
	// a heavy daily user supplied without ever cycling back through the same
	// content (binge-exclusion in LA_Algorithm finishes the job).
	//
	// Why not 100+ per channel? yt-dlp --flat-playlist is fast, but the per-
	// new-item oEmbed fetch (~1s each) adds up during initial backfill of
	// a freshly-seeded channel. At 30 per channel × 8 channels per tick =
	// up to ~240s of oembed work — still under the 300s PHP timeout. Going
	// higher would risk timeouts on first sync of new channels.
	// Wave 68/69: ingestion tuned for fast catalog growth then steady-
	// state freshness.
	//   MAX_PER_SYNC is the default ceiling per channel per run.
	//   CATCH_UP_PULL is used when a channel is below CATCH_UP_THRESHOLD
	//   videos in our DB — we go deeper on first contact to fill out the
	//   catalog, then back off to MAX_PER_SYNC once we've got a baseline.
	//   Skip-if-fresh (Wave 69) means a fully-synced channel is a near-
	//   zero-cost check.
	const MAX_PER_SYNC       = 50;    // Items per scholar in steady state
	const CATCH_UP_PULL      = 200;   // Wave 71: items for undersized channels — deeper historical pull
	const CATCH_UP_THRESHOLD = 30;    // < this many videos = catch-up mode
	const TIMEOUT_SEC        = 60;    // Per-subprocess timeout (bumped for deeper pulls)
	const BATCH_PER_TICK     = 15;    // Scholars processed per hourly cron tick

	/**
	 * Locate yt-dlp binary. Cloudways installs it under ~/bin, others under /usr/local/bin.
	 * Cache once per request.
	 */
	private static $ytdlp_path = null;
	private static function ytdlp() : string {
		if ( self::$ytdlp_path !== null ) return self::$ytdlp_path;
		$candidates = [
			get_option( 'la_ytdlp_path' ),                  // admin override
			getenv( 'HOME' ) . '/bin/yt-dlp',               // Cloudways default (~/bin)
			'/home/master/bin/yt-dlp',                      // Cloudways absolute
			'/usr/local/bin/yt-dlp',                        // Linux default
			'/opt/homebrew/bin/yt-dlp',                     // macOS arm64
			'/usr/bin/yt-dlp',                              // system pkg
		];
		foreach ( $candidates as $p ) {
			if ( $p && is_executable( $p ) ) {
				self::$ytdlp_path = $p;
				return $p;
			}
		}
		// Last-ditch: trust PATH
		self::$ytdlp_path = 'yt-dlp';
		return self::$ytdlp_path;
	}

	/** Max duration per content type — shorts are short, nasheeds longer, lectures unbounded */
	private static function max_duration_for( string $type ) : int {
		switch ( $type ) {
			case 'reminder':
			case 'short':       return 180;       // 3 min
			case 'nasheed':     return 720;       // 12 min
			case 'dhikr':
			case 'mindfulness': return 3600;      // 1 hour
			case 'qirat':       return 7200;      // 2 hours
			case 'lecture':     return 14400;     // 4 hours
			default:            return 600;
		}
	}

	/** Which tabs to try (in order). Portrait-only mode means /shorts first for everyone. */
	private static function tabs_for( string $type ) : array {
		// /shorts always tried first since it guarantees portrait orientation.
		// /videos as fallback, but those videos still get filtered by aspect ratio.
		return [ 'shorts', 'videos' ];
	}

	public static function sync_all() : array {
		$scholars = LA_Scholars::all();
		return self::sync_list( $scholars );
	}

	/**
	 * Smart prioritising sync (Wave 69). Replaces the naive round-robin
	 * with a priority queue:
	 *   1. NEVER-SYNCED channels first (last_synced_at IS NULL)
	 *   2. Then UNDERSIZED channels (< CATCH_UP_THRESHOLD videos in DB)
	 *      — ordered by oldest sync, so we don't keep hitting the same
	 *      bot-blocked channel forever
	 *   3. Then channels sorted by last_synced_at ASC (round-robin
	 *      across the rest)
	 *
	 * This pattern fills out the catalog FAST when it's small, then
	 * settles into steady-state freshness rotation once every channel
	 * has a baseline.
	 */
	public static function sync_next_batch( int $batch = self::BATCH_PER_TICK ) : array {
		global $wpdb;
		$t = LA_DB::tables();

		// Wave 71 hotfix: only include the status filter if the column
		// has actually been migrated. Otherwise the SQL fails and the
		// catch-up silently returns 0 channels.
		$has_status_col = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = %s
			   AND COLUMN_NAME = 'status'",
			$t['scholars']
		) );
		$status_where = $has_status_col
			? " AND ( s.status IS NULL OR s.status = '' OR s.status = 'active' )"
			: '';

		// Compute per-channel video counts in one query so we can
		// surface undersized channels first.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*,
			        COALESCE(p.video_count, 0) AS video_count
			 FROM {$t['scholars']} s
			 LEFT JOIN (
			   SELECT scholar_id, COUNT(*) AS video_count
			   FROM {$t['feed_posts']}
			   GROUP BY scholar_id
			 ) p ON p.scholar_id = s.id
			 WHERE s.source_url IS NOT NULL AND s.source_url <> ''
			   {$status_where}
			 ORDER BY
			   (s.last_synced_at IS NULL) DESC,
			   (COALESCE(p.video_count, 0) < %d) DESC,
			   COALESCE(p.video_count, 0) ASC,
			   s.last_synced_at ASC,
			   s.id ASC
			 LIMIT %d",
			self::CATCH_UP_THRESHOLD,
			$batch
		) );
		return self::sync_list( $rows ?: [] );
	}

	/** Shared inner loop — sync a given list of scholar rows. */
	private static function sync_list( array $scholars ) : array {
		$result = [ 'synced' => 0, 'inserted' => 0, 'errors' => [] ];
		foreach ( $scholars as $s ) {
			try {
				$r = self::sync_scholar( $s );
				$result['synced']++;
				$result['inserted'] += $r['inserted'];
				if ( ! empty( $r['reason'] ) ) {
					$result['errors'][] = $s->username . ': ' . $r['reason'];
				}
			} catch ( Throwable $e ) {
				$result['errors'][] = $s->username . ': ' . $e->getMessage();
			}
		}
		// Notify the rest of the plugin so it can invalidate caches
		// (e.g. wp_cache_delete( 'la_ranked_content' ) wired up in loveallah.php).
		// Only fire when something actually changed — otherwise we'd thrash the cache
		// every hour with no benefit.
		if ( $result['inserted'] > 0 ) {
			do_action( 'la_after_feed_sync', $result );
		}
		return $result;
	}

	public static function sync_scholar( $scholar ) : array {
		global $wpdb;
		$t = LA_DB::tables();

		if ( empty( $scholar->source_url ) ) {
			return [ 'inserted' => 0, 'reason' => 'no_source_url' ];
		}

		$type      = $scholar->default_content_type ?? 'reminder';
		$max_dur   = self::max_duration_for( $type );
		$tabs      = self::tabs_for( $type );

		// Wave 69: catch-up mode for undersized channels — pull deeper
		// to fill out the catalog on first contact, then back off.
		// $scholar->video_count is populated by sync_next_batch's JOIN,
		// or we fetch it here if a caller passes us a raw scholar row.
		$existing_count = isset( $scholar->video_count )
			? (int) $scholar->video_count
			: (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['feed_posts']} WHERE scholar_id = %d",
				(int) $scholar->id
			) );
		$pull_limit = ( $existing_count < self::CATCH_UP_THRESHOLD )
			? self::CATCH_UP_PULL
			: self::MAX_PER_SYNC;

		// Detect search-URL channels so we don't pointlessly call yt-dlp
		// twice with the same URL (the /shorts and /videos suffixes are
		// stripped for query-string URLs in url_for_tab).
		$is_search = strpos( (string) $scholar->source_url, '?' ) !== false;

		// Try each tab in order until we get a list
		$list     = [];
		$used_tab = null;
		$tabs_to_try = $is_search ? [ 'search' ] : $tabs;
		foreach ( $tabs_to_try as $tab ) {
			$url  = self::url_for_tab( $scholar->source_url, $tab );
			$list = self::flat_list( $url, $pull_limit );
			if ( ! empty( $list ) ) { $used_tab = $tab; break; }
		}

		if ( empty( $list ) ) {
			// Wave 71: record WHY the channel returned nothing so the
			// admin diagnostic page can show the reason. Defensive: only
			// write last_sync_error if the column exists.
			$update_data = [ 'last_synced_at' => current_time( 'mysql' ) ];
			if ( self::has_sync_error_column() ) {
				$update_data['last_sync_error'] = 'No videos returned by yt-dlp (channel may be empty, bot-blocked, or have no /shorts or /videos tab)';
			}
			$wpdb->update( $t['scholars'], $update_data, [ 'id' => (int) $scholar->id ] );
			return [ 'inserted' => 0, 'reason' => 'no_content_tabs' ];
		}

		// Wave 69: SKIP-IF-FRESH optimisation. If we already have the
		// channel's most recent N videos (the first few items in $list
		// since yt-dlp returns newest-first), nothing has changed
		// upstream — skip the expensive per-video metadata fetches
		// for items we'd reject anyway. Only kicks in for steady-state
		// channels (those NOT in catch-up mode) — newly-discovered
		// channels still get the full sweep.
		if ( $existing_count >= self::CATCH_UP_THRESHOLD && count( $list ) >= 3 ) {
			$top_three = array_slice( $list, 0, 3 );
			$urls = array_map( function ( $v ) {
				return "https://www.youtube.com/watch?v={$v['id']}";
			}, $top_three );
			$placeholders = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
			$known = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['feed_posts']}
				 WHERE original_source_url IN ($placeholders)
				    OR original_source_url IN (" . implode( ',', array_fill( 0, count( $urls ), '%s' ) ) . ")",
				array_merge(
					$urls,
					array_map( function ( $v ) {
						return "https://www.youtube.com/shorts/{$v['id']}";
					}, $top_three )
				)
			) );
			if ( $known >= 3 ) {
				$wpdb->update( $t['scholars'], [ 'last_synced_at' => current_time( 'mysql' ) ], [ 'id' => (int) $scholar->id ] );
				return [ 'inserted' => 0, 'fetched' => count( $list ), 'tab' => $used_tab, 'skipped_fresh' => true ];
			}
		}

		$inserted = 0;
		foreach ( $list as $v ) {
			// search-derived items go through watch?v= since we can't know
			// whether they're shorts or longform.
			$source_url = $used_tab === 'shorts'
				? "https://www.youtube.com/shorts/{$v['id']}"
				: "https://www.youtube.com/watch?v={$v['id']}";

			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['feed_posts']} WHERE original_source_url = %s LIMIT 1",
				$source_url
			) );
			if ( $exists ) continue;

			// Audio-first types (qirat, dhikr, lecture, mindfulness) don't need
			// portrait orientation — the content IS the voice, the visual is
			// static or backdrop. Accept any orientation from /videos for those.
			// Also treat search-derived items as audio-first: the user explicitly
			// chose a search URL because there's no canonical channel (typically
			// classical qaris or curated cross-channel topical search), so the
			// portrait check makes no sense there.
			$audio_first = in_array( $type, [ 'qirat', 'dhikr', 'lecture', 'mindfulness' ], true )
			            || $used_tab === 'search';

			if ( $used_tab === 'shorts' ) {
				// FAST PATH: portrait is guaranteed by YouTube's Shorts format.
				// Skip yt-dlp per-video metadata fetch (bot-blocked on cloud IPs)
				// and use lightweight oEmbed for title verification.
				$oembed = self::oembed( $v['id'] );
				$title  = $oembed['title'] ?? $v['title'];
				$caption = '';
				$duration = 0;
				$published_at = gmdate( 'Y-m-d H:i:s' );
			} elseif ( $audio_first ) {
				// Audio-first /videos: accept without portrait check (since voice
				// is the content). Use oEmbed instead of bot-blocked single_metadata.
				$oembed = self::oembed( $v['id'] );
				$title  = $oembed['title'] ?? $v['title'];
				$caption = '';
				$duration = 0;
				$published_at = gmdate( 'Y-m-d H:i:s' );
			} else {
				// Visual /videos: orientation matters — must verify via metadata fetch.
				$meta = self::single_metadata( $v['id'] );
				if ( empty( $meta ) ) continue;
				if ( ! self::is_portrait( $meta ) ) continue;
				if ( $meta['duration'] > 0 && $meta['duration'] > $max_dur ) continue;
				$title    = $v['title'];
				$caption  = self::trim_caption( $meta['description'] );
				$duration = (int) $meta['duration'];
				$published_at = self::parse_ytdlp_date( $meta['upload_date'] ) ?: gmdate( 'Y-m-d H:i:s' );
			}

			$wpdb->insert( $t['feed_posts'], [
				'scholar_id'          => (int) $scholar->id,
				'type'                => $type,
				'title'               => mb_substr( $title, 0, 250 ),
				'caption'             => $caption,
				'video_url'           => "https://www.youtube.com/embed/{$v['id']}",
				'thumbnail_url'       => "https://i.ytimg.com/vi/{$v['id']}/hqdefault.jpg",
				'original_source_url' => $source_url,
				'duration_sec'        => $duration,
				'published_at'        => $published_at,
				// `created_at` is when WE ingested it (drives the +200 freshness
				// boost in LA_Algorithm). MySQL's CURRENT_TIMESTAMP default would
				// work too, but stamping explicitly keeps the value identical
				// across sites with non-UTC server timezones.
				'created_at'          => current_time( 'mysql', true ),
			] );
			$inserted++;
		}

		$update_data = [ 'last_synced_at' => current_time( 'mysql' ) ];
		if ( self::has_sync_error_column() ) {
			$update_data['last_sync_error'] = null;
		}
		$wpdb->update( $t['scholars'], $update_data, [ 'id' => (int) $scholar->id ] );
		return [ 'inserted' => $inserted, 'fetched' => count( $list ), 'tab' => $used_tab ];
	}

	/** Cached column-existence check for last_sync_error. */
	private static function has_sync_error_column() : bool {
		static $cached = null;
		if ( $cached !== null ) return $cached;
		global $wpdb;
		$t = LA_DB::tables();
		$cached = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = %s
			   AND COLUMN_NAME = 'last_sync_error'",
			$t['scholars']
		) );
		return $cached;
	}

	/** Build a tab URL (/shorts or /videos) from a channel source URL.
	 *
	 * For search-query URLs (`youtube.com/results?search_query=...`) and any
	 * URL containing a query string we return as-is — appending /shorts to a
	 * search URL would land inside the query value and break yt-dlp. The
	 * search extractor returns whatever the query matches, which is what we
	 * want for deceased classical qaris with no official channel.
	 */
	private static function url_for_tab( ?string $source_url, string $tab ) : ?string {
		if ( empty( $source_url ) ) return null;
		// Search URLs or any URL with a query string: return raw, no tab suffix.
		if ( strpos( $source_url, '?' ) !== false ) return $source_url;
		$source_url = preg_replace( '#/(shorts|videos|featured|streams)/?$#', '', $source_url );
		return rtrim( $source_url, '/' ) . '/' . $tab;
	}

	/** Fast playlist listing: returns array of [id, title, view_count] */
	private static function flat_list( string $shorts_url, int $limit ) : array {
		// Extractor args bypass YouTube's bot detection on cloud server IPs.
		$cmd = sprintf(
			'%s --flat-playlist --no-warnings --no-cache-dir --playlist-end %d --extractor-args "youtube:player_client=web,player_skip=configs" --print "%%(id)s|||%%(title)s|||%%(view_count)s" %s 2>&1',
			escapeshellcmd( self::ytdlp() ),
			(int) $limit,
			escapeshellarg( $shorts_url )
		);
		$output = self::run( $cmd );
		if ( empty( $output ) ) return [];
		if ( strpos( $output, 'does not have' ) !== false && strpos( $output, 'tab' ) !== false ) return [];
		if ( strpos( $output, 'ERROR' ) === 0 ) return [];

		$videos = [];
		foreach ( explode( "\n", trim( $output ) ) as $line ) {
			$line = trim( $line );
			if ( empty( $line ) || strpos( $line, 'ERROR' ) === 0 ) continue;
			$parts = explode( '|||', $line );
			if ( count( $parts ) < 2 ) continue;
			$videos[] = [
				'id'    => trim( $parts[0] ),
				'title' => trim( $parts[1] ),
				'view_count' => (int) ( $parts[2] ?? 0 ),
			];
		}
		return $videos;
	}

	/** Single-video metadata fetch (duration + upload_date + description + width/height) */
	private static function single_metadata( string $video_id ) : array {
		$url = "https://www.youtube.com/watch?v={$video_id}";
		$cmd = sprintf(
			'%s --no-warnings --no-cache-dir --skip-download --print "%%(duration)s|||%%(upload_date)s|||%%(description).400s|||%%(view_count)s|||%%(width)s|||%%(height)s" %s 2>&1',
			escapeshellcmd( self::ytdlp() ),
			escapeshellarg( $url )
		);
		$output = trim( self::run( $cmd ) );
		if ( empty( $output ) || strpos( $output, 'ERROR' ) === 0 ) return [];

		$parts = explode( '|||', $output );
		return [
			'duration'    => (int) ( $parts[0] ?? 0 ),
			'upload_date' => $parts[1] ?? '',
			'description' => $parts[2] ?? '',
			'view_count'  => (int) ( $parts[3] ?? 0 ),
			'width'       => (int) ( $parts[4] ?? 0 ),
			'height'      => (int) ( $parts[5] ?? 0 ),
		];
	}

	/**
	 * Lightweight oEmbed fetch (no auth needed, no bot wall).
	 * Returns ['title' => ..., 'author_name' => ..., 'thumbnail_url' => ...] or [].
	 */
	private static function oembed( string $video_id ) : array {
		$url = 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( "https://www.youtube.com/watch?v={$video_id}" );
		$res = wp_remote_get( $url, [ 'timeout' => 8, 'redirection' => 3 ] );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) return [];
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return is_array( $data ) ? $data : [];
	}

	/** True if the video is portrait (Shorts format) — height > width */
	private static function is_portrait( array $meta ) : bool {
		$w = (int) ( $meta['width']  ?? 0 );
		$h = (int) ( $meta['height'] ?? 0 );
		if ( $w === 0 || $h === 0 ) return false; // unknown resolution → reject
		return $h > $w;
	}

	/** Shell exec with timeout */
	private static function run( string $cmd ) : string {
		// Prefix with `timeout` to prevent hung processes
		$wrapped = 'timeout ' . self::TIMEOUT_SEC . ' ' . $cmd;
		return (string) @shell_exec( $wrapped );
	}

	private static function parse_ytdlp_date( string $yyyymmdd ) : ?string {
		if ( ! preg_match( '#^(\d{4})(\d{2})(\d{2})$#', $yyyymmdd, $m ) ) return null;
		return "{$m[1]}-{$m[2]}-{$m[3]} 12:00:00";
	}

	private static function trim_caption( string $description ) : string {
		$clean = preg_replace( '#https?://\S+#', '', $description );
		$clean = wp_strip_all_tags( $clean );
		$clean = trim( preg_replace( '#\s+#', ' ', $clean ) );
		return mb_substr( $clean, 0, 220 );
	}

	/** Cron tick — runs every hour. Processes BATCH_PER_TICK scholars,
	 * oldest-synced-first. Light enough to fit inside Cloudways' PHP timeout
	 * even when yt-dlp is slow.
	 */
	public static function cron_tick() : void {
		$r = self::sync_next_batch();
		// Persist last-tick stats so the admin "Sync status" panel can show
		// what happened on the most recent run without us hunting the log.
		update_option( 'la_yt_last_tick', [
			'at'       => current_time( 'mysql' ),
			'synced'   => (int) $r['synced'],
			'inserted' => (int) $r['inserted'],
			'errors'   => array_slice( (array) $r['errors'], 0, 5 ),
		], false );
	}
}
