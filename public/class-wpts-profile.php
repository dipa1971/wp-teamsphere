<?php
/**
 * Single profile page. Rendered inside the theme's normal post content, so it
 * works with classic and block themes alike. The theme prints the person's name as the page title.
 *
 * @package WP_TeamSphere
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Profile {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	/**
	 * Wrap the biography in the profile layout.
	 *
	 * @param string $content Content (the biography).
	 * @return string
	 */
	public static function filter_content( $content ) {
		static $running = false;

		if ( $running || doing_filter( 'get_the_excerpt' ) || ! is_singular( WPTS_Post_Types::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		// Editors may preview unpublished profiles; everyone else only sees published ones (WordPress enforces that too).
		$profile = WPTS_Employee::get_public_profile( $post->ID, current_user_can( 'edit_post', $post->ID ) );
		if ( ! $profile ) {
			return $content;
		}

		$running = true;
		WPTS_Public::enqueue();
		$html    = self::render( $profile, $content );
		$running = false;

		return $html;
	}

	/**
	 * Build the two-column layout.
	 *
	 * @param array  $profile Public profile.
	 * @param string $content Biography HTML (already filtered by WordPress).
	 * @return string
	 */
	private static function render( $profile, $content ) {
		$fields  = $profile['fields'];
		$socials = WPTS_Render::social_links( $profile, true );
		$has_contact = ! empty( $fields['work_email'] ) || ! empty( $fields['work_phone'] ) || ! empty( $fields['office_location'] );

		ob_start();
		?>
		<div class="wpts wpts-profile wpts-shape-<?php echo esc_attr( WPTS_Public::shape() ); ?>">
			<aside class="wpts-profile__side">
				<div class="wpts-photo"><?php echo WPTS_Render::photo( $profile, 'large' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

				<?php if ( $has_contact ) : ?>
					<section class="wpts-profile__panel">
						<h2 class="wpts-profile__heading"><?php esc_html_e( 'Contact', 'wp-teamsphere' ); ?></h2>
						<ul class="wpts-contact">
							<?php if ( ! empty( $fields['work_email'] ) ) : ?>
								<li>
									<?php echo WPTS_Icons::get( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<a href="mailto:<?php echo antispambot( $fields['work_email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo antispambot( $fields['work_email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
								</li>
							<?php endif; ?>
							<?php if ( ! empty( $fields['work_phone'] ) ) : ?>
								<li>
									<?php echo WPTS_Icons::get( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $fields['work_phone'] ) ); ?>"><?php echo esc_html( $fields['work_phone'] ); ?></a>
								</li>
							<?php endif; ?>
							<?php if ( ! empty( $fields['office_location'] ) ) : ?>
								<li>
									<?php echo WPTS_Icons::get( 'location' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<span><?php echo esc_html( $fields['office_location'] ); ?></span>
								</li>
							<?php endif; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( '' !== $socials ) : ?>
					<section class="wpts-profile__panel">
						<h2 class="wpts-profile__heading"><?php esc_html_e( 'Links', 'wp-teamsphere' ); ?></h2>
						<?php echo $socials; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</section>
				<?php endif; ?>
			</aside>

			<div class="wpts-profile__main">
				<?php if ( ! empty( $fields['job_title'] ) ) : ?>
					<p class="wpts-profile__job"><?php echo esc_html( $fields['job_title'] ); ?></p>
				<?php endif; ?>

				<?php if ( $profile['department'] || $profile['teams'] ) : ?>
					<p class="wpts-chips">
						<?php
						if ( $profile['department'] ) {
							echo WPTS_Render::chip( $profile['department']['id'], $profile['department']['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						foreach ( $profile['teams'] as $team ) {
							echo WPTS_Render::chip( $team['id'], $team['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $fields['short_intro'] ) ) : ?>
					<p class="wpts-profile__intro"><?php echo esc_html( $fields['short_intro'] ); ?></p>
				<?php endif; ?>

				<div class="wpts-profile__bio"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already filtered by core the_content filters. ?></div>

				<?php if ( ! empty( $profile['skills'] ) ) : ?>
					<section>
						<h2 class="wpts-profile__heading"><?php esc_html_e( 'Skills', 'wp-teamsphere' ); ?></h2>
						<ul class="wpts-tags">
							<?php foreach ( $profile['skills'] as $skill ) : ?>
								<li><?php echo esc_html( $skill ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}