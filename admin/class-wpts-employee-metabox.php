<?php
/**
 * Employee edit screen: meta boxes and saving.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Employee_Metabox {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes_' . WPTS_Post_Types::POST_TYPE, array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post_' . WPTS_Post_Types::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'editor_heading' ) );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string  $text Default text.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( $post && WPTS_Post_Types::POST_TYPE === $post->post_type ) {
			return __( 'Display name, e.g. John Smith', 'wp-teamsphere' );
		}
		return $text;
	}

	/**
	 * Label above the main editor, which holds the biography.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function editor_heading( $post ) {
		if ( $post && WPTS_Post_Types::POST_TYPE === $post->post_type ) {
			echo '<h2 class="wpts-editor-heading">' . esc_html__( 'Biography', 'wp-teamsphere' ) . '</h2>';
		}
	}

	/**
	 * Register meta boxes.
	 */
	public static function add_boxes() {
		$type = WPTS_Post_Types::POST_TYPE;

		add_meta_box( 'wpts_basic', __( 'Basic information', 'wp-teamsphere' ), array( __CLASS__, 'render_fields_box' ), $type, 'normal', 'high', array( 'box' => 'basic' ) );
		add_meta_box( 'wpts_contact', __( 'Contact & location', 'wp-teamsphere' ), array( __CLASS__, 'render_fields_box' ), $type, 'normal', 'default', array( 'box' => 'contact' ) );
		add_meta_box( 'wpts_employment', __( 'Employment', 'wp-teamsphere' ), array( __CLASS__, 'render_fields_box' ), $type, 'normal', 'default', array( 'box' => 'employment' ) );
		add_meta_box( 'wpts_links', __( 'Professional links', 'wp-teamsphere' ), array( __CLASS__, 'render_fields_box' ), $type, 'normal', 'default', array( 'box' => 'links' ) );
		add_meta_box( 'wpts_private', __( 'Internal information (private)', 'wp-teamsphere' ), array( __CLASS__, 'render_fields_box' ), $type, 'normal', 'low', array( 'box' => 'private' ) );

		add_meta_box( 'wpts_preview', __( 'Card preview', 'wp-teamsphere' ), array( __CLASS__, 'render_preview_box' ), $type, 'side', 'high' );
		add_meta_box( 'wpts_org', __( 'Organization', 'wp-teamsphere' ), array( __CLASS__, 'render_org_box' ), $type, 'side', 'high' );
		add_meta_box( 'wpts_skills', __( 'Skills', 'wp-teamsphere' ), array( __CLASS__, 'render_skills_box' ), $type, 'side', 'default' );
		add_meta_box( 'wpts_cover', __( 'Cover image', 'wp-teamsphere' ), array( __CLASS__, 'render_cover_box' ), $type, 'side', 'low' );
	}

	/**
	 * Placeholder text for a field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function placeholder( $key ) {
		$map = array(
			'job_title'       => __( 'e.g. Senior Developer', 'wp-teamsphere' ),
			'office_location' => __( 'e.g. Dhaka office', 'wp-teamsphere' ),
			'work_email'      => 'name@company.com',
			'work_phone'      => '+880 1XXX-XXXXXX',
			'linkedin'        => 'https://www.linkedin.com/in/username',
			'x'               => 'https://x.com/username',
			'github'          => 'https://github.com/username',
			'facebook'        => 'https://facebook.com/username',
			'instagram'       => 'https://instagram.com/username',
			'website'         => 'https://example.com',
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Short helper text under a field.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	private static function hint( $key ) {
		$map = array(
			'job_title'       => __( 'Shown under the name on cards and profile pages.', 'wp-teamsphere' ),
			'office_location' => __( 'Optional. City or office name.', 'wp-teamsphere' ),
			'start_date'      => __( 'Used for the career timeline.', 'wp-teamsphere' ),
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Generic table of fields for a box.
	 *
	 * @param WP_Post $post    Post.
	 * @param array   $metabox Meta box args.
	 */
	public static function render_fields_box( $post, $metabox ) {
		$box = isset( $metabox['args']['box'] ) ? $metabox['args']['box'] : '';

		if ( 'basic' === $box ) {
			wp_nonce_field( 'wpts_save_employee', 'wpts_employee_nonce' );
		}

		if ( 'private' === $box ) {
			echo '<p class="wpts-private-note"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html__( 'These fields are never shown on the website, in search, or in any public data feed.', 'wp-teamsphere' ) . '</p>';
		}

		if ( 'links' === $box ) {
			echo '<p class="wpts-box-note">' . esc_html__( 'Leave a link blank and its icon will not be shown.', 'wp-teamsphere' ) . '</p>';
		}

		echo '<table class="wpts-form-table"><tbody>';
		foreach ( WPTS_Fields::by_box( $box ) as $key => $def ) {
			self::render_field( $post->ID, $key, $def );
		}
		echo '</tbody></table>';
	}

	/**
	 * One field row.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Field key.
	 * @param array  $def     Field definition.
	 */
	private static function render_field( $post_id, $key, $def ) {
		$id          = 'wpts_field_' . $key;
		$name        = 'wpts_fields[' . $key . ']';
		$value       = WPTS_Employee::get_field( $post_id, $key );
		$placeholder = self::placeholder( $key );
		$desc        = ! empty( $def['desc'] ) ? $def['desc'] : self::hint( $key );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';

		if ( 'textarea' === $def['type'] ) {
			printf(
				'<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_textarea( $value )
			);
		} else {
			$type = in_array( $def['type'], array( 'email', 'tel', 'url', 'date' ), true ) ? $def['type'] : 'text';
			printf(
				'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" class="regular-text">',
				esc_attr( $type ),
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( $placeholder )
			);
		}

		if ( '' !== $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Live preview of the directory card (updated by admin.js while typing).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_preview_box( $post ) {
		$name = (string) $post->post_title;
		if ( '' === trim( $name ) ) {
			$name = trim( WPTS_Employee::get_field( $post->ID, 'first_name' ) . ' ' . WPTS_Employee::get_field( $post->ID, 'last_name' ) );
		}
		$job  = (string) WPTS_Employee::get_field( $post->ID, 'job_title' );
		$dept = WPTS_Employee::get_department( $post->ID );
		?>
		<div class="wpts-preview" id="wpts-preview">
			<?php if ( has_post_thumbnail( $post->ID ) ) : ?>
				<?php echo get_the_post_thumbnail( $post->ID, array( 96, 96 ), array( 'class' => 'wpts-preview-photo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<span class="wpts-preview-initial" id="wpts-preview-initial" aria-hidden="true"><?php echo esc_html( WPTS_Admin_Fields::initial( $name ) ); ?></span>
			<?php endif; ?>
			<p class="wpts-preview-name" id="wpts-preview-name"><?php echo esc_html( '' !== $name ? $name : __( 'Display name', 'wp-teamsphere' ) ); ?></p>
			<p class="wpts-preview-job" id="wpts-preview-job"><?php echo esc_html( $job ); ?></p>
			<span class="wpts-chip" id="wpts-preview-dept" style="<?php echo $dept ? esc_attr( WPTS_Terms::chip_style( WPTS_Terms::get_color( $dept->term_id ) ) ) : 'display:none'; ?>"><?php echo $dept ? esc_html( $dept->name ) : ''; ?></span>
		</div>
		<p class="description wpts-preview-note"><?php esc_html_e( 'A quick look at how this profile will appear in the directory.', 'wp-teamsphere' ); ?></p>
		<?php
	}

	/**
	 * Side box: department (single), teams (multiple), manager.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_org_box( $post ) {
		$department    = WPTS_Employee::get_department( $post->ID );
		$department_id = $department ? (int) $department->term_id : 0;
		$team_ids      = array_map( 'intval', wp_list_pluck( WPTS_Employee::get_teams( $post->ID ), 'term_id' ) );
		$manager_id    = (int) WPTS_Employee::get_field( $post->ID, 'manager_id' );
		$departments   = WPTS_Terms::get_departments();
		$teams         = WPTS_Terms::get_teams();
		?>
		<p>
			<label for="wpts_department"><strong><?php esc_html_e( 'Department', 'wp-teamsphere' ); ?></strong></label><br>
			<select id="wpts_department" name="wpts_department" class="widefat">
				<option value="0"><?php esc_html_e( '— None —', 'wp-teamsphere' ); ?></option>
				<?php foreach ( $departments as $term ) : ?>
					<option value="<?php echo esc_attr( $term->term_id ); ?>" data-color="<?php echo esc_attr( WPTS_Terms::get_color( $term->term_id ) ); ?>" <?php selected( $department_id, (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( empty( $departments ) ) : ?>
				<span class="description">
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . WPTS_Post_Types::TAX_DEPARTMENT . '&post_type=' . WPTS_Post_Types::POST_TYPE ) ); ?>"><?php esc_html_e( 'Create a department first.', 'wp-teamsphere' ); ?></a>
				</span>
			<?php endif; ?>
		</p>

		<fieldset class="wpts-teams-fieldset">
			<legend><strong><?php esc_html_e( 'Teams', 'wp-teamsphere' ); ?></strong></legend>
			<input type="hidden" name="wpts_teams_present" value="1">
			<?php foreach ( $teams as $term ) : ?>
				<label class="wpts-team-option">
					<input type="checkbox" name="wpts_teams[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, $team_ids, true ) ); ?>>
					<span class="wpts-dot" style="background: <?php echo esc_attr( WPTS_Terms::get_color( $term->term_id ) ); ?>"></span>
					<?php echo esc_html( $term->name ); ?>
				</label>
			<?php endforeach; ?>
			<?php if ( empty( $teams ) ) : ?>
				<span class="description">
					<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . WPTS_Post_Types::TAX_TEAM . '&post_type=' . WPTS_Post_Types::POST_TYPE ) ); ?>"><?php esc_html_e( 'Create a team first.', 'wp-teamsphere' ); ?></a>
				</span>
			<?php endif; ?>
		</fieldset>

		<p>
			<label for="wpts_manager_id"><strong><?php esc_html_e( 'Reports to', 'wp-teamsphere' ); ?></strong></label><br>
			<?php
			// Built from escaped parts in WPTS_Admin_Fields::employee_select().
			echo WPTS_Admin_Fields::employee_select( 'wpts_manager_id', $manager_id, $post->ID, 'wpts_manager_id' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</p>
		<?php
	}

	/**
	 * Skills as removable chips (JSON in a hidden field is read on save).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_skills_box( $post ) {
		$current     = wp_list_pluck( WPTS_Employee::get_skills( $post->ID ), 'name' );
		$suggestions = get_terms(
			array(
				'taxonomy'   => WPTS_Post_Types::TAX_SKILL,
				'hide_empty' => false,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 100,
				'fields'     => 'names',
			)
		);
		$suggestions = is_wp_error( $suggestions ) ? array() : $suggestions;
		?>
		<div class="wpts-chips-field">
			<input type="hidden" name="wpts_skills_json" class="wpts-chips-value" value="<?php echo esc_attr( wp_json_encode( array_values( $current ) ) ); ?>">
			<ul class="wpts-chip-list">
				<?php foreach ( $current as $name ) : ?>
					<li class="wpts-skill-chip" data-value="<?php echo esc_attr( $name ); ?>">
						<span><?php echo esc_html( $name ); ?></span>
						<?php /* translators: %s: skill name. */ ?>
						<button type="button" class="wpts-chip-remove" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s', 'wp-teamsphere' ), $name ) ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<label for="wpts-skill-input" class="screen-reader-text"><?php esc_html_e( 'Add a skill', 'wp-teamsphere' ); ?></label>
			<div class="wpts-chip-input">
				<input type="text" id="wpts-skill-input" class="wpts-chip-text" list="wpts-skill-suggestions" placeholder="<?php esc_attr_e( 'Type a skill, press Enter', 'wp-teamsphere' ); ?>" autocomplete="off">
				<button type="button" class="button wpts-chip-add"><?php esc_html_e( 'Add', 'wp-teamsphere' ); ?></button>
			</div>
			<datalist id="wpts-skill-suggestions">
				<?php foreach ( $suggestions as $suggestion ) : ?>
					<option value="<?php echo esc_attr( $suggestion ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
			<p class="description"><?php esc_html_e( 'New skills are added to your skill list automatically.', 'wp-teamsphere' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Side box: cover image.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_cover_box( $post ) {
		$fields = WPTS_Fields::all();
		echo WPTS_Admin_Fields::media_field( 'wpts_fields[cover_image]', (int) WPTS_Employee::get_field( $post->ID, 'cover_image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p class="description">' . esc_html( $fields['cover_image']['desc'] ) . '</p>';
	}

	/**
	 * Save everything from the edit screen.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['wpts_employee_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpts_employee_nonce'] ) ), 'wpts_save_employee' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Plain fields (every value is sanitized by type in WPTS_Fields::sanitize()).
		$posted = ( isset( $_POST['wpts_fields'] ) && is_array( $_POST['wpts_fields'] ) ) ? wp_unslash( $_POST['wpts_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		foreach ( WPTS_Fields::all() as $key => $def ) {
			if ( 'employee' === $def['type'] || ! isset( $posted[ $key ] ) || ! is_scalar( $posted[ $key ] ) ) {
				continue;
			}
			WPTS_Employee::set_field( $post_id, $key, WPTS_Fields::sanitize( $key, $posted[ $key ] ) );
		}

		// Department: one per employee.
		if ( isset( $_POST['wpts_department'] ) ) {
			WPTS_Employee::set_department( $post_id, absint( wp_unslash( $_POST['wpts_department'] ) ) );
		}

		// Teams: any number.
		if ( isset( $_POST['wpts_teams_present'] ) ) {
			$teams = ( isset( $_POST['wpts_teams'] ) && is_array( $_POST['wpts_teams'] ) ) ? array_map( 'absint', wp_unslash( $_POST['wpts_teams'] ) ) : array();
			WPTS_Employee::set_teams( $post_id, $teams );
		}

		// Skills: JSON list of names, sanitized one by one in WPTS_Employee::set_skills().
		if ( isset( $_POST['wpts_skills_json'] ) && is_string( $_POST['wpts_skills_json'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['wpts_skills_json'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			WPTS_Employee::set_skills( $post_id, is_array( $decoded ) ? $decoded : array() );
		}

		// Manager: validated against self-reference and loops.
		if ( isset( $_POST['wpts_manager_id'] ) ) {
			$result = WPTS_Employee::set_manager( $post_id, absint( wp_unslash( $_POST['wpts_manager_id'] ) ) );
			if ( is_wp_error( $result ) ) {
				WPTS_Admin::add_notice( $result->get_error_message() );
			}
		}

		// Convenience: build the display name from first + last name when the title is empty.
		if ( '' === trim( (string) $post->post_title ) ) {
			$name = trim( WPTS_Employee::get_field( $post_id, 'first_name' ) . ' ' . WPTS_Employee::get_field( $post_id, 'last_name' ) );
			if ( '' !== $name ) {
				remove_action( 'save_post_' . WPTS_Post_Types::POST_TYPE, array( __CLASS__, 'save' ), 10 );
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => $name,
						'post_name'  => sanitize_title( $name ),
					)
				);
				add_action( 'save_post_' . WPTS_Post_Types::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
			}
		}
	}
}
