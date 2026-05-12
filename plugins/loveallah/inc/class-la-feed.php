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

	public static function record_interaction( int $post_id, string $action, ?int $user_id, ?string $session_id ) : bool {
		$valid = [ 'view', 'like', 'save', 'share', 'complete' ];
		if ( ! in_array( $action, $valid, true ) ) return false;

		global $wpdb;
		$t = LA_DB::tables();
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
		} elseif ( $action === 'save' ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$t['feed_posts']} SET saves_count = saves_count + 1 WHERE id = %d",
				$post_id
			) );
		} elseif ( $action === 'share' ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$t['feed_posts']} SET shares_count = shares_count + 1 WHERE id = %d",
				$post_id
			) );
		}
		return true;
	}
}
