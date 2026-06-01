<?php
/*
 * Plugin Name: M4W Cookie Consent
 * Description: A simple Wodrpess Cookie Consent plugin
 * Version: 1.0.0
 * Author: m4g4
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/admin-settings.php';
require_once __DIR__ . '/meta-pixel.php';

class M4W_CC_Cookie_Consent {

	const COOKIE_NEW = 'm4w_cc_consent';
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

	public function get_defaults() {
		return array(
			'banner_title'       => array(
				'sk' => 'Tento web používa cookies',
				'en' => 'This website uses cookies',
			),
			'banner_description' => array(
				'sk' => 'Cookies používame na zabezpečenie fungovania stránky, analýzu návštevnosti a personalizáciu obsahu a reklám.',
				'en' => 'We use cookies to ensure the website functions properly, analyze traffic, and personalize content and ads.',
			),
			'btn_accept'         => array(
				'sk' => 'Prijať všetky',
				'en' => 'Accept All',
			),
			'btn_accept_all'         => array(
				'sk' => 'Prijať všetky',
				'en' => 'Accept All',
			),
			'btn_reject'         => array(
				'sk' => 'Len nevyhnutné',
				'en' => 'Only Necessary',
			),
			'btn_customize'      => array(
				'sk' => 'Nastavenia',
				'en' => 'Customize',
			),
			'btn_save'           => array(
				'sk' => 'Uložiť nastavenia',
				'en' => 'Save Settings',
			),
			'pref_title'         => array(
				'sk' => 'Nastavenia cookies',
				'en' => 'Cookie Settings',
			),
			'privacy_link_text'  => array(
				'sk' => 'Zásady ochrany osobných údajov',
				'en' => 'Privacy Policy',
			),
			'privacy_url'        => home_url( '/ochrana-osobnych-udajov/' ),
			'enabled'                => true,
			'consent_expiry'         => 365,
			'consent_expiry_rejected' => 30,
			'gcm_enabled'            => true,
			'custom_css'             => '',
			'header_scripts'         => '',
			'categories'         => array(
				'necessary'     => array(
					'label'       => array(
						'sk' => 'Nevyhnutné',
						'en' => 'Necessary',
					),
					'description' => array(
						'sk' => 'Tieto cookies sú nevyhnutné pre správne fungovanie webu.',
						'en' => 'These cookies are essential for the website to function properly.',
					),
					'required'    => true,
					'default'     => true,
				),
				'functional'    => array(
					'label'       => array(
						'sk' => 'Funkčné',
						'en' => 'Functional',
					),
					'description' => array(
						'sk' => 'Umožňujú zapamätať si Vaše preferencie a zlepšujú používateľský komfort.',
						'en' => 'Allow us to remember your preferences and enhance your experience.',
					),
					'required'    => false,
					'default'     => false,
				),
				'analytics'     => array(
					'label'       => array(
						'sk' => 'Analytické',
						'en' => 'Analytics',
					),
					'description' => array(
						'sk' => 'Pomáhajú nám analyzovať, ako návštevníci používajú web, a zlepšovať jeho fungovanie.',
						'en' => 'Help us analyze how visitors use the website and improve its performance.',
					),
					'required'    => false,
					'default'     => false,
				),
				'advertisement' => array(
					'label'       => array(
						'sk' => 'Marketingové',
						'en' => 'Marketing',
					),
					'description' => array(
						'sk' => 'Používajú sa na zobrazovanie relevantných reklám a meranie ich účinnosti.',
						'en' => 'Used to show relevant ads and measure their effectiveness.',
					),
					'required'    => false,
					'default'     => false,
				),
			),
		);
	}

	public function get_settings() {
		$defaults = $this->get_defaults();
		$saved    = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, $defaults );
	}

	public function get_locale() {
		$locale = get_locale();
		if ( strpos( $locale, 'sk' ) === 0 ) {
			return 'sk';
		}
		return 'en';
	}

	public function t( $key ) {
		$settings = $this->get_settings();
		$lang     = $this->get_locale();
		if ( isset( $settings[ $key ][ $lang ] ) ) {
			return $settings[ $key ][ $lang ];
		}
		if ( isset( $settings[ $key ]['en'] ) ) {
			return $settings[ $key ]['en'];
		}
		if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		return '';
	}

	public function get_consent_cookie() {
		$new = isset( $_COOKIE[ self::COOKIE_NEW ] ) ? $_COOKIE[ self::COOKIE_NEW ] : '';
		if ( $new ) {
			return $this->parse_consent_cookie( $new );
		}
		$old = isset( $_COOKIE[ self::COOKIE_OLD ] ) ? $_COOKIE[ self::COOKIE_OLD ] : '';
		if ( $old ) {
			return $this->parse_consent_cookie( $old );
		}
		return null;
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
		setcookie( self::COOKIE_NEW, $value, time() + $expiry * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE[ self::COOKIE_NEW ] = $value;
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
		wp_enqueue_style( 'm4w-cc-banner', $url . '/assets/m4w-cookie-consent.css', array(), '1.0' );
		wp_enqueue_script( 'm4w-cc-banner', $url . '/assets/m4w-cookie-consent.js', array(), '1.0', true );
		$settings = $this->get_settings();
		wp_localize_script(
			'm4w-cc-banner',
			'_m4wCC',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'm4w_cc_consent' ),
				'cookie'           => self::COOKIE_NEW,
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
						<?php echo esc_html( isset( $cat['label'][ $this->get_locale() ] ) ? $cat['label'][ $this->get_locale() ] : $cat['label']['en'] ); ?>
					</label>
				</td>
				<td>
					<?php echo esc_html( isset( $cat['description'][ $this->get_locale() ] ) ? $cat['description'][ $this->get_locale() ] : $cat['description']['en'] ); ?>
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
