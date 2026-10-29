<?php
/**
 * Settings screen (basic version): directory defaults, public fields, data on uninstall.
 * More tabs (appearance, import / export) arrive in Milestone 5.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Settings_Page {

	const SLUG  = 'wpts-settings';
	const GROUP = 'wpts_settings_group';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 12 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( __CLASS__, 'capability' ) );
	}

	/**
	 * Capability needed to save through options.php.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_wpts_settings';
	}

	/**
	 * Submenu item.
	 */
	public static function menu() {
		add_submenu_page(
			WPTS_Admin::MENU_SLUG,
			__( 'Settings', 'wp-teamsphere' ),
			__( 'Settings', 'wp-teamsphere' ),
			'manage_wpts_settings',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option with the Settings API.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WPTS_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WPTS_Settings::defaults(),
			)
		);
	}

	/**
	 * Validate everything that is saved.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$old   = get_option( WPTS_Settings::OPTION, array() );
		$out   = is_array( $old ) ? $old : array(); // Keeps values this screen does not edit (for example the URL base).

		$layout                = isset( $input['default_layout'] ) ? sanitize_key( $input['default_layout'] ) : 'grid';
		$out['default_layout'] = in_array( $layout, array( 'grid', 'list', 'compact' ), true ) ? $layout : 'grid';

		$per_page        = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 12;
		$out['per_page'] = min( 60, max( 1, $per_page ) );

		$shape                = isset( $input['image_shape'] ) ? sanitize_key( $input['image_shape'] ) : 'rounded';
		$out['image_shape']   = in_array( $shape, array( 'rounded', 'circle', 'square' ), true ) ? $shape : 'rounded';

		$allowed = array();
		foreach ( WPTS_Fields::all() as $key => $def ) {
			if ( empty( $def['locked'] ) ) {
				$allowed[] = $key;
			}
		}
		$posted               = ( isset( $input['public_fields'] ) && is_array( $input['public_fields'] ) ) ? array_map( 'sanitize_key', $input['public_fields'] ) : array();
		$out['public_fields'] = array_values( array_intersect( $allowed, $posted ) );

		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		return $out;
	}

	/**
	 * Page output.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_wpts_settings' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-teamsphere' ), '', array( 'response' => 403 ) );
		}

		$option    = WPTS_Settings::OPTION;
		$layout    = WPTS_Settings::get( 'default_layout', 'grid' );
		$per_page  = (int) WPTS_Settings::get( 'per_page', 12 );
		$shape     = WPTS_Public::shape();
		$public    = WPTS_Fields::public_keys();
		$delete    = (int) WPTS_Settings::get( 'delete_data_on_uninstall', 0 );
		$fields    = WPTS_Fields::all();
		$locked    = array();
		$shareable = array();

		foreach ( $fields as $key => $def ) {
			if ( ! empty( $def['locked'] ) ) {
				$locked[] = $def['label'];
			} else {
				$shareable[ $key ] = $def['label'];
			}
		}
		?>
		<div class="wrap wpts-settings">
			<h1><?php esc_html_e( 'Settings', 'wp-teamsphere' ); ?></h1>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<section class="wpts-panel">
					<h2><?php esc_html_e( 'Directory defaults', 'wp-teamsphere' ); ?></h2>
					<p class="wpts-panel-sub"><?php esc_html_e( 'Used by [wpts_team] when the shortcode does not say otherwise.', 'wp-teamsphere' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wpts_default_layout"><?php esc_html_e( 'Default layout', 'wp-teamsphere' ); ?></label></th>
							<td>
								<select id="wpts_default_layout" name="<?php echo esc_attr( $option ); ?>[default_layout]">
									<option value="grid" <?php selected( $layout, 'grid' ); ?>><?php esc_html_e( 'Grid', 'wp-teamsphere' ); ?></option>
									<option value="list" <?php selected( $layout, 'list' ); ?>><?php esc_html_e( 'List', 'wp-teamsphere' ); ?></option>
									<option value="compact" <?php selected( $layout, 'compact' ); ?>><?php esc_html_e( 'Compact cards', 'wp-teamsphere' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpts_per_page"><?php esc_html_e( 'Profiles per page', 'wp-teamsphere' ); ?></label></th>
							<td>
								<input type="number" id="wpts_per_page" name="<?php echo esc_attr( $option ); ?>[per_page]" value="<?php echo esc_attr( $per_page ); ?>" min="1" max="60" class="small-text">
								<p class="description"><?php esc_html_e( 'Between 1 and 60. Visitors move between pages with Previous / Next.', 'wp-teamsphere' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wpts_image_shape"><?php esc_html_e( 'Photo shape', 'wp-teamsphere' ); ?></label></th>
							<td>
								<select id="wpts_image_shape" name="<?php echo esc_attr( $option ); ?>[image_shape]">
									<option value="rounded" <?php selected( $shape, 'rounded' ); ?>><?php esc_html_e( 'Rounded square', 'wp-teamsphere' ); ?></option>
									<option value="circle" <?php selected( $shape, 'circle' ); ?>><?php esc_html_e( 'Circle', 'wp-teamsphere' ); ?></option>
									<option value="square" <?php selected( $shape, 'square' ); ?>><?php esc_html_e( 'Square', 'wp-teamsphere' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</section>

				<section class="wpts-panel">
					<h2><?php esc_html_e( 'Privacy: what visitors can see', 'wp-teamsphere' ); ?></h2>
					<p class="wpts-panel-sub"><?php esc_html_e( 'Only ticked fields appear on the website, in search, and in any public data. Email addresses are protected against spam bots.', 'wp-teamsphere' ); ?></p>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Public fields', 'wp-teamsphere' ); ?></legend>
						<div class="wpts-check-list">
							<?php foreach ( $shareable as $key => $label ) : ?>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[public_fields][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $public, true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
					<p class="wpts-chip-label"><?php esc_html_e( 'Always private (cannot be shown)', 'wp-teamsphere' ); ?></p>
					<ul class="wpts-chips">
						<?php foreach ( $locked as $label ) : ?>
							<li><?php echo esc_html( $label ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>

				<section class="wpts-panel">
					<h2><?php esc_html_e( 'Data', 'wp-teamsphere' ); ?></h2>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[delete_data_on_uninstall]" value="1" <?php checked( 1, $delete ); ?>>
						<?php esc_html_e( 'Delete all team data when the plugin is deleted', 'wp-teamsphere' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Leave this off to keep your profiles, departments and teams even if you remove the plugin. Deactivating never deletes anything.', 'wp-teamsphere' ); ?></p>
				</section>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}