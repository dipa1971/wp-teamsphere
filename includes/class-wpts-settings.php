<?php
/**
 * Plugin settings storage. The settings screen itself arrives in Milestone 5.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Settings {

	const OPTION = 'wpts_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'permalink_base'           => 'team',
			'default_layout'           => 'grid',
			'image_shape'              => 'rounded',
			'per_page'                 => 12,
			'public_fields'            => WPTS_Fields::default_public_keys(),
			'delete_data_on_uninstall' => 0, // Safe by default: uninstalling never deletes data unless explicitly enabled.
		);
	}

	/**
	 * Get a setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$saved    = get_option( self::OPTION, array() );
		$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Store defaults on first activation without overwriting existing values.
	 */
	public static function install_defaults() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}
}
