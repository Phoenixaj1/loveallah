<?php
/**
 * Feed algorithm — scholar-affinity scoring + dhikr interleaving.
 *
 * Each user (identified by user_id or session_id) gets a personalised
 * feed. Content is scored by:
 *   - recency (newer = higher)
 *   - affinity: how often the user has engaged with that scholar
 *   - (future) cultural match: language / region tags
 *
 * Today's incomplete dhikr are interleaved at fixed positions so
 * remembrance happens organically as the user scrolls, but never
 * blocks the experience.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Algorithm {

	/** Positions in the feed where dhikr cards appear */
	const DHIKR_POSITIONS = [ 0, 4, 8, 12, 16 ];

	/** Positions where signup cards interrupt for non-captured anonymous users */
	const SIGNUP_POSITIONS = [ 7, 16 ];

	/** Engagement weights for affinity scoring.
	 *
	 * Wave 29: 'view' dropped from 2 → 0. The old weight quietly turned every
	 * scroll-past into +2 affinity for that scholar (×3 within 24h = +6 per
	 * view). Binge-scroll 30 reels from one channel and the affinity would
	 * pull the next visit straight back to that same channel — exactly the
	 * "same content twice" loop we're trying to break. Affinity should only
	 * grow from active intent (like, save, share, complete), not from passive
	 * scrolling. Views are still recorded; they drive the seen-tracking and
	 * binge-exclusion, just no longer the affinity score.
	 */
	const ENGAGEMENT_WEIGHTS = [
		'like'     => 25,
		'save'     => 35,
		'share'    => 40,
		'complete' => 15,
		'view'     => 0,
	];

	/**
	 * Build the personalised feed for a given page.
	 * Returns an array of card objects, each with a _card_type field.
	 *
	 * Page 0 is the initial render (dhikr cards interleaved).
	 * Pages 1+ are infinite-scroll batches (content only, wraps around the
	 * content pool with a deterministic per-cycle shuffle so re-encounters
	 * feel fresh rather than identical).
	 */
	public static function for_user( ?int $user_id, ?string $session_id, int $limit = 20, int $page = 0, ?string $type_filter = null, array $extra_seen_ids = [] ) : array {
		$affinities = self::scholar_affinities( $user_id, $session_id );

		// Tiered seen-tracking (Wave 29). "Saw this already" is the #1 reason
		// users close the app, so we treat repeat-views as the strongest
		// negative signal:
		//   $seen_once = 1-2 views in 30d → -800 penalty applied in score_post()
		//   $binged    = 3+ views in 30d  → HARD excluded from the SQL pool
		//                                   (with cold-start fallback below).
		[ $seen_once, $binged ] = self::seen_tiered( $user_id, $session_id );

		// Wave 77: merge client-supplied seen ids (from localStorage backstop)
		// into the once-seen map. These are ids the client knows it has shown
		// even if the server-side identity has rolled and has no DB record.
		if ( $extra_seen_ids ) {
			foreach ( $extra_seen_ids as $eid ) {
				$pid = (int) $eid;
				if ( $pid > 0 ) $seen_once[ $pid ] = true;
			}
		}
		$all = self::ranked_content_full( $affinities, $seen_once, $binged, $user_id, $session_id, $page, $type_filter );

		// Wave 77 / 81: tiered cold-start fallback. The first pass excludes
		// ANY post seen in the last 90 days (Wave 81 widened the window
		// from 30 → 90 days at user request — top creators stick around
		// longer so a casual viewer shouldn't see Menk's Friday clip again
		// for three months). If that drains the pool below one page, soften:
		// still exclude binged (≥3 views) but allow once-seen back. If THAT
		// still leaves us short, drop all exclusion — we'd rather repeat
		// than ship empty.
		if ( count( $all ) < $limit ) {
			$all = self::ranked_content_full( $affinities, [], $binged, $user_id, $session_id, $page, $type_filter );
		}
		if ( count( $all ) < $limit ) {
			$all = self::ranked_content_full( $affinities, [], [], $user_id, $session_id, $page, $type_filter );
		}

		// Wave 66: dhikr cards removed from the main feed. Dhikr lives
		// on its own /dhikr/ page now (Solitude / Witness / Pulse /
		// Names). Mixing it into the scroll-feed was friction that
		// users didn't want.
		$dhikr = [];

		if ( empty( $all ) ) {
			return []; // empty feed if no content (no dhikr fallback)
		}

		$total = count( $all );
		$cycle = intdiv( $page * $limit, $total );
		$offset = ( $page * $limit ) % $total;

		// Per-cycle shuffle for variety on loops (deterministic by cycle + user)
		if ( $cycle > 0 ) {
			$seed_key = $user_id ? "u{$user_id}" : ( $session_id ?: 'anon' );
			$seed = crc32( $seed_key . '|' . $cycle );
			mt_srand( $seed );
			shuffle( $all );
		}

		// Slice — but for filtered views with a tiny pool, return UNIQUE
		// posts only (no wraparound duplication). 20 copies of the same
		// clip is worse than 1 clip; the empty-state UI is better than
		// "spam this video at me 20 times". Unfiltered feed still wraps
		// because the main feed pool is always large enough that
		// wraparound is real cycling, not pathological duplication.
		$content = [];
		if ( ! empty( $type_filter ) && $total < $limit ) {
			// Tiny filtered pool — return whatever unique posts we have,
			// no padding. Caller decides whether to show empty state.
			$content = array_slice( $all, $offset );
			if ( count( $content ) < $total ) {
				$content = array_merge( $content, array_slice( $all, 0, $total - count( $content ) ) );
			}
		} else {
			for ( $i = 0; $i < $limit; $i++ ) {
				$content[] = $all[ ( $offset + $i ) % $total ];
			}
		}

		// Diversity pass — no two adjacent posts from the same scholar.
		// Keeps the feed feeling varied even when one channel has many videos
		// at the top of the score list (e.g. a recently-ingested batch).
		$content = self::diversify_by_scholar( $content );

		// Wave 82: type-balance pass. User feedback after the catalogue grew
		// to 4,600+ posts: qirat (Quran recitations) was flooding the feed
		// because reciter channels carry the deepest catalogues AND we have
		// many qaris seeded. Round-robin draw against a target type rotation
		// surfaces lectures + reminders sooner so the feed doesn't feel
		// one-note. Skipped when the user has filtered to a single type
		// (the /qirat, /lecture, etc. tab pages) — that's an explicit
		// user choice we shouldn't override.
		if ( empty( $type_filter ) ) {
			$content = self::diversify_by_type( $content );
		}

		// Wave 66: dhikr + signup card interruptions removed. Main feed
		// is pure content now — dhikr has its own tab, identity uses
		// the sign-in chip in the header instead of a mid-feed gate.
		return $content;
	}

	/** Whether to show signup cards in the feed for this identity */
	private static function should_show_signup( ?int $user_id, ?string $session_id ) : bool {
		// Logged-in WordPress users are already known
		if ( $user_id ) return false;
		if ( ! $session_id ) return true; // anonymous with no session — pitch it
		global $wpdb;
		$t = LA_DB::tables();
		$captured = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t['email_captures']} WHERE session_id = %s LIMIT 1",
			$session_id
		) );
		return $captured === 0;
	}

	/** Synthetic card object with copy that varies by position */
	private static function signup_card_obj( int $pos ) : object {
		$variants = [
			7  => [ 'overline' => 'Stay close to Allah', 'title' => 'Get the weekly remembrance', 'body' => 'A short dose of dhikr, qira\'at and reminders — straight to your inbox every Friday.' ],
			16 => [ 'overline' => "You're loving this", 'title' => 'Make it yours',                'body' => 'Sign up so we save your favourites and your remembrance streak — and so your masjid knows you\'re here.' ],
		];
		$v = $variants[ $pos ] ?? $variants[7];
		return (object) [
			'_card_type' => 'signup',
			'_position'  => $pos,
			'overline'   => $v['overline'],
			'title'      => $v['title'],
			'body'       => $v['body'],
		];
	}

	/**
	 * Scholar engagement scores for this user, time-decayed.
	 * Returns map: scholar_id => total_weight.
	 *
	 * Recent engagements count MORE than old ones so a like today actually
	 * reshapes the next feed pull rather than getting drowned out by
	 * historical noise:
	 *   ≤ 24h  → 3× multiplier (your current mood matters most)
	 *   ≤ 7d   → 2× multiplier
	 *   ≤ 30d  → 1× multiplier
	 *   ≤ 60d  → 0.5× multiplier
	 *   > 60d  → not counted (filtered out at SQL level)
	 *
	 * Works identically for logged-in users (user_id) and anon visitors
	 * (session_id) — same query, different identity column.
	 */
	private static function scholar_affinities( ?int $user_id, ?string $session_id ) : array {
		global $wpdb;
		$t = LA_DB::tables();
		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;
		if ( ! $val ) return [];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT fp.scholar_id, fi.action,
			        TIMESTAMPDIFF(HOUR, fi.occurred_at, NOW()) as age_h
			 FROM {$t['feed_interactions']} fi
			 JOIN {$t['feed_posts']} fp ON fp.id = fi.post_id
			 WHERE fi.{$col} = %s
			   AND fi.occurred_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)",
			(string) $val
		) );

		$scores = [];
		foreach ( $rows as $r ) {
			$w = self::ENGAGEMENT_WEIGHTS[ $r->action ] ?? 0;
			$age_h = (int) $r->age_h;
			if ( $age_h <= 24 )       $mul = 3.0;
			elseif ( $age_h <= 168 )  $mul = 2.0;  // 7 days
			elseif ( $age_h <= 720 )  $mul = 1.0;  // 30 days
			else                      $mul = 0.5;  // 30-60 days
			$scores[ (int) $r->scholar_id ] = ( $scores[ (int) $r->scholar_id ] ?? 0 ) + ( $w * $mul );
		}
		return $scores;
	}

	/**
	 * Full ranked content pool (no LIMIT) — drives the cycling pagination.
	 * For larger pools we'd add a cap, but with curated content (10s-100s of posts)
	 * we want all of them in scoring rotation.
	 */
	private static function ranked_content_full( array $affinities, array $seen_ids, array $binged_ids, ?int $user_id, ?string $session_id, int $page, ?string $type_filter = null ) : array {
		global $wpdb;
		$t = LA_DB::tables();

		$type_where = '';
		$type_args  = [];
		if ( ! empty( $type_filter ) ) {
			// 'nasheed' deliberately omitted — feed is scholars + qaris only
			$allowed = [ 'short', 'reminder', 'dhikr', 'mindfulness', 'qirat', 'lecture' ];
			if ( $type_filter === 'dhikr' ) {
				// Wave 47 — robust dhikr filter that works even if production
				// has mistagged legacy data.
				//
				// Two conditions can include a post:
				//   A) Post AND scholar are BOTH currently tagged dhikr.
				//      The double-check defends against legacy posts where
				//      p.type='dhikr' but the scholar was later re-typed to
				//      qirat (Alafasy's old misharyrashed posts, etc.) The
				//      scholar's current default_content_type is the source
				//      of truth.
				//   B) The post's title contains a dhikr keyword. This catches
				//      dhikr content from any channel regardless of how its
				//      scholar is typed — Mufti Menk's "Subhanallah Loop"
				//      surfaces here even though Menk's channel is 'reminder'.
				//
				// REGEXP is case-insensitive on default utf8 collation. The
				// alternation list is conservative (high-precision phrases
				// only) so we don't pull lectures that merely mention "dhikr"
				// in passing.
				$type_where = " AND (
				                  ( p.type = 'dhikr' AND s.default_content_type = 'dhikr' )
				                  OR p.title REGEXP '(dhikr|tasbih|tasbeeh|la[[:space:]]+ilaha[[:space:]]+illa|illallah|subhanallah|subhān|salawat|durood|durūd|kalimah|halaqa|ya[[:space:]]+hayyu[[:space:]]+ya[[:space:]]+qayyum)'
				               )";
				// No bound params — phrase list is hardcoded above.
			} elseif ( in_array( $type_filter, $allowed, true ) ) {
				$type_where = " AND p.type = %s";
				$type_args[] = $type_filter;
			}
		}

		// Wave 77 / 81: HARD-exclude ANY post the user has seen in the last
		// 90 days (Wave 81 widened the seen window from 30 → 90 days at
		// user request). With 4,600+ videos in the catalog and content
		// arriving daily, repeats are unacceptable — the −800 penalty alone
		// left low-quality unseen content losing to a seen favourite.
		// Merge once-seen + binged IDs into the SQL NOT IN list.
		// The tiered fallback in for_user() re-includes seen content if
		// the strict exclusion leaves us with nothing to show.
		$all_seen_ids = array_unique( array_merge(
			array_map( 'intval', array_keys( (array) $seen_ids ) ),
			array_map( 'intval', array_keys( (array) $binged_ids ) )
		) );
		$seen_where = '';
		if ( $all_seen_ids ) {
			$seen_where = ' AND p.id NOT IN (' . implode( ',', $all_seen_ids ) . ')';
		}
		// Keep the old var name available in case any downstream code references it.
		$binged_where = $seen_where;

		// Wave 71 hotfix: filter out hidden/archived scholars ONLY IF the
		// status column exists. The dbDelta migration may not have applied
		// yet on production (opcache + breeze cache) — without this check
		// the SQL silently returns empty if the column is missing.
		$status_where = '';
		$has_status_col = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = %s
			   AND COLUMN_NAME = 'status'",
			$t['scholars']
		) );
		if ( $has_status_col ) {
			$status_where = " AND ( s.status IS NULL OR s.status = '' OR s.status = 'active' )";
		}

		// Prefer last 60 days BY EITHER PUBLICATION OR INGESTION.
		// For the visual /videos ingest path, published_at carries the real
		// YouTube upload date (potentially years old). Without OR-ing in
		// created_at, freshly-ingested long-form lectures would be silently
		// filtered out of the feed even though we just pulled them.
		$sql = "SELECT p.*,
				s.username as scholar_username,
				s.display_name as scholar_display_name,
				s.account_type as scholar_account_type,
				s.avatar as scholar_avatar,
				s.source_url as scholar_source_url
			 FROM {$t['feed_posts']} p
			 LEFT JOIN {$t['scholars']} s ON s.id = p.scholar_id
			 WHERE ( p.expires_at IS NULL OR p.expires_at > NOW() )
			   AND (
			        p.published_at >= DATE_SUB( NOW(), INTERVAL 60 DAY )
			        OR p.created_at >= DATE_SUB( NOW(), INTERVAL 60 DAY )
			   )
			   {$status_where}
			   {$binged_where}
			   {$type_where}
			 ORDER BY GREATEST(p.published_at, p.created_at) DESC";
		$rows = $type_args ? $wpdb->get_results( $wpdb->prepare( $sql, $type_args ) ) : $wpdb->get_results( $sql );

		// Fallback to all if no recent content (cold start)
		if ( empty( $rows ) ) {
			$sql_all = "SELECT p.*,
					s.username as scholar_username,
					s.display_name as scholar_display_name,
					s.account_type as scholar_account_type,
					s.avatar as scholar_avatar,
					s.source_url as scholar_source_url
				 FROM {$t['feed_posts']} p
				 LEFT JOIN {$t['scholars']} s ON s.id = p.scholar_id
				 WHERE ( p.expires_at IS NULL OR p.expires_at > NOW() )
				   {$status_where}
				   {$binged_where}
				   {$type_where}
				 ORDER BY p.published_at DESC";
			$rows = $type_args ? $wpdb->get_results( $wpdb->prepare( $sql_all, $type_args ) ) : $wpdb->get_results( $sql_all );
		}

		// Build stable per-(session+page) jitter — same user sees same order
		// during one session, but different users / different pages get a
		// fresh shuffle. Without this, identical scores cluster posts from
		// the same scholar at the top of the feed.
		$seed_key = ( $user_id ? "u{$user_id}" : ( $session_id ?: 'anon' ) ) . '|p' . $page;
		$seed = abs( crc32( $seed_key ) );

		// Wave 31: compute per-post quality scores from collective skip/engage/
		// like/save/share signals across all users. The single biggest signal
		// of "this content is good" vs "this content is shit" is whether
		// viewers watch past the hook or scroll away within the first 10%.
		$quality = self::post_quality_scores( wp_list_pluck( $rows, 'id' ) );

		foreach ( $rows as $r ) {
			$r->_card_type = 'content';
			$r->_quality = $quality[ (int) $r->id ] ?? 0;
			$r->_score = self::score_post( $r, $affinities, $seen_ids, $seed );
		}
		usort( $rows, function( $a, $b ) { return $b->_score <=> $a->_score; } );

		// Wave 87j: per-scholar slice cap.
		//
		// After scoring + sorting, walk the ranked list once and keep at most
		// PER_SCHOLAR_CAP posts per scholar in the "primary" tier. The rest
		// get pushed to a "secondary" tier appended at the end.
		//
		// Why: a single deep-import (e.g. Mufti Menk 15 → 48 videos in 5 min)
		// can dump 30+ posts all scoring within ±10 of each other at the very
		// top of the ranked list. Pure ranking ⊕ round-robin diversify still
		// produces "Menk, X, Menk, Y, Menk, Menk, Menk, Menk, …" once the
		// other-scholar buckets drain. Capping at the source — the ranked
		// pool itself — means the top 100 posts will always be drawn from
		// ≥ ceil(100/CAP) different scholars.
		//
		// Cap of 4 means a 20-post page is drawn from at least 5 scholars,
		// and a 50-post page from at least ~13 scholars. The cap is per-pull,
		// not per-account: tomorrow the secondary tier rotates into primary as
		// today's top posts drop out via the seen-exclusion window.
		$PER_SCHOLAR_CAP = 4;
		$primary   = [];
		$secondary = [];
		$by_scholar = [];
		foreach ( $rows as $r ) {
			$sid = (int) ( $r->scholar_id ?? 0 );
			$by_scholar[ $sid ] = ( $by_scholar[ $sid ] ?? 0 ) + 1;
			if ( $by_scholar[ $sid ] <= $PER_SCHOLAR_CAP ) {
				$primary[] = $r;
			} else {
				$secondary[] = $r;
			}
		}
		// Primary tier stays in score order; secondary tier appended after.
		// Caller's slice picks from primary first, falls through to secondary
		// only when the cap-limited pool is exhausted.
		return array_merge( $primary, $secondary );
	}

	/**
	 * Per-post crowdsourced quality score, computed from the interactions
	 * the whole user base has logged against each post.
	 *
	 *   quality = engagement_lift − skip_drag
	 *   engagement_lift = positive_actions / unique_viewers   × 200  (cap +200)
	 *   skip_drag       = unique_skippers   / unique_viewers   × 300  (cap −300)
	 *
	 * positive_actions = engage + complete + like + save + share
	 * (engage = "card stayed in view past 30% of duration", from client)
	 * (skip   = "card left view before 10% of duration",    from client)
	 *
	 * Posts with < 10 unique viewers are treated as quality-neutral (0)
	 * to avoid penalising fresh ingest before it has data.
	 *
	 * Returns map [ post_id => float_score ] only for posts with data.
	 */
	private static function post_quality_scores( array $post_ids ) : array {
		if ( empty( $post_ids ) ) return [];
		global $wpdb;
		$t = LA_DB::tables();
		$ids = array_map( 'intval', $post_ids );
		$in  = implode( ',', $ids );

		$rows = $wpdb->get_results(
			"SELECT post_id,
				COUNT(DISTINCT CASE WHEN action = 'view'     THEN COALESCE(user_id, session_id) END) AS viewers,
				COUNT(DISTINCT CASE WHEN action = 'skip'     THEN COALESCE(user_id, session_id) END) AS skippers,
				COUNT(DISTINCT CASE WHEN action = 'engage'   THEN COALESCE(user_id, session_id) END) AS engagers,
				COUNT(DISTINCT CASE WHEN action = 'complete' THEN COALESCE(user_id, session_id) END) AS completers,
				COUNT(DISTINCT CASE WHEN action = 'like'     THEN COALESCE(user_id, session_id) END) AS likers,
				COUNT(DISTINCT CASE WHEN action = 'save'     THEN COALESCE(user_id, session_id) END) AS savers,
				COUNT(DISTINCT CASE WHEN action = 'share'    THEN COALESCE(user_id, session_id) END) AS sharers
			 FROM {$t['feed_interactions']}
			 WHERE post_id IN ({$in})
			   AND occurred_at >= DATE_SUB( NOW(), INTERVAL 90 DAY )
			 GROUP BY post_id"
		);

		$out = [];
		foreach ( $rows as $r ) {
			$viewers = max( 0, (int) $r->viewers );
			if ( $viewers < 10 ) {
				// Cold start: not enough signal yet. Leave at quality-neutral.
				$out[ (int) $r->post_id ] = 0.0;
				continue;
			}
			$skip_rate    = $r->skippers / $viewers;
			$positives    = (int) $r->engagers + (int) $r->completers + (int) $r->likers + (int) $r->savers + (int) $r->sharers;
			$engage_rate  = $positives / $viewers;
			$engagement_lift = min( 1.0, $engage_rate ) * 200;
			$skip_drag       = min( 1.0, $skip_rate )   * 300;
			$out[ (int) $r->post_id ] = $engagement_lift - $skip_drag;
		}
		return $out;
	}

	private static function score_post( $post, array $affinities, array $seen_ids, int $seed ) : float {
		$score = 1000.0;
		$age_days = ( time() - strtotime( $post->published_at ) ) / 86400;

		// EXPONENTIAL recency decay — fresh wins big
		//  0-3 days: full score
		//  4-14 days: linear -15/day (so 14d = -165)
		//  15-30 days: linear -30/day  (compounding to push older down)
		//  30+ days: -1000 floor (effectively buried)
		if ( $age_days <= 3 ) {
			// no penalty
		} elseif ( $age_days <= 14 ) {
			$score -= ( $age_days - 3 ) * 15;
		} elseif ( $age_days <= 30 ) {
			$score -= ( 11 * 15 ) + ( $age_days - 14 ) * 30;
		} else {
			$score -= 1000; // archived — only surfaces if nothing else
		}

		// INGESTION freshness boost — separate from published_at because /videos
		// inserts carry the real YouTube upload date (could be years old), while
		// `created_at` is WHEN we pulled it into our pool. This lifts brand-new
		// arrivals from the hourly cron so the feed feels alive on every visit
		// even when the underlying scholar uploaded the video long ago.
		//
		// Wave 87j: dropped magnitudes from +250/+200/+80 → +80/+50/+20 because
		// a single deep-import (Mufti Menk: 48 videos created in ~5 min) used to
		// flood the entire top of the ranked pool — the freshness boost dwarfed
		// every other signal and the diversify_by_scholar round-robin couldn't
		// recover when 45/50 top-ranked posts were the same scholar. The new
		// magnitudes still beat a same-day repeat, but no longer monopolise.
		// The per-scholar slice cap below is the structural fix; this is the
		// scoring complement that prevents a single ingestion event from
		// reshaping the entire score ordering.
		//   ≤ 1h   → +80
		//   ≤ 24h  → +50
		//   ≤ 7d   → +20
		//   > 7d   → no boost
		if ( ! empty( $post->created_at ) ) {
			$age_h_ingest = ( time() - strtotime( $post->created_at ) ) / 3600;
			if ( $age_h_ingest <= 1 )       $score += 80;
			elseif ( $age_h_ingest <= 24 )  $score += 50;
			elseif ( $age_h_ingest <= 168 ) $score += 20;
		}

		// Scholar affinity boost: rewards what this user has engaged with
		// (likes, saves, shares, completions). The cap (was 250, now 450)
		// is higher because affinities are now time-decayed — a like today
		// gives ~75 boost (25 × 3× recency), so binge-engaging with one
		// scholar still lets them surface 3-5 videos worth of content
		// without monopolising the feed entirely.
		if ( ! empty( $post->scholar_id ) && isset( $affinities[ (int) $post->scholar_id ] ) ) {
			$score += min( 450, $affinities[ (int) $post->scholar_id ] );
		}

		// Already-seen penalty (Wave 29 strengthened −400 → −800).
		//
		// The old −400 could be beaten by a strong scholar affinity (up to
		// +450), which meant a binge-watched favourite kept resurfacing the
		// same videos. −800 puts seen content firmly below any unseen
		// alternative from the same channel, while still letting it appear
		// before truly-stale (>30d) content as a last-resort.
		if ( isset( $seen_ids[ (int) $post->id ] ) ) {
			$score -= 800;
		}

		// Popularity nudge
		$score += min( 50, (int) ( $post->likes_count ?? 0 ) );

		// Wave 31: crowdsourced quality (skip rate vs engagement rate).
		// This is the strongest "good content vs shit content" signal we
		// have. Range: roughly −300 (universally skipped) to +200 (consistently
		// watched past the hook). Pre-computed in post_quality_scores() and
		// stamped onto each row as _quality before scoring.
		$score += (float) ( $post->_quality ?? 0 );

		// Deterministic per-session jitter [-30, +30] — breaks ties so equal-
		// score posts shuffle predictably per user per page, no two visits
		// land on the same Qalam-Qalam-Qalam ordering.
		$jitter = ( ( $seed ^ ( (int) $post->id * 2654435761 ) ) % 61 ) - 30;
		$score += $jitter;

		return $score;
	}

	/**
	 * Round-robin scholars across the slice so the feed never shows two
	 * adjacent posts from the same channel. Preserves overall ranking
	 * order — just spreads same-scholar runs apart.
	 */
	private static function diversify_by_scholar( array $content ) : array {
		if ( count( $content ) <= 2 ) return $content;

		// Group by scholar in original order
		$buckets = [];
		foreach ( $content as $c ) {
			$sid = (int) ( $c->scholar_id ?? 0 );
			$buckets[ $sid ][] = $c;
		}
		// One scholar in the result? Nothing to interleave.
		if ( count( $buckets ) === 1 ) return $content;

		// Round-robin: take one from each non-empty bucket, looping until
		// all are drained. This guarantees no two adjacent posts from the
		// same scholar unless one scholar has more than half the total.
		$result = [];
		$total  = count( $content );
		while ( count( $result ) < $total ) {
			$any_pushed = false;
			foreach ( $buckets as $sid => &$bucket ) {
				if ( ! empty( $bucket ) ) {
					// Avoid duplicating the last pushed scholar back-to-back.
					$last_sid = empty( $result ) ? null : ( (int) ( end( $result )->scholar_id ?? 0 ) );
					if ( $last_sid === $sid && count( array_filter( $buckets, function( $b ) { return ! empty( $b ); } ) ) > 1 ) {
						continue;
					}
					$result[] = array_shift( $bucket );
					$any_pushed = true;
				}
			}
			unset( $bucket );
			// Failsafe: if we couldn't push anything (all remaining same scholar),
			// drain them rather than infinite loop.
			if ( ! $any_pushed ) {
				foreach ( $buckets as $sid => &$bucket ) {
					while ( ! empty( $bucket ) ) {
						$result[] = array_shift( $bucket );
					}
				}
				unset( $bucket );
				break;
			}
		}
		return $result;
	}

	/**
	 * Wave 82: Type-balance pass. The catalogue is qirat-heavy (deep
	 * reciter back-catalogues + many qaris seeded), so the score-sorted
	 * pool tends to surface 6-7 qirats per 10 cards. Users want a healthy
	 * mix of voices: a reminder, a recitation, a lecture, a nasheed —
	 * not 10 surahs in a row.
	 *
	 * Approach: bucket by `type`, then round-robin draw against a target
	 * rotation pattern that biases toward under-represented types. Each
	 * draw takes the highest-scoring item from the chosen bucket (the
	 * input is already score-sorted, so the top of each bucket IS the
	 * best item of that type). When a bucket runs dry, the rotation
	 * skips its slot and tries the next preferred type instead — so
	 * we never insert filler for diversity's sake.
	 *
	 * Rotation pattern is weighted to give:
	 *   - reminder & lecture two slots each (compensating for their
	 *     smaller per-channel catalogues)
	 *   - qirat two slots (still well-represented but not dominant)
	 *   - nasheed, mindfulness, dhikr one slot each
	 * That's 9 slots per cycle ≈ 22% reminder, 22% qirat, 22% lecture,
	 * 11% each of nasheed/mindfulness/dhikr. Feels like a balanced
	 * Islamic content feed rather than a Quran radio station.
	 */
	private static function diversify_by_type( array $content ) : array {
		if ( count( $content ) <= 3 ) return $content;

		$buckets = [];
		foreach ( $content as $c ) {
			$type = (string) ( $c->type ?? 'unknown' );
			$buckets[ $type ][] = $c;
		}
		if ( count( $buckets ) <= 1 ) return $content;

		$rotation = [ 'reminder', 'qirat', 'lecture', 'reminder', 'qirat', 'lecture', 'nasheed', 'mindfulness', 'dhikr' ];
		$rot_len  = count( $rotation );

		$result = [];
		$total  = count( $content );
		$rot_i  = 0;
		$safety = 0;
		while ( count( $result ) < $total && $safety++ < $total * 4 ) {
			$picked = false;
			// Search the rotation for the next type that still has content.
			for ( $tries = 0; $tries < $rot_len; $tries++ ) {
				$want = $rotation[ ( $rot_i + $tries ) % $rot_len ];
				if ( ! empty( $buckets[ $want ] ) ) {
					$result[] = array_shift( $buckets[ $want ] );
					$rot_i    = ( $rot_i + $tries + 1 ) % $rot_len;
					$picked   = true;
					break;
				}
			}
			// Rotation drained — drain any remaining buckets (untracked types).
			if ( ! $picked ) {
				foreach ( $buckets as $type => &$bucket ) {
					while ( ! empty( $bucket ) ) {
						$result[] = array_shift( $bucket );
					}
				}
				break;
			}
		}
		return $result;
	}

	/**
	 * Posts this identity has seen in the last N interactions.
	 * Kept for back-compat / legacy callers. Returns the 1-or-more-views set.
	 */
	private static function recently_seen_post_ids( ?int $user_id, ?string $session_id, int $limit = 60 ) : array {
		[ $once, $_binged ] = self::seen_tiered( $user_id, $session_id );
		return $once;
	}

	/**
	 * Tiered seen-tracking. "Seeing the same thing twice" is the #1 reason
	 * users close the app and switch to another, so we treat repeat-views
	 * as the strongest negative signal in the algorithm.
	 *
	 * Wave 81: window widened 30 → 90 days. Wave 77 already hard-excluded
	 * any seen post (was −800 penalty). The 90-day window means a user who
	 * watches a Mufti Menk clip in March doesn't see it again until June,
	 * even if their interaction history is sparse in between. Pairs with
	 * the localStorage 500-id ring buffer that survives cookie clears.
	 *
	 *   seen_once = posts viewed 1-2 times in last 90 days → HARD excluded
	 *   binged    = posts viewed 3+ times in last 90 days  → HARD excluded
	 *
	 * Both buckets feed into the SQL NOT IN list now (Wave 77 algorithm
	 * change), so the once/binged distinction only affects the tiered
	 * cold-start fallback in for_user().
	 *
	 * Returns [ once_map, binged_map ] both keyed by post_id for O(1) lookup.
	 */
	private static function seen_tiered( ?int $user_id, ?string $session_id ) : array {
		global $wpdb;
		$t = LA_DB::tables();
		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;
		if ( ! $val ) return [ [], [] ];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, COUNT(*) AS views
			 FROM {$t['feed_interactions']}
			 WHERE {$col} = %s
			   AND occurred_at >= DATE_SUB( NOW(), INTERVAL 90 DAY )
			 GROUP BY post_id
			 ORDER BY views DESC, MAX(id) DESC
			 LIMIT 5000",
			(string) $val
		) );

		$once   = [];
		$binged = [];
		foreach ( $rows as $r ) {
			$pid = (int) $r->post_id;
			if ( (int) $r->views >= 3 ) {
				$binged[ $pid ] = true;
			} else {
				$once[ $pid ] = true;
			}
		}
		return [ $once, $binged ];
	}

	/**
	 * Today's dhikr that this user hasn't completed yet.
	 * Returns synthetic card objects.
	 */
	private static function today_remaining_dhikr( ?int $user_id, ?string $session_id ) : array {
		if ( ! class_exists( 'LA_Unlock' ) || ! class_exists( 'LA_Content' ) ) return [];

		$state     = LA_Unlock::today_state( $user_id, $session_id );
		$completed = (int) $state->dhikr_completed;
		$all       = LA_Content::today_dhikr();
		$remaining = array_slice( $all, $completed );

		$cards = [];
		$index = $completed;
		foreach ( $remaining as $d ) {
			$cards[] = (object) [
				'_card_type'   => 'dhikr',
				'_dhikr_index' => $index,
				'dhikr'        => $d,
				'step'         => $index + 1,
				'total'        => LA_Unlock::REQUIRED_DHIKR,
			];
			$index++;
		}
		return $cards;
	}
}
