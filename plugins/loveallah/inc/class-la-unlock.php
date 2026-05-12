<?php
/**
 * Daily dhikr unlock state — gates feed access.
 *
 * Feed unlocks once user completes all 5 dhikr for today.
 * Resets at Fajr (handled client-side via UTC date comparison).
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Unlock {

	const REQUIRED_DHIKR = 5;

	public static function today_state( ?int $user_id, ?string $session_id ) : object {
		global $wpdb;
		$t = LA_DB::tables();
		$today = current_time( 'Y-m-d' );

		$row = null;
		if ( $user_id ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$t['unlock_state']} WHERE user_id = %d AND date = %s LIMIT 1",
				$user_id, $today
			) );
		} elseif ( $session_id ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$t['unlock_state']} WHERE session_id = %s AND date = %s LIMIT 1",
				$session_id, $today
			) );
		}

		if ( $row ) return $row;

		return (object) [
			'dhikr_completed' => 0,
			'unlocked_at'     => null,
			'date'            => $today,
		];
	}

	public static function complete_dhikr( ?int $user_id, ?string $session_id ) : object {
		global $wpdb;
		$t = LA_DB::tables();
		$today = current_time( 'Y-m-d' );

		$state = self::today_state( $user_id, $session_id );
		$new_count = min( self::REQUIRED_DHIKR, (int) $state->dhikr_completed + 1 );
		$unlocked = $new_count >= self::REQUIRED_DHIKR;
		$unlocked_at = $unlocked ? current_time( 'mysql' ) : ( $state->unlocked_at ?? null );

		if ( $state->dhikr_completed === 0 && empty( $state->id ) ) {
			$wpdb->insert( $t['unlock_state'], [
				'user_id' => $user_id,
				'session_id' => $session_id ?: null,
				'date' => $today,
				'dhikr_completed' => $new_count,
				'unlocked_at' => $unlocked_at,
			] );
		} else {
			$where = $user_id
				? [ 'user_id' => $user_id, 'date' => $today ]
				: [ 'session_id' => $session_id, 'date' => $today ];
			$wpdb->update( $t['unlock_state'], [
				'dhikr_completed' => $new_count,
				'unlocked_at' => $unlocked_at,
			], $where );
		}

		return (object) [
			'dhikr_completed' => $new_count,
			'required' => self::REQUIRED_DHIKR,
			'unlocked' => $unlocked,
			'unlocked_at' => $unlocked_at,
		];
	}

	public static function is_unlocked( ?int $user_id, ?string $session_id ) : bool {
		$state = self::today_state( $user_id, $session_id );
		return (int) $state->dhikr_completed >= self::REQUIRED_DHIKR;
	}

	/**
	 * Streak = consecutive days ending today (or yesterday for grace)
	 * where the user fully unlocked the feed.
	 */
	public static function get_streak( ?int $user_id, ?string $session_id ) : int {
		global $wpdb;
		$t = LA_DB::tables();

		if ( ! $user_id && ! $session_id ) return 0;

		$col = $user_id ? 'user_id' : 'session_id';
		$val = $user_id ?: $session_id;

		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT date FROM {$t['unlock_state']}
			 WHERE {$col} = %s AND unlocked_at IS NOT NULL
			 ORDER BY date DESC LIMIT 365",
			(string) $val
		) );

		if ( ! $rows ) return 0;

		$today     = current_time( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', current_time( 'U' ) - 86400 );

		// Streak must end on today or yesterday (grace day)
		if ( $rows[0] !== $today && $rows[0] !== $yesterday ) return 0;

		$streak = 1;
		$prev   = strtotime( $rows[0] );
		for ( $i = 1; $i < count( $rows ); $i++ ) {
			$curr = strtotime( $rows[ $i ] );
			if ( ( $prev - $curr ) === 86400 ) {
				$streak++;
				$prev = $curr;
			} else {
				break;
			}
		}
		return $streak;
	}
}
