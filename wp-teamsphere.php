<?php
/**
 * Plugin Name:       WP TeamSphere
 * Plugin URI:        https://example.com/wp-teamsphere
 * Description:       Employee profiles, departments, teams and a public team directory for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-teamsphere
 * Domain Path:       /languages
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

define( 'WPTS_VERSION', '1.0.0' );
define( 'WPTS_FILE', __FILE__ );
define( 'WPTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPTS_URL', plugin_dir_url( __FILE__ ) );

require_once WPTS_PATH . 'includes/class-wpts-fields.php';
require_once WPTS_PATH . 'includes/class-wpts-settings.php';
require_once WPTS_PATH . 'includes/class-wpts-capabilities.php';
require_once WPTS_PATH . 'includes/class-wpts-post-types.php';
require_once WPTS_PATH . 'includes/class-wpts-terms.php';
require_once WPTS_PATH . 'includes/class-wpts-employee.php';
require_once WPTS_PATH . 'includes/class-wpts-activator.php';
require_once WPTS_PATH . 'includes/class-wpts-icons.php';
require_once WPTS_PATH . 'public/class-wpts-public.php';
require_once WPTS_PATH . 'public/class-wpts-render.php';
require_once WPTS_PATH . 'public/class-wpts-directory.php';
require_once WPTS_PATH . 'public/class-wpts-profile.php';

register_activation_hook( WPTS_FILE, array( 'WPTS_Activator', 'activate' ) );
register_deactivation_hook( WPTS_FILE, array( 'WPTS_Activator', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function wpts_init() {
	WPTS_Post_Types::init();
	WPTS_Public::init();
	WPTS_Directory::init();
	WPTS_Profile::init();

	if ( is_admin() ) {
		require_once WPTS_PATH . 'admin/class-wpts-admin-fields.php';
		require_once WPTS_PATH . 'admin/class-wpts-admin.php';
		require_once WPTS_PATH . 'admin/class-wpts-employee-metabox.php';
		require_once WPTS_PATH . 'admin/class-wpts-employee-list.php';
		require_once WPTS_PATH . 'admin/class-wpts-term-fields.php';
		require_once WPTS_PATH . 'admin/class-wpts-settings-page.php';

		add_action( 'admin_init', array( 'WPTS_Activator', 'maybe_upgrade' ) );

		WPTS_Admin::init();
		WPTS_Employee_Metabox::init();
		WPTS_Employee_List::init();
		WPTS_Term_Fields::init();
		WPTS_Settings_Page::init();
	}
}
add_action( 'plugins_loaded', 'wpts_init' );
