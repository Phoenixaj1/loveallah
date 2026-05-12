<?php
/**
 * YouTube ingestion via yt-dlp — Shorts only.
 *
 * Strategy:
 *   1. yt-dlp --flat-playlist  →  fast list of video IDs from /shorts tab
 *   2. For each NEW video (not in DB), yt-dlp single-video fetch  →  duration, upload_date, description
 *   3. Filter to videos ≤ 180s (so we never accidentally ingest long-form)
 *   4. Insert with full metadata, dedup by source URL
 *
 * Runs on a 6-hourly WP-Cron. After the first sync, subsequent runs
 * only do metadata fetches for newly-discovered IDs — cheap.
 *
 * Requires yt-dlp binary at /usr/local/bin/yt-dlp (production Dockerfile
 * should install Python 3 + yt-dlp).
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_YouTube {

	const YTDLP        = '/usr/local/bin/yt-dlp';
	const MAX_PER_SYNC = 15;          // Items to consider per scholar per run
	const TIMEOUT_SEC  = 30;

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

		// Try each tab in order until we get a list
		$list     = [];
		$used_tab = null;
		foreach ( $tabs as $tab ) {
			$url  = self::url_for_tab( $scholar->source_url, $tab );
			$list = self::flat_list( $url, self::MAX_PER_SYNC );
			if ( ! empty( $list ) ) { $used_tab = $tab; break; }
		}

		if ( empty( $list ) ) {
			$wpdb->update( $t['scholars'], [ 'last_synced_at' => current_time( 'mysql' ) ], [ 'id' => (int) $scholar->id ] );
			return [ 'inserted' => 0, 'reason' => 'no_content_tabs' ];
		}

		$inserted = 0;
		foreach ( $list as $v ) {
			$source_url = $used_tab === 'shorts'
				? "https://www.youtube.com/shorts/{$v['id']}"
				: "https://www.youtube.com/watch?v={$v['id']}";

			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['feed_posts']} WHERE original_source_url = %s LIMIT 1",
				$source_url
			) );
			if ( $exists ) continue;

			$meta = self::single_metadata( $v['id'] );
			if ( empty( $meta ) ) continue;
			// Portrait-only: reject landscape videos (Shorts format requirement)
			if ( ! self::is_portrait( $meta ) ) continue;
			if ( $meta['duration'] > 0 && $meta['duration'] > $max_dur ) continue;

			$published_at = self::parse_ytdlp_date( $meta['upload_date'] );

			$wpdb->insert( $t['feed_posts'], [
				'scholar_id'          => (int) $scholar->id,
				'type'                => $type,
				'title'               => mb_substr( $v['title'], 0, 250 ),
				'caption'             => self::trim_caption( $meta['description'] ),
				'video_url'           => "https://www.youtube.com/embed/{$v['id']}",
				'thumbnail_url'       => "https://i.ytimg.com/vi/{$v['id']}/maxresdefault.jpg",
				'original_source_url' => $source_url,
				'duration_sec'        => (int) $meta['duration'],
				'published_at'        => $published_at ?: gmdate( 'Y-m-d H:i:s' ),
			] );
			$inserted++;
		}

		$wpdb->update( $t['scholars'], [ 'last_synced_at' => current_time( 'mysql' ) ], [ 'id' => (int) $scholar->id ] );
		return [ 'inserted' => $inserted, 'fetched' => count( $list ), 'tab' => $used_tab ];
	}

	/** Build a tab URL (/shorts or /videos) from a channel source URL */
	private static function url_for_tab( ?string $source_url, string $tab ) : ?string {
		if ( empty( $source_url ) ) return null;
		$source_url = preg_replace( '#/(shorts|videos|featured|streams)/?$#', '', $source_url );
		return rtrim( $source_url, '/' ) . '/' . $tab;
	}

	/** Fast playlist listing: returns array of [id, title, view_count] */
	private static function flat_list( string $shorts_url, int $limit ) : array {
		$cmd = sprintf(
			'%s --flat-playlist --no-warnings --no-cache-dir --playlist-end %d --print "%%(id)s|||%%(title)s|||%%(view_count)s" %s 2>&1',
			escapeshellcmd( self::YTDLP ),
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
			escapeshellcmd( self::YTDLP ),
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

	/** Cron tick — runs every 6 hours */
	public static function cron_tick() : void {
		self::sync_all();
	}
}
