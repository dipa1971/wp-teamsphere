<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Data is removed ONLY if "Delete all data on uninstall" is enabled in settings.
 *
 * @package WP_TeamSphere
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpts-capabilities.php';

/**
 * Remove plugin data for the current site.
 */
function wpts_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'wpts_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Profiles (all statuses, including our custom one, which is not registered during uninstall).
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wpts_employee' ) );
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}

	// Terms (register temporarily so core can delete them and their meta).
	foreach ( array( 'wpts_department', 'wpts_team', 'wpts_skill' ) as $taxonomy ) {
		register_taxonomy( $taxonomy, 'wpts_employee', array( 'public' => false ) );
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}
	}

	delete_option( 'wpts_settings' );
	delete_option( 'wpts_db_version' );
	WPTS_Capabilities::revoke_from_all_roles();
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
		switch_to_blog( $blog_id );
		wpts_uninstall_site();
		restore_current_blog();
	}
} else {
	wpts_uninstall_site();
}
