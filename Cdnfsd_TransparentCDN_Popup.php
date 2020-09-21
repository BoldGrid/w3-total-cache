<?php
namespace W3TC;



class Cdnfsd_StackPath2_Popup {
	static public function w3tc_ajax() {
		$o = new Cdnfsd_TransparentCDN_Popup();

		add_action( 'w3tc_ajax_cdn_transparentcdn_fsd_intro',
			array( $o, 'w3tc_ajax_cdn_transparentcdn_fsd_intro' ) );
		add_action( 'w3tc_ajax_cdn_stackpath2_fsd_configure_site',
			array( $o, 'w3tc_ajax_cdn_stackpath2_fsd_configure_site' ) );
	}



	public function w3tc_ajax_cdn_stackpath2_fsd_intro() {
		$config = Dispatcher::config();

		$this->render_intro( array(
			'client_id' => $config->get_string( 'cdnfsd.transparentcdn.client_id' ),
			'client_secret' => $config->get_string( 'cdnfsd.transparentcdn.client_secret' ),
			'company_id' => $config->get_string( 'cdnfsd.transparentcdn.company_id' )
		) );
	}



	private function render_intro( $details ) {
		$config = Dispatcher::config();
		//$url_obtain_key = W3TC_STACKPATH2_AUTHORIZE_URL;
		include  W3TC_DIR . '/Cdnfsd_TransparentCDN_Popup_View_Intro.php';
		exit();
	}



	public function w3tc_ajax_cdn_stackpath2_fsd_configure_site() {
		$api_config = $this->api_config_decode( $_REQUEST['api_config'] );
		$site_id = Util_Request::get( 'site_id', '' );

		$api = new Cdn_StackPath2_Api( $api_config );

		try {
			if ( empty( $site_id ) ) {
				// create new zone mode
				$hostname = parse_url( home_url(), PHP_URL_HOST );
				$hostname = 'an6.w3-edge.com';

				$r = $api->site_create( array(
					'domain' => $hostname,
					'origin' => array(
						'path' => '/',
						'hostname' => $hostname,
						'port' => 80,
						'securePort' => 443
					),
					'features' => array( 'CDN' )
				) );

				$site_id = $r['site']['id'];
			}

			$r = $api->site_dns_targets_get( $site_id );
			$domains = $r['addresses'];
		} catch ( \Exception $ex ) {
			$this->render_intro( array(
					'client_id' => $api_config['client_id'],
					'client_secret' => $api_config['client_secret'],
					'stack_id' => $api_config['stack_id'],
					'error_message' => __('Can\'t obtain site: ') . $ex->getMessage()
				) );
			exit();
		}

		$c = Dispatcher::config();
		$c->set( 'cdnfsd.stackpath2.client_id', $api_config['client_id'] );
		$c->set( 'cdnfsd.stackpath2.client_secret', $api_config['client_secret'] );
		$c->set( 'cdnfsd.stackpath2.stack_id', $api_config['stack_id'] );
		$c->set( 'cdnfsd.stackpath2.site_id', $site_id );
		$c->set( 'cdnfsd.stackpath2.site_root_domain', $domains[0] );
		$c->set( 'cdnfsd.stackpath2.domain', $domains );
		$c->save();

		include  W3TC_DIR . '/Cdnfsd_StackPath2_Popup_View_Success.php';
		exit();
	}



	private function api_config_encode( $c ) {
		return implode( ';', array(
			$c['client_id'], $c['client_secret'],
			isset( $c['stack_id'] ) ? $c['stack_id'] : ''
		) );
	}



	private function api_config_decode( $s ) {
		$a = explode( ';', $s );
		return array(
			'client_id' => $a[0],
			'client_secret' => $a[1],
			'stack_id' => $a[2]
		);
	}
}
