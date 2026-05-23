<?php
/**
 * Feed posts — curated Islamic content.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Feed {

	public static function recent( int $limit = 20, ?int $mosque_id = null ) : array {
		global $wpdb;
		$t = LA_DB::tables();

		if ( $mosque_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$t['feed_posts']}
				 WHERE ( mosque_id = %d OR mosque_id IS NULL )
				   AND ( expires_at IS NULL OR expires_at > NOW() )
				 ORDER BY ( mosque_id = %d ) DESC, published_at DESC
				 LIMIT %d",
				$mosque_id, $mosque_id, $limit
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t['feed_posts']}
			 WHERE ( expires_at IS NULL OR expires_at > NOW() )
			 ORDER BY published_at DESC
			 LIMIT %d",
			$limit
		) );
	}

	public static function get_by_id( int $id ) {
		global $wpdb;
		$t = LA_DB::tables();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t['feed_posts']} WHERE id = %d LIMIT 1",
			$id
		) );
	}

	public static function record_interaction( int $post_id, string $action, ?int $user_id, ?string $session_id ) : array {
		$valid = [ 'view', 'like', 'save', 'share', 'complete' ];
		if ( ! in_array( $action, $valid, true ) ) return [ 'ok' => false ];

		global $wpdb;
		$t = LA_DB::tables();
		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;
		if ( ! $val ) return [ 'ok' => false ];

		// Save + like are TOGGLES — second tap removes the row so the user
		// can unsave / unlike. view/share/complete always append (audit trail).
		$is_toggle = in_array( $action, [ 'save', 'like' ], true );

		if ( $is_toggle ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['feed_interactions']}
				 WHERE post_id = %d AND action = %s AND {$col} = %s
				 LIMIT 1",
				$post_id, $action, (string) $val
			) );
			if ( $existing ) {
				$wpdb->delete( $t['feed_interactions'], [ 'id' => $existing ] );
				if ( $action === 'like' ) {
					$wpdb->query( $wpdb->prepare(
						"UPDATE {$t['feed_posts']} SET likes_count = GREATEST(0, likes_count - 1) WHERE id = %d",
						$post_id
					) );
				} else {
					$wpdb->query( $wpdb->prepare(
						"UPDATE {$t['feed_posts']} SET saves_count = GREATEST(0, saves_count - 1) WHERE id = %d",
						$post_id
					) );
				}
				return [ 'ok' => true, 'active' => false ];
			}
			$wpdb->insert( $t['feed_interactions'], [
				'post_id' => $post_id,
				'action' => $action,
				'user_id' => $user_id,
				'session_id' => $session_id ?: null,
			] );
			if ( $action === 'like' ) {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$t['feed_posts']} SET likes_count = likes_count + 1 WHERE id = %d",
					$post_id
				) );
			} else {
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$t['feed_posts']} SET saves_count = saves_count + 1 WHERE id = %d",
					$post_id
				) );
			}
			return [ 'ok' => true, 'active' => true ];
		}

		// view, share, complete — append every time
		$wpdb->insert( $t['feed_interactions'], [
			'post_id' => $post_id,
			'action' => $action,
			'user_id' => $user_id,
			'session_id' => $session_id ?: null,
		] );
		if ( $action === 'share' ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$t['feed_posts']} SET shares_count = shares_count + 1 WHERE id = %d",
				$post_id
			) );
		}
		return [ 'ok' => true, 'active' => true ];
	}

	/**
	 * Bulk-fetch which post IDs an identity has saved/liked so the feed
	 * can render the correct is-active state on first paint instead of
	 * waiting for the client to read localStorage after hydration.
	 */
	public static function active_actions_for( ?int $user_id, ?string $session_id, array $post_ids, string $action = 'save' ) : array {
		if ( empty( $post_ids ) ) return [];
		global $wpdb;
		$t = LA_DB::tables();
		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;
		if ( ! $val ) return [];

		$ids = array_map( 'intval', $post_ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$t['feed_interactions']}
			 WHERE action = %s AND {$col} = %s AND post_id IN ( $placeholders )",
			array_merge( [ $action, (string) $val ], $ids )
		) );
		$map = [];
		foreach ( $rows as $id ) { $map[ (int) $id ] = true; }
		return $map;
	}
}
