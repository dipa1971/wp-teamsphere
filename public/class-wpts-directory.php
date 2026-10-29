<?php
/**
 * The [wpts_team] shortcode: grid / list / compact directory with
 * department tabs, search, team and skill filters, and Previous / Next pagination.
 *
 * Filters live in the URL (?wpts_q=...&wpts_dept=3), so they work without JavaScript,
 * can be bookmarked, and survive page changes. Every profile goes through
 * WPTS_Employee::get_public_profile(), so private data can never leak from here.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Directory {

	/**
	 * How many directories have been printed on this page (keeps URL parameters separate).
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'wpts_team', array( __CLASS__, 'shortcode' ) );
		add_filter( 'posts_search', array( __CLASS__, 'search_clause' ), 10, 2 );
	}

	/**
	 * Is a shortcode value "on"?
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function yes( $value ) {
		return ! in_array( strtolower( trim( (string) $value ) ), array( 'no', 'false', '0', 'off' ), true );
	}

	/**
	 * Resolve a comma separated list of IDs or slugs to term IDs.
	 * Private departments / teams are ignored. Returns null when the attribute is empty.
	 *
	 * @param string $value    Attribute value.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]|null
	 */
	private static function resolve_terms( $value, $taxonomy ) {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
		if ( ! $parts ) {
			return null;
		}

		$ids = array();
		foreach ( $parts as $part ) {
			$term = ctype_digit( $part ) ? get_term( (int) $part, $taxonomy ) : get_term_by( 'slug', sanitize_title( $part ), $taxonomy );
			if ( $term && ! is_wp_error( $term ) && ( WPTS_Post_Types::TAX_SKILL === $taxonomy || WPTS_Terms::is_public( $term->term_id ) ) ) {
				$ids[] = (int) $term->term_id;
			}
		}

		return $ids;
	}

	/**
	 * Visible fields per layout, or the list given in show="...".
	 *
	 * @param string $value  show attribute.
	 * @param string $layout Layout.
	 * @return string[]
	 */
	private static function parse_show( $value, $layout ) {
		$defaults = array(
			'grid'    => array( 'photo', 'name', 'title', 'department', 'social' ),
			'list'    => array( 'photo', 'name', 'title', 'department', 'intro', 'social', 'button' ),
			'compact' => array( 'photo', 'name', 'title' ),
		);
		$allowed  = array( 'photo', 'name', 'title', 'department', 'team', 'intro', 'social', 'button' );

		$chosen = array_intersect( $allowed, array_map( 'sanitize_key', explode( ',', (string) $value ) ) );

		return $chosen ? array_values( $chosen ) : $defaults[ $layout ];
	}

	/**
	 * Keyword search across name, biography, public fields and skill names.
	 * Private fields (internal notes, personal phone, hidden email...) are never searched.
	 *
	 * @param string   $search SQL.
	 * @param WP_Query $query  Query.
	 * @return string
	 */
	public static function search_clause( $search, $query ) {
		$term = $query->get( 'wpts_search' );
		if ( ! $term || ! is_string( $term ) ) {
			return $search;
		}

		global $wpdb;

		$words = array_slice( preg_split( '/\s+/', trim( $term ), -1, PREG_SPLIT_NO_EMPTY ), 0, 5 );
		if ( ! $words ) {
			return $search;
		}

		$meta_keys = array();
		foreach ( array_intersect( array( 'first_name', 'last_name', 'job_title', 'short_intro' ), WPTS_Fields::public_keys() ) as $key ) {
			$meta_keys[] = WPTS_Fields::meta_key( $key );
		}

		$clauses = array();
		foreach ( $words as $word ) {
			$like  = '%' . $wpdb->esc_like( $word ) . '%';
			$parts = array( "{$wpdb->posts}.post_title LIKE %s", "{$wpdb->posts}.post_content LIKE %s" );
			$args  = array( $like, $like );

			if ( $meta_keys ) {
				$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
				$parts[]      = "{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value LIKE %s )";
				$args         = array_merge( $args, $meta_keys, array( $like ) );
			}

			$parts[] = "{$wpdb->posts}.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND t.name LIKE %s )";
			$args[]  = WPTS_Post_Types::TAX_SKILL;
			$args[]  = $like;

			$clauses[] = $wpdb->prepare( '( ' . implode( ' OR ', $parts ) . ' )', $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return ' AND ' . implode( ' AND ', $clauses ) . ' ';
	}

	/**
	 * Shortcode output.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$raw  = is_array( $atts ) ? $atts : array();
		$atts = shortcode_atts(
			array(
				'layout'     => '',
				'columns'    => '',
				'department' => '',
				'team'       => '',
				'skill'      => '',
				'members'    => '',
				'per_page'   => '',
				'number'     => '',
				'search'     => '',
				'filters'    => '',
				'pagination' => 'yes',
				'show'       => '',
				'shape'      => '',
				'orderby'    => 'name',
			),
			$atts,
			'wpts_team'
		);

		self::$instance++;
		$n      = self::$instance;
		$prefix = 'wpts' . ( $n > 1 ? $n : '' ) . '_';

		WPTS_Public::enqueue();

		/* ---- Layout, columns, page size ---- */
		$layouts = array( 'grid', 'list', 'compact' );
		$layout  = in_array( $atts['layout'], $layouts, true ) ? $atts['layout'] : WPTS_Settings::get( 'default_layout', 'grid' );
		if ( ! in_array( $layout, $layouts, true ) ) {
			$layout = 'grid';
		}

		$columns = absint( $atts['columns'] );
		if ( ! $columns ) {
			$columns = 'compact' === $layout ? 4 : 3;
		}
		$columns = min( 5, max( 2, $columns ) );

		$per_page = absint( $atts['per_page'] ? $atts['per_page'] : $atts['number'] );
		if ( ! $per_page ) {
			$per_page = (int) WPTS_Settings::get( 'per_page', 12 );
		}
		$per_page = min( 60, max( 1, $per_page ) );

		$shape = in_array( $atts['shape'], array( 'rounded', 'circle', 'square' ), true ) ? $atts['shape'] : WPTS_Public::shape();
		$show  = self::parse_show( $atts['show'], $layout );

		/* ---- What the shortcode fixes in advance ---- */
		$members_given = '' !== trim( (string) $atts['members'] );
		$member_ids    = $members_given ? array_values( array_filter( array_map( 'absint', explode( ',', $atts['members'] ) ) ) ) : array();
		$fixed_dept    = self::resolve_terms( $atts['department'], WPTS_Post_Types::TAX_DEPARTMENT );
		$fixed_team    = self::resolve_terms( $atts['team'], WPTS_Post_Types::TAX_TEAM );
		$fixed_skill   = self::resolve_terms( $atts['skill'], WPTS_Post_Types::TAX_SKILL );

		$search_on  = isset( $raw['search'] ) ? self::yes( $raw['search'] ) : ! $members_given;
		$filters_on = isset( $raw['filters'] ) ? self::yes( $raw['filters'] ) : ! $members_given;
		$pager_on   = self::yes( $atts['pagination'] );

		/* ---- Filter choices (only public departments / teams, only ones that have people) ---- */
		$departments = array();
		$teams       = array();
		$skills      = array();

		if ( $filters_on ) {
			if ( null === $fixed_dept ) {
				$departments = array_values(
					array_filter(
						WPTS_Terms::get_public_departments(),
						function ( $term ) {
							return $term->count > 0;
						}
					)
				);
			}
			if ( null === $fixed_team ) {
				$teams = array_values(
					array_filter(
						WPTS_Terms::get_public_teams(),
						function ( $term ) {
							return $term->count > 0;
						}
					)
				);
			}
			if ( null === $fixed_skill ) {
				$found_skills = get_terms(
					array(
						'taxonomy'   => WPTS_Post_Types::TAX_SKILL,
						'hide_empty' => true,
						'orderby'    => 'name',
					)
				);
				$skills       = is_wp_error( $found_skills ) ? array() : $found_skills;
			}
		}

		/* ---- Current visitor choices from the URL ---- */
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filtering, no state is changed.
		$q = ( $search_on && isset( $_GET[ $prefix . 'q' ] ) ) ? sanitize_text_field( wp_unslash( $_GET[ $prefix . 'q' ] ) ) : '';
		$q = trim( wp_html_excerpt( $q, 100 ) );

		$dept  = isset( $_GET[ $prefix . 'dept' ] ) ? absint( wp_unslash( $_GET[ $prefix . 'dept' ] ) ) : 0;
		$team  = isset( $_GET[ $prefix . 'team' ] ) ? absint( wp_unslash( $_GET[ $prefix . 'team' ] ) ) : 0;
		$skill = isset( $_GET[ $prefix . 'skill' ] ) ? absint( wp_unslash( $_GET[ $prefix . 'skill' ] ) ) : 0;
		$page  = isset( $_GET[ $prefix . 'page' ] ) ? max( 1, absint( wp_unslash( $_GET[ $prefix . 'page' ] ) ) ) : 1;
		// phpcs:enable

		// Only accept choices that are actually offered.
		$dept  = in_array( $dept, array_map( 'intval', wp_list_pluck( $departments, 'term_id' ) ), true ) ? $dept : 0;
		$team  = in_array( $team, array_map( 'intval', wp_list_pluck( $teams, 'term_id' ) ), true ) ? $team : 0;
		$skill = in_array( $skill, array_map( 'intval', wp_list_pluck( $skills, 'term_id' ) ), true ) ? $skill : 0;

		/* ---- Query ---- */
		$args = array(
			'post_type'           => WPTS_Post_Types::POST_TYPE,
			'post_status'         => 'publish', // Unpublished profiles can never appear here.
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'orderby'             => 'title',
			'order'               => 'ASC',
		);

		if ( 'newest' === $atts['orderby'] ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		if ( $members_given ) {
			$args['post__in'] = $member_ids ? $member_ids : array( 0 );
			$args['orderby']  = 'post__in';
		}

		$tax_query = array( 'relation' => 'AND' );
		$wanted    = array(
			array( WPTS_Post_Types::TAX_DEPARTMENT, $fixed_dept, $dept ),
			array( WPTS_Post_Types::TAX_TEAM, $fixed_team, $team ),
			array( WPTS_Post_Types::TAX_SKILL, $fixed_skill, $skill ),
		);

		foreach ( $wanted as $item ) {
			if ( null !== $item[1] ) {
				if ( empty( $item[1] ) ) {
					$args['post__in'] = array( 0 ); // Shortcode points at nothing valid or public: show nothing.
				} else {
					$tax_query[] = array(
						'taxonomy' => $item[0],
						'field'    => 'term_id',
						'terms'    => $item[1],
					);
				}
			}
			if ( $item[2] ) {
				$tax_query[] = array(
					'taxonomy' => $item[0],
					'field'    => 'term_id',
					'terms'    => array( $item[2] ),
				);
			}
		}

		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		if ( '' !== $q ) {
			$args['wpts_search'] = $q;
		}

		$query = new WP_Query( $args );

		// A page number beyond the last page: fall back to the last one.
		if ( $page > 1 && $query->max_num_pages && $page > $query->max_num_pages ) {
			$page          = (int) $query->max_num_pages;
			$args['paged'] = $page;
			$query         = new WP_Query( $args );
		}

		$found     = (int) $query->found_posts;
		$max_pages = (int) $query->max_num_pages;
		$filtered  = ( '' !== $q || $dept || $team || $skill );

		/* ---- URLs ---- */
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$keys = array( $prefix . 'q', $prefix . 'dept', $prefix . 'team', $prefix . 'skill', $prefix . 'page' );
		$base = remove_query_arg( $keys, $uri );

		$state   = array(
			$prefix . 'q'     => $q,
			$prefix . 'dept'  => $dept,
			$prefix . 'team'  => $team,
			$prefix . 'skill' => $skill,
		);
		$url_for = function ( $override = array(), $to_page = 1 ) use ( $base, $state, $prefix ) {
			$query_args = array_filter(
				array_merge( $state, $override ),
				function ( $value ) {
					return '' !== $value && 0 !== $value && null !== $value;
				}
			);
			if ( $to_page > 1 ) {
				$query_args[ $prefix . 'page' ] = $to_page;
			}
			return $query_args ? add_query_arg( $query_args, $base ) : $base;
		};

		// Other query parameters of the page (for example ?page_id=12) must survive the search form.
		$carry = array();
		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_scalar( $value ) && ! in_array( $key, $keys, true ) ) {
				$carry[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}
		}

		$show_tabs    = ! empty( $departments );
		$show_selects = ( ! empty( $teams ) || ! empty( $skills ) );
		$show_bar     = $search_on || $show_selects;
		$show_count   = $search_on || $filters_on;

		if ( $found > 0 ) {
			$from = ( $page - 1 ) * $per_page + 1;
			$to   = min( $found, $page * $per_page );
			if ( $pager_on && $max_pages > 1 ) {
				/* translators: 1: first number on this page, 2: last number on this page, 3: total. */
				$count_text = sprintf( __( 'Showing %1$s–%2$s of %3$s team members', 'wp-teamsphere' ), number_format_i18n( $from ), number_format_i18n( $to ), number_format_i18n( $found ) );
			} else {
				/* translators: %s: number of team members. */
				$count_text = sprintf( _n( '%s team member', '%s team members', $found, 'wp-teamsphere' ), number_format_i18n( $found ) );
			}
		}

		ob_start();
		?>
		<div id="wpts-directory-<?php echo (int) $n; ?>" class="wpts wpts-directory wpts-layout-<?php echo esc_attr( $layout ); ?> wpts-cols-<?php echo (int) $columns; ?> wpts-shape-<?php echo esc_attr( $shape ); ?>">

			<?php if ( $show_tabs ) : ?>
				<nav class="wpts-tabs" aria-label="<?php esc_attr_e( 'Departments', 'wp-teamsphere' ); ?>">
					<ul>
						<li><a class="wpts-tab wpts-tab--all" href="<?php echo esc_url( $url_for( array( $prefix . 'dept' => 0 ) ) ); ?>"<?php echo 0 === $dept ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'All', 'wp-teamsphere' ); ?></a></li>
						<?php foreach ( $departments as $term ) : ?>
							<li>
								<a class="wpts-tab" style="--wpts-dot: <?php echo esc_attr( WPTS_Terms::get_color( $term->term_id ) ); ?>" href="<?php echo esc_url( $url_for( array( $prefix . 'dept' => (int) $term->term_id ) ) ); ?>"<?php echo $dept === (int) $term->term_id ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $term->name ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<?php if ( $show_bar ) : ?>
				<form class="wpts-search" method="get" action="<?php echo esc_url( $base ); ?>" role="search" aria-label="<?php esc_attr_e( 'Search the team', 'wp-teamsphere' ); ?>">
					<?php foreach ( $carry as $name => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
					<?php endforeach; ?>
					<?php if ( $dept ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $prefix . 'dept' ); ?>" value="<?php echo (int) $dept; ?>">
					<?php endif; ?>

					<?php if ( $search_on ) : ?>
						<div class="wpts-field">
							<label class="wpts-sr" for="<?php echo esc_attr( 'wpts-q-' . $n ); ?>"><?php esc_html_e( 'Search by name, role or skill', 'wp-teamsphere' ); ?></label>
							<input type="search" id="<?php echo esc_attr( 'wpts-q-' . $n ); ?>" name="<?php echo esc_attr( $prefix . 'q' ); ?>" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Search by name, role or skill', 'wp-teamsphere' ); ?>">
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $teams ) ) : ?>
						<div>
							<label class="wpts-sr" for="<?php echo esc_attr( 'wpts-team-' . $n ); ?>"><?php esc_html_e( 'Team', 'wp-teamsphere' ); ?></label>
							<select class="wpts-select" id="<?php echo esc_attr( 'wpts-team-' . $n ); ?>" name="<?php echo esc_attr( $prefix . 'team' ); ?>">
								<option value="0"><?php esc_html_e( 'All teams', 'wp-teamsphere' ); ?></option>
								<?php foreach ( $teams as $term ) : ?>
									<option value="<?php echo (int) $term->term_id; ?>" <?php selected( $team, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $skills ) ) : ?>
						<div>
							<label class="wpts-sr" for="<?php echo esc_attr( 'wpts-skill-' . $n ); ?>"><?php esc_html_e( 'Skill', 'wp-teamsphere' ); ?></label>
							<select class="wpts-select" id="<?php echo esc_attr( 'wpts-skill-' . $n ); ?>" name="<?php echo esc_attr( $prefix . 'skill' ); ?>">
								<option value="0"><?php esc_html_e( 'All skills', 'wp-teamsphere' ); ?></option>
								<?php foreach ( $skills as $term ) : ?>
									<option value="<?php echo (int) $term->term_id; ?>" <?php selected( $skill, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<button type="submit" class="wpts-submit"><?php esc_html_e( 'Search', 'wp-teamsphere' ); ?></button>

					<?php if ( $filtered ) : ?>
						<a class="wpts-clear" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Clear', 'wp-teamsphere' ); ?></a>
					<?php endif; ?>
				</form>
			<?php endif; ?>

			<?php if ( $found > 0 ) : ?>
				<?php if ( $show_count ) : ?>
					<p class="wpts-count" aria-live="polite"><?php echo esc_html( $count_text ); ?></p>
				<?php endif; ?>

				<div class="wpts-items wpts-items--<?php echo esc_attr( $layout ); ?>">
					<?php
					foreach ( $query->posts as $post ) {
						$profile = WPTS_Employee::get_public_profile( $post->ID );
						if ( $profile ) {
							echo self::render_item( $profile, $layout, $show ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
						}
					}
					?>
				</div>

				<?php if ( $pager_on && $max_pages > 1 ) : ?>
					<nav class="wpts-pagination" aria-label="<?php esc_attr_e( 'Team pages', 'wp-teamsphere' ); ?>">
						<?php if ( $page > 1 ) : ?>
							<a class="wpts-page-link" rel="prev" href="<?php echo esc_url( $url_for( array(), $page - 1 ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'wp-teamsphere' ); ?></a>
						<?php else : ?>
							<span class="wpts-page-link is-disabled" aria-disabled="true">&larr; <?php esc_html_e( 'Previous', 'wp-teamsphere' ); ?></span>
						<?php endif; ?>

						<span class="wpts-page-status">
							<?php
							/* translators: 1: current page, 2: total pages. */
							echo esc_html( sprintf( __( 'Page %1$s of %2$s', 'wp-teamsphere' ), number_format_i18n( $page ), number_format_i18n( $max_pages ) ) );
							?>
						</span>

						<?php if ( $page < $max_pages ) : ?>
							<a class="wpts-page-link" rel="next" href="<?php echo esc_url( $url_for( array(), $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'wp-teamsphere' ); ?> &rarr;</a>
						<?php else : ?>
							<span class="wpts-page-link is-disabled" aria-disabled="true"><?php esc_html_e( 'Next', 'wp-teamsphere' ); ?> &rarr;</span>
						<?php endif; ?>
					</nav>
				<?php endif; ?>

			<?php else : ?>
				<div class="wpts-empty">
					<?php if ( $filtered ) : ?>
						<p><strong><?php esc_html_e( 'No team members match your search.', 'wp-teamsphere' ); ?></strong></p>
						<p><?php esc_html_e( 'Try a different word, or remove a filter.', 'wp-teamsphere' ); ?></p>
						<p><a class="wpts-button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Clear filters', 'wp-teamsphere' ); ?></a></p>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'No team members to show yet.', 'wp-teamsphere' ); ?></strong></p>
						<p><?php esc_html_e( 'Published profiles will appear here.', 'wp-teamsphere' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * One person, in any layout (the CSS decides how it looks).
	 *
	 * @param array    $profile Public profile.
	 * @param string   $layout  Layout.
	 * @param string[] $show    Visible parts.
	 * @return string
	 */
	private static function render_item( $profile, $layout, $show ) {
		$fields     = $profile['fields'];
		$name_shown = in_array( 'name', $show, true );
		$size       = array(
			'grid'    => 'medium_large',
			'list'    => 'medium',
			'compact' => 'thumbnail',
		);

		ob_start();
		?>
		<article class="wpts-item wpts-item--<?php echo esc_attr( $layout ); ?>">
			<?php if ( in_array( 'photo', $show, true ) ) : ?>
				<a class="wpts-photo" href="<?php echo esc_url( $profile['url'] ); ?>"<?php echo $name_shown ? ' tabindex="-1" aria-hidden="true"' : ' aria-label="' . esc_attr( $profile['name'] ) . '"'; ?>>
					<?php echo WPTS_Render::photo( $profile, $size[ $layout ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>

			<div class="wpts-item__body">
				<?php if ( $name_shown ) : ?>
					<h3 class="wpts-name"><a href="<?php echo esc_url( $profile['url'] ); ?>"><?php echo esc_html( $profile['name'] ); ?></a></h3>
				<?php endif; ?>

				<?php if ( in_array( 'title', $show, true ) && ! empty( $fields['job_title'] ) ) : ?>
					<p class="wpts-role"><?php echo esc_html( $fields['job_title'] ); ?></p>
				<?php endif; ?>

				<?php
				$chips = '';
				if ( in_array( 'department', $show, true ) && $profile['department'] ) {
					$chips .= WPTS_Render::chip( $profile['department']['id'], $profile['department']['name'] );
				}
				if ( in_array( 'team', $show, true ) ) {
					foreach ( $profile['teams'] as $team ) {
						$chips .= WPTS_Render::chip( $team['id'], $team['name'] );
					}
				}
				if ( '' !== $chips ) {
					echo '<p class="wpts-chips">' . $chips . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>

				<?php if ( in_array( 'intro', $show, true ) && ! empty( $fields['short_intro'] ) ) : ?>
					<p class="wpts-intro"><?php echo esc_html( wp_trim_words( $fields['short_intro'], 24 ) ); ?></p>
				<?php endif; ?>

				<?php
				if ( in_array( 'social', $show, true ) ) {
					echo WPTS_Render::social_links( $profile ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>

				<?php if ( in_array( 'button', $show, true ) ) : ?>
					<p class="wpts-more">
						<a class="wpts-button" href="<?php echo esc_url( $profile['url'] ); ?>"><?php esc_html_e( 'View profile', 'wp-teamsphere' ); ?><span class="wpts-sr"> <?php echo esc_html( $profile['name'] ); ?></span></a>
					</p>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}