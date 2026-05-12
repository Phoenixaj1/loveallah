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

	/** Engagement weights for affinity scoring */
	const ENGAGEMENT_WEIGHTS = [
		'like'     => 25,
		'save'     => 35,
		'share'    => 40,
		'complete' => 15,
		'view'     => 2,
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
	public static function for_user( ?int $user_id, ?string $session_id, int $limit = 20, int $page = 0, ?string $type_filter = null ) : array {
		$affinities = self::scholar_affinities( $user_id, $session_id );
		$all        = self::ranked_content_full( $affinities, $type_filter );

		// Dhikr only mixed in on page 0 AND only when not filtering (filtered views = pure content)
		$dhikr = ( $page === 0 && empty( $type_filter ) ) ? self::today_remaining_dhikr( $user_id, $session_id ) : [];

		if ( empty( $all ) ) {
			return $dhikr; // dhikr-only feed if no content
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

		// Slice with wraparound
		$content = [];
		for ( $i = 0; $i < $limit; $i++ ) {
			$content[] = $all[ ( $offset + $i ) % $total ];
		}

		// Interleave dhikr (page 0 only) + signup cards (page 0, anonymous, not captured, no filter)
		$signups = ( $page === 0 && empty( $type_filter ) && self::should_show_signup( $user_id, $session_id ) )
			? self::SIGNUP_POSITIONS
			: [];

		if ( ! empty( $dhikr ) || ! empty( $signups ) ) {
			$feed = [];
			$content_idx = 0;
			$dhikr_idx   = 0;
			$total_positions = max( $limit + count( $signups ), count( $content ) + count( $dhikr ) + count( $signups ) );

			for ( $pos = 0; $pos < $total_positions; $pos++ ) {
				if ( in_array( $pos, $signups, true ) ) {
					$feed[] = self::signup_card_obj( $pos );
				} elseif ( in_array( $pos, self::DHIKR_POSITIONS, true ) && $dhikr_idx < count( $dhikr ) ) {
					$feed[] = $dhikr[ $dhikr_idx++ ];
				} elseif ( $content_idx < count( $content ) ) {
					$feed[] = $content[ $content_idx++ ];
				} elseif ( $dhikr_idx < count( $dhikr ) ) {
					$feed[] = $dhikr[ $dhikr_idx++ ];
				}
			}
			return $feed;
		}

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
	 * Scholar engagement scores for this user.
	 * Returns map: scholar_id => total_weight.
	 */
	private static function scholar_affinities( ?int $user_id, ?string $session_id ) : array {
		global $wpdb;
		$t = LA_DB::tables();
		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;
		if ( ! $val ) return [];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT fp.scholar_id, fi.action, COUNT(*) as n
			 FROM {$t['feed_interactions']} fi
			 JOIN {$t['feed_posts']} fp ON fp.id = fi.post_id
			 WHERE fi.{$col} = %s
			 GROUP BY fp.scholar_id, fi.action",
			(string) $val
		) );

		$scores = [];
		foreach ( $rows as $r ) {
			$w = self::ENGAGEMENT_WEIGHTS[ $r->action ] ?? 0;
			$scores[ (int) $r->scholar_id ] = ( $scores[ (int) $r->scholar_id ] ?? 0 ) + ( $w * (int) $r->n );
		}
		return $scores;
	}

	/**
	 * Full ranked content pool (no LIMIT) — drives the cycling pagination.
	 * For larger pools we'd add a cap, but with curated content (10s-100s of posts)
	 * we want all of them in scoring rotation.
	 */
	private static function ranked_content_full( array $affinities, ?string $type_filter = null ) : array {
		global $wpdb;
		$t = LA_DB::tables();

		$type_where = '';
		$type_args  = [];
		if ( ! empty( $type_filter ) ) {
			$allowed = [ 'short', 'reminder', 'nasheed', 'dhikr', 'mindfulness', 'qirat', 'lecture' ];
			if ( in_array( $type_filter, $allowed, true ) ) {
				$type_where = " AND p.type = %s";
				$type_args[] = $type_filter;
			}
		}

		// Prefer last 60 days of content
		$sql = "SELECT p.*,
				s.username as scholar_username,
				s.display_name as scholar_display_name,
				s.account_type as scholar_account_type,
				s.avatar as scholar_avatar,
				s.source_url as scholar_source_url
			 FROM {$t['feed_posts']} p
			 LEFT JOIN {$t['scholars']} s ON s.id = p.scholar_id
			 WHERE ( p.expires_at IS NULL OR p.expires_at > NOW() )
			   AND p.published_at >= DATE_SUB( NOW(), INTERVAL 60 DAY )
			   {$type_where}
			 ORDER BY p.published_at DESC";
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
				   {$type_where}
				 ORDER BY p.published_at DESC";
			$rows = $type_args ? $wpdb->get_results( $wpdb->prepare( $sql_all, $type_args ) ) : $wpdb->get_results( $sql_all );
		}

		foreach ( $rows as $r ) {
			$r->_card_type = 'content';
			$r->_score = self::score_post( $r, $affinities );
		}
		usort( $rows, function( $a, $b ) { return $b->_score <=> $a->_score; } );
		return $rows;
	}

	private static function score_post( $post, array $affinities ) : float {
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

		// Scholar affinity boost
		if ( ! empty( $post->scholar_id ) && isset( $affinities[ (int) $post->scholar_id ] ) ) {
			$score += min( 250, $affinities[ (int) $post->scholar_id ] );
		}
		// Tiny popularity nudge
		$score += min( 50, (int) ( $post->likes_count ?? 0 ) );
		return $score;
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
