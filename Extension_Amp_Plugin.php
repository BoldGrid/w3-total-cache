<?php
/**
 * File: Extension_Amp_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
/**
 * Class Extension_Amp_Plugin
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Extension_Amp_Plugin {
	/**
	 * Is AMP endpoint
	 *
	 * @var bool|null
	 */
	private $is_amp_endpoint = null;

	/**
	 * Adds actions for loading the AMP extension.
	 *
	 * @return void
	 */
	public static function wp_loaded() {
		add_action( 'w3tc_extension_load', array( '\W3TC\Extension_Amp_Plugin', 'w3tc_extension_load' ) );
		add_action( 'w3tc_extension_load_admin', array( '\W3TC\Extension_Amp_Plugin_Admin', 'w3tc_extension_load_admin' ) );
	}

	/**
	 * Loads the AMP extension by adding various filters.
	 *
	 * @return void
	 */
	public static function w3tc_extension_load() {
		$w3tc_o = new Extension_Amp_Plugin();

		add_filter( 'w3tc_minify_js_enable', array( $w3tc_o, 'w3tc_minify_jscss_enable' ) );
		add_filter( 'w3tc_minify_css_enable', array( $w3tc_o, 'w3tc_minify_jscss_enable' ) );
		add_filter( 'w3tc_lazyload_can_process', array( $w3tc_o, 'w3tc_lazyload_can_process' ) );
		add_filter( 'w3tc_footer_comment', array( $w3tc_o, 'w3tc_footer_comment' ) );
		add_filter( 'w3tc_newrelic_should_disable_auto_rum', array( $w3tc_o, 'w3tc_newrelic_should_disable_auto_rum' ) );
		add_filter( 'w3tc_pgcache_flush_post_queued_urls', array( $w3tc_o, 'x_flush_post_queued_urls' ) );
		add_filter( 'w3tc_varnish_flush_post_queued_urls', array( $w3tc_o, 'x_flush_post_queued_urls' ) );
		add_filter( 'w3tc_pagecache_set', array( $w3tc_o, 'w3tc_pagecache_set' ) );
		add_filter( 'w3tc_config_default_values', array( $w3tc_o, 'w3tc_config_default_values' ) );

		// rules generation.
		add_filter( 'w3tc_pagecache_rules_apache_accept_qs', array( $w3tc_o, 'w3tc_pagecache_rules_x_accept_qs' ) );
		add_filter( 'w3tc_pagecache_rules_apache_accept_qs_rules', array( $w3tc_o, 'w3tc_pagecache_rules_apache_accept_qs_rules' ), 10, 2 );
		add_filter( 'w3tc_pagecache_rules_apache_uri_prefix', array( $w3tc_o, 'w3tc_pagecache_rules_apache_uri_prefix' ) );

		add_filter( 'w3tc_pagecache_rules_nginx_accept_qs', array( $w3tc_o, 'w3tc_pagecache_rules_x_accept_qs' ) );
		add_filter( 'w3tc_pagecache_rules_nginx_accept_qs_rules', array( $w3tc_o, 'w3tc_pagecache_rules_nginx_accept_qs_rules' ), 10, 2 );
		add_filter( 'w3tc_pagecache_rules_nginx_uri_prefix', array( $w3tc_o, 'w3tc_pagecache_rules_nginx_uri_prefix' ) );
	}

	/**
	 * Checks if the current request is an AMP endpoint.
	 *
	 * @return bool True if it is an AMP endpoint, false otherwise.
	 */
	private function is_amp_endpoint() {
		// support for different plugins defining those own functions.
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

	/**
	 * Enables or disables minification of JS and CSS for AMP endpoints.
	 *
	 * @param bool $w3tc_enabled Whether minification is enabled.
	 *
	 * @return bool False if AMP endpoint, otherwise the original enabled value.
	 */
	public function w3tc_minify_jscss_enable( $w3tc_enabled ) {
		if ( $this->is_amp_endpoint() ) {
			// amp has own rules for CSS and JS files, don't touch them by default.
			return false;
		}

		return $w3tc_enabled;
	}

	/**
	 * Determines if New Relic's auto RUM should be disabled for AMP endpoints.
	 *
	 * @param string $reject_reason The current rejection reason.
	 *
	 * @return string Modified rejection reason.
	 */
	public function w3tc_newrelic_should_disable_auto_rum( $reject_reason ) {
		if ( $this->is_amp_endpoint() ) {
			return 'AMP endpoint';
		}

		return $reject_reason;
	}

	/**
	 * Disables lazy loading on AMP endpoints.
	 *
	 * @param array $can_process Array indicating whether lazyload can process.
	 *
	 * @return array Modified can_process array.
	 */
	public function w3tc_lazyload_can_process( $can_process ) {
		if ( $this->is_amp_endpoint() ) {
			$can_process['enabled'] = false;
			$can_process['reason']  = 'AMP endpoint';
		}

		return $can_process;
	}


	/**
	 * Modifies queued URLs to include AMP URLs.
	 *
	 * @param array $queued_urls List of queued URLs to be flushed.
	 *
	 * @return array Modified list of queued URLs.
	 */
	public function x_flush_post_queued_urls( $queued_urls ) {
		$amp_urls    = array();
		$w3tc_c      = Dispatcher::config();
		$url_postfix = $w3tc_c->get_string( array( 'amp', 'url_postfix' ) );

		if ( 'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) ) {
			foreach ( $queued_urls as $w3tc_url ) {
				$amp_urls[] = $w3tc_url . '?' . $url_postfix;
			}
		} else {
			foreach ( $queued_urls as $w3tc_url ) {
				$amp_urls[] = trailingslashit( $w3tc_url ) . $url_postfix;
			}
		}

		$queued_urls = array_merge( $queued_urls, $amp_urls );

		return $queued_urls;
	}

	/**
	 * Adds a comment to footer for AMP pages indicating limited minification.
	 *
	 * @param array $strings Array of strings for footer comments.
	 *
	 * @return array Modified array of footer comment strings.
	 */
	public function w3tc_footer_comment( $strings ) {
		if ( $this->is_amp_endpoint() ) {
			$strings[] = 'AMP page, minification is limited';
		}

		return $strings;
	}

	/**
	 * Modifies page cache set data for AMP pages.
	 *
	 * @param array $w3tc_data The data to be cached.
	 *
	 * @return array Modified cache data.
	 */
	public function w3tc_pagecache_set( $w3tc_data ) {
		if ( $this->is_amp_endpoint() ) {
			// workaround to prevent Link headers from parent page to appear in amp page coming from it's .htaccess.
			$w3tc_c = Dispatcher::config();
			if ( $w3tc_c->getf_boolean( 'minify.css.http2push' ) ||
					$w3tc_c->getf_boolean( 'minify.js.http2push' ) ) {
				$w3tc_data['headers'][] = array(
					'n'           => 'Link',
					'v'           => '',
					'files_match' => '\\.html[_a-z]*$',
				);

				$this->w3tc_pagecache_set_header_register_once();
			}
		}

		return $w3tc_data;
	}

	/**
	 * Registers a filter for the page cache set header only once.
	 *
	 * @return void
	 */
	private function w3tc_pagecache_set_header_register_once() {
		static $registered = false;

		if ( ! $registered ) {
			add_filter( 'w3tc_pagecache_set_header', array( $this, 'w3tc_pagecache_set_header' ), 20, 2 );
		}
	}

	/**
	 * Modifies the page cache set header for AMP pages.
	 *
	 * @param array $header The current header.
	 * @param array $header_original The original header.
	 *
	 * @return array Modified header.
	 */
	public function w3tc_pagecache_set_header( $header, $header_original ) {
		if ( 'Link' === $header_original['n'] && empty( $header_original['v'] ) ) {
			// forces removal of Link header for file_generic when its set by parent .htaccess.
			return $header_original;
		}

		return $header;
	}

	/**
	 * Sets the default values for AMP configuration.
	 *
	 * @param array $default_values The default configuration values.
	 *
	 * @return array Modified default values.
	 */
	public function w3tc_config_default_values( $default_values ) {
		$default_values['amp'] = array(
			'url_type'    => 'tag',
			'url_postfix' => 'amp',
		);

		return $default_values;
	}

	/**
	 * Normalizes URL fragments for AMP pages.
	 *
	 * @param array $url_fragments The URL fragments to be normalized.
	 *
	 * @return array Modified URL fragments.
	 */
	public static function pagecache_normalize_url_fragments( $url_fragments ) {
		$w3tc_c = Dispatcher::config();

		if ( 'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) ) {
			if ( ! empty( $url_fragments['querystring'] ) ) {
				$qs = $w3tc_c->get_string( array( 'amp', 'url_postfix' ) );

				$url_qs = substr( $url_fragments['querystring'], 1 ); // cut off "?".

				$regexp = Util_Environment::preg_quote( str_replace( '+', ' ', $qs ) );
				if ( @preg_match( "~^(.*?&|)$regexp(=[^&]*)?(&.*|)$~i", $url_qs, $m ) ) {
					$url_qs = $m[1] . $m[3];
					$url_qs = preg_replace( '~[&]+~', '&', $url_qs );
					$url_qs = trim( $url_qs, '&' );
					$url_qs = empty( $url_qs ) ? '' : '?' . $url_qs;

					$url_fragments['querystring']   = $url_qs;
					$url_fragments['amp_extension'] = 1;
				}
			}
		}

		return $url_fragments;
	}

	/**
	 * Modifies the page cache key for AMP pages.
	 *
	 * @param array $w3tc_o The original page cache data.
	 *
	 * @return array Modified page cache data.
	 */
	public static function pagecache_page_key( $w3tc_o ) {
		$w3tc_c = Dispatcher::config();

		if ( isset( $w3tc_o['url_fragments']['amp_extension'] ) ) {
			$w3tc_o['key'][1] .= '_amp';

		}

		return $w3tc_o;
	}

	/**
	 * Modifies query strings for AMP page cache rules.
	 *
	 * @param array $query_strings The query strings to be modified.
	 *
	 * @return array Modified query strings.
	 */
	public function w3tc_pagecache_rules_x_accept_qs( $query_strings ) {
		$w3tc_c = Dispatcher::config();

		if ( 'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) ) {
			$query_strings[] = $w3tc_c->get_string( array( 'amp', 'url_postfix' ) );
		}

		return $query_strings;
	}

	/**
	 * Modifies Apache query rules for AMP URLs.
	 *
	 * @param array  $query_rules The original query rules.
	 * @param string $query The query string to check.
	 *
	 * @return array Modified query rules.
	 */
	public function w3tc_pagecache_rules_apache_accept_qs_rules( $query_rules, $query ) {
		$w3tc_c = Dispatcher::config();

		if (
			'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) &&
			$query === $w3tc_c->get_string( array( 'amp', 'url_postfix' ) )
		) {
			$query_rules[1] = str_replace( '[E=', '[E=W3TC_AMP:_amp,E=', $query_rules[1] );
		}

		return $query_rules;
	}

	/**
	 * Modifies Apache URI prefix for AMP pages.
	 *
	 * @param string $uri_prefix The original URI prefix.
	 *
	 * @return string Modified URI prefix.
	 */
	public function w3tc_pagecache_rules_apache_uri_prefix( $uri_prefix ) {
		$w3tc_c = Dispatcher::config();

		if ( 'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) ) {
			$uri_prefix .= '%{ENV:W3TC_AMP}';
		}

		return $uri_prefix;
	}

	/**
	 * Modifies Nginx query rules for AMP URLs.
	 *
	 * @param array  $query_rules The original query rules.
	 * @param string $query The query string to check.
	 *
	 * @return array Modified query rules.
	 */
	public function w3tc_pagecache_rules_nginx_accept_qs_rules( $query_rules, $query ) {
		$w3tc_c = Dispatcher::config();

		if (
			'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) &&
			$query === $w3tc_c->get_string( array( 'amp', 'url_postfix' ) )
		) {
			array_splice( $query_rules, 1, 0, '    set $w3tc_amp "_amp";' );
			array_unshift( $query_rules, 'set $w3tc_amp "";' );
		}

		return $query_rules;
	}

	/**
	 * Modifies Nginx URI prefix for AMP pages.
	 *
	 * @param string $uri_prefix The original URI prefix.
	 *
	 * @return string Modified URI prefix.
	 */
	public function w3tc_pagecache_rules_nginx_uri_prefix( $uri_prefix ) {
		$w3tc_c = Dispatcher::config();

		if ( 'querystring' === $w3tc_c->get_string( array( 'amp', 'url_type' ) ) ) {
			$uri_prefix .= '$w3tc_amp';
		}

		return $uri_prefix;
	}
}

w3tc_add_action( 'pagecache_normalize_url_fragments', array( '\W3TC\Extension_Amp_Plugin', 'pagecache_normalize_url_fragments' ) );
w3tc_add_action( 'pagecache_page_key', array( '\W3TC\Extension_Amp_Plugin', 'pagecache_page_key' ) );
w3tc_add_action( 'wp_loaded', array( '\W3TC\Extension_Amp_Plugin', 'wp_loaded' ) );
