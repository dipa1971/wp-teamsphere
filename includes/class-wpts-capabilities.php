<?php
/**
 * Capabilities. Administrators get everything; other roles get nothing by default.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Capabilities {

	/**
	 * Primitive capabilities used by the employee post type.
	 *
	 * @return string[]
	 */
	public static function employee_caps() {
		return array(
			'create_wpts_employees',
			'edit_wpts_employees',
			'edit_others_wpts_employees',
			'edit_published_wpts_employees',
			'edit_private_wpts_employees',
			'publish_wpts_employees',
			'read_private_wpts_employees',
			'delete_wpts_employees',
			'delete_others_wpts_employees',
			'delete_published_wpts_employees',
			'delete_private_wpts_employees',
		);
	}

	/**
	 * Every capability the plugin defines.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array_merge(
			self::employee_caps(),
			array( 'manage_wpts_terms', 'manage_wpts_settings' )
		);
	}

	/**
	 * Give administrators full control.
	 */
	public static function grant_to_admin() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		foreach ( self::all() as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Remove every plugin capability from every role (used on uninstall).
	 */
	public static function revoke_from_all_roles() {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
