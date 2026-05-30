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

		// Wave 87 — shorts-only path. When scholar.shorts_only=1 we skip
		// every mixed-content source (RSS, Invidious, /videos page) and
		// hit ONLY /shorts. Shorts are guaranteed portrait + <60s by
		// YouTube's own definition, so we don't need any client-side
		// duration/orientation filtering — every video that appears on a
		// channel's /shorts tab is automatically feed-ready. This pivot
		// makes the feed addictive in the TikTok sense — every card is a
		// punchy <1-minute reminder, never a 30-minute lecture sitting
		// awkwardly inside a vertical scroller.
		$shorts_only = ! empty( $scholar->shorts_only );
		if ( $shorts_only ) {
			$videos = self::scrape_channel_shorts( $scholar->source_url );
			// Wave 87b fallback: when /shorts page returns a JS-shell with
			// zero videoIds (Mufti Menk, Omar Suleiman, Bilal Assad —
			// per-channel A/B test from YouTube's side), use the official
			// YouTube Data API v3 to enumerate the channel's recent
			// uploads and filter to those with duration ≤ 60s (Shorts).
			// Requires la_yt_api_key option to be set.
			if ( empty( $videos ) ) {
				$api_key = (string) get_option( 'la_yt_api_key', '' );
				if ( $api_key !== '' ) {
					$videos = self::yt_api_v3_shorts( $channel_id, $api_key );
				}
			}
		} else {
			// Legacy mixed-content path. Three sources in priority order,
			// deduped by video id:
			//   1. YouTube RSS (15 latest, fast, but top creators return 0)
			//   2. Invidious public API (~60 videos, JSON, no auth)        ← Wave 79
			//   3. /videos page scrape (~30 from ytInitialData, captcha-prone)
			$videos = self::rss_videos( $channel_id );
			$invidious = self::invidious_videos( $channel_id );
			if ( $invidious ) {
				$known_ids = array_flip( array_column( $videos, 'id' ) );
				foreach ( $invidious as $v ) {
					if ( ! isset( $known_ids[ $v['id'] ] ) ) {
						$videos[] = $v;
						$known_ids[ $v['id'] ] = true;
					}
				}
			}
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
		// Wave 87d: check if the dedicated youtube_video_id column
		// exists once per sync run — used both for dedup checks and
		// the insert payload.
		static $has_vid_col = null;
		if ( $has_vid_col === null ) {
			$has_vid_col = (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE()
				   AND TABLE_NAME = %s
				   AND COLUMN_NAME = 'youtube_video_id'",
				$t['feed_posts']
			) );
		}
		$inserted = 0;
		foreach ( $videos as $v ) {
			$source_url = "https://www.youtube.com/watch?v={$v['id']}";
			$shorts_url = "https://www.youtube.com/shorts/{$v['id']}";

			// Wave 87d: dedup by youtube_video_id when the column exists,
			// fall back to URL match otherwise. Indexed lookup → O(1).
			// This belt-and-braces approach means a video can never be
			// inserted twice even if it surfaces via two different paths
			// (e.g. /shorts scrape AND API v3 fallback hitting same
			// channel back-to-back with slightly different URL formats).
			if ( $has_vid_col ) {
				$exists = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$t['feed_posts']}
					 WHERE youtube_video_id = %s LIMIT 1",
					$v['id']
				) );
			} else {
				$exists = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$t['feed_posts']}
					 WHERE original_source_url IN (%s, %s) LIMIT 1",
					$source_url, $shorts_url
				) );
			}
			if ( $exists ) continue;

			// Wave 87: when sourced from /shorts we know it IS a short,
			// so tag the type accordingly. This keeps the LA_Algorithm
			// type-balancer (Wave 82) working — it can still rotate
			// "short" alongside "lecture"/"qirat" for the legacy mixed
			// content. The original_source_url uses /shorts/{id} so the
			// share buttons + clip links open native YouTube Shorts UI
			// on mobile (full-screen autoplay loop) rather than the
			// regular /watch?v= player.
			$post_type = $shorts_only ? 'short' : $type;
			$shorts_source_url = "https://www.youtube.com/shorts/{$v['id']}";

			// Wave 87d: explicitly persist the bare 11-char video ID
			// alongside the URLs. This is the canonical identity for the
			// row — URLs can change format (watch vs shorts vs embed) but
			// the video ID never does.
			$insert_payload = [
				'scholar_id'          => (int) $scholar->id,
				'type'                => $post_type,
				'title'               => mb_substr( $v['title'], 0, 250 ),
				'caption'             => mb_substr( $v['description'] ?? '', 0, 220 ),
				'video_url'           => "https://www.youtube.com/embed/{$v['id']}",
				'thumbnail_url'       => ! empty( $v['thumbnail'] ) ? $v['thumbnail'] : "https://i.ytimg.com/vi/{$v['id']}/hqdefault.jpg",
				'original_source_url' => $shorts_only ? $shorts_source_url : $source_url,
				'duration_sec'        => 0,
				'published_at'        => ! empty( $v['published'] ) ? $v['published'] : gmdate( 'Y-m-d H:i:s' ),
				// `created_at` is when WE ingested it (drives the +200 freshness
				// boost in LA_Algorithm). Stamp explicitly so values are
				// identical across sites with non-UTC server timezones.
				'created_at'          => current_time( 'mysql', true ),
			];
			if ( $has_vid_col ) {
				$insert_payload['youtube_video_id'] = $v['id'];
			}
			$wpdb->insert( $t['feed_posts'], $insert_payload );
			$inserted++;
		}

		$update_data = [ 'last_synced_at' => current_time( 'mysql' ) ];
		if ( self::has_sync_error_column() ) {
			$update_data['last_sync_error'] = null;
		}
		$wpdb->update( $t['scholars'], $update_data, [ 'id' => (int) $scholar->id ] );
		return [
			'inserted' => $inserted,
			'fetched'  => count( $videos ),
			'via'      => $shorts_only ? 'shorts_scrape' : 'rss',
		];
	}

	/**
	 * Wave 87: scrape the channel's /shorts tab for portrait <60s clips.
	 *
	 * YouTube's /shorts tab only ever lists videos that meet the Shorts
	 * definition (vertical 9:16 aspect, ≤60s duration, music-track or no-
	 * music). So if we extract videoIds from this page, we don't need
	 * any further duration/aspect-ratio filter — every result is a
	 * portrait clip ready to drop into the snap feed.
	 *
	 * Same anti-bot tricks as scrape_channel_videos():
	 *   - ?app=desktop&hl=en + PREF cookie to bypass m.youtube.com
	 *   - Linux Chrome UA which YouTube serves desktop HTML to
	 *   - CONSENT cookie to skip the EU consent wall
	 *
	 * Returns up to 60 shorts (more than enough for catalog backfill).
	 */
	private static function scrape_channel_shorts( string $source_url ) : array {
		if ( empty( $source_url ) ) return [];
		// Normalise to .../shorts regardless of what suffix the source_url had.
		$base = preg_replace( '#/(shorts|videos|featured|streams|playlists|community|about)/?$#', '', $source_url );
		$base = rtrim( (string) $base, '/' );
		$url  = $base . '/shorts?app=desktop&hl=en';

		$res = wp_remote_get( $url, [
			'timeout'     => 15,
			'redirection' => 5,
			'user-agent'  => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'headers'     => [
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-GB,en;q=0.9',
				'Cookie'          => 'CONSENT=YES+cb.20210328-17-p0.en+FX+999; SOCS=CAI; PREF=f6=4000000',
			],
		] );
		if ( is_wp_error( $res ) ) return [];
		if ( (int) wp_remote_retrieve_response_code( $res ) !== 200 ) return [];
		$body = (string) wp_remote_retrieve_body( $res );
		if ( empty( $body ) ) return [];
		// Mobile-redirect guard, same as the /videos scraper.
		if ( strpos( $body, 'm.youtube.com' ) !== false && strpos( $body, '"videoId"' ) === false ) {
			return [];
		}

		// Locate the ytInitialData blob; cap the window so big channel
		// pages don't blow up the regex engine.
		$start = strpos( $body, 'var ytInitialData' );
		$haystack = $start === false ? $body : substr( $body, $start, 1500000 );

		$videos = [];
		$seen   = [];

		// Pattern 1 (preferred): shortsLockupViewModel — newer card shape
		// that wraps a videoId with the title text right after.
		if ( preg_match_all(
			'#"videoId":"([A-Za-z0-9_-]{11})"[^{]*?(?:"headline":\{"runs":\[\{"text":"|"accessibilityText":")([^"]{1,200})#',
			$haystack,
			$matches,
			PREG_SET_ORDER
		) ) {
			foreach ( $matches as $m ) {
				$id = $m[1];
				if ( isset( $seen[ $id ] ) ) continue;
				$seen[ $id ] = true;
				$title = json_decode( '"' . str_replace( '"', '\\"', $m[2] ) . '"' );
				if ( ! is_string( $title ) ) $title = $m[2];
				$videos[] = [
					'id'          => $id,
					'title'       => trim( (string) $title ),
					'published'   => '',
					'thumbnail'   => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
					'description' => '',
				];
				if ( count( $videos ) >= 60 ) break;
			}
		}

		// Pattern 2 (fallback): naked videoId scan — picks up everything
		// the structured matcher missed. Safe here because we're on /shorts
		// — every videoId on this page is, by definition, a Short.
		if ( count( $videos ) < 60
		     && preg_match_all( '#"videoId":"([A-Za-z0-9_-]{11})"#', $haystack, $bare )
		) {
			foreach ( $bare[1] as $id ) {
				if ( isset( $seen[ $id ] ) ) continue;
				$seen[ $id ] = true;
				$videos[] = [
					'id'          => $id,
					'title'       => '',
					'published'   => '',
					'thumbnail'   => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
					'description' => '',
				];
				if ( count( $videos ) >= 60 ) break;
			}
		}

		return $videos;
	}

	/**
	 * Wave 87b: YouTube Data API v3 fallback for Shorts discovery.
	 *
	 * Two API calls per channel:
	 *   1. search.list?channelId=...&type=video&order=date&maxResults=50
	 *      → returns the 50 most-recent video IDs (cost: 100 units)
	 *   2. videos.list?id=ID1,ID2,...&part=contentDetails,snippet
	 *      → returns duration (PT15S, PT1M30S, etc.) and metadata
	 *      → cost: 1 unit per call (up to 50 IDs in one call)
	 *
	 * Total cost per channel sync = 101 units.
	 * Free quota = 10,000/day → ~99 channel-syncs/day at this rate.
	 *
	 * We post-filter to videos whose duration parses to ≤ 60 seconds
	 * (the YouTube Shorts cap). This is the most reliable source-of-
	 * truth for "is this a Short" — official API, no scraping, no
	 * captcha, no per-channel A/B inconsistency.
	 */
	private static function yt_api_v3_shorts( string $channel_id, string $api_key ) : array {
		if ( empty( $channel_id ) || empty( $api_key ) ) return [];

		// Wave 87c — daily quota guard. The free YouTube Data API tier
		// caps at 10,000 units/day. Each call here costs ~101 units
		// (search.list = 100 + videos.list = 1). We hard-stop at 90
		// channel-syncs per UTC day to leave headroom for retries or
		// the occasional channels.list lookup. The counter is stored
		// in wp_options la_yt_api_calls_today_{Ymd} and auto-rolls
		// over at UTC midnight by virtue of the date-stamped key.
		//
		// Why this matters for scale: this guard protects WRITES.
		// READS (user views) never call this method — they read
		// pre-stored embed URLs from feed_posts. So the 90-channel
		// cap caps catalog GROWTH per day, not user capacity.
		// A 100M-user app and a 1-user app both fit inside 10k/day.
		$today_key = 'la_yt_api_calls_today_' . gmdate( 'Ymd' );
		$count_today = (int) get_option( $today_key, 0 );
		if ( $count_today >= 90 ) {
			// Quota guard tripped. Bail and let tomorrow's tick pick up.
			return [];
		}
		update_option( $today_key, $count_today + 1, false );

		// Step 1 — recent uploads via search.list.
		$search_url = add_query_arg( [
			'key'        => $api_key,
			'channelId'  => $channel_id,
			'part'       => 'id',
			'type'       => 'video',
			'order'      => 'date',
			'maxResults' => 50,
		], 'https://www.googleapis.com/youtube/v3/search' );

		$res = wp_remote_get( $search_url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $res ) ) return [];
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code !== 200 ) return [];
		$body = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || empty( $json['items'] ) ) return [];

		$ids = [];
		foreach ( $json['items'] as $item ) {
			$id = $item['id']['videoId'] ?? '';
			if ( $id ) $ids[] = $id;
		}
		if ( empty( $ids ) ) return [];

		// Step 2 — duration + metadata via videos.list (one batched call).
		$videos_url = add_query_arg( [
			'key'  => $api_key,
			'id'   => implode( ',', $ids ),
			'part' => 'contentDetails,snippet',
		], 'https://www.googleapis.com/youtube/v3/videos' );

		$res2 = wp_remote_get( $videos_url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $res2 ) ) return [];
		if ( (int) wp_remote_retrieve_response_code( $res2 ) !== 200 ) return [];
		$body2 = (string) wp_remote_retrieve_body( $res2 );
		$json2 = json_decode( $body2, true );
		if ( ! is_array( $json2 ) || empty( $json2['items'] ) ) return [];

		$out = [];
		foreach ( $json2['items'] as $item ) {
			$id  = $item['id'] ?? '';
			if ( ! $id ) continue;
			$dur = $item['contentDetails']['duration'] ?? '';
			$sec = self::iso8601_to_seconds( $dur );
			// Skip anything longer than 61s — those aren't Shorts.
			// (We use 61 not 60 so videos rounded to "PT1M" pass.)
			if ( $sec <= 0 || $sec > 61 ) continue;

			$snip = $item['snippet'] ?? [];
			$title = trim( (string) ( $snip['title'] ?? '' ) );
			$desc  = trim( (string) ( $snip['description'] ?? '' ) );
			$pub   = trim( (string) ( $snip['publishedAt'] ?? '' ) );
			$published_at = '';
			if ( $pub ) {
				$ts = strtotime( $pub );
				if ( $ts ) $published_at = gmdate( 'Y-m-d H:i:s', $ts );
			}
			$thumb = '';
			if ( ! empty( $snip['thumbnails']['high']['url'] ) ) {
				$thumb = $snip['thumbnails']['high']['url'];
			} elseif ( ! empty( $snip['thumbnails']['default']['url'] ) ) {
				$thumb = $snip['thumbnails']['default']['url'];
			} else {
				$thumb = "https://i.ytimg.com/vi/{$id}/hqdefault.jpg";
			}

			$out[] = [
				'id'          => $id,
				'title'       => $title,
				'published'   => $published_at,
				'thumbnail'   => $thumb,
				'description' => $desc,
			];
		}
		return $out;
	}

	/**
	 * Convert ISO-8601 duration (e.g. PT1M30S, PT45S, PT2H15M) to seconds.
	 * Returns 0 for unparseable input.
	 */
	private static function iso8601_to_seconds( string $iso ) : int {
		if ( empty( $iso ) ) return 0;
		if ( ! preg_match( '#^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$#', $iso, $m ) ) return 0;
		$h = isset( $m[1] ) ? (int) $m[1] : 0;
		$min = isset( $m[2] ) ? (int) $m[2] : 0;
		$s = isset( $m[3] ) ? (int) $m[3] : 0;
		return $h * 3600 + $min * 60 + $s;
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
	 * Wave 79: Invidious public-API channel video listing.
	 *
	 * Invidious is an open-source YouTube proxy with many public instances.
	 * Their /api/v1/channels/{cid}/videos endpoint returns ~60 videos as
	 * clean JSON, no auth, no captcha, no rate limiting from individual
	 * instances. Top creators (Mufti Menk, Sudais, Alafasy) — whose RSS
	 * feeds YouTube has turned off — pull cleanly through Invidious.
	 *
	 * We try a list of public instances in random order; first one that
	 * returns videos wins. The instance list is editable via the
	 * `la_invidious_instances` option (one URL per line) so admins can
	 * swap in their own preferred mirrors when uptime drops.
	 */
	public static function invidious_instances() : array {
		$opt = (string) get_option( 'la_invidious_instances', '' );
		if ( $opt ) {
			$rows = array_filter( array_map( 'trim', explode( "\n", $opt ) ) );
			if ( $rows ) return $rows;
		}
		// Curated list of healthy public instances as of mid-2026.
		// Pulled from https://api.invidious.io/ (community-maintained list).
		return [
			'https://invidious.nerdvpn.de',
			'https://yewtu.be',
			'https://invidious.privacyredirect.com',
			'https://iv.nboeck.de',
			'https://invidious.materialio.us',
			'https://invidious.jing.rocks',
			'https://invidious.protokolla.fi',
			'https://invidious.private.coffee',
		];
	}

	private static function invidious_videos( string $channel_id, int $limit = 60 ) : array {
		if ( empty( $channel_id ) ) return [];
		$instances = self::invidious_instances();
		shuffle( $instances ); // rotate so we don't hammer one host

		foreach ( $instances as $base ) {
			$base = rtrim( $base, '/' );
			$url  = $base . '/api/v1/channels/' . urlencode( $channel_id ) . '/videos';
			$res = wp_remote_get( $url, [
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'Mozilla/5.0 (compatible; LoveAllah/1.0; +https://loveallah.app)',
				'headers'     => [ 'Accept' => 'application/json' ],
			] );
			if ( is_wp_error( $res ) ) continue;
			$code = (int) wp_remote_retrieve_response_code( $res );
			if ( $code !== 200 ) continue;
			$body = (string) wp_remote_retrieve_body( $res );
			if ( empty( $body ) || $body[0] !== '{' && $body[0] !== '[' ) continue;
			$data = json_decode( $body, true );
			// Invidious response shape: { videos: [ { videoId, title, published, lengthSeconds, ... } ], ... }
			// Some instances return the videos array directly.
			$rows = [];
			if ( is_array( $data ) ) {
				$rows = is_array( $data['videos'] ?? null ) ? $data['videos']
				      : ( isset( $data[0]['videoId'] ) ? $data : [] );
			}
			if ( empty( $rows ) ) continue;

			$videos = [];
			foreach ( $rows as $r ) {
				$vid = (string) ( $r['videoId'] ?? '' );
				if ( strlen( $vid ) !== 11 ) continue;
				$title = (string) ( $r['title'] ?? '' );
				$pub_ts = (int) ( $r['published'] ?? 0 );
				$pub = $pub_ts ? gmdate( 'Y-m-d H:i:s', $pub_ts ) : '';
				// Description rarely populated on the channel-list response; we can
				// leave it blank — algorithm doesn't depend on it.
				$videos[] = [
					'id'          => $vid,
					'title'       => trim( $title ),
					'published'   => $pub,
					'thumbnail'   => "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg",
					'description' => '',
				];
				if ( count( $videos ) >= $limit ) break;
			}
			if ( $videos ) return $videos;
		}
		return [];
	}

	/**
	 * Wave 78d: probe helpers — return raw diagnostics for the Sync Now
	 * admin page so we can see why a channel isn't pulling.
	 */
	public static function debug_probe( $scholar ) : array {
		$out = [
			'source_url'     => (string) ( $scholar->source_url ?? '' ),
			'cached_cid'     => (string) ( $scholar->youtube_channel_id ?? '' ),
			'resolved_cid'   => '',
			'rss_status'     => '',
			'rss_body_len'   => 0,
			'rss_entries'    => 0,
			'scrape_status'  => '',
			'scrape_body_len'=> 0,
			'scrape_url'     => '',
			'scrape_uc_hits' => 0,
			'scrape_vid_hits'=> 0,
			'scrape_mobile'  => false,
		];
		$cid = $out['cached_cid'];
		if ( ! $cid ) {
			$cid = self::resolve_channel_id( $out['source_url'] );
			$out['resolved_cid'] = $cid;
		}
		// Wave 79: Invidious probe — try each instance separately and report.
		$out['invidious_tries'] = [];
		if ( $cid ) {
			foreach ( self::invidious_instances() as $base ) {
				$base = rtrim( $base, '/' );
				$url = $base . '/api/v1/channels/' . urlencode( $cid ) . '/videos';
				$res = wp_remote_get( $url, [ 'timeout' => 8, 'redirection' => 3 ] );
				$status = is_wp_error( $res ) ? ( 'err: ' . substr( $res->get_error_message(), 0, 40 ) ) : (string) wp_remote_retrieve_response_code( $res );
				$body_len = is_wp_error( $res ) ? 0 : strlen( (string) wp_remote_retrieve_body( $res ) );
				$count = 0;
				if ( ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) === 200 ) {
					$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
					$rows = is_array( $data ) ? ( $data['videos'] ?? ( isset( $data[0]['videoId'] ) ? $data : [] ) ) : [];
					$count = is_array( $rows ) ? count( $rows ) : 0;
				}
				$host = parse_url( $base, PHP_URL_HOST );
				$out['invidious_tries'][] = "{$host}: status={$status} body={$body_len}B videos={$count}";
				// Stop after we find a working instance with content
				if ( $count > 0 ) break;
				// Cap at 5 tries to keep diagnostic page fast
				if ( count( $out['invidious_tries'] ) >= 5 ) break;
			}
		}
		// RSS probe
		if ( $cid ) {
			$rss_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode( $cid );
			$rss = wp_remote_get( $rss_url, [ 'timeout' => 10, 'redirection' => 3 ] );
			if ( is_wp_error( $rss ) ) {
				$out['rss_status'] = 'wp_error: ' . $rss->get_error_message();
			} else {
				$out['rss_status']   = (string) wp_remote_retrieve_response_code( $rss );
				$body                = (string) wp_remote_retrieve_body( $rss );
				$out['rss_body_len'] = strlen( $body );
				$out['rss_entries']  = substr_count( $body, '<entry>' );
			}
		}
		// Scrape probe — same headers/url as scrape_channel_videos
		$scrape_url = preg_replace( '#/(shorts|videos|featured|streams|playlists|community|about)/?$#', '', $out['source_url'] );
		$scrape_url = rtrim( (string) $scrape_url, '/' ) . '/videos?app=desktop&hl=en';
		$out['scrape_url'] = $scrape_url;
		$sr = wp_remote_get( $scrape_url, [
			'timeout' => 15,
			'redirection' => 5,
			'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'headers' => [
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-GB,en;q=0.9',
				'Cookie'          => 'CONSENT=YES+cb.20210328-17-p0.en+FX+999; SOCS=CAI; PREF=f6=4000000',
			],
		] );
		if ( is_wp_error( $sr ) ) {
			$out['scrape_status'] = 'wp_error: ' . $sr->get_error_message();
		} else {
			$out['scrape_status']   = (string) wp_remote_retrieve_response_code( $sr );
			$body                   = (string) wp_remote_retrieve_body( $sr );
			$out['scrape_body_len'] = strlen( $body );
			// Count occurrences of UC channelIds and videoIds
			$out['scrape_uc_hits']  = preg_match_all( '/UC[A-Za-z0-9_-]{22}/', $body, $junk );
			$out['scrape_vid_hits'] = preg_match_all( '/"videoId":"[A-Za-z0-9_-]{11}"/', $body, $junk );
			$out['scrape_mobile']   = ( strpos( $body, 'm.youtube.com' ) !== false ) && ( strpos( $body, '"videoId"' ) === false );
			// Wave 78e: more diagnostic signals — what KIND of page is this?
			$out['has_yt_data']     = strpos( $body, 'ytInitialData' ) !== false;
			$out['has_ytcfg']       = strpos( $body, 'ytcfg' ) !== false;
			$out['watch_v_hits']    = preg_match_all( '#/watch\?v=[A-Za-z0-9_-]{11}#', $body, $junk );
			$out['shorts_hits']     = preg_match_all( '#/shorts/[A-Za-z0-9_-]{11}#', $body, $junk );
			$out['esc_vid_hits']    = preg_match_all( '/\\\\"videoId\\\\":\\\\"[A-Za-z0-9_-]{11}\\\\"/', $body, $junk );
			$out['title']           = (string) ( preg_match( '#<title>([^<]+)#', $body, $m ) ? trim( $m[1] ) : '' );
			// Look for any 11-char ID-like token preceded by characteristic context
			$out['rich_item_hits']  = substr_count( $body, '"richItemRenderer"' );
			$out['video_renderer']  = substr_count( $body, '"videoRenderer"' );
			$out['captcha']         = ( stripos( $body, 'captcha' ) !== false );
			$out['consent_page']    = ( stripos( $body, 'consent.youtube.com' ) !== false );
		}

		// Wave 87e — diagnose the new shorts-only paths separately so we
		// can see exactly why a channel is or isn't filling.
		$out['shorts_only_flag'] = (int) ( $scholar->shorts_only ?? 0 );

		// 1. /shorts page scrape
		if ( ! empty( $out['source_url'] ) ) {
			$shorts_url = preg_replace( '#/(shorts|videos|featured|streams|playlists|community|about)/?$#', '', $out['source_url'] );
			$shorts_url = rtrim( (string) $shorts_url, '/' ) . '/shorts?app=desktop&hl=en';
			$out['shorts_page_url'] = $shorts_url;
			$sh = wp_remote_get( $shorts_url, [
				'timeout' => 15, 'redirection' => 5,
				'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'headers' => [
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language' => 'en-GB,en;q=0.9',
					'Cookie' => 'CONSENT=YES+cb.20210328-17-p0.en+FX+999; SOCS=CAI; PREF=f6=4000000',
				],
			] );
			if ( is_wp_error( $sh ) ) {
				$out['shorts_page_status'] = 'wp_error: ' . $sh->get_error_message();
			} else {
				$body = (string) wp_remote_retrieve_body( $sh );
				$out['shorts_page_status']   = (string) wp_remote_retrieve_response_code( $sh );
				$out['shorts_page_body_len'] = strlen( $body );
				$out['shorts_page_vid_hits'] = preg_match_all( '/"videoId":"[A-Za-z0-9_-]{11}"/', $body, $junk );
				$out['shorts_page_mobile']   = ( strpos( $body, 'm.youtube.com' ) !== false ) && ( strpos( $body, '"videoId"' ) === false );
			}
		}

		// 2. YouTube Data API v3 path
		$api_key = (string) get_option( 'la_yt_api_key', '' );
		$out['api_v3_key_set'] = ( $api_key !== '' );

		// 2a. Wave 87f — authoritatively resolve the handle → channelId via
		// channels.list?forHandle. If this disagrees with the cached id, the
		// cached id is wrong (scrape picked up an unrelated UC* string).
		if ( $api_key !== '' && ! empty( $out['source_url'] ) ) {
			if ( preg_match( '#/@([A-Za-z0-9._-]+)#', $out['source_url'], $hm ) ) {
				$handle = $hm[1];
				$ch_url = add_query_arg( [
					'key' => $api_key, 'forHandle' => '@' . $handle, 'part' => 'id,snippet,statistics',
				], 'https://www.googleapis.com/youtube/v3/channels' );
				$cr = wp_remote_get( $ch_url, [ 'timeout' => 15 ] );
				if ( ! is_wp_error( $cr ) ) {
					$out['api_v3_channels_status'] = (string) wp_remote_retrieve_response_code( $cr );
					$cjson = json_decode( (string) wp_remote_retrieve_body( $cr ), true );
					if ( is_array( $cjson ) && ! empty( $cjson['items'][0] ) ) {
						$item = $cjson['items'][0];
						$out['api_v3_resolved_cid']     = (string) ( $item['id'] ?? '' );
						$out['api_v3_resolved_title']   = (string) ( $item['snippet']['title'] ?? '' );
						$out['api_v3_resolved_videos']  = (int)    ( $item['statistics']['videoCount'] ?? 0 );
						$out['api_v3_cid_matches']      = ( $out['api_v3_resolved_cid'] === $cid );
					} else {
						$out['api_v3_channels_error'] = isset( $cjson['error']['message'] ) ? substr( (string) $cjson['error']['message'], 0, 200 ) : 'no items returned';
					}
				}
			}
		}

		if ( $cid && $api_key !== '' ) {
			$search_url = add_query_arg( [
				'key' => $api_key, 'channelId' => $cid, 'part' => 'id',
				'type' => 'video', 'order' => 'date', 'maxResults' => 25,
			], 'https://www.googleapis.com/youtube/v3/search' );
			$ar = wp_remote_get( $search_url, [ 'timeout' => 15 ] );
			if ( is_wp_error( $ar ) ) {
				$out['api_v3_search_status'] = 'wp_error: ' . $ar->get_error_message();
			} else {
				$out['api_v3_search_status'] = (string) wp_remote_retrieve_response_code( $ar );
				$body = (string) wp_remote_retrieve_body( $ar );
				$json = json_decode( $body, true );
				$out['api_v3_search_items']  = is_array( $json ) ? count( $json['items'] ?? [] ) : 0;
				$out['api_v3_search_error']  = is_array( $json ) && isset( $json['error']['message'] ) ? substr( (string) $json['error']['message'], 0, 200 ) : '';
				// Count how many of those have duration ≤ 61s (i.e. Shorts)
				if ( ! empty( $json['items'] ) ) {
					$ids = [];
					foreach ( $json['items'] as $item ) {
						$vid = $item['id']['videoId'] ?? '';
						if ( $vid ) $ids[] = $vid;
					}
					if ( $ids ) {
						$videos_url = add_query_arg( [
							'key' => $api_key, 'id' => implode( ',', $ids ),
							'part' => 'contentDetails',
						], 'https://www.googleapis.com/youtube/v3/videos' );
						$vr = wp_remote_get( $videos_url, [ 'timeout' => 15 ] );
						if ( ! is_wp_error( $vr ) ) {
							$vjson = json_decode( (string) wp_remote_retrieve_body( $vr ), true );
							$shorts_count = 0;
							foreach ( ( $vjson['items'] ?? [] ) as $v ) {
								$dur = $v['contentDetails']['duration'] ?? '';
								$sec = self::iso8601_to_seconds( $dur );
								if ( $sec > 0 && $sec <= 61 ) $shorts_count++;
							}
							$out['api_v3_shorts_count'] = $shorts_count;
						}
					}
				}
			}
		}

		return $out;
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
