<?php
/**
 * File: BrowserCache_ConfigLabels.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: BrowserCache_ConfigLabels
 */
class BrowserCache_ConfigLabels {
	/**
	 * Get config labels
	 *
	 * @param array $config_labels Config labels.
	 *
	 * @return array
	 */
	public function config_labels( $config_labels ) {
		return array_merge(
			$config_labels,
			array(
				'browsercache.enabled'                   => __( 'Browser Cache:', 'w3-total-cache' ),
				'browsercache.replace.exceptions'        => __( 'Prevent caching exception list:', 'w3-total-cache' ),
				'browsercache.querystring'               => __( 'Remove query strings from static resources', 'w3-total-cache' ),
				'browsercache.no404wp'                   => __( 'Do not process 404 errors for static objects with WordPress', 'w3-total-cache' ),
				'browsercache.no404wp.exceptions'        => __( '404 error exception list:', 'w3-total-cache' ),
				'browsercache.rewrite'                   => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for URL, 2: closing acronym tag.
						__(
							'Rewrite %1$sURL%2$s structure of objects',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Universal Resource Locator', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.cssjs.last_modified'       => __( 'Set Last-Modified header', 'w3-total-cache' ),
				'browsercache.cssjs.expires'             => __( 'Set expires header', 'w3-total-cache' ),
				'browsercache.cssjs.lifetime'            => __( 'Expires header lifetime:', 'w3-total-cache' ),
				'browsercache.cssjs.cache.control'       => __( 'Set cache control header', 'w3-total-cache' ),
				'browsercache.cssjs.cache.policy'        => __( 'Cache Control policy:', 'w3-total-cache' ),
				'browsercache.cssjs.etag'                => __( 'Set entity tag (eTag)', 'w3-total-cache' ),
				'browsercache.cssjs.w3tc'                => __( 'Set W3 Total Cache header', 'w3-total-cache' ),
				'browsercache.cssjs.compression'         => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Enable %1$sHTTP%2$s (gzip) compression',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.cssjs.brotli'              => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Enable %1$sHTTP%2$s (brotli) compression',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.cssjs.replace'             => __( 'Prevent caching of objects after settings change', 'w3-total-cache' ),
				'browsercache.cssjs.querystring'         => __( 'Remove query strings from static resources', 'w3-total-cache' ),
				'browsercache.cssjs.nocookies'           => __( 'Disable cookies for static files', 'w3-total-cache' ),
				'browsercache.html.last_modified'        => __( 'Set Last-Modified header', 'w3-total-cache' ),
				'browsercache.html.expires'              => __( 'Set expires header', 'w3-total-cache' ),
				'browsercache.html.lifetime'             => __( 'Expires header lifetime:', 'w3-total-cache' ),
				'browsercache.html.cache.control'        => __( 'Set cache control header', 'w3-total-cache' ),
				'browsercache.html.cache.policy'         => __( 'Cache Control policy:', 'w3-total-cache' ),
				'browsercache.html.etag'                 => __( 'Set entity tag (ETag)', 'w3-total-cache' ),
				'browsercache.html.w3tc'                 => __( 'Set W3 Total Cache header', 'w3-total-cache' ),
				'browsercache.html.compression'          => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Enable %1$sHTTP%2$s (gzip) compression',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.html.brotli'               => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Enable %1$sHTTP%2$s (brotli) compression',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.other.last_modified'       => __( 'Set Last-Modified header', 'w3-total-cache' ),
				'browsercache.other.expires'             => __( 'Set expires header', 'w3-total-cache' ),
				'browsercache.other.lifetime'            => __( 'Expires header lifetime:', 'w3-total-cache' ),
				'browsercache.other.cache.control'       => __( 'Set cache control header', 'w3-total-cache' ),
				'browsercache.other.cache.policy'        => __( 'Cache Control policy:', 'w3-total-cache' ),
				'browsercache.other.etag'                => __( 'Set entity tag (ETag)', 'w3-total-cache' ),
				'browsercache.other.w3tc'                => __( 'Set W3 Total Cache header', 'w3-total-cache' ),
				'browsercache.other.compression'         => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Enable %1$sHTTP%2$s (gzip) compression',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.other.brotli'              => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Enable %1$sHTTP%2$s (brotli) compression',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.other.replace'             => __( 'Prevent caching of objects after settings change', 'w3-total-cache' ),
				'browsercache.other.querystring'         => __( 'Remove query strings from static resources', 'w3-total-cache' ),
				'browsercache.other.nocookies'           => __( 'Disable cookies for static files', 'w3-total-cache' ),
				'browsercache.security.session.cookie_httponly' => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'Access session cookies through the %1$sHTTP%2$s only:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.security.session.cookie_secure' => __( 'Send session cookies only to secure connections:', 'w3-total-cache' ),
				'browsercache.security.session.use_only_cookies' => __( 'Use cookies to store session IDs:', 'w3-total-cache' ),
				'browsercache.hsts'                      => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'%1$sHTTP%2$s Strict Transport Security policy',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.security.hsts.directive'   => __( 'Directive:', 'w3-total-cache' ),
				'browsercache.security.xfo'              => __( 'X-Frame-Options', 'w3-total-cache' ),
				'browsercache.security.xfo.directive'    => __( 'Directive:', 'w3-total-cache' ),
				'browsercache.security.xss'              => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for XSS, 2: closing acronym tag.
						__(
							'X-%1$sXSS%2$s-Protection',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Cross-Site Scripting', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.security.xss.directive'    => __( 'Directive:', 'w3-total-cache' ),
				'browsercache.security.xcto'             => __( 'X-Content-Type-Options', 'w3-total-cache' ),
				'browsercache.security.pkp'              => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for HTTP, 2: closing acronym tag.
						__(
							'%1$sHTTP%2$s Public Key Pinning',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Hypertext Transfer Protocol', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.security.pkp.pin'          => __( 'Public Key:', 'w3-total-cache' ),
				'browsercache.security.pkp.pin.backup'   => __( 'Public Key (Backup):', 'w3-total-cache' ),
				'browsercache.security.pkp.extra'        => __( 'Extra Parameters:', 'w3-total-cache' ),
				'browsercache.security.pkp.report.url'   => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for URL, 2: closing acronym tag.
						__(
							'Report %1$sURL%2$s:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Uniform Resource Locator', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'browsercache.security.pkp.report.only'  => __( 'Report Mode Only:', 'w3-total-cache' ),
				'browsercache.security.referrer.policy'  => __( 'Referrer Policy', 'w3-total-cache' ),
				'browsercache.security.referrer.policy.directive' => __( 'Directive:', 'w3-total-cache' ),
				'browsercache.security.csp'              => __( 'Content Security Policy', 'w3-total-cache' ),
				'browsercache.security.csp.reporturi'    => __( 'report-uri:', 'w3-total-cache' ),
				'browsercache.security.csp.reportto'     => __( 'report-to:', 'w3-total-cache' ),
				'browsercache.security.csp.base'         => __( 'base-uri:', 'w3-total-cache' ),
				'browsercache.security.csp.frame'        => __( 'frame-src:', 'w3-total-cache' ),
				'browsercache.security.csp.connect'      => __( 'connect-src:', 'w3-total-cache' ),
				'browsercache.security.csp.font'         => __( 'font-src:', 'w3-total-cache' ),
				'browsercache.security.csp.script'       => __( 'script-src:', 'w3-total-cache' ),
				'browsercache.security.csp.style'        => __( 'style-src:', 'w3-total-cache' ),
				'browsercache.security.csp.img'          => __( 'img-src:', 'w3-total-cache' ),
				'browsercache.security.csp.media'        => __( 'media-src:', 'w3-total-cache' ),
				'browsercache.security.csp.object'       => __( 'object-src:', 'w3-total-cache' ),
				'browsercache.security.csp.plugin'       => __( 'plugin-types:', 'w3-total-cache' ),
				'browsercache.security.csp.form'         => __( 'form-action:', 'w3-total-cache' ),
				'browsercache.security.csp.frame.ancestors' => __( 'frame-ancestors:', 'w3-total-cache' ),
				'browsercache.security.csp.sandbox'      => __( 'sandbox:', 'w3-total-cache' ),
				'browsercache.security.csp.child'        => __( 'child-src:', 'w3-total-cache' ),
				'browsercache.security.csp.manifest'     => __( 'manifest-src:', 'w3-total-cache' ),
				'browsercache.security.csp.scriptelem'   => __( 'script-src-elem:', 'w3-total-cache' ),
				'browsercache.security.csp.scriptattr'   => __( 'script-src-attr:', 'w3-total-cache' ),
				'browsercache.security.csp.styleelem'    => __( 'style-src-elem:', 'w3-total-cache' ),
				'browsercache.security.csp.styleattr'    => __( 'style-src-attr:', 'w3-total-cache' ),
				'browsercache.security.csp.worker'       => __( 'worker-src:', 'w3-total-cache' ),
				'browsercache.security.csp.default'      => __( 'default-src:', 'w3-total-cache' ),
				'browsercache.security.cspro'            => __( 'Content Security Policy Report Only', 'w3-total-cache' ),
				'browsercache.security.cspro.reporturi'  => __( 'report-uri:', 'w3-total-cache' ),
				'browsercache.security.cspro.reportto'   => __( 'report-to:', 'w3-total-cache' ),
				'browsercache.security.cspro.base'       => __( 'base-uri:', 'w3-total-cache' ),
				'browsercache.security.cspro.frame'      => __( 'frame-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.connect'    => __( 'connect-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.font'       => __( 'font-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.script'     => __( 'script-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.style'      => __( 'style-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.img'        => __( 'img-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.media'      => __( 'media-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.object'     => __( 'object-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.plugin'     => __( 'plugin-types:', 'w3-total-cache' ),
				'browsercache.security.cspro.form'       => __( 'form-action:', 'w3-total-cache' ),
				'browsercache.security.cspro.frame.ancestors' => __( 'frame-ancestors:', 'w3-total-cache' ),
				'browsercache.security.cspro.sandbox'    => __( 'sandbox:', 'w3-total-cache' ),
				'browsercache.security.cspro.child'      => __( 'child-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.manifest'   => __( 'manifest-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.scriptelem' => __( 'script-src-elem:', 'w3-total-cache' ),
				'browsercache.security.cspro.scriptattr' => __( 'script-src-attr:', 'w3-total-cache' ),
				'browsercache.security.cspro.styleelem'  => __( 'style-src-elem:', 'w3-total-cache' ),
				'browsercache.security.cspro.styleattr'  => __( 'style-src-attr:', 'w3-total-cache' ),
				'browsercache.security.cspro.worker'     => __( 'worker-src:', 'w3-total-cache' ),
				'browsercache.security.cspro.default'    => __( 'default-src:', 'w3-total-cache' ),
			)
		);
	}
}
