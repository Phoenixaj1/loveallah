<?php
/**
 * Capabilities + custom role for Love Allah.
 *
 * Platform admins: get `manage_loveallah` cap on activation.
 * Masjid admins: `loveallah_masjid_admin` role with `manage_loveallah_own_masjid`.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Caps {

	const CAP_PLATFORM = 'manage_loveallah';
	const CAP_OWN      = 'manage_loveallah_own_masjid';
	const ROLE_MASJID  = 'loveallah_masjid_admin';

	/** Run on activation. Idempotent. */
	public static function install() : void {
		// Give platform cap to administrators
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_PLATFORM );
			$admin->add_cap( self::CAP_OWN );
		}

		// Register the masjid admin role
		if ( ! get_role( self::ROLE_MASJID ) ) {
			add_role( self::ROLE_MASJID, 'Masjid Admin', [
				'read'                 => true,
				'edit_posts'           => false,
				self::CAP_OWN          => true,
				'upload_files'         => true,
			] );
		}
	}

	/** Run on deactivation — keeps caps so reactivation is seamless. */
	public static function uninstall() : void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( self::CAP_PLATFORM );
			$admin->remove_cap( self::CAP_OWN );
		}
		remove_role( self::ROLE_MASJID );
	}

	/** Quick checker for platform admins */
	public static function can_manage_platform() : bool {
		return current_user_can( self::CAP_PLATFORM );
	}

	/** Quick checker for masjid-scoped admins */
	public static function can_manage_own( int $mosque_id ) : bool {
		if ( current_user_can( self::CAP_PLATFORM ) ) return true;
		if ( ! current_user_can( self::CAP_OWN ) ) return false;
		$mosque = LA_Mosques::get_by_id( $mosque_id );
		return $mosque && (int) $mosque->claimed_user_id === get_current_user_id();
	}
}
