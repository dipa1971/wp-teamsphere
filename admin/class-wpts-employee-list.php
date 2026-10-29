<?php
/**
 * Team Members list table: columns, department/team filters, archive / restore.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Employee_List {

	/**
	 * Hooks.
	 */
	public static function init() {
		$type = WPTS_Post_Types::POST_TYPE;

		add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ), 10, 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'admin_post_wpts_archive', array( __CLASS__, 'handle_archive' ) );
		add_action( 'admin_post_wpts_restore', array( __CLASS__, 'handle_restore' ) );
	}

	/**
	 * Columns: photo, name, job title, department, teams, reports to, date.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['wpts_photo'] = __( 'Photo', 'wp-teamsphere' );
			}
			if ( 'date' === $key ) {
				$new['wpts_manager'] = __( 'Reports to', 'wp-teamsphere' );
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wpts_job_title']  = __( 'Job title', 'wp-teamsphere' );
				$new['wpts_department'] = __( 'Department', 'wp-teamsphere' );
				$new['wpts_teams']      = __( 'Teams', 'wp-teamsphere' );
			}
		}

		return $new;
	}

	/**
	 * Column output.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wpts_photo':
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, array( 40, 40 ), array( 'class' => 'wpts-list-photo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<span class="dashicons dashicons-admin-users wpts-list-photo-empty" aria-hidden="true"></span>';
				}
				break;

			case 'wpts_job_title':
				$title = WPTS_Employee::get_field( $post_id, 'job_title' );
				echo '' !== $title ? esc_html( $title ) : '<span aria-hidden="true">—</span>';
				break;

			case 'wpts_department':
				$department = WPTS_Employee::get_department( $post_id );
				if ( $department ) {
					echo WPTS_Terms::chip( $department, add_query_arg( array( 'post_type' => WPTS_Post_Types::POST_TYPE, 'wpts_filter_dept' => $department->term_id ), admin_url( 'edit.php' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<span class="wpts-muted" aria-hidden="true">—</span>';
				}
				break;

			case 'wpts_teams':
				$teams = WPTS_Employee::get_teams( $post_id );
				if ( $teams ) {
					echo '<span class="wpts-chip-group">';
					foreach ( $teams as $team ) {
						echo WPTS_Terms::chip( $team, add_query_arg( array( 'post_type' => WPTS_Post_Types::POST_TYPE, 'wpts_filter_team' => $team->term_id ), admin_url( 'edit.php' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '</span>';
				} else {
					echo '<span class="wpts-muted" aria-hidden="true">—</span>';
				}
				break;

			case 'wpts_manager':
				$manager_id = (int) WPTS_Employee::get_field( $post_id, 'manager_id' );
				if ( $manager_id && WPTS_Post_Types::POST_TYPE === get_post_type( $manager_id ) ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( get_edit_post_link( $manager_id ) ),
						esc_html( get_the_title( $manager_id ) )
					);
				} else {
					echo '<span aria-hidden="true">—</span>';
				}
				break;
		}
	}

	/**
	 * Is this the Team Members list screen?
	 *
	 * @return bool
	 */
	private static function is_list_screen() {
		global $pagenow;
		return 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && WPTS_Post_Types::POST_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Department and team dropdowns above the table.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function filters( $post_type ) {
		if ( WPTS_Post_Types::POST_TYPE !== $post_type ) {
			return;
		}

		$map = array(
			'wpts_filter_dept' => array( WPTS_Post_Types::TAX_DEPARTMENT, __( 'All departments', 'wp-teamsphere' ) ),
			'wpts_filter_team' => array( WPTS_Post_Types::TAX_TEAM, __( 'All teams', 'wp-teamsphere' ) ),
		);

		foreach ( $map as $param => $config ) {
			$selected = isset( $_GET[ $param ] ) ? absint( wp_unslash( $_GET[ $param ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			wp_dropdown_categories(
				array(
					'taxonomy'        => $config[0],
					'name'            => $param,
					'show_option_all' => $config[1],
					'value_field'     => 'term_id',
					'selected'        => $selected,
					'hide_empty'      => false,
					'hierarchical'    => false,
					'orderby'         => 'name',
				)
			);
		}
	}

	/**
	 * Apply the dropdown filters to the list query.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function apply_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || ! self::is_list_screen() ) {
			return;
		}

		$map       = array(
			'wpts_filter_dept' => WPTS_Post_Types::TAX_DEPARTMENT,
			'wpts_filter_team' => WPTS_Post_Types::TAX_TEAM,
		);
		$tax_query = array();

		foreach ( $map as $param => $taxonomy ) {
			$term_id = isset( $_GET[ $param ] ) ? absint( wp_unslash( $_GET[ $param ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $term_id ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				);
			}
		}

		// Dashboard shortcuts: profiles that are missing a photo or a department.
		$missing = isset( $_GET['wpts_missing'] ) ? sanitize_key( wp_unslash( $_GET['wpts_missing'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'photo' === $missing ) {
			$query->set( 'post_status', array( 'publish', 'draft', 'pending', 'private' ) );
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					),
				)
			);
		} elseif ( 'department' === $missing ) {
			$query->set( 'post_status', array( 'publish', 'draft', 'pending', 'private' ) );
			$tax_query[] = array(
				'taxonomy' => WPTS_Post_Types::TAX_DEPARTMENT,
				'operator' => 'NOT EXISTS',
			);
		}

		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
	}

	/**
	 * Archive / Restore row actions.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( WPTS_Post_Types::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		if ( WPTS_Post_Types::STATUS_ARCHIVED === $post->post_status ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpts_restore&post=' . $post->ID ), 'wpts_restore_' . $post->ID );
			$actions['wpts_restore'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Restore as draft', 'wp-teamsphere' ) . '</a>';
		} elseif ( in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=wpts_archive&post=' . $post->ID ), 'wpts_archive_' . $post->ID );
			$actions['wpts_archive'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Archive', 'wp-teamsphere' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Show "Archived" next to the name in the All list.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function post_states( $states, $post ) {
		if ( WPTS_Post_Types::POST_TYPE === $post->post_type && WPTS_Post_Types::STATUS_ARCHIVED === $post->post_status ) {
			$states['wpts_archived'] = __( 'Archived', 'wp-teamsphere' );
		}
		return $states;
	}

	/** Archive handler. */
	public static function handle_archive() {
		self::change_status( 'wpts_archive_', WPTS_Post_Types::STATUS_ARCHIVED );
	}

	/** Restore handler (returns the profile as an unpublished draft). */
	public static function handle_restore() {
		self::change_status( 'wpts_restore_', 'draft' );
	}

	/**
	 * Shared status change with nonce + capability checks.
	 *
	 * @param string $nonce_prefix Nonce action prefix.
	 * @param string $status       New status.
	 */
	private static function change_status( $nonce_prefix, $status ) {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		check_admin_referer( $nonce_prefix . $post_id );

		if ( ! $post_id || WPTS_Post_Types::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'wp-teamsphere' ), '', array( 'response' => 403 ) );
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . WPTS_Post_Types::POST_TYPE ) );
		exit;
	}
}
