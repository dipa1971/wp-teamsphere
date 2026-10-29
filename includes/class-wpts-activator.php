<?php
/**
 * Activation, deactivation and upgrade routines.
 *
 * Deactivation never deletes data.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Activator {

	/**
	 * Plugin activation.
	 *
	 * @param bool $network_wide Whether activated network-wide.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::setup_site();
				restore_current_blog();
			}
			return;
		}

		self::setup_site();
	}

	/**
	 * Per-site setup.
	 */
	private static function setup_site() {
		WPTS_Post_Types::register();
		WPTS_Capabilities::grant_to_admin();
		WPTS_Settings::install_defaults();
		update_option( 'wpts_db_version', WPTS_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation (data is kept).
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Re-apply capabilities after updates.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'wpts_db_version' ) !== WPTS_VERSION ) {
			WPTS_Capabilities::grant_to_admin();
			update_option( 'wpts_db_version', WPTS_VERSION );
		}
	}
}
