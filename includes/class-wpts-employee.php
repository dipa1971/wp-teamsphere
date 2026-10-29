<?php
/**
 * Employee data access. Every rule about profiles lives here so later features
 * (templates, REST, AJAX, CSV export, blocks) all go through the same privacy gate.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Employee {

	/**
	 * Raw field value. TRUSTED/ADMIN USE ONLY. Public output must use get_public_profile().
	 *
	 * @param int    $post_id Employee ID.
	 * @param string $key     Field key.
	 * @return mixed
	 */
	public static function get_field( $post_id, $key ) {
		return get_post_meta( $post_id, WPTS_Fields::meta_key( $key ), true );
	}

	/**
	 * Save or clear a field (value must already be sanitized).
	 *
	 * @param int    $post_id Employee ID.
	 * @param string $key     Field key.
	 * @param mixed  $value   Sanitized value.
	 */
	public static function set_field( $post_id, $key, $value ) {
		$meta_key = WPTS_Fields::meta_key( $key );

		if ( '' === $value || 0 === $value || null === $value ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		update_post_meta( $post_id, $meta_key, $value );
	}

	/**
	 * Primary department.
	 *
	 * @param int $post_id Employee ID.
	 * @return WP_Term|null
	 */
	public static function get_department( $post_id ) {
		$terms = wp_get_object_terms( $post_id, WPTS_Post_Types::TAX_DEPARTMENT );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : null;
	}

	/**
	 * Set the single primary department (0 clears it).
	 *
	 * @param int $post_id Employee ID.
	 * @param int $term_id Department term ID.
	 */
	public static function set_department( $post_id, $term_id ) {
		$term_id = absint( $term_id );

		if ( $term_id && ! term_exists( $term_id, WPTS_Post_Types::TAX_DEPARTMENT ) ) {
			$term_id = 0;
		}

		wp_set_object_terms( $post_id, $term_id ? array( $term_id ) : array(), WPTS_Post_Types::TAX_DEPARTMENT );
	}

	/**
	 * Teams the employee belongs to.
	 *
	 * @param int $post_id Employee ID.
	 * @return WP_Term[]
	 */
	public static function get_teams( $post_id ) {
		$terms = wp_get_object_terms( $post_id, WPTS_Post_Types::TAX_TEAM );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Replace team memberships.
	 *
	 * @param int   $post_id  Employee ID.
	 * @param int[] $term_ids Team term IDs.
	 */
	public static function set_teams( $post_id, $term_ids ) {
		$valid = array();
		foreach ( (array) $term_ids as $term_id ) {
			$term_id = absint( $term_id );
			if ( $term_id && term_exists( $term_id, WPTS_Post_Types::TAX_TEAM ) ) {
				$valid[] = $term_id;
			}
		}

		wp_set_object_terms( $post_id, array_values( array_unique( $valid ) ), WPTS_Post_Types::TAX_TEAM );
	}

	/**
	 * Skills of an employee.
	 *
	 * @param int $post_id Employee ID.
	 * @return WP_Term[]
	 */
	public static function get_skills( $post_id ) {
		$terms = wp_get_object_terms( $post_id, WPTS_Post_Types::TAX_SKILL );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Replace skills by name. New skills are created automatically, but only
	 * users who may manage terms can create them; everyone else can only pick existing ones.
	 *
	 * @param int      $post_id Employee ID.
	 * @param string[] $names   Skill names.
	 */
	public static function set_skills( $post_id, $names ) {
		$clean = array();

		foreach ( (array) $names as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}
			$name = trim( wp_html_excerpt( sanitize_text_field( $name ), 60 ) );
			if ( '' === $name ) {
				continue;
			}
			if ( ! current_user_can( 'manage_wpts_terms' ) && ! term_exists( $name, WPTS_Post_Types::TAX_SKILL ) ) {
				continue;
			}
			$clean[ strtolower( $name ) ] = $name;
		}

		wp_set_object_terms( $post_id, array_slice( array_values( $clean ), 0, 40 ), WPTS_Post_Types::TAX_SKILL );
	}

	/**
	 * Would making $manager_id the manager of $employee_id create a loop?
	 *
	 * @param int $employee_id Employee being edited.
	 * @param int $manager_id  Proposed manager.
	 * @return bool
	 */
	public static function would_create_cycle( $employee_id, $manager_id ) {
		$employee_id = (int) $employee_id;
		$current     = (int) $manager_id;
		$seen        = array();

		for ( $i = 0; $i < 200 && $current; $i++ ) {
			if ( $current === $employee_id || isset( $seen[ $current ] ) ) {
				return true;
			}
			$seen[ $current ] = true;
			$current          = (int) self::get_field( $current, 'manager_id' );
		}

		return false;
	}

	/**
	 * Set the manager, rejecting invalid or circular choices.
	 *
	 * @param int $post_id    Employee ID.
	 * @param int $manager_id Manager ID (0 = none).
	 * @return true|WP_Error
	 */
	public static function set_manager( $post_id, $manager_id ) {
		$manager_id = absint( $manager_id );

		if ( ! $manager_id ) {
			self::set_field( $post_id, 'manager_id', '' );
			return true;
		}

		if ( WPTS_Post_Types::POST_TYPE !== get_post_type( $manager_id ) ) {
			return new WP_Error( 'wpts_invalid_manager', __( 'The selected manager is not a team member, so "Reports to" was not changed.', 'wp-teamsphere' ) );
		}

		if ( $manager_id === (int) $post_id ) {
			return new WP_Error( 'wpts_self_manager', __( 'A team member cannot report to themselves, so "Reports to" was not changed.', 'wp-teamsphere' ) );
		}

		if ( self::would_create_cycle( $post_id, $manager_id ) ) {
			return new WP_Error( 'wpts_circular', __( 'That choice would create a circular reporting line, so "Reports to" was not changed.', 'wp-teamsphere' ) );
		}

		self::set_field( $post_id, 'manager_id', $manager_id );
		return true;
	}

	/**
	 * Employees for dropdowns: ID => name.
	 *
	 * @param int $exclude Optional employee ID to leave out.
	 * @return array
	 */
	public static function get_employee_options( $exclude = 0 ) {
		$ids = get_posts(
			array(
				'post_type'      => WPTS_Post_Types::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post__not_in'   => $exclude ? array( (int) $exclude ) : array(),
			)
		);

		$options = array();
		foreach ( $ids as $id ) {
			$title = get_the_title( $id );
			/* translators: %d: post ID of a profile with no name yet. */
			$options[ $id ] = '' !== $title ? $title : sprintf( __( '(no name) #%d', 'wp-teamsphere' ), $id );
		}

		return $options;
	}

	/**
	 * THE privacy gate for public output.
	 *
	 * Returns null unless the profile is published. Only fields that are public
	 * in settings AND not locked are ever included. Private departments / teams are omitted.
	 *
	 * @param int  $post_id       Employee ID.
	 * @param bool $allow_preview Let users who can edit this profile preview it before it is published.
	 * @return array|null
	 */
	public static function get_public_profile( $post_id, $allow_preview = false ) {
		$post = get_post( $post_id );

		if ( ! $post || WPTS_Post_Types::POST_TYPE !== $post->post_type || post_password_required( $post ) ) {
			return null;
		}

		if ( 'publish' !== $post->post_status && ! ( $allow_preview && current_user_can( 'edit_post', $post->ID ) ) ) {
			return null;
		}

		$profile = array(
			'id'         => $post->ID,
			'name'       => get_the_title( $post ),
			'url'        => get_permalink( $post ),
			'photo_id'   => (int) get_post_thumbnail_id( $post ),
			'fields'     => array(),
			'department' => null,
			'teams'      => array(),
			'skills'     => array(),
		);

		foreach ( WPTS_Fields::public_keys() as $key ) {
			$value = self::get_field( $post->ID, $key );
			if ( '' !== $value && null !== $value && false !== $value ) {
				$profile['fields'][ $key ] = $value;
			}
		}

		$department = self::get_department( $post->ID );
		if ( $department && WPTS_Terms::is_public( $department->term_id ) ) {
			$profile['department'] = array(
				'id'   => $department->term_id,
				'name' => $department->name,
			);
		}

		foreach ( self::get_teams( $post->ID ) as $team ) {
			if ( WPTS_Terms::is_public( $team->term_id ) ) {
				$profile['teams'][] = array(
					'id'   => $team->term_id,
					'name' => $team->name,
				);
			}
		}

		foreach ( self::get_skills( $post->ID ) as $skill ) {
			$profile['skills'][] = $skill->name;
		}

		return $profile;
	}
}
