<?php
/**
 * Extra fields and a friendlier list on the Department and Team screens.
 *
 * Department: colour, image, head, visibility, order.
 * Team:       colour, leader, visibility.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Term_Fields {

	/**
	 * Hooks.
	 */
	public static function init() {
		foreach ( array( WPTS_Post_Types::TAX_DEPARTMENT, WPTS_Post_Types::TAX_TEAM ) as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'render_add' ), 10, 1 );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render_edit' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save' ), 10, 1 );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save' ), 10, 1 );
			add_filter( "manage_edit-{$taxonomy}_columns", array( __CLASS__, 'columns' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( __CLASS__, 'column_content' ), 10, 3 );
		}
	}

	/**
	 * A different default colour for each new item.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private static function next_color( $taxonomy ) {
		$palette = WPTS_Terms::palette();
		$count   = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		$count   = is_wp_error( $count ) ? 0 : (int) $count;

		return $palette[ $count % count( $palette ) ];
	}

	/**
	 * Field definitions for the forms. "html" is already escaped.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param int    $term_id  Term ID (0 on the add form).
	 * @return array
	 */
	private static function fields( $taxonomy, $term_id ) {
		$is_dept = WPTS_Post_Types::TAX_DEPARTMENT === $taxonomy;
		$fields  = array();

		$fields[] = array(
			'id'    => 'wpts_color_input',
			'label' => __( 'Colour', 'wp-teamsphere' ),
			'html'  => WPTS_Admin_Fields::color_field( 'wpts_color', $term_id ? WPTS_Terms::get_color( $term_id ) : self::next_color( $taxonomy ) ),
			'desc'  => __( 'Used for badges and charts so people can recognise it at a glance.', 'wp-teamsphere' ),
		);

		if ( $is_dept ) {
			$fields[] = array(
				'id'    => 'wpts_image_button',
				'label' => __( 'Image or icon', 'wp-teamsphere' ),
				'html'  => WPTS_Admin_Fields::media_field( 'wpts_image', $term_id ? (int) get_term_meta( $term_id, WPTS_Terms::META_IMAGE, true ) : 0, 'wpts_image_button' ),
				'desc'  => __( 'Optional. Shown on the department page of your directory.', 'wp-teamsphere' ),
			);
		}

		$person_key = $is_dept ? WPTS_Terms::META_HEAD : WPTS_Terms::META_LEADER;
		$fields[]   = array(
			'id'    => 'wpts_person',
			'label' => $is_dept ? __( 'Department head', 'wp-teamsphere' ) : __( 'Team leader', 'wp-teamsphere' ),
			'html'  => WPTS_Admin_Fields::employee_select( 'wpts_person', $term_id ? (int) get_term_meta( $term_id, $person_key, true ) : 0, 0, 'wpts_person' ),
			'desc'  => $is_dept ? __( 'The person who leads this department.', 'wp-teamsphere' ) : __( 'The person who leads this team.', 'wp-teamsphere' ),
		);

		$visibility = $term_id ? get_term_meta( $term_id, WPTS_Terms::META_VISIBILITY, true ) : 'public';
		$visibility = 'private' === $visibility ? 'private' : 'public';

		$fields[] = array(
			'id'    => 'wpts_visibility',
			'label' => __( 'Public visibility', 'wp-teamsphere' ),
			'html'  => '<select name="wpts_visibility" id="wpts_visibility">'
				. '<option value="public"' . selected( $visibility, 'public', false ) . '>' . esc_html__( 'Public', 'wp-teamsphere' ) . '</option>'
				. '<option value="private"' . selected( $visibility, 'private', false ) . '>' . esc_html__( 'Private (not shown on the website)', 'wp-teamsphere' ) . '</option>'
				. '</select>',
			'desc'  => __( 'Private items stay in the admin area only.', 'wp-teamsphere' ),
		);

		if ( $is_dept ) {
			$order    = $term_id ? (int) get_term_meta( $term_id, WPTS_Terms::META_ORDER, true ) : 0;
			$fields[] = array(
				'id'    => 'wpts_order',
				'label' => __( 'Display order', 'wp-teamsphere' ),
				'html'  => '<input type="number" name="wpts_order" id="wpts_order" value="' . esc_attr( $order ) . '" min="0" step="1" class="small-text">',
				'desc'  => __( 'Lower numbers appear first.', 'wp-teamsphere' ),
			);
		}

		return $fields;
	}

	/**
	 * "Add new" form.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function render_add( $taxonomy ) {
		wp_nonce_field( 'wpts_save_term', 'wpts_term_nonce' );

		foreach ( self::fields( $taxonomy, 0 ) as $field ) {
			?>
			<div class="form-field">
				<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<?php echo $field['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $field['desc'] ) : ?>
					<p><?php echo esc_html( $field['desc'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Edit form.
	 *
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function render_edit( $term, $taxonomy ) {
		wp_nonce_field( 'wpts_save_term', 'wpts_term_nonce' );

		foreach ( self::fields( $taxonomy, (int) $term->term_id ) as $field ) {
			?>
			<tr class="form-field">
				<th scope="row"><label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
				<td>
					<?php echo $field['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $field['desc'] ) : ?>
						<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Save term meta. Skipped unless our own form (with a valid nonce) was submitted.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST['wpts_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpts_term_nonce'] ) ), 'wpts_save_term' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_wpts_terms' ) ) {
			return;
		}

		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$is_dept = WPTS_Post_Types::TAX_DEPARTMENT === $term->taxonomy;

		$color = isset( $_POST['wpts_color'] ) ? WPTS_Terms::sanitize_color( sanitize_text_field( wp_unslash( $_POST['wpts_color'] ) ) ) : '';
		if ( $color ) {
			update_term_meta( $term_id, WPTS_Terms::META_COLOR, $color );
		} else {
			delete_term_meta( $term_id, WPTS_Terms::META_COLOR );
		}

		// Head / leader must be a real team member.
		$person = isset( $_POST['wpts_person'] ) ? absint( wp_unslash( $_POST['wpts_person'] ) ) : 0;
		if ( $person && WPTS_Post_Types::POST_TYPE !== get_post_type( $person ) ) {
			$person = 0;
		}
		self::update_meta( $term_id, $is_dept ? WPTS_Terms::META_HEAD : WPTS_Terms::META_LEADER, $person );

		$visibility = isset( $_POST['wpts_visibility'] ) ? sanitize_key( wp_unslash( $_POST['wpts_visibility'] ) ) : 'public';
		update_term_meta( $term_id, WPTS_Terms::META_VISIBILITY, WPTS_Terms::sanitize_visibility( $visibility ) );

		if ( $is_dept ) {
			$image = isset( $_POST['wpts_image'] ) ? absint( wp_unslash( $_POST['wpts_image'] ) ) : 0;
			if ( $image && ! wp_attachment_is_image( $image ) ) {
				$image = 0;
			}
			self::update_meta( $term_id, WPTS_Terms::META_IMAGE, $image );

			$order = isset( $_POST['wpts_order'] ) ? absint( wp_unslash( $_POST['wpts_order'] ) ) : 0;
			self::update_meta( $term_id, WPTS_Terms::META_ORDER, $order );
		}
	}

	/**
	 * Store or clear an integer meta value.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Meta key.
	 * @param int    $value    Value.
	 */
	private static function update_meta( $term_id, $meta_key, $value ) {
		if ( $value ) {
			update_term_meta( $term_id, $meta_key, $value );
		} else {
			delete_term_meta( $term_id, $meta_key );
		}
	}

	/**
	 * Simplified list columns: colour dot, name, head/leader, members, visibility.
	 * (Slug, description and the raw count are still available on the edit screen.)
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$screen  = get_current_screen();
		$is_dept = $screen && WPTS_Post_Types::TAX_DEPARTMENT === $screen->taxonomy;

		$new = array();
		if ( isset( $columns['cb'] ) ) {
			$new['cb'] = $columns['cb'];
		}
		$new['wpts_color']      = '<span class="screen-reader-text">' . esc_html__( 'Colour', 'wp-teamsphere' ) . '</span>';
		$new['name']            = isset( $columns['name'] ) ? $columns['name'] : __( 'Name', 'wp-teamsphere' );
		$new['wpts_person']     = $is_dept ? __( 'Head', 'wp-teamsphere' ) : __( 'Leader', 'wp-teamsphere' );
		$new['wpts_members']    = __( 'Members', 'wp-teamsphere' );
		$new['wpts_visibility'] = __( 'Visibility', 'wp-teamsphere' );

		return $new;
	}

	/**
	 * Column output.
	 *
	 * @param string $content     Existing content.
	 * @param string $column_name Column.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public static function column_content( $content, $column_name, $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return $content;
		}

		$is_dept = WPTS_Post_Types::TAX_DEPARTMENT === $term->taxonomy;

		switch ( $column_name ) {
			case 'wpts_color':
				return '<span class="wpts-dot" style="background:' . esc_attr( WPTS_Terms::get_color( $term_id ) ) . '"></span>';

			case 'wpts_person':
				$person = (int) get_term_meta( $term_id, $is_dept ? WPTS_Terms::META_HEAD : WPTS_Terms::META_LEADER, true );
				if ( $person && WPTS_Post_Types::POST_TYPE === get_post_type( $person ) ) {
					return '<span class="wpts-person">' . WPTS_Admin_Fields::mini_avatar( $person ) . sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_edit_post_link( $person ) ), esc_html( get_the_title( $person ) ) ) . '</span>';
				}
				return '<span class="wpts-muted" aria-hidden="true">—</span>';

			case 'wpts_members':
				$counts = WPTS_Terms::member_counts( $term->taxonomy );
				$number = isset( $counts[ $term_id ] ) ? $counts[ $term_id ] : 0;
				$url    = add_query_arg(
					array(
						'post_type'                         => WPTS_Post_Types::POST_TYPE,
						$is_dept ? 'wpts_filter_dept' : 'wpts_filter_team' => $term_id,
					),
					admin_url( 'edit.php' )
				);
				return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( number_format_i18n( $number ) ) );

			case 'wpts_visibility':
				return WPTS_Terms::is_public( $term_id )
					? '<span class="wpts-badge is-success">' . esc_html__( 'Public', 'wp-teamsphere' ) . '</span>'
					: '<span class="wpts-badge is-neutral">' . esc_html__( 'Private', 'wp-teamsphere' ) . '</span>';
		}

		return $content;
	}
}
