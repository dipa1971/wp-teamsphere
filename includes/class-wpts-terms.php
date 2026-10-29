<?php
/**
 * Department / team term meta and helpers.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Terms {

	const META_VISIBILITY = '_wpts_visibility';
	const META_IMAGE      = '_wpts_image_id';
	const META_HEAD       = '_wpts_head_id';
	const META_LEADER     = '_wpts_leader_id';
	const META_ORDER      = '_wpts_order';
	const META_COLOR      = '_wpts_color';

	/**
	 * Register term meta (none of it is exposed to REST).
	 */
	public static function register_meta() {
		$base = array(
			'single'       => true,
			'show_in_rest' => false,
		);

		foreach ( array( WPTS_Post_Types::TAX_DEPARTMENT, WPTS_Post_Types::TAX_TEAM ) as $taxonomy ) {
			register_term_meta(
				$taxonomy,
				self::META_VISIBILITY,
				array_merge(
					$base,
					array(
						'type'              => 'string',
						'default'           => 'public',
						'sanitize_callback' => array( __CLASS__, 'sanitize_visibility' ),
					)
				)
			);

			register_term_meta(
				$taxonomy,
				self::META_COLOR,
				array_merge(
					$base,
					array(
						'type'              => 'string',
						'sanitize_callback' => array( __CLASS__, 'sanitize_color' ),
					)
				)
			);
		}

		$int = array_merge(
			$base,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);

		register_term_meta( WPTS_Post_Types::TAX_DEPARTMENT, self::META_IMAGE, $int );
		register_term_meta( WPTS_Post_Types::TAX_DEPARTMENT, self::META_HEAD, $int );
		register_term_meta( WPTS_Post_Types::TAX_DEPARTMENT, self::META_ORDER, $int );
		register_term_meta( WPTS_Post_Types::TAX_TEAM, self::META_LEADER, $int );
	}

	/**
	 * Sanitize visibility value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_visibility( $value ) {
		return 'private' === $value ? 'private' : 'public';
	}

	/**
	 * Sanitize a colour to a lowercase 6-digit hex, or '' if invalid.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_color( $value ) {
		$color = sanitize_hex_color( is_scalar( $value ) ? (string) $value : '' );
		if ( ! $color ) {
			return '';
		}
		if ( 4 === strlen( $color ) ) { // #abc => #aabbcc.
			$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
		}
		return strtolower( $color );
	}

	/**
	 * Formal, solid colours offered as swatches.
	 *
	 * @return string[]
	 */
	public static function palette() {
		return array( '#1f4e79', '#2f7d6d', '#6b4c9a', '#b4541a', '#a63d40', '#3f7f3a', '#0e7490', '#9a7b0a', '#4b5563', '#9d3d73' );
	}

	/**
	 * Colour for a department / team. Falls back to a palette colour so that
	 * items created without choosing one are still distinguishable.
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function get_color( $term_id ) {
		$color = self::sanitize_color( get_term_meta( $term_id, self::META_COLOR, true ) );
		if ( $color ) {
			return $color;
		}

		$palette = self::palette();
		return $palette[ absint( $term_id ) % count( $palette ) ];
	}

	/**
	 * Inline CSS variables for a coloured chip (solid tint, no gradient).
	 *
	 * @param string $color 6-digit hex.
	 * @return string
	 */
	public static function chip_style( $color ) {
		return '--wpts-chip:' . $color . ';--wpts-chip-bg:' . $color . '1f';
	}

	/**
	 * Coloured chip HTML for a term (escaped).
	 *
	 * @param WP_Term $term Term.
	 * @param string  $url  Optional link.
	 * @return string
	 */
	public static function chip( $term, $url = '' ) {
		$style = self::chip_style( self::get_color( $term->term_id ) );

		if ( $url ) {
			return sprintf( '<a class="wpts-chip" style="%1$s" href="%2$s">%3$s</a>', esc_attr( $style ), esc_url( $url ), esc_html( $term->name ) );
		}

		return sprintf( '<span class="wpts-chip" style="%1$s">%2$s</span>', esc_attr( $style ), esc_html( $term->name ) );
	}

	/**
	 * Is the department / team visible on the public site?
	 *
	 * @param int $term_id Term ID.
	 * @return bool
	 */
	public static function is_public( $term_id ) {
		return 'private' !== get_term_meta( $term_id, self::META_VISIBILITY, true );
	}

	/**
	 * Active (non-archived) profile counts per term, in one query.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return int[] term_id => count.
	 */
	public static function member_counts( $taxonomy ) {
		static $cache = array();

		if ( isset( $cache[ $taxonomy ] ) ) {
			return $cache[ $taxonomy ];
		}

		global $wpdb;

		$statuses     = array( 'publish', 'draft', 'pending', 'private' );
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "SELECT tt.term_id, COUNT(DISTINCT p.ID) AS total
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status IN ($placeholders)
			GROUP BY tt.term_id";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $taxonomy, WPTS_Post_Types::POST_TYPE ), $statuses ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->term_id ] = (int) $row->total;
		}

		$cache[ $taxonomy ] = $counts;
		return $counts;
	}

	/**
	 * Departments in the admin-defined order, then by name.
	 *
	 * @return WP_Term[]
	 */
	public static function get_departments() {
		$terms = get_terms(
			array(
				'taxonomy'   => WPTS_Post_Types::TAX_DEPARTMENT,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			function ( $a, $b ) {
				$order_a = (int) get_term_meta( $a->term_id, WPTS_Terms::META_ORDER, true );
				$order_b = (int) get_term_meta( $b->term_id, WPTS_Terms::META_ORDER, true );
				return $order_a === $order_b ? strcasecmp( $a->name, $b->name ) : $order_a <=> $order_b;
			}
		);

		return $terms;
	}

	/**
	 * Teams, alphabetical.
	 *
	 * @return WP_Term[]
	 */
	public static function get_teams() {
		$terms = get_terms(
			array(
				'taxonomy'   => WPTS_Post_Types::TAX_TEAM,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Departments that are visible on the public site.
	 *
	 * @return WP_Term[]
	 */
	public static function get_public_departments() {
		return array_values(
			array_filter(
				self::get_departments(),
				function ( $term ) {
					return WPTS_Terms::is_public( $term->term_id );
				}
			)
		);
	}

	/**
	 * Teams that are visible on the public site.
	 *
	 * @return WP_Term[]
	 */
	public static function get_public_teams() {
		return array_values(
			array_filter(
				self::get_teams(),
				function ( $term ) {
					return WPTS_Terms::is_public( $term->term_id );
				}
			)
		);
	}
}
