<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'm4w_cc_add_admin_menu' );
add_action( 'admin_init', 'm4w_cc_register_settings' );

function m4w_cc_add_admin_menu() {
	add_submenu_page(
		'options-general.php',
		'Cookie panel',
		'Cookie panel',
		'manage_options',
		'm4w-cookie-consent',
		'm4w_cc_render_admin_page'
	);
}

function m4w_cc_register_settings() {
	register_setting( 'm4w_cc_settings', 'm4w_cc_settings', array( 'sanitize_callback' => 'm4w_cc_sanitize_settings' ) );
}

function m4w_cc_sanitize_settings( $input ) {
	$consent = M4W_CC_Cookie_Consent::get_instance();
	$defaults = $consent->get_defaults();
	$output   = $defaults;

	foreach ( array( 'banner_title', 'banner_description', 'btn_accept', 'btn_accept_all', 'btn_reject', 'btn_customize', 'btn_save', 'pref_title', 'privacy_link_text' ) as $text_field ) {
		if ( isset( $input[ $text_field ] ) ) {
			$output[ $text_field ] = $text_field === 'banner_description' ? sanitize_textarea_field( $input[ $text_field ] ) : sanitize_text_field( $input[ $text_field ] );
		}
	}

	if ( isset( $input['privacy_url'] ) ) {
		$output['privacy_url'] = esc_url_raw( $input['privacy_url'] );
	}

	$output['enabled']                 = ! empty( $input['enabled'] );
	$output['cookie_id']               = isset( $input['cookie_id'] ) ? $consent->sanitize_cookie_id( $input['cookie_id'] ) : $defaults['cookie_id'];
	$output['cookie_id']               = $output['cookie_id'] ? $output['cookie_id'] : $defaults['cookie_id'];
	$output['consent_expiry']         = isset( $input['consent_expiry'] ) ? absint( $input['consent_expiry'] ) : 365;
	$output['consent_expiry_rejected'] = isset( $input['consent_expiry_rejected'] ) ? absint( $input['consent_expiry_rejected'] ) : 30;
	$output['gcm_enabled']            = ! empty( $input['gcm_enabled'] );
	$output['custom_css']     = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';
	$output['header_scripts'] = isset( $input['header_scripts'] ) ? $input['header_scripts'] : '';

	if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
		foreach ( $output['categories'] as $slug => $cat ) {
			$output['categories'][ $slug ]['label']       = isset( $input['categories'][ $slug ]['label'] ) ? sanitize_text_field( $input['categories'][ $slug ]['label'] ) : '';
			$output['categories'][ $slug ]['description'] = isset( $input['categories'][ $slug ]['description'] ) ? sanitize_textarea_field( $input['categories'][ $slug ]['description'] ) : '';
		}
	}

	return $output;
}

function m4w_cc_render_admin_page() {
	$consent = M4W_CC_Cookie_Consent::get_instance();
	$settings = $consent->get_settings();
	?>
	<div class="wrap">
		<h1>Cookie Consent Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'm4w_cc_settings' ); ?>
			<?php do_settings_sections( 'm4w_cc_settings' ); ?>

			<h2><?php esc_html_e( 'Text Overrides', 'm4w-cookie-consent' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Leave fields empty to use the translated default text.', 'm4w-cookie-consent' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( array( 'banner_title', 'banner_description', 'btn_accept', 'btn_accept_all', 'btn_reject', 'btn_customize', 'btn_save', 'pref_title', 'privacy_link_text' ) as $key ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?></th>
					<td>
						<?php if ( in_array( $key, array( 'banner_description' ), true ) ) : ?>
							<textarea name="m4w_cc_settings[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text" placeholder="<?php echo esc_attr( $consent->get_default_text( $key ) ); ?>"><?php echo esc_textarea( is_string( $settings[ $key ] ) ? $settings[ $key ] : '' ); ?></textarea>
						<?php else : ?>
							<input type="text" name="m4w_cc_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( is_string( $settings[ $key ] ) ? $settings[ $key ] : '' ); ?>" placeholder="<?php echo esc_attr( $consent->get_default_text( $key ) ); ?>" class="large-text">
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Categories', 'm4w-cookie-consent' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $settings['categories'] as $slug => $cat ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $slug ); ?></th>
					<td>
						<p><strong><?php esc_html_e( 'Label override', 'm4w-cookie-consent' ); ?>:</strong>
						<input type="text" name="m4w_cc_settings[categories][<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( is_string( $cat['label'] ) ? $cat['label'] : '' ); ?>" placeholder="<?php echo esc_attr( $consent->get_default_category_text( $slug, 'label' ) ); ?>" class="large-text"></p>
						<p><strong><?php esc_html_e( 'Description override', 'm4w-cookie-consent' ); ?>:</strong>
						<textarea name="m4w_cc_settings[categories][<?php echo esc_attr( $slug ); ?>][description]" rows="2" class="large-text" placeholder="<?php echo esc_attr( $consent->get_default_category_text( $slug, 'description' ) ); ?>"><?php echo esc_textarea( is_string( $cat['description'] ) ? $cat['description'] : '' ); ?></textarea></p>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Settings', 'm4w-cookie-consent' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Plugin', 'm4w-cookie-consent' ); ?></th>
					<td><label><input type="checkbox" name="m4w_cc_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Display cookie consent banner on the frontend', 'm4w-cookie-consent' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Privacy Policy URL', 'm4w-cookie-consent' ); ?></th>
					<td><input type="url" name="m4w_cc_settings[privacy_url]" value="<?php echo esc_attr( $settings['privacy_url'] ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cookie ID', 'm4w-cookie-consent' ); ?></th>
					<td>
						<input type="text" name="m4w_cc_settings[cookie_id]" value="<?php echo esc_attr( $settings['cookie_id'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Cookie name written by this plugin. Changing this value invalidates consent stored under the previous primary cookie name; CookieYes consent is still read as a migration fallback.', 'm4w-cookie-consent' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Consent Expiry (days)', 'm4w-cookie-consent' ); ?></th>
					<td><input type="number" name="m4w_cc_settings[consent_expiry]" value="<?php echo esc_attr( $settings['consent_expiry'] ); ?>" min="1" max="730">
					<p class="description"><?php esc_html_e( 'For users who accepted all categories.', 'm4w-cookie-consent' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rejected Expiry (days)', 'm4w-cookie-consent' ); ?></th>
					<td><input type="number" name="m4w_cc_settings[consent_expiry_rejected]" value="<?php echo esc_attr( $settings['consent_expiry_rejected'] ); ?>" min="1" max="730">
					<p class="description"><?php esc_html_e( 'For users who rejected or customized. Shorter value shows the banner more often.', 'm4w-cookie-consent' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Google Consent Mode v2', 'm4w-cookie-consent' ); ?></th>
					<td><label><input type="checkbox" name="m4w_cc_settings[gcm_enabled]" value="1" <?php checked( $settings['gcm_enabled'] ); ?>> <?php esc_html_e( 'Enable GCM v2', 'm4w-cookie-consent' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'CSS Override', 'm4w-cookie-consent' ); ?></th>
					<td><textarea name="m4w_cc_settings[custom_css]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Custom CSS to override banner styles. Uses m4w-cc-* selectors.', 'm4w-cookie-consent' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Header Scripts', 'm4w-cookie-consent' ); ?></th>
					<td><textarea name="m4w_cc_settings[header_scripts]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['header_scripts'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Custom JavaScript injected into <head>. Do not include <script> tags.', 'm4w-cookie-consent' ); ?></p></td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
