<?php
/**
 * Registers the employee post type, the department / team / skill taxonomies and the archived status.
 *
 * Privacy notes:
 * - Nothing is exposed through the REST API in this milestone (show_in_rest = false everywhere).
 * - Taxonomies are not public, so there are no department / team / skill archive URLs.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Post_Types {

	const POST_TYPE       = 'wpts_employee';
	const TAX_DEPARTMENT  = 'wpts_department';
	const TAX_TEAM        = 'wpts_team';
	const TAX_SKILL       = 'wpts_skill';
	const STATUS_ARCHIVED = 'wpts_archived';

	/**
	 * Hook registration.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register everything. Also called on activation before flushing rewrite rules.
	 */
	public static function register() {
		self::register_post_type();
		self::register_taxonomies();
		self::register_statuses();
		WPTS_Terms::register_meta();
	}

	/**
	 * Taxonomy slugs owned by the plugin.
	 *
	 * @return string[]
	 */
	public static function taxonomies() {
		return array( self::TAX_DEPARTMENT, self::TAX_TEAM, self::TAX_SKILL );
	}

	/**
	 * Employee post type.
	 */
	private static function register_post_type() {
		$base = sanitize_title( (string) WPTS_Settings::get( 'permalink_base', 'team' ) );
		if ( '' === $base ) {
			$base = 'team';
		}

		$labels = array(
			'name'                  => __( 'Team Members', 'wp-teamsphere' ),
			'singular_name'         => __( 'Team Member', 'wp-teamsphere' ),
			'add_new'               => __( 'Add New', 'wp-teamsphere' ),
			'add_new_item'          => __( 'Add New Team Member', 'wp-teamsphere' ),
			'edit_item'             => __( 'Edit Team Member', 'wp-teamsphere' ),
			'new_item'              => __( 'New Team Member', 'wp-teamsphere' ),
			'view_item'             => __( 'View Profile', 'wp-teamsphere' ),
			'search_items'          => __( 'Search Team Members', 'wp-teamsphere' ),
			'not_found'             => __( 'No team members found. Use "Add New" to create a profile.', 'wp-teamsphere' ),
			'not_found_in_trash'    => __( 'No team members found in Trash.', 'wp-teamsphere' ),
			'all_items'             => __( 'Team Members', 'wp-teamsphere' ),
			'menu_name'             => __( 'Team Members', 'wp-teamsphere' ),
			'featured_image'        => __( 'Profile photo', 'wp-teamsphere' ),
			'set_featured_image'    => __( 'Set profile photo', 'wp-teamsphere' ),
			'remove_featured_image' => __( 'Remove profile photo', 'wp-teamsphere' ),
			'use_featured_image'    => __( 'Use as profile photo', 'wp-teamsphere' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => 'wpts-dashboard',
				'show_in_rest'    => false,
				'has_archive'     => false,
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'         => array(
					'slug'       => $base,
					'with_front' => false,
				),
				'capability_type' => array( 'wpts_employee', 'wpts_employees' ),
				'capabilities'    => array( 'create_posts' => 'create_wpts_employees' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Build taxonomy labels.
	 *
	 * @param string $singular Singular name (already translated).
	 * @param string $plural   Plural name (already translated).
	 * @return array
	 */
	private static function tax_labels( $singular, $plural ) {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: plural taxonomy name, e.g. Departments. */
			'search_items'  => sprintf( __( 'Search %s', 'wp-teamsphere' ), $plural ),
			/* translators: %s: plural taxonomy name. */
			'all_items'     => sprintf( __( 'All %s', 'wp-teamsphere' ), $plural ),
			/* translators: %s: singular taxonomy name, e.g. Department. */
			'edit_item'     => sprintf( __( 'Edit %s', 'wp-teamsphere' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'update_item'   => sprintf( __( 'Update %s', 'wp-teamsphere' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'add_new_item'  => sprintf( __( 'Add New %s', 'wp-teamsphere' ), $singular ),
			/* translators: %s: singular taxonomy name. */
			'new_item_name' => sprintf( __( 'New %s Name', 'wp-teamsphere' ), $singular ),
			/* translators: %s: plural taxonomy name. */
			'not_found'     => sprintf( __( 'No %s yet. Use the form to add the first one.', 'wp-teamsphere' ), $plural ),
		);
	}

	/**
	 * Departments, teams and skills.
	 */
	private static function register_taxonomies() {
		$common = array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false, // Menu items are added manually under the plugin menu.
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_tagcloud'      => false,
			'rewrite'            => false,
			'query_var'          => false,
			'hierarchical'       => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_wpts_terms',
				'edit_terms'   => 'manage_wpts_terms',
				'delete_terms' => 'manage_wpts_terms',
				'assign_terms' => 'edit_wpts_employees',
			),
		);

		// Department: exactly one per employee in v1.0, so we render our own single select.
		register_taxonomy(
			self::TAX_DEPARTMENT,
			self::POST_TYPE,
			array_merge(
				$common,
				array(
					'labels'            => self::tax_labels( __( 'Department', 'wp-teamsphere' ), __( 'Departments', 'wp-teamsphere' ) ),
					'meta_box_cb'       => false,
					'show_admin_column'  => false,
					'show_in_quick_edit' => false,
				)
			)
		);

		// Team: many per employee, rendered as a checklist in our Organization box.
		register_taxonomy(
			self::TAX_TEAM,
			self::POST_TYPE,
			array_merge(
				$common,
				array(
					'labels'            => self::tax_labels( __( 'Team', 'wp-teamsphere' ), __( 'Teams', 'wp-teamsphere' ) ),
					'meta_box_cb'       => false,
					'show_admin_column'  => false,
					'show_in_quick_edit' => false,
				)
			)
		);

		// Skills: reusable tags, native tag box.
		register_taxonomy(
			self::TAX_SKILL,
			self::POST_TYPE,
			array_merge(
				$common,
				array(
					'labels' => self::tax_labels( __( 'Skill', 'wp-teamsphere' ), __( 'Skills', 'wp-teamsphere' ) ),
					'meta_box_cb' => false, // Skills use our own chip input.
				)
			)
		);
	}

	/**
	 * "Archived" status for former employees. Not public; records are kept.
	 */
	private static function register_statuses() {
		register_post_status(
			self::STATUS_ARCHIVED,
			array(
				'label'                     => _x( 'Archived', 'post status', 'wp-teamsphere' ),
				'public'                    => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of archived profiles. */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'wp-teamsphere' ),
			)
		);
	}
}
