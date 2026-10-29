<?php
/**
 * Shared front-end building blocks. Every method returns escaped HTML and
 * only ever receives data from WPTS_Employee::get_public_profile().
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Render {

	/**
	 * First letter of a name (multibyte safe).
	 *
	 * @param string $text Name.
	 * @return string
	 */
	public static function initial( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '?';
		}
		if ( function_exists( 'mb_substr' ) ) {
			$char = mb_substr( $text, 0, 1 );
			return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $char ) : $char;
		}
		return strtoupper( substr( $text, 0, 1 ) );
	}

	/**
	 * Photo, or the person's initial when there is no photo.
	 *
	 * @param array  $profile Public profile.
	 * @param string $size    Image size.
	 * @return string
	 */
	public static function photo( $profile, $size = 'medium' ) {
		if ( ! empty( $profile['photo_id'] ) ) {
			$image = wp_get_attachment_image(
				$profile['photo_id'],
				$size,
				false,
				array(
					'alt'      => $profile['name'],
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			if ( $image ) {
				return $image;
			}
		}

		return '<span class="wpts-photo__initial" aria-hidden="true">' . esc_html( self::initial( $profile['name'] ) ) . '</span>';
	}

	/**
	 * A coloured chip for a department or team.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $name    Term name.
	 * @return string
	 */
	public static function chip( $term_id, $name ) {
		$style = WPTS_Terms::chip_style( WPTS_Terms::get_color( $term_id ) );
		return sprintf( '<span class="wpts-chip" style="%1$s">%2$s</span>', esc_attr( $style ), esc_html( $name ) );
	}

	/**
	 * Social links. Only links that are public and not empty are shown.
	 *
	 * @param array $profile  Public profile.
	 * @param bool  $labelled Show the network name next to the icon.
	 * @return string
	 */
	public static function social_links( $profile, $labelled = false ) {
		$networks = array(
			'linkedin'  => 'LinkedIn',
			'x'         => 'X',
			'github'    => 'GitHub',
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'website'   => __( 'Website', 'wp-teamsphere' ),
		);

		$items = '';
		foreach ( $networks as $key => $label ) {
			if ( empty( $profile['fields'][ $key ] ) ) {
				continue;
			}

			$aria   = $labelled ? '' : ' aria-label="' . esc_attr( $label . ': ' . $profile['name'] ) . '"';
			$items .= sprintf(
				'<li><a href="%1$s" target="_blank" rel="noopener noreferrer"%2$s>%3$s%4$s</a></li>',
				esc_url( $profile['fields'][ $key ] ),
				$aria,
				WPTS_Icons::get( $key ),
				$labelled ? '<span>' . esc_html( $label ) . '</span>' : ''
			);
		}

		if ( '' === $items ) {
			return '';
		}

		return '<ul class="wpts-social' . ( $labelled ? ' wpts-social--labelled' : '' ) . '">' . $items . '</ul>';
	}
}