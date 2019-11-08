<?php
namespace W3TC;

class Extension_Amp_Plugin {
	private $is_amp_endpoint = null;



	public function run() {
		add_filter( 'w3tc_minify_js_enable',
			array( $this, 'w3tc_minify_jscss_enable' ) );
		add_filter( 'w3tc_minify_css_enable',
			array( $this, 'w3tc_minify_jscss_enable' ) );
		add_filter( 'w3tc_lazyload_can_process',
			array( $this, 'w3tc_lazyload_can_process' ) );
		add_filter( 'w3tc_footer_comment',
			array( $this, 'w3tc_footer_comment' ) );
		add_filter( 'w3tc_newrelic_should_disable_auto_rum',
			array( $this, 'w3tc_newrelic_should_disable_auto_rum' ) );
		add_filter( 'pgcache_flush_post_queued_urls',
			array( $this, 'x_flush_post_queued_urls' ) );
		add_filter( 'varnish_flush_post_queued_urls',
			array( $this, 'x_flush_post_queued_urls' ) );
		add_filter( 'w3tc_pagecache_set',
			array( $this, 'w3tc_pagecache_set' ) );
	}



	private function is_amp_endpoint() {
		// support for different plugins defining those own functions
		if ( is_null( $this->is_amp_endpoint ) ) {
			if ( function_exists( 'is_amp_endpoint' ) ) {
				$this->is_amp_endpoint = is_amp_endpoint();
			} elseif ( function_exists( 'ampforwp_is_amp_endpoint' ) ) {
				$this->is_amp_endpoint = ampforwp_is_amp_endpoint();
			} elseif ( function_exists( 'is_better_amp' ) ) {
				$this->is_amp_endpoint = is_better_amp();
			}
		}

		return is_null( $this->is_amp_endpoint ) ? false : $this->is_amp_endpoint;
	}



	public function w3tc_minify_jscss_enable( $enabled ) {
		if ( $this->is_amp_endpoint() ) {
			// amp has own rules for CSS and JS files, don't touch them by default
			return false;
		}

		return $enabled;
	}



	public function w3tc_newrelic_should_disable_auto_rum( $reject_reason ) {
		if ( $this->is_amp_endpoint() ) {
			return 'AMP endpoint';
		}

		return $reject_reason;
	}



	public function w3tc_lazyload_can_process( $can_process ) {
		if ( $this->is_amp_endpoint() ) {
			$can_process['enabled'] = false;
			$can_process['reason'] = 'AMP endpoint';
		}

		return $can_process;
	}



	public function x_flush_post_queued_urls( $queued_urls ) {
		$amp_urls = array();

		foreach ( $queued_urls as $url ) {
			$amp_urls[] = trailingslashit( $url ) . 'amp';
		}

		$queued_urls = array_merge( $queued_urls, $amp_urls );
		return $queued_urls;
	}



	public function w3tc_footer_comment( $strings ) {
		if ( $this->is_amp_endpoint() ) {
			$strings[] = 'AMP page, minification is limited';
		}

		return $strings;
	}



	public function w3tc_pagecache_set( $data ) {
		if ( $this->is_amp_endpoint() ) {
			// workaround to prevent Link headers from parent page
			// to appear in amp page coming from it's .htaccess
			$c = Dispatcher::config();
			if ( $c->getf_boolean( 'minify.css.http2push' ) ||
					$c->getf_boolean( 'minify.js.http2push' ) ) {
				$data['headers'][] = array(
					'n' => 'Link', 'v' => '', 'files_match' => '\\.html[_a-z]*$' );

				$this->w3tc_pagecache_set_header_register_once();
			}
		}

		return $data;
	}



	private function w3tc_pagecache_set_header_register_once() {
		static $registered = false;

		if ( !$registered ) {
			add_filter( 'w3tc_pagecache_set_header',
				array( $this, 'w3tc_pagecache_set_header' ), 20, 2 );
		}
	}



	public function w3tc_pagecache_set_header( $header, $header_original ) {
		if ( $header_original['n'] == 'Link' && empty( $header_original['v'] ) ) {
			// forces removal of Link header for file_generic when its set by
			// parent .htaccess
			return $header_original;
		}

		return $header;
	}
}



$p = new Extension_Amp_Plugin();
$p->run();

if ( is_admin() ) {
	$p = new Extension_Amp_Plugin_Admin();
	$p->run();
}
