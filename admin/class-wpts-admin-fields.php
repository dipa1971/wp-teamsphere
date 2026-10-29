<?php
/**
 * Small reusable admin form controls.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Admin_Fields {

	/**
	 * First letter of a name, for avatar placeholders (multibyte safe).
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
	 * Small avatar (photo or initial) for tables. Returns escaped HTML.
	 *
	 * @param int $post_id Employee ID.
	 * @return string
	 */
	public static function mini_avatar( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail( $post_id, array( 24, 24 ), array( 'class' => 'wpts-mini-avatar' ) );
		}
		return '<span class="wpts-mini-avatar is-initial" aria-hidden="true">' . esc_html( self::initial( get_the_title( $post_id ) ) ) . '</span>';
	}

	/**
	 * Image picker (uses the WordPress media library). Returns escaped HTML.
	 *
	 * @param string $name          Input name.
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $button_id     ID for the choose button (for label association).
	 * @return string
	 */
	public static function media_field( $name, $attachment_id = 0, $button_id = '' ) {
		$attachment_id = absint( $attachment_id );
		$preview       = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail' ) : '';

		ob_start();
		?>
		<div class="wpts-media-field">
			<input type="hidden" class="wpts-media-id" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $attachment_id ? $attachment_id : '' ); ?>">
			<div class="wpts-media-preview"><?php echo wp_kses_post( $preview ); ?></div>
			<button type="button" class="button wpts-media-select"<?php echo $button_id ? ' id="' . esc_attr( $button_id ) . '"' : ''; ?>><?php esc_html_e( 'Choose image', 'wp-teamsphere' ); ?></button>
			<button type="button" class="button-link button-link-delete wpts-media-remove"<?php echo $attachment_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'wp-teamsphere' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Colour picker: ready-made swatches plus a custom colour input. Returns escaped HTML.
	 *
	 * @param string $name  Input name.
	 * @param string $color Current colour.
	 * @return string
	 */
	public static function color_field( $name, $color ) {
		$color = WPTS_Terms::sanitize_color( $color );
		if ( '' === $color ) {
			$palette = WPTS_Terms::palette();
			$color   = $palette[0];
		}

		ob_start();
		?>
		<div class="wpts-color-field">
			<div class="wpts-swatches" role="group" aria-label="<?php esc_attr_e( 'Choose a colour', 'wp-teamsphere' ); ?>">
				<?php foreach ( WPTS_Terms::palette() as $swatch ) : ?>
					<?php $active = ( $swatch === $color ); ?>
					<button type="button" class="wpts-swatch<?php echo $active ? ' is-active' : ''; ?>" data-color="<?php echo esc_attr( $swatch ); ?>" style="background: <?php echo esc_attr( $swatch ); ?>" aria-pressed="<?php echo $active ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $swatch ); ?>"></button>
				<?php endforeach; ?>
			</div>
			<label class="wpts-color-custom">
				<span><?php esc_html_e( 'Custom colour', 'wp-teamsphere' ); ?></span>
				<input type="color" id="wpts_color_input" class="wpts-color-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $color ); ?>">
			</label>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Dropdown of employees. Returns escaped HTML.
	 *
	 * @param string $name     Select name.
	 * @param int    $selected Selected employee ID.
	 * @param int    $exclude  Employee ID to omit.
	 * @param string $id       Element ID.
	 * @return string
	 */
	public static function employee_select( $name, $selected = 0, $exclude = 0, $id = '' ) {
		$html  = '<select name="' . esc_attr( $name ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' class="widefat">';
		$html .= '<option value="0">' . esc_html__( '— None —', 'wp-teamsphere' ) . '</option>';

		foreach ( WPTS_Employee::get_employee_options( $exclude ) as $option_id => $title ) {
			$html .= sprintf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $option_id,
				selected( (int) $selected, (int) $option_id, false ),
				esc_html( $title )
			);
		}

		return $html . '</select>';
	}
}
