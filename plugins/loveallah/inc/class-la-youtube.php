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
	const DEEP_PULL_LIMIT    = 500;   // Wave 73: "import last 365 days" mode — pull up to 500 latest
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

	/**
	 * Wave 75: RSS-based ingestion (HTTP only — no shell required).
	 *
	 * Cloudways' production php.ini disables shell_exec, exec, proc_open,
	 * popen, passthru, system, escapeshellcmd — so yt-dlp (which we used
	 * pre-Wave 75) is completely impossible on this host. Every yt-dlp
	 * call was silently failing for weeks before we surfaced the error
	 * via the Wave 74 diagnostic.
	 *
	 * The new path uses YouTube's public RSS feed:
	 *   https://www.youtube.com/feeds/videos.xml?channel_id=UCxxx
	 *
	 * Pros: no auth, no rate limits, plain XML, no bot detection.
	 * Cons: only 15 latest videos per channel (enough for steady-state
	 *       + initial backfill = 349 × 15 ≈ 5,200 video roster).
	 *
	 * For deeper backfill (the Wave 73 "365 day import" mode), we'd need
	 * the YouTube Data API v3 — out of scope for this hotfix.
	 */
	public static function sync_scholar( $scholar, array $opts = [] ) : array {
		global $wpdb;
		$t = LA_DB::tables();

		if ( empty( $scholar->source_url ) ) {
			return [ 'inserted' => 0, 'reason' => 'no_source_url' ];
		}

		$type      = $scholar->default_content_type ?? 'reminder';
		$deep_mode = ! empty( $opts['deep'] );

		// Step 1 — resolve channel_id. Cached on the scholars row after
		// first lookup so future syncs skip the extra HTTP hop.
		$channel_id = self::ensure_channel_id( $scholar );
		if ( empty( $channel_id ) ) {
			$update_data = [ 'last_synced_at' => current_time( 'mysql' ) ];
			if ( self::has_sync_error_column() ) {
				$update_data['last_sync_error'] = 'Could not resolve YouTube channel_id from source_url';
			}
			$wpdb->update( $t['scholars'], $update_data, [ 'id' => (int) $scholar->id ] );
			return [ 'inserted' => 0, 'reason' => 'no_channel_id' ];
		}

		// Step 2 — fetch videos. RSS gives 15 latest; scraping the channel's
		// /videos page yields ~30 more from ytInitialData. Merging both
		// (deduped by video id) gets us ~30 unique videos per channel in one
		// sync pass — 2× the RSS-only ceiling. Wave 78.
		$videos = self::rss_videos( $channel_id );
		$scraped = self::scrape_channel_videos( $scholar->source_url );
		if ( $scraped ) {
			$known_ids = array_flip( array_column( $videos, 'id' ) );
			foreach ( $scraped as $v ) {
				if ( ! isset( $known_ids[ $v['id'] ] ) ) {
					$videos[] = $v;
					$known_ids[ $v['id'] ] = true;
				}
			}
		}
		if ( empty( $videos ) ) {
			$update_data = [ 'last_synced_at' => current_time( 'mysql' ) ];
			if ( self::has_sync_error_column() ) {
				$update_data['last_sync_error'] = 'RSS feed + page scrape both empty (channel may be private/banned/empty)';
			}
			$wpdb->update( $t['scholars'], $update_data, [ 'id' => (int) $scholar->id ] );
			return [ 'inserted' => 0, 'reason' => 'rss_empty' ];
		}

		// Step 3 — SKIP-IF-FRESH: RSS returns newest-first. If we already
		// have the top 3, nothing changed upstream — bail out to save
		// the per-row INSERT existence checks.
		$existing_count = isset( $scholar->video_count )
			? (int) $scholar->video_count
			: (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['feed_posts']} WHERE scholar_id = %d",
				(int) $scholar->id
			) );
		if ( ! $deep_mode && $existing_count >= self::CATCH_UP_THRESHOLD && count( $videos ) >= 3 ) {
			$top_ids = array_slice( array_column( $videos, 'id' ), 0, 3 );
			$top_urls = array_merge(
				array_map( function ( $id ) { return "https://www.youtube.com/watch?v={$id}"; },  $top_ids ),
				array_map( function ( $id ) { return "https://www.youtube.com/shorts/{$id}"; },  $top_ids )
			);
			$ph = implode( ',', array_fill( 0, count( $top_urls ), '%s' ) );
			$known = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['feed_posts']} WHERE original_source_url IN ($ph)",
				...$top_urls
			) );
			if ( $known >= 3 ) {
				$wpdb->update( $t['scholars'], [ 'last_synced_at' => current_time( 'mysql' ) ], [ 'id' => (int) $scholar->id ] );
				return [ 'inserted' => 0, 'fetched' => count( $videos ), 'via' => 'rss', 'skipped_fresh' => true ];
			}
		}

		// Step 4 — insert new videos. Orientation/duration filters are
		// dropped here because RSS doesn't carry that metadata (yt-dlp
		// fetched it via single_metadata before). The trade-off: we
		// accept some landscape videos for visual types, but the catalog
		// fills out instantly. Frontend already renders any aspect ratio
		// gracefully (object-fit: cover on the iframe wrapper).
		$inserted = 0;
		foreach ( $videos as $v ) {
			$source_url = "https://www.youtube.com/watch?v={$v['id']}";
			$shorts_url = "https://www.youtube.com/shorts/{$v['id']}";

			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['feed_posts']}
				 WHERE original_source_url IN (%s, %s) LIMIT 1",
				$source_url, $shorts_url
			) );
			if ( $exists ) continue;

			$wpdb->insert( $t['feed_posts'], [
				'scholar_id'          => (int) $scholar->id,
				'type'                => $type,
				'title'               => mb_substr( $v['title'], 0, 250 ),
				'caption'             => mb_substr( $v['description'] ?? '', 0, 220 ),
				'video_url'           => "https://www.youtube.com/embed/{$v['id']}",
				'thumbnail_url'       => ! empty( $v['thumbnail'] ) ? $v['thumbnail'] : "https://i.ytimg.com/vi/{$v['id']}/hqdefault.jpg",
				'original_source_url' => $source_url,
				'duration_sec'        => 0,
				'published_at'        => ! empty( $v['published'] ) ? $v['published'] : gmdate( 'Y-m-d H:i:s' ),
				// `created_at` is when WE ingested it (drives the +200 freshness
				// boost in LA_Algorithm). Stamp explicitly so values are
				// identical across sites with non-UTC server timezones.
				'created_at'          => current_time( 'mysql', true ),
			] );
			$inserted++;
		}

		$update_data = [ 'last_synced_at' => current_time( 'mysql' ) ];
		if ( self::has_sync_error_column() ) {
			$update_data['last_sync_error'] = null;
		}
		$wpdb->update( $t['scholars'], $update_data, [ 'id' => (int) $scholar->id ] );
		return [ 'inserted' => $inserted, 'fetched' => count( $videos ), 'via' => 'rss' ];
	}

	/**
	 * Returns the cached channel_id from the scholars row, or resolves
	 * it now (HTTP fetch of the channel page) and caches the result.
	 */
	private static function ensure_channel_id( $scholar ) : string {
		global $wpdb;
		$t = LA_DB::tables();

		if ( isset( $scholar->youtube_channel_id ) && ! empty( $scholar->youtube_channel_id ) ) {
			return (string) $scholar->youtube_channel_id;
		}

		// Some seed rows already use /channel/UCxxx URLs — extract directly.
		if ( preg_match( '#/channel/(UC[A-Za-z0-9_-]{20,})#', (string) $scholar->source_url, $m ) ) {
			$cid = $m[1];
			self::cache_channel_id( (int) $scholar->id, $cid );
			return $cid;
		}

		$cid = self::resolve_channel_id( (string) $scholar->source_url );
		if ( $cid ) self::cache_channel_id( (int) $scholar->id, $cid );
		return $cid;
	}

	/** Defensive cache write — column may not exist on legacy installs yet. */
	private static function cache_channel_id( int $scholar_id, string $cid ) : void {
		global $wpdb;
		$t = LA_DB::tables();
		static $has_col = null;
		if ( $has_col === null ) {
			$has_col = (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
				   AND COLUMN_NAME = 'youtube_channel_id'",
				$t['scholars']
			) );
		}
		if ( $has_col ) {
			$wpdb->update( $t['scholars'], [ 'youtube_channel_id' => $cid ], [ 'id' => $scholar_id ] );
		}
	}

	/**
	 * Scrapes the YouTube channel page to find the UC-prefixed channel_id.
	 * Tries multiple HTML patterns since YouTube changes its inline-config
	 * shape periodically. Returns empty string if nothing matches.
	 */
	private static function resolve_channel_id( string $source_url ) : string {
		if ( empty( $source_url ) ) return '';
		// Strip tab suffixes so we hit the canonical channel page.
		$url = preg_replace( '#/(shorts|videos|featured|streams|playlists|community|about)/?$#', '', $source_url );
		$url = rtrim( (string) $url, '/' );

		// Wave 76: YouTube redirects unknown server IPs to consent.youtube.com
		// asking to accept cookies before showing channel pages — that consent
		// wall has no channelId, so resolve_channel_id failed for ~8 high-
		// priority scholars (Mishary Alafasy, Sudais, Saad Al-Ghamdi, etc.).
		// Sending CONSENT=YES+cb skips the wall, and a real Chrome user-agent
		// avoids the simplified-bot HTML that lacks the inline JSON config.
		$res = wp_remote_get( $url, [
			'timeout'     => 15,
			'redirection' => 5,
			'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'headers'     => [
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-GB,en;q=0.9',
				'Cookie'          => 'CONSENT=YES+cb.20210328-17-p0.en+FX+999; SOCS=CAI',
			],
		] );
		if ( is_wp_error( $res ) ) return '';
		if ( (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return '';
		$body = (string) wp_remote_retrieve_body( $res );
		if ( empty( $body ) ) return '';

		// YouTube embeds channel_id in several places. Check the common ones,
		// stop at the first hit. The UC-prefix length (24 chars total) keeps
		// the regex safe against picking up unrelated UC* strings.
		$patterns = [
			'#"channelId":"(UC[A-Za-z0-9_-]{22})"#',
			'#"externalId":"(UC[A-Za-z0-9_-]{22})"#',
			'#"browseId":"(UC[A-Za-z0-9_-]{22})"#',
			'#<meta itemprop="(?:channelId|identifier)" content="(UC[A-Za-z0-9_-]{22})"#',
			'#data-channel-external-id="(UC[A-Za-z0-9_-]{22})"#',
			// Catch-all: any link to /channel/UCxxx in the page (footer subscribe links,
			// canonical URL, related-channel cards all use this). Most permissive last.
			'#/channel/(UC[A-Za-z0-9_-]{22})#',
		];
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $body, $m ) ) return $m[1];
		}
		return '';
	}

	/**
	 * Wave 78: scrape the channel's /videos page for additional video ids
	 * beyond the 15-row RSS ceiling. YouTube's ytInitialData blob in the
	 * page HTML contains ~30 video entries across the videos + shorts tabs.
	 *
	 * Returns array of [id, title, published, thumbnail, description] —
	 * same shape as rss_videos(). published/description/thumbnail may be
	 * empty since the channel-page card has less metadata than RSS.
	 */
	private static function scrape_channel_videos( string $source_url ) : array {
		if ( empty( $source_url ) ) return [];
		// Hit /videos to maximise the items in ytInitialData; the channel root
		// only renders the home tab which has fewer cards.
		$url = preg_replace( '#/(shorts|videos|featured|streams|playlists|community|about)/?$#', '', $source_url );
		$url = rtrim( (string) $url, '/' ) . '/videos';

		// Wave 78b: YouTube was serving m.youtube.com (mobile) to our Cloudways
		// server IP — the mobile page loads videos asynchronously via JS and has
		// ZERO videoId tokens in initial HTML, so the scrape returned nothing
		// for every top creator. Fix: append ?app=desktop AND send the
		// `PREF=f6=4000000` cookie that opts the session out of mobile
		// redirects. Also switched UA to Linux Chrome which YouTube reliably
		// serves the desktop www.youtube.com response.
		$url_with_app = $url . '?app=desktop&hl=en';

		$res = wp_remote_get( $url_with_app, [
			'timeout'     => 15,
			'redirection' => 5,
			'user-agent'  => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'headers'     => [
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-GB,en;q=0.9',
				// PREF=f6=4000000 disables mobile auto-redirect; CONSENT skips the EU consent wall.
				'Cookie'          => 'CONSENT=YES+cb.20210328-17-p0.en+FX+999; SOCS=CAI; PREF=f6=4000000',
			],
		] );
		if ( is_wp_error( $res ) ) return [];
		if ( (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return [];
		$body = (string) wp_remote_retrieve_body( $res );
		if ( empty( $body ) ) return [];
		// Safety guard: if YouTube STILL served us m.youtube.com (PREF didn't
		// stick), the body has zero videoId tokens — bail early so we don't
		// waste regex cycles on an empty page.
		if ( strpos( $body, 'm.youtube.com' ) !== false && strpos( $body, '"videoId"' ) === false ) {
			return [];
		}

		// Extract ytInitialData JSON blob — the channel page embeds it in a
		// <script> tag. We don't fully parse the nested structure (it's
		// huge and YouTube changes it weekly); instead we pull all
		// (videoId, title) pairs by pattern match. The 11-char videoId
		// length keeps the regex from false-positive matching unrelated
		// strings.
		$start = strpos( $body, 'var ytInitialData' );
		if ( $start === false ) {
			// Fall back to whole-page scan if the var marker moved.
			$haystack = $body;
		} else {
			$haystack = substr( $body, $start, 1500000 ); // cap window so big pages don't blow PHP regex
		}

		$videos = [];
		$seen   = [];
		// Pattern: "videoId":"XXX" appears next to "title":{"runs":[{"text":"..."}]}
		// in videoRenderer / gridVideoRenderer cards. Capture each pair.
		if ( preg_match_all(
			'#"videoId":"([A-Za-z0-9_-]{11})"[^{]*?(?:"thumbnail":\{[^}]*\}[^{]*?)?(?:"title":\{"runs":\[\{"text":"([^"]{1,200})"#',
			$haystack,
			$matches,
			PREG_SET_ORDER
		) ) {
			foreach ( $matches as $m ) {
				$id = $m[1];
				if ( isset( $seen[ $id ] ) ) continue;
				$seen[ $id ] = true;
				// Decode unicode escapes that YouTube uses in JSON (e.g. é).
				$title = json_decode( '"' . str_replace( '"', '\\"', $m[2] ) . '"' );
				if ( ! is_string( $title ) ) $title = $m[2];
				$videos[] = [
					'id'          => $id,
					'title'       => trim( (string) $title ),
					'published'   => '',  // not in ytInitialData for the card view
					'thumbnail'   => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
					'description' => '',
				];
				if ( count( $videos ) >= 40 ) break;
			}
		}
		// Shorts cards use a different shape — pick up the IDs only.
		if ( count( $videos ) < 40
		     && preg_match_all( '#"videoId":"([A-Za-z0-9_-]{11})"#', $haystack, $shorts_m )
		) {
			foreach ( $shorts_m[1] as $id ) {
				if ( isset( $seen[ $id ] ) ) continue;
				$seen[ $id ] = true;
				$videos[] = [
					'id'          => $id,
					'title'       => '',  // resolve later if needed via oEmbed
					'published'   => '',
					'thumbnail'   => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
					'description' => '',
				];
				if ( count( $videos ) >= 40 ) break;
			}
		}
		return $videos;
	}

	/**
	 * Fetches the YouTube RSS feed for a channel and parses out the
	 * 15 latest videos. Returns array of [id, title, published, thumbnail, description].
	 */
	private static function rss_videos( string $channel_id ) : array {
		$url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode( $channel_id );
		$res = wp_remote_get( $url, [ 'timeout' => 10, 'redirection' => 3 ] );
		if ( is_wp_error( $res ) ) return [];
		if ( (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return [];
		$body = (string) wp_remote_retrieve_body( $res );
		if ( empty( $body ) ) return [];

		// SimpleXML throws PHP warnings into the page output on parse errors;
		// suppress them so admin pages don't fill with libxml noise.
		$prev_errors = libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );
		if ( $xml === false ) return [];

		$ns           = $xml->getNamespaces( true );
		$yt_ns_uri    = $ns['yt']    ?? 'http://www.youtube.com/xml/schemas/2015';
		$media_ns_uri = $ns['media'] ?? 'http://search.yahoo.com/mrss/';

		$videos = [];
		foreach ( $xml->entry as $entry ) {
			$yt  = $entry->children( $yt_ns_uri );
			$vid = (string) $yt->videoId;
			if ( empty( $vid ) ) continue;

			$published = '';
			$raw_pub   = (string) $entry->published;
			if ( $raw_pub ) {
				$ts = strtotime( $raw_pub );
				if ( $ts ) $published = gmdate( 'Y-m-d H:i:s', $ts );
			}

			$thumb = '';
			$desc  = '';
			$media_root = $entry->children( $media_ns_uri );
			if ( isset( $media_root->group ) ) {
				$group_kids = $media_root->group->children( $media_ns_uri );
				if ( isset( $group_kids->thumbnail ) ) {
					foreach ( $group_kids->thumbnail as $th ) {
						$thumb = (string) $th['url'];
						break;
					}
				}
				if ( isset( $group_kids->description ) ) {
					$desc = trim( (string) $group_kids->description );
				}
			}

			$videos[] = [
				'id'          => $vid,
				'title'       => trim( (string) $entry->title ),
				'published'   => $published,
				'thumbnail'   => $thumb,
				'description' => $desc,
			];
		}
		return $videos;
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
			self::shell_cmd( self::ytdlp() ),
			(int) $limit,
			self::shell_arg( $shorts_url )
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
			self::shell_cmd( self::ytdlp() ),
			self::shell_arg( $url )
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
		if ( ! function_exists( 'shell_exec' ) ) {
			// Surface a clear reason so the catch-up diagnostic page can
			// flag this — otherwise we silently return empty and the channel
			// gets marked as "no_content_tabs".
			throw new \RuntimeException( 'shell_exec disabled by php.ini disable_functions — YouTube sync cannot run' );
		}
		// Prefix with `timeout` to prevent hung processes
		$wrapped = 'timeout ' . self::TIMEOUT_SEC . ' ' . $cmd;
		return (string) @shell_exec( $wrapped );
	}

	/**
	 * Polyfills for escapeshellcmd / escapeshellarg.
	 *
	 * Some shared hosts (Cloudways included for a stretch around mid-2026)
	 * disable these in php.ini's disable_functions, even while shell_exec
	 * itself remains available. Without polyfills the whole YouTube sync
	 * dies with "Call to undefined function ..." and every catch-up pass
	 * silently reports 0 channels checked.
	 *
	 * The polyfills reproduce PHP core semantics closely enough for our
	 * usage — we only ever pass our own yt-dlp binary path + YouTube URLs
	 * (never user input), so we don't need 100% byte-identical output, just
	 * shell-safe quoting that won't break command parsing.
	 */
	private static function shell_cmd( string $cmd ) : string {
		if ( function_exists( 'escapeshellcmd' ) ) return escapeshellcmd( $cmd );
		// Drop nulls (PHP core strips these). Backslash-escape shell
		// metacharacters so the command can't be hijacked even if a
		// malicious URL ever slipped through.
		$cmd = str_replace( "\x00", '', $cmd );
		return preg_replace( '/([#&;`|*?~<>^()\[\]{}$\\\\\x0A\xFF\'\"\s])/', '\\\\$1', $cmd );
	}

	private static function shell_arg( string $arg ) : string {
		if ( function_exists( 'escapeshellarg' ) ) return escapeshellarg( $arg );
		// Single-quote-wrap. Embedded single quotes get closed, escaped,
		// and reopened — the canonical POSIX trick: '\'' inside ' ... '.
		$arg = str_replace( "\x00", '', $arg );
		return "'" . str_replace( "'", "'\\''", $arg ) . "'";
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
