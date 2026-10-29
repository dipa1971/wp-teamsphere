<?php
/**
 * Admin menu, dashboard, notices and assets.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Admin {

	const MENU_SLUG = 'wpts-dashboard';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'register_submenus' ), 11 );
		add_filter( 'parent_file', array( __CLASS__, 'fix_parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'fix_submenu_file' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
	}

	/**
	 * Top level menu. Runs before the post type adds "Team Members" under it.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'WP TeamSphere', 'wp-teamsphere' ),
			__( 'WP TeamSphere', 'wp-teamsphere' ),
			'edit_wpts_employees',
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-groups',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'wp-teamsphere' ),
			__( 'Dashboard', 'wp-teamsphere' ),
			'edit_wpts_employees',
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' )
		);
	}

	/**
	 * Taxonomy screens, added after "Team Members".
	 */
	public static function register_submenus() {
		$items = array(
			WPTS_Post_Types::TAX_DEPARTMENT => __( 'Departments', 'wp-teamsphere' ),
			WPTS_Post_Types::TAX_TEAM       => __( 'Teams', 'wp-teamsphere' ),
			WPTS_Post_Types::TAX_SKILL      => __( 'Skills', 'wp-teamsphere' ),
		);

		foreach ( $items as $taxonomy => $label ) {
			add_submenu_page(
				self::MENU_SLUG,
				$label,
				$label,
				'manage_wpts_terms',
				'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . WPTS_Post_Types::POST_TYPE
			);
		}
	}

	/**
	 * Keep the plugin menu open on taxonomy screens.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public static function fix_parent_file( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && $screen->taxonomy && in_array( $screen->taxonomy, WPTS_Post_Types::taxonomies(), true ) ) {
			return self::MENU_SLUG;
		}
		return $parent_file;
	}

	/**
	 * Highlight the right submenu item.
	 *
	 * @param string $submenu_file Submenu file.
	 * @return string
	 */
	public static function fix_submenu_file( $submenu_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $submenu_file;
		}

		if ( $screen->taxonomy && in_array( $screen->taxonomy, WPTS_Post_Types::taxonomies(), true ) ) {
			return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=' . WPTS_Post_Types::POST_TYPE;
		}

		if ( WPTS_Post_Types::POST_TYPE === $screen->post_type && in_array( $screen->base, array( 'post', 'edit' ), true ) ) {
			return 'edit.php?post_type=' . WPTS_Post_Types::POST_TYPE;
		}

		return $submenu_file;
	}

	/**
	 * Queue a notice for the current user (shown on the next admin page load).
	 *
	 * @param string $message Plain text message.
	 */
	public static function add_notice( $message ) {
		$key     = 'wpts_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		$notices = is_array( $notices ) ? $notices : array();

		$notices[] = (string) $message;
		set_transient( $key, $notices, 120 );
	}

	/**
	 * Print queued notices.
	 */
	public static function render_notices() {
		$key     = 'wpts_notices_' . get_current_user_id();
		$notices = get_transient( $key );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		delete_transient( $key );

		foreach ( $notices as $message ) {
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Is the current screen one of ours?
	 *
	 * @return bool
	 */
	private static function is_our_screen() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return WPTS_Post_Types::POST_TYPE === $screen->post_type
			|| ( $screen->taxonomy && in_array( $screen->taxonomy, WPTS_Post_Types::taxonomies(), true ) )
			|| 'toplevel_page_' . self::MENU_SLUG === $screen->id
			|| false !== strpos( $screen->id, '_page_wpts-' );
	}

	/**
	 * Scope our styles with a body class on our screens only.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::is_our_screen() ? $classes . ' wpts-screen' : $classes;
	}

	/**
	 * Load assets only on our screens.
	 */
	public static function enqueue() {
		if ( ! self::is_our_screen() ) {
			return;
		}

		wp_enqueue_style( 'wpts-admin', WPTS_URL . 'admin/css/admin.css', array(), WPTS_VERSION );

		$screen = get_current_screen();
		if ( in_array( $screen->base, array( 'post', 'edit-tags', 'term' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'wpts-admin', WPTS_URL . 'admin/js/admin.js', array( 'jquery' ), WPTS_VERSION, true );
			wp_localize_script(
				'wpts-admin',
				'wptsAdmin',
				array(
					'chooseTitle'  => __( 'Choose an image', 'wp-teamsphere' ),
					'chooseButton' => __( 'Use this image', 'wp-teamsphere' ),
					'advanced'     => __( 'Advanced options (URL name)', 'wp-teamsphere' ),
					/* translators: %s: skill name. */
					'removeSkill'  => __( 'Remove %s', 'wp-teamsphere' ),
					'previewName'  => __( 'Display name', 'wp-teamsphere' ),
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Dashboard data helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Statuses that count as "active" (everything except archived and trash).
	 *
	 * @return string[]
	 */
	private static function active_statuses() {
		return array( 'publish', 'draft', 'pending', 'private' );
	}

	/**
	 * Number of terms in a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return int
	 */
	private static function term_total( $taxonomy ) {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	/**
	 * Count active profiles matching extra query args.
	 *
	 * @param array $args Extra WP_Query args.
	 * @return int
	 */
	private static function count_active( $args = array() ) {
		$query = new WP_Query(
			array_merge(
				array(
					'post_type'      => WPTS_Post_Types::POST_TYPE,
					'post_status'    => self::active_statuses(),
					'posts_per_page' => 1,
					'fields'         => 'ids',
				),
				$args
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Active profiles per department, largest first.
	 *
	 * @return array[] Each: name, count, color, url.
	 */
	private static function department_breakdown() {
		$list_url = admin_url( 'edit.php?post_type=' . WPTS_Post_Types::POST_TYPE );
		$totals   = WPTS_Terms::member_counts( WPTS_Post_Types::TAX_DEPARTMENT );
		$result   = array();

		foreach ( WPTS_Terms::get_departments() as $term ) {
			$result[] = array(
				'name'  => $term->name,
				'count' => isset( $totals[ $term->term_id ] ) ? $totals[ $term->term_id ] : 0,
				'color' => WPTS_Terms::get_color( $term->term_id ),
				'url'   => add_query_arg( 'wpts_filter_dept', $term->term_id, $list_url ),
			);
		}

		usort(
			$result,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		return $result;
	}

	/**
	 * Initial letter for avatar placeholders (multibyte safe).
	 *
	 * @param string $text Name.
	 * @return string
	 */
	private static function initial( $text ) {
		$text = trim( $text );
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
	 * Label and CSS modifier for a status badge.
	 *
	 * @param string $status Post status.
	 * @return array
	 */
	private static function badge( $status ) {
		switch ( $status ) {
			case 'publish':
				return array( __( 'Published', 'wp-teamsphere' ), 'is-success' );
			case 'draft':
				return array( __( 'Draft', 'wp-teamsphere' ), 'is-warning' );
			case 'pending':
				return array( __( 'Pending', 'wp-teamsphere' ), 'is-warning' );
			case 'private':
				return array( __( 'Private', 'wp-teamsphere' ), 'is-neutral' );
			case WPTS_Post_Types::STATUS_ARCHIVED:
				return array( __( 'Archived', 'wp-teamsphere' ), 'is-neutral' );
		}
		return array( $status, 'is-neutral' );
	}

	/**
	 * Time-of-day greeting for the current user.
	 *
	 * @return string
	 */
	private static function greeting() {
		$name = wp_get_current_user()->display_name;
		$hour = (int) current_time( 'G' );

		if ( $hour < 12 ) {
			/* translators: %s: user display name. */
			return sprintf( __( 'Good morning, %s', 'wp-teamsphere' ), $name );
		}
		if ( $hour < 17 ) {
			/* translators: %s: user display name. */
			return sprintf( __( 'Good afternoon, %s', 'wp-teamsphere' ), $name );
		}
		/* translators: %s: user display name. */
		return sprintf( __( 'Good evening, %s', 'wp-teamsphere' ), $name );
	}

	/* ---------------------------------------------------------------------
	 * Dashboard
	 * ------------------------------------------------------------------ */

	/**
	 * Dashboard screen.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_wpts_employees' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-teamsphere' ), '', array( 'response' => 403 ) );
		}

		$type      = WPTS_Post_Types::POST_TYPE;
		$archived  = WPTS_Post_Types::STATUS_ARCHIVED;
		$list_url  = admin_url( 'edit.php?post_type=' . $type );
		$dept_url  = admin_url( 'edit-tags.php?taxonomy=' . WPTS_Post_Types::TAX_DEPARTMENT . '&post_type=' . $type );
		$team_url  = admin_url( 'edit-tags.php?taxonomy=' . WPTS_Post_Types::TAX_TEAM . '&post_type=' . $type );
		$skill_url = admin_url( 'edit-tags.php?taxonomy=' . WPTS_Post_Types::TAX_SKILL . '&post_type=' . $type );
		$new_url   = admin_url( 'post-new.php?post_type=' . $type );

		// Counts.
		$counts = wp_count_posts( $type );
		$total  = 0;
		foreach ( (array) $counts as $status => $number ) {
			if ( in_array( $status, array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}
			$total += (int) $number;
		}

		$published  = (int) $counts->publish;
		$drafts     = (int) $counts->draft;
		$pending    = isset( $counts->pending ) ? (int) $counts->pending : 0;
		$archived_n = isset( $counts->$archived ) ? (int) $counts->$archived : 0;
		$active     = max( 0, $total - $archived_n );
		$percent    = $active ? (int) round( ( $published / $active ) * 100 ) : 0;
		$dept_total = self::term_total( WPTS_Post_Types::TAX_DEPARTMENT );
		$team_total = self::term_total( WPTS_Post_Types::TAX_TEAM );

		// Things that need attention.
		$no_photo = self::count_active(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$no_dept  = self::count_active(
			array(
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => WPTS_Post_Types::TAX_DEPARTMENT,
						'operator' => 'NOT EXISTS',
					),
				),
			)
		);

		$attention = array(
			array( __( 'Profiles waiting to be published', 'wp-teamsphere' ), $drafts + $pending, add_query_arg( 'post_status', 'draft', $list_url ) ),
			array( __( 'Profiles without a photo', 'wp-teamsphere' ), $no_photo, add_query_arg( 'wpts_missing', 'photo', $list_url ) ),
			array( __( 'Profiles without a department', 'wp-teamsphere' ), $no_dept, add_query_arg( 'wpts_missing', 'department', $list_url ) ),
		);
		$attention_total = 0;
		foreach ( $attention as $item ) {
			$attention_total += (int) $item[1];
		}

		$departments = self::department_breakdown();
		$max_dept    = 1;
		foreach ( $departments as $department ) {
			$max_dept = max( $max_dept, $department['count'] );
		}

		$recent = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => array_merge( self::active_statuses(), array( $archived ) ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		// Privacy summary.
		$all_fields   = WPTS_Fields::all();
		$public_count = count( WPTS_Fields::public_keys() );
		$locked       = array();
		foreach ( $all_fields as $def ) {
			if ( ! empty( $def['locked'] ) ) {
				$locked[] = $def['label'];
			}
		}

		$actions = array(
			array( 'dashicons-admin-users', __( 'Add team member', 'wp-teamsphere' ), __( 'Create a new profile', 'wp-teamsphere' ), $new_url, current_user_can( 'create_wpts_employees' ) ),
			array( 'dashicons-building', __( 'Add department', 'wp-teamsphere' ), __( 'Group people by function', 'wp-teamsphere' ), $dept_url, current_user_can( 'manage_wpts_terms' ) ),
			array( 'dashicons-groups', __( 'Add team', 'wp-teamsphere' ), __( 'Create a working group', 'wp-teamsphere' ), $team_url, current_user_can( 'manage_wpts_terms' ) ),
			array( 'dashicons-tag', __( 'Manage skills', 'wp-teamsphere' ), __( 'Reusable skills and expertise', 'wp-teamsphere' ), $skill_url, current_user_can( 'manage_wpts_terms' ) ),
		);

		$stats = array(
			array( __( 'Total team members', 'wp-teamsphere' ), $total, sprintf( /* translators: 1: active count, 2: archived count. */ __( '%1$d active, %2$d archived', 'wp-teamsphere' ), $active, $archived_n ), $list_url, false ),
			array( __( 'Published profiles', 'wp-teamsphere' ), $published, sprintf( /* translators: %d: percentage. */ __( '%d%% of active profiles', 'wp-teamsphere' ), $percent ), add_query_arg( 'post_status', 'publish', $list_url ), true ),
			array( __( 'Draft profiles', 'wp-teamsphere' ), $drafts, __( 'Not visible on the website', 'wp-teamsphere' ), add_query_arg( 'post_status', 'draft', $list_url ), false ),
			array( __( 'Archived', 'wp-teamsphere' ), $archived_n, __( 'Former members, kept on record', 'wp-teamsphere' ), add_query_arg( 'post_status', $archived, $list_url ), false ),
			array( __( 'Departments', 'wp-teamsphere' ), $dept_total, __( 'One per team member', 'wp-teamsphere' ), $dept_url, false ),
			array( __( 'Teams', 'wp-teamsphere' ), $team_total, __( 'Members can join several', 'wp-teamsphere' ), $team_url, false ),
		);

		// ---- Extra values used only by the new design (no data logic changed) ----
		$stat_icons   = array( 'dashicons-groups', 'dashicons-yes-alt', 'dashicons-edit', 'dashicons-archive' );
		$stat_tones   = array( 'is-teal', 'is-yellow', 'is-teal', 'is-navy' );

		// Donut chart (published / draft & others / archived).
		$other_n = max( 0, $total - $published - $archived_n );
		$pub_end = $total ? (int) round( ( $published / $total ) * 100 ) : 0;
		$oth_end = $total ? min( 100, $pub_end + (int) round( ( $other_n / $total ) * 100 ) ) : 0;
		$donut   = $total
			? sprintf( 'conic-gradient(#0b4d63 0 %1$d%%, #f6b955 %1$d%% %2$d%%, #232a4a %2$d%% 100%%)', $pub_end, $oth_end )
			: 'conic-gradient(#e3e8ee 0 100%)';
		?>
		<div class="wrap wpts-dashboard">
			<h1 class="screen-reader-text"><?php esc_html_e( 'WP TeamSphere Dashboard', 'wp-teamsphere' ); ?></h1>

			<div class="wpts-app">

				<!-- ============ Main ============ -->
				<div class="wpts-d-main">

					<div class="wpts-d-top">
						<div>
							<h2 class="wpts-d-title"><?php echo esc_html( self::greeting() ); ?></h2>
							<p class="wpts-d-sub">
								<?php
								echo esc_html( wp_date( 'l, ' . get_option( 'date_format' ) ) );
								echo ' &middot; ';
								/* translators: %s: number of active team members. */
								echo esc_html( sprintf( _n( '%s active team member', '%s active team members', $active, 'wp-teamsphere' ), number_format_i18n( $active ) ) );
								?>
							</p>
						</div>
						<div class="wpts-d-top-actions">
							<?php if ( current_user_can( 'create_wpts_employees' ) ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add team member', 'wp-teamsphere' ); ?></a>
							<?php endif; ?>
							<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'View all', 'wp-teamsphere' ); ?></a>
						</div>
					</div>

					<!-- Coloured stat cards (first 4 of $stats) -->
					<div class="wpts-d-stats">
						<?php foreach ( array_slice( $stats, 0, 4 ) as $i => $stat ) : ?>
							<a class="wpts-d-stat <?php echo esc_attr( $stat_tones[ $i ] ); ?>" href="<?php echo esc_url( $stat[3] ); ?>">
								<span class="wpts-d-stat-icon dashicons <?php echo esc_attr( $stat_icons[ $i ] ); ?>" aria-hidden="true"></span>
								<span class="wpts-d-stat-label"><?php echo esc_html( $stat[0] ); ?></span>
								<span class="wpts-d-stat-number"><?php echo esc_html( number_format_i18n( $stat[1] ) ); ?></span>
								<?php if ( $stat[4] ) : ?>
									<span class="wpts-progress" role="presentation"><span style="width: <?php echo (int) $percent; ?>%"></span></span>
								<?php endif; ?>
								<span class="wpts-d-stat-hint">
									<span><?php echo esc_html( $stat[2] ); ?></span>
									<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
								</span>
							</a>
						<?php endforeach; ?>
					</div>

					<!-- Row 1: Recently updated + Team by department -->
					<div class="wpts-d-row is-wide">

						<section class="wpts-panel wpts-card">
							<div class="wpts-panel-head">
								<div>
									<h2><?php esc_html_e( 'Recently updated', 'wp-teamsphere' ); ?></h2>
									<p class="wpts-panel-sub"><?php esc_html_e( 'The latest changes to profiles.', 'wp-teamsphere' ); ?></p>
								</div>
								<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'View all', 'wp-teamsphere' ); ?></a>
							</div>
							<?php if ( $recent->have_posts() ) : ?>
								<ul class="wpts-d-list">
									<?php
									while ( $recent->have_posts() ) :
										$recent->the_post();
										$post_id = get_the_ID();
										$title   = get_the_title();
										$job     = WPTS_Employee::get_field( $post_id, 'job_title' );
										$badge   = self::badge( get_post_status( $post_id ) );
										?>
										<li>
											<?php
											if ( has_post_thumbnail( $post_id ) ) {
												echo get_the_post_thumbnail( $post_id, array( 40, 40 ), array( 'class' => 'wpts-avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
											} else {
												echo '<span class="wpts-avatar wpts-avatar-initial" aria-hidden="true">' . esc_html( self::initial( $title ) ) . '</span>';
											}
											?>
											<span class="wpts-recent-text">
												<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php echo esc_html( '' !== $title ? $title : __( '(no name)', 'wp-teamsphere' ) ); ?></a>
												<span class="wpts-muted">
													<?php
													if ( '' !== $job ) {
														echo esc_html( $job ) . ' &middot; ';
													}
													/* translators: %s: human readable time difference, e.g. "2 hours". */
													echo esc_html( sprintf( __( '%s ago', 'wp-teamsphere' ), human_time_diff( get_post_modified_time( 'U', true ), time() ) ) );
													?>
												</span>
											</span>
											<span class="wpts-badge <?php echo esc_attr( $badge[1] ); ?>"><?php echo esc_html( $badge[0] ); ?></span>
										</li>
									<?php endwhile; ?>
								</ul>
								<?php wp_reset_postdata(); ?>
							<?php else : ?>
								<p><?php esc_html_e( 'No team members yet.', 'wp-teamsphere' ); ?></p>
							<?php endif; ?>
						</section>

						<?php if ( 0 === $total ) : ?>
							<section class="wpts-panel wpts-card">
								<h2><?php esc_html_e( 'Get started', 'wp-teamsphere' ); ?></h2>
								<p class="wpts-panel-sub"><?php esc_html_e( 'Three steps to your first team directory.', 'wp-teamsphere' ); ?></p>
								<ol class="wpts-steps">
									<li class="<?php echo $dept_total ? 'is-done' : ''; ?>"><a href="<?php echo esc_url( $dept_url ); ?>"><?php esc_html_e( 'Create your departments', 'wp-teamsphere' ); ?></a></li>
									<li class="<?php echo $team_total ? 'is-done' : ''; ?>"><a href="<?php echo esc_url( $team_url ); ?>"><?php esc_html_e( 'Create your teams', 'wp-teamsphere' ); ?></a></li>
									<li><a href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add your first team member', 'wp-teamsphere' ); ?></a></li>
								</ol>
							</section>
						<?php else : ?>
							<section class="wpts-panel wpts-card">
								<div class="wpts-panel-head">
									<div>
										<h2><?php esc_html_e( 'Team by department', 'wp-teamsphere' ); ?></h2>
										<p class="wpts-panel-sub"><?php esc_html_e( 'Active profiles in each department.', 'wp-teamsphere' ); ?></p>
									</div>
									<a href="<?php echo esc_url( $dept_url ); ?>"><?php esc_html_e( 'Manage', 'wp-teamsphere' ); ?></a>
								</div>
								<?php if ( empty( $departments ) ) : ?>
									<p><?php esc_html_e( 'No departments yet.', 'wp-teamsphere' ); ?> <a href="<?php echo esc_url( $dept_url ); ?>"><?php esc_html_e( 'Create one', 'wp-teamsphere' ); ?></a></p>
								<?php else : ?>
									<ul class="wpts-d-depts">
										<?php foreach ( array_slice( $departments, 0, 8 ) as $department ) : ?>
											<li>
												<a class="wpts-d-dept" href="<?php echo esc_url( $department['url'] ); ?>" style="--wpts-bar: <?php echo esc_attr( $department['color'] ); ?>">
													<span class="wpts-d-dept-icon" aria-hidden="true"><?php echo esc_html( self::initial( $department['name'] ) ); ?></span>
													<span>
														<span class="wpts-d-dept-name"><?php echo esc_html( $department['name'] ); ?></span>
														<span class="wpts-d-dept-track" role="presentation"><span style="width: <?php echo (int) round( ( $department['count'] / $max_dept ) * 100 ); ?>%"></span></span>
													</span>
													<span class="wpts-d-dept-count"><?php echo esc_html( number_format_i18n( $department['count'] ) ); ?></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</section>
						<?php endif; ?>
					</div>

					<!-- Row 2: Profile status (donut) + Needs attention -->
					<div class="wpts-d-row">

						<section class="wpts-panel wpts-card">
							<h2><?php esc_html_e( 'Profile status', 'wp-teamsphere' ); ?></h2>
							<p class="wpts-panel-sub"><?php esc_html_e( 'How your profiles are split.', 'wp-teamsphere' ); ?></p>
							<div class="wpts-d-donut-wrap" style="margin-top: 14px;">
								<div class="wpts-d-donut" style="background: <?php echo esc_attr( $donut ); ?>">
									<div class="wpts-d-donut-center">
										<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
										<span><?php esc_html_e( 'members', 'wp-teamsphere' ); ?></span>
									</div>
								</div>
								<ul class="wpts-d-legend">
									<li><i style="background:#0b4d63"></i><?php esc_html_e( 'Published', 'wp-teamsphere' ); ?><b><?php echo esc_html( number_format_i18n( $published ) ); ?></b></li>
									<li><i style="background:#f6b955"></i><?php esc_html_e( 'Draft / pending', 'wp-teamsphere' ); ?><b><?php echo esc_html( number_format_i18n( $other_n ) ); ?></b></li>
									<li><i style="background:#232a4a"></i><?php esc_html_e( 'Archived', 'wp-teamsphere' ); ?><b><?php echo esc_html( number_format_i18n( $archived_n ) ); ?></b></li>
								</ul>
							</div>
							<div class="wpts-d-tiles">
								<?php foreach ( array_slice( $stats, 4, 2 ) as $stat ) : ?>
									<a class="wpts-d-tile" href="<?php echo esc_url( $stat[3] ); ?>">
										<strong><?php echo esc_html( number_format_i18n( $stat[1] ) ); ?></strong>
										<span><?php echo esc_html( $stat[0] ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="wpts-panel wpts-card">
							<div class="wpts-panel-head">
								<h2><?php esc_html_e( 'Needs attention', 'wp-teamsphere' ); ?></h2>
								<?php if ( $attention_total ) : ?>
									<span class="wpts-badge is-warning">
										<?php
										/* translators: %d: number of items to review. */
										echo esc_html( sprintf( _n( '%d to review', '%d to review', $attention_total, 'wp-teamsphere' ), $attention_total ) );
										?>
									</span>
								<?php else : ?>
									<span class="wpts-badge is-success"><?php esc_html_e( 'All clear', 'wp-teamsphere' ); ?></span>
								<?php endif; ?>
							</div>
							<ul class="wpts-attention">
								<?php foreach ( $attention as $item ) : ?>
									<li class="<?php echo $item[1] ? 'has-items' : 'is-clear'; ?>">
										<span class="wpts-attention-count"><?php echo esc_html( number_format_i18n( $item[1] ) ); ?></span>
										<span class="wpts-attention-label"><?php echo esc_html( $item[0] ); ?></span>
										<?php if ( $item[1] ) : ?>
											<a href="<?php echo esc_url( $item[2] ); ?>"><?php esc_html_e( 'Review', 'wp-teamsphere' ); ?></a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</section>
					</div>

					<!-- Row 3: Quick actions + Privacy overview -->
					<div class="wpts-d-row">

						<section class="wpts-panel wpts-card">
							<h2><?php esc_html_e( 'Quick actions', 'wp-teamsphere' ); ?></h2>
							<div class="wpts-actions">
								<?php foreach ( $actions as $action ) : ?>
									<?php if ( $action[4] ) : ?>
										<a class="wpts-action" href="<?php echo esc_url( $action[3] ); ?>">
											<span class="dashicons <?php echo esc_attr( $action[0] ); ?>" aria-hidden="true"></span>
											<span>
												<strong><?php echo esc_html( $action[1] ); ?></strong>
												<small><?php echo esc_html( $action[2] ); ?></small>
											</span>
										</a>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="wpts-panel wpts-card">
							<h2><?php esc_html_e( 'Privacy overview', 'wp-teamsphere' ); ?></h2>
							<p class="wpts-panel-sub">
								<?php
								/* translators: 1: number of public profile fields, 2: total number of profile fields. */
								echo esc_html( sprintf( __( '%1$d of %2$d profile fields are shown on the public site.', 'wp-teamsphere' ), $public_count, count( $all_fields ) ) );
								?>
							</p>
							<p class="wpts-chip-label"><?php esc_html_e( 'Always private', 'wp-teamsphere' ); ?></p>
							<ul class="wpts-chips">
								<?php foreach ( $locked as $label ) : ?>
									<li><?php echo esc_html( $label ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					</div>

				</div><!-- /.wpts-d-main -->
			</div><!-- /.wpts-app -->
		</div>
		<?php
	}
}