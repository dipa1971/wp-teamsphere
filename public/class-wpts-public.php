<?php
/**
 * Front-end assets. Styles load only on pages that need them.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Public {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 20 );
	}

	/**
	 * Register the stylesheet.
	 */
	public static function register() {
		wp_register_style( 'wpts-public', WPTS_URL . 'public/css/public.css', array(), WPTS_VERSION );
	}

	/**
	 * Enqueue the stylesheet (also called from shortcodes as a fallback for page builders).
	 */
	public static function enqueue() {
		if ( ! wp_style_is( 'wpts-public', 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( 'wpts-public' );
	}

	/**
	 * Load early on profile pages and on pages whose content contains the shortcode.
	 */
	public static function maybe_enqueue() {
		if ( is_singular( WPTS_Post_Types::POST_TYPE ) ) {
			self::enqueue();
			return;
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'wpts_team' ) ) {
				self::enqueue();
			}
		}
	}

	/**
	 * Photo shape from settings: rounded (default), circle or square.
	 *
	 * @return string
	 */
	public static function shape() {
		$shape = WPTS_Settings::get( 'image_shape', 'rounded' );
		return in_array( $shape, array( 'rounded', 'circle', 'square' ), true ) ? $shape : 'rounded';
	}
}