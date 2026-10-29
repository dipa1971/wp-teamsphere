<?php
/**
 * Field registry: the single source of truth for profile fields and their privacy rules.
 *
 * - "public"  => visible on the public site by default.
 * - "locked"  => can NEVER be made public (internal notes, personal phone, etc.).
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Fields {

	const META_PREFIX = '_wpts_'; // Underscore prefix = protected meta (hidden from custom fields UI and REST).

	/**
	 * Post meta key for a field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public static function meta_key( $key ) {
		return self::META_PREFIX . $key;
	}

	/**
	 * All fields.
	 *
	 * @return array
	 */
	public static function all() {
		static $fields = null;

		if ( null !== $fields ) {
			return $fields;
		}

		$fields = array(
			'first_name'      => array(
				'label'  => __( 'First name', 'wp-teamsphere' ),
				'type'   => 'text',
				'box'    => 'basic',
				'public' => true,
			),
			'last_name'       => array(
				'label'  => __( 'Last name', 'wp-teamsphere' ),
				'type'   => 'text',
				'box'    => 'basic',
				'public' => true,
			),
			'job_title'       => array(
				'label'  => __( 'Job title', 'wp-teamsphere' ),
				'type'   => 'text',
				'box'    => 'basic',
				'public' => true,
			),
			'short_intro'     => array(
				'label'  => __( 'Short introduction', 'wp-teamsphere' ),
				'type'   => 'textarea',
				'box'    => 'basic',
				'public' => true,
				'desc'   => __( 'One or two sentences, used on directory cards.', 'wp-teamsphere' ),
			),
			'office_location' => array(
				'label'  => __( 'Office location', 'wp-teamsphere' ),
				'type'   => 'text',
				'box'    => 'contact',
				'public' => true,
			),
			'work_email'      => array(
				'label' => __( 'Work email', 'wp-teamsphere' ),
				'type'  => 'email',
				'box'   => 'contact',
				'desc'  => __( 'Hidden from the public site by default.', 'wp-teamsphere' ),
			),
			'work_phone'      => array(
				'label' => __( 'Work phone', 'wp-teamsphere' ),
				'type'  => 'tel',
				'box'   => 'contact',
				'desc'  => __( 'Hidden from the public site by default.', 'wp-teamsphere' ),
			),
			'start_date'      => array(
				'label' => __( 'Start date', 'wp-teamsphere' ),
				'type'  => 'date',
				'box'   => 'employment',
			),
			'end_date'        => array(
				'label'  => __( 'End date', 'wp-teamsphere' ),
				'type'   => 'date',
				'box'    => 'employment',
				'locked' => true,
				'desc'   => __( 'For former employees. Never shown publicly.', 'wp-teamsphere' ),
			),
			'manager_id'      => array(
				'label'  => __( 'Reports to', 'wp-teamsphere' ),
				'type'   => 'employee',
				'box'    => 'org',
				'locked' => true, // Exposed later through the org chart, which only links published profiles.
			),
			'linkedin'        => array(
				'label'  => __( 'LinkedIn', 'wp-teamsphere' ),
				'type'   => 'url',
				'box'    => 'links',
				'public' => true,
			),
			'x'               => array(
				'label'  => __( 'X (Twitter)', 'wp-teamsphere' ),
				'type'   => 'url',
				'box'    => 'links',
				'public' => true,
			),
			'github'          => array(
				'label'  => __( 'GitHub', 'wp-teamsphere' ),
				'type'   => 'url',
				'box'    => 'links',
				'public' => true,
			),
			'facebook'        => array(
				'label'  => __( 'Facebook', 'wp-teamsphere' ),
				'type'   => 'url',
				'box'    => 'links',
				'public' => true,
			),
			'instagram'       => array(
				'label'  => __( 'Instagram', 'wp-teamsphere' ),
				'type'   => 'url',
				'box'    => 'links',
				'public' => true,
			),
			'website'         => array(
				'label'  => __( 'Website', 'wp-teamsphere' ),
				'type'   => 'url',
				'box'    => 'links',
				'public' => true,
			),
			'cover_image'     => array(
				'label'  => __( 'Cover image', 'wp-teamsphere' ),
				'type'   => 'image',
				'box'    => 'cover',
				'public' => true,
				'desc'   => __( 'Optional wide image for the top of the profile page.', 'wp-teamsphere' ),
			),
			'personal_phone'  => array(
				'label'  => __( 'Personal phone', 'wp-teamsphere' ),
				'type'   => 'tel',
				'box'    => 'private',
				'locked' => true,
			),
			'internal_notes'  => array(
				'label'  => __( 'Internal notes', 'wp-teamsphere' ),
				'type'   => 'textarea',
				'box'    => 'private',
				'locked' => true,
			),
		);

		foreach ( $fields as $key => $def ) {
			$fields[ $key ] = wp_parse_args(
				$def,
				array(
					'desc'   => '',
					'public' => false,
					'locked' => false,
				)
			);
			if ( $fields[ $key ]['locked'] ) {
				$fields[ $key ]['public'] = false;
			}
		}

		return $fields;
	}

	/**
	 * Fields that belong to a meta box.
	 *
	 * @param string $box Box id.
	 * @return array
	 */
	public static function by_box( $box ) {
		return array_filter(
			self::all(),
			function ( $def ) use ( $box ) {
				return $def['box'] === $box;
			}
		);
	}

	/**
	 * Factory default: which fields are public.
	 *
	 * @return string[]
	 */
	public static function default_public_keys() {
		$keys = array();
		foreach ( self::all() as $key => $def ) {
			if ( $def['public'] && ! $def['locked'] ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Currently public fields (settings, with locked fields always removed).
	 *
	 * @return string[]
	 */
	public static function public_keys() {
		$fields = self::all();
		$saved  = WPTS_Settings::get( 'public_fields', array() );
		$keys   = array();

		if ( ! is_array( $saved ) ) {
			return $keys;
		}

		foreach ( $saved as $key ) {
			if ( is_string( $key ) && isset( $fields[ $key ] ) && empty( $fields[ $key ]['locked'] ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Sanitize a raw value for a field.
	 *
	 * @param string $key Field key.
	 * @param mixed  $raw Raw value.
	 * @return string|int Empty string means "no value".
	 */
	public static function sanitize( $key, $raw ) {
		$fields = self::all();
		if ( ! isset( $fields[ $key ] ) || ! is_scalar( $raw ) ) {
			return '';
		}

		$raw = (string) $raw;

		switch ( $fields[ $key ]['type'] ) {
			case 'email':
				return sanitize_email( $raw );

			case 'tel':
				return trim( preg_replace( '/[^0-9+\-\s().x]/', '', sanitize_text_field( $raw ) ) );

			case 'url':
				return esc_url_raw( trim( $raw ), array( 'http', 'https' ) );

			case 'textarea':
				return sanitize_textarea_field( $raw );

			case 'date':
				$raw  = sanitize_text_field( $raw );
				$date = DateTime::createFromFormat( 'Y-m-d', $raw );
				return ( $date && $date->format( 'Y-m-d' ) === $raw ) ? $raw : '';

			case 'image':
				$id = absint( $raw );
				return ( $id && wp_attachment_is_image( $id ) ) ? $id : '';

			case 'employee':
				$id = absint( $raw );
				return $id ? $id : '';

			case 'text':
			default:
				return sanitize_text_field( $raw );
		}
	}
}
