<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class M4W_CC_Meta_Pixel_Consent {

	private $consent;

	public function __construct( $consent_instance ) {
		$this->consent = $consent_instance;
		add_action( 'template_redirect', array( $this, 'start_output_buffer' ) );
	}

	public function start_output_buffer() {
		ob_start( array( $this, 'rewrite_tracking_scripts' ) );
	}

	public function rewrite_tracking_scripts( $html ) {
		$consent       = $this->consent->get_consent_cookie();
		$initial_grant = $consent && isset( $consent['advertisement'] ) && $consent['advertisement'] === 'yes';

		return preg_replace_callback(
			"/fbq\s*\(\s*'init'\s*,.*?\);\s*/s",
			function ( $match ) use ( $initial_grant ) {
				$code = "fbq('consent', 'revoke');\n" . $match[0];
				if ( $initial_grant ) {
					$code .= "\nfbq('consent', 'grant');";
				}
				$code .= "\ndocument.addEventListener('m4w_cc_consent_update',function(e){fbq('consent',e.detail.advertisement==='yes'?'grant':'revoke');});";
				return $code;
			},
			$html
		);
	}
}
