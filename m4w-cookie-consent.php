<?php
/*
 * Plugin Name: M4W Cookie Consent
 * Description: A simple WordPress Cookie Consent plugin
 * Version: 1.1.1
 * Author: m4g4
 * License: MIT
 * Text Domain: m4w-cookie-consent
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-settings.php';
require_once __DIR__ . '/meta-pixel.php';

class M4W_CC_Cookie_Consent {

	const COOKIE_DEFAULT = 'm4w_cc_consent';
	const COOKIE_OLD = 'cookieyes-consent';
	const OPTION_KEY = 'm4w_cc_settings';

	private static $instance;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$settings = $this->get_settings();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		if ( $settings['enabled'] ) {
			add_action( 'wp_head', array( $this, 'output_gcm_defaults' ), -PHP_INT_MAX );
			add_action( 'wp_head', array( $this, 'output_header_scripts' ), 100 );
			add_action( 'wp_head', array( $this, 'output_custom_css' ), 100 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_footer', array( $this, 'output_banner' ) );

			new M4W_CC_Meta_Pixel_Consent( $this );
		}

		add_action( 'wp_ajax_m4w_cc_save_consent', array( $this, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_m4w_cc_save_consent', array( $this, 'ajax_save_consent' ) );
	}

	public function ajax_save_consent() {
		check_ajax_referer('m4w_cc_consent');
		$data = array();
		parse_str($_POST['data'] ?? '', $data);
		$this->set_consent_cookie( $data );
		wp_send_json_success();
	}

	public function load_textdomain() {
		load_muplugin_textdomain( 'm4w-cookie-consent', 'm4w-cookie-consent/languages' );
	}

	public function get_defaults() {
		return array(
			'banner_title'       => '',
			'banner_description' => '',
			'btn_accept'         => '',
			'btn_accept_all'     => '',
			'btn_reject'         => '',
			'btn_customize'      => '',
			'btn_save'           => '',
			'pref_title'         => '',
			'privacy_link_text'  => '',
			'privacy_url'        => home_url( '/ochrana-osobnych-udajov/' ),
			'enabled'                => true,
			'cookie_id'              => self::COOKIE_DEFAULT,
			'consent_expiry'         => 365,
			'consent_expiry_rejected' => 30,
			'gcm_enabled'            => true,
			'custom_css'             => '',
			'header_scripts'         => '',
			'categories'         => array(
				'necessary'     => array(
					'label'       => '',
					'description' => '',
					'required'    => true,
					'default'     => true,
				),
				'functional'    => array(
					'label'       => '',
					'description' => '',
					'required'    => false,
					'default'     => false,
				),
				'analytics'     => array(
					'label'       => '',
					'description' => '',
					'required'    => false,
					'default'     => false,
				),
				'advertisement' => array(
					'label'       => '',
					'description' => '',
					'required'    => false,
					'default'     => false,
				),
			),
		);
	}

	public function get_settings() {
		$defaults = $this->get_defaults();
		$saved    = get_option( self::OPTION_KEY, array() );
		return array_replace_recursive( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public function t( $key ) {
		$settings = $this->get_settings();
		if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && $settings[ $key ] !== '' ) {
			return $settings[ $key ];
		}

		return $this->get_default_text( $key );
	}

	public function get_default_text( $key ) {
		$defaults = array(
			'banner_title'       => __( 'This website uses cookies', 'm4w-cookie-consent' ),
			'banner_description' => __( 'We use cookies to ensure the website functions properly, analyze traffic, and personalize content and ads.', 'm4w-cookie-consent' ),
			'btn_accept'         => __( 'Accept All', 'm4w-cookie-consent' ),
			'btn_accept_all'     => __( 'Accept All', 'm4w-cookie-consent' ),
			'btn_reject'         => __( 'Only Necessary', 'm4w-cookie-consent' ),
			'btn_customize'      => __( 'Customize', 'm4w-cookie-consent' ),
			'btn_save'           => __( 'Save Settings', 'm4w-cookie-consent' ),
			'pref_title'         => __( 'Cookie Settings', 'm4w-cookie-consent' ),
			'privacy_link_text'  => __( 'Privacy Policy', 'm4w-cookie-consent' ),
		);

		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	public function get_category_text( $slug, $field ) {
		$settings = $this->get_settings();
		if ( isset( $settings['categories'][ $slug ][ $field ] ) && is_string( $settings['categories'][ $slug ][ $field ] ) && $settings['categories'][ $slug ][ $field ] !== '' ) {
			return $settings['categories'][ $slug ][ $field ];
		}

		return $this->get_default_category_text( $slug, $field );
	}

	public function get_default_category_text( $slug, $field ) {
		$defaults = array(
			'necessary'     => array(
				'label'       => __( 'Necessary', 'm4w-cookie-consent' ),
				'description' => __( 'These cookies are essential for the website to function properly.', 'm4w-cookie-consent' ),
			),
			'functional'    => array(
				'label'       => __( 'Functional', 'm4w-cookie-consent' ),
				'description' => __( 'Allow us to remember your preferences and enhance your experience.', 'm4w-cookie-consent' ),
			),
			'analytics'     => array(
				'label'       => __( 'Analytics', 'm4w-cookie-consent' ),
				'description' => __( 'Help us analyze how visitors use the website and improve its performance.', 'm4w-cookie-consent' ),
			),
			'advertisement' => array(
				'label'       => __( 'Marketing', 'm4w-cookie-consent' ),
				'description' => __( 'Used to show relevant ads and measure their effectiveness.', 'm4w-cookie-consent' ),
			),
		);

		return isset( $defaults[ $slug ][ $field ] ) ? $defaults[ $slug ][ $field ] : '';
	}

	public function get_consent_cookie() {
		$primary_cookie_id = $this->get_primary_cookie_id();
		$primary_value     = isset( $_COOKIE[ $primary_cookie_id ] ) ? $_COOKIE[ $primary_cookie_id ] : '';
		if ( $primary_value ) {
			return $this->parse_consent_cookie( $primary_value );
		}

		$old_value = isset( $_COOKIE[ self::COOKIE_OLD ] ) ? $_COOKIE[ self::COOKIE_OLD ] : '';
		if ( $old_value ) {
			return $this->parse_consent_cookie( $old_value );
		}

		return null;
	}

	public function get_primary_cookie_id() {
		$settings  = $this->get_settings();
		$cookie_id = isset( $settings['cookie_id'] ) ? $this->sanitize_cookie_id( $settings['cookie_id'] ) : '';

		return $cookie_id ? $cookie_id : self::COOKIE_DEFAULT;
	}

	public function sanitize_cookie_id( $cookie_id ) {
		return preg_replace( '/[^A-Za-z0-9_.-]/', '', trim( (string) $cookie_id ) );
	}

	private function parse_consent_cookie( $value ) {
		$data = array(
			'consentid' => '',
			'consent'   => '',
			'action'    => '',
		);

		$pairs = explode( ',', $value );
		foreach ( $pairs as $pair ) {
			$parts = explode( ':', $pair, 2 );
			if ( count( $parts ) === 2 ) {
				$data[ $parts[0] ] = $parts[1];
			}
		}

		return $data;
	}

	public function set_consent_cookie( $data ) {
		$parts = array();
		foreach ( $data as $key => $value ) {
			$parts[] = $key . ':' . $value;
		}
		$value  = implode( ',', $parts );
		$settings = $this->get_settings();
		if ( $this->is_full_consent( $data ) ) {
			$expiry = (int) $settings['consent_expiry'];
		} else {
			$expiry = (int) $settings['consent_expiry_rejected'];
		}
		$cookie_id = $this->get_primary_cookie_id();
		setcookie( $cookie_id, $value, time() + $expiry * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ $cookie_id ] = $value;
	}

	private function is_full_consent( $data ) {
		$categories = $this->get_settings()['categories'];
		foreach ( $categories as $slug => $cat ) {
			if ( ! empty( $cat['required'] ) ) {
				continue;
			}
			if ( ! isset( $data[ $slug ] ) || $data[ $slug ] !== 'yes' ) {
				return false;
			}
		}
		return true;
	}

	public function get_gcm_map() {
		return array(
			'necessary'     => array(
				'granted' => array( 'security_storage' ),
				'denied'  => array(),
			),
			'functional'    => array(
				'granted' => array( 'functionality_storage', 'personalization_storage' ),
				'denied'  => array( 'functionality_storage', 'personalization_storage' ),
			),
			'analytics'     => array(
				'granted' => array( 'analytics_storage' ),
				'denied'  => array( 'analytics_storage' ),
			),
			'advertisement' => array(
				'granted' => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
				'denied'  => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
			),
		);
	}

	public function output_gcm_defaults() {
		if ( ! $this->get_settings()['gcm_enabled'] ) {
			return;
		}
		$consent = $this->get_consent_cookie();
		?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}

gtag("consent","default",{
	ad_storage:"denied",
	ad_user_data:"denied",
	ad_personalization:"denied",
	analytics_storage:"denied",
	functionality_storage:"denied",
	personalization_storage:"denied",
	security_storage:"granted",
	wait_for_update:500
});
gtag("set","ads_data_redaction",false);
gtag("set","url_passthrough",false);
gtag("set","developer_id.dY2Q2ZW",true);
<?php if ( $consent && isset( $consent['action'] ) && $consent['action'] === 'yes' ) : ?>
(function(){
	var map = <?php echo json_encode( $this->get_gcm_map() ); ?>;
	var data = {};
	<?php foreach ( $this->get_settings()['categories'] as $slug => $cat ) : ?>
	if (<?php echo json_encode( $slug ); ?> in map) {
		var granted = <?php echo isset( $consent[ $slug ] ) && $consent[ $slug ] === 'yes' ? 'true' : 'false'; ?>;
		(map[<?php echo json_encode( $slug ); ?>][granted ? "granted" : "denied"]).forEach(function(t){ data[t] = granted ? "granted" : "denied"; });
	}
	<?php endforeach; ?>
	gtag("consent","update",data);
})();
<?php endif; ?>
</script>
		<?php
	}

	public function enqueue_assets() {
		$url = untrailingslashit( plugin_dir_url( __FILE__ ) );
		wp_enqueue_style( 'm4w-cc-banner', $url . '/assets/m4w-cc-core.css', array(), '1.1.1' );
		wp_enqueue_script( 'm4w-cc-banner', $url . '/assets/m4w-cc-core.js', array(), '1.1.1', true );
		$settings = $this->get_settings();
		wp_localize_script(
			'm4w-cc-banner',
			'_m4wCC',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'm4w_cc_consent' ),
				'cookie'           => $this->get_primary_cookie_id(),
				'oldCookie'        => self::COOKIE_OLD,
				'expiry'           => (int) $settings['consent_expiry'],
				'expiryRejected'   => (int) $settings['consent_expiry_rejected'],
				'gcm'              => $settings['gcm_enabled'],
				'gcmMap'           => $this->get_gcm_map(),
			)
		);
	}

	public function output_custom_css() {
		$css = $this->get_settings()['custom_css'];
		if ( ! $css ) {
			return;
		}
		echo '<style id="m4w-cc-custom-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
	}

	public function output_header_scripts() {
		$js = $this->get_settings()['header_scripts'];
		if ( ! $js ) {
			return;
		}
		echo '<script>' . "\n" . $js . "\n" . '</script>' . "\n";
	}

	public function output_banner() {
		$consent = $this->get_consent_cookie();
		if ( $consent && isset( $consent['action'] ) && $consent['action'] === 'yes' ) {
			return;
		}
		$settings = $this->get_settings();
		?>
<div id="m4w-cc-banner" role="dialog" aria-label="Cookie Consent">
	<div class="m4w-cc-inner">
		<div class="m4w-cc-text">
			<p class="m4w-cc-title"><?php echo esc_html( $this->t( 'banner_title' ) ); ?></p>
			<p class="m4w-cc-desc"><?php echo esc_html( $this->t( 'banner_description' ) ); ?></p>
		</div>
		<div class="m4w-cc-buttons">
			<button id="m4w-cc-accept-all" class="ui button small primary m4w-cc-btn m4w-cc-accept-all"><?php echo esc_html( $this->t( 'btn_accept' ) ); ?></button>
			<button id="m4w-cc-customize" class="ui button small secondary m4w-cc-btn m4w-cc-customize"><?php echo esc_html( $this->t( 'btn_customize' ) ); ?></button>
			<button id="m4w-cc-reject-all" class="ui button small secondary m4w-cc-btn m4w-cc-reject-all"><?php echo esc_html( $this->t( 'btn_reject' ) ); ?></button>
		</div>
	</div>
</div>

<div id="m4w-cc-modal" class="m4w-cc-hidden">
	<div class="m4w-cc-modal-overlay"></div>
	<div class="m4w-cc-modal-content" role="dialog" aria-label="<?php echo esc_attr( $this->t( 'pref_title' ) ); ?>">
		<button class="m4w-cc-modal-close">&times;</button>
		<h2><?php echo esc_html( $this->t( 'pref_title' ) ); ?></h2>
		<table>
			<?php foreach ( $settings['categories'] as $slug => $cat ) : ?>
			<tr>
				<td>
					<label>
						<input type="checkbox" class="m4w-cc-cat-checkbox" data-slug="<?php echo esc_attr( $slug ); ?>"
							<?php echo ! empty( $cat['required'] ) ? 'checked disabled' : ''; ?>>
						<?php echo esc_html( $this->get_category_text( $slug, 'label' ) ); ?>
					</label>
				</td>
				<td>
					<?php echo esc_html( $this->get_category_text( $slug, 'description' ) ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<div class="m4w-cc-modal-actions">
			<button class="ui button small primary m4w-cc-accept-all"><?php echo esc_html( $this->t( 'btn_accept_all' ) ); ?></button>
			<button class="ui button small secondary m4w-cc-save"><?php echo esc_html( $this->t( 'btn_save' ) ); ?></button>
		</div>
		<?php if ( $this->t( 'privacy_url' ) ) : ?>
		<p class="m4w-cc-privacy"><a href="<?php echo esc_url( $this->t( 'privacy_url' ) ); ?>" target="_blank"><?php echo esc_html( $this->t( 'privacy_link_text' ) ); ?></a></p>
		<?php endif; ?>
	</div>
</div>
		<?php
	}

}

M4W_CC_Cookie_Consent::get_instance();
