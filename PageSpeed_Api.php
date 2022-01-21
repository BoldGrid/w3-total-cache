<?php
namespace W3TC;

/**
 * Google Page Speed API
 */
define( 'W3TC_PAGESPEED_API_URL', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' );

/**
 * class PageSpeed_Api
 */
class PageSpeed_Api {
	/**
	 * API Key
	 */
	private $key = '';
	/**
	 * Referrer for key restricting
	 */
	private $key_restrict_referrer = '';



	function __construct( $api_key, $api_ref ) {
		$this->key = $api_key;
		$this->key_restrict_referrer = $api_ref;
	}



	public function analyze( $url ) {
		return array(
			'mobile' => $this->analyze_strategy( $url, 'mobile' ),
			'desktop' => $this->analyze_strategy( $url, 'desktop' ),
			'test_url' => Util_Environment::url_format(
				'https://developers.google.com/speed/pagespeed/insights/',
				array( 'url' => $url ) )
		);
	}

	public function analyze_strategy( $url, $strategy ) {
		$json = $this->_request( array(
				'url' => $url,
				'category' => 'performance',
				'strategy' => $strategy
			) );

		if ( !$json ) {
			return null;
		}

		$data = array();
		try {
			$data = json_decode( $json, true );
		} catch ( \Exception $e ) {
		}

		return array(
			'score' => $this->v( $data, array( 'lighthouseResult', 'categories', 'performance', 'score' ) ) * 100,
			'first-contentful-paint' => array(
				'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'score',  ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'displayValue',  ) ),
			),
			'largest-contentful-paint' => array(
				'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'score',  ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'displayValue',  ) ),
			),
			'interactive' => array(
				'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'interactive', 'score',  ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'interactive', 'displayValue',  ) ),
			),
			'cumulative-layout-shift' => array(
				'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'score',  ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'displayValue',  ) ),
			),
			'total-blocking-time' => array(
				'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'score',  ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'displayValue',  ) ),
			),
			'speed-index' => array(
				'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'score',  ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'displayValue',  ) ),
			),
			'screenshots' => array(
				'final' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'title',  ) ),
					'screenshot' => $this->v( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'details', 'data',  ) ),
				),
				'other' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'title',  ) ),
					'screenshots' => $this->v( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'details', 'items',  ) ),
				)
			),
			'opportunities' => array(
				'render-blocking-resources' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'unused-css-rules' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'unminified-css' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'unminified-javascript' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'unused-javascript' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'details', 'items', ) ),
					'type' => array(
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'uses-responsive-images' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'offscreen-images' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'uses-optimized-images' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'modern-image-formats' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'uses-text-compression' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'uses-rel-preconnect' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'server-response-time' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'redirects' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'uses-rel-preload' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'efficient-animated-content' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'details', 'items', ) ),
					'type' => array(
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'duplicated-javascript' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'legacy-javascript' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'preload-lcp-image' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'details', 'items', ) ),
					'type' => array(
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'total-byte-weight' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'details', 'items', ) ),
					'type' => array(
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'dom-size' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'user-timings' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'bootup-time' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'mainthread-work-breakdown' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'third-party-summary' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'third-party-facades' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
				'lcp-lazy-loaded' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'uses-passive-event-listeners' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'no-document-write' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'non-composited-animations' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'details', 'items', ) ),
					'type' => array(
						'CLS',
					),
					'instruction' => 'To be determined',
				),
				'unsized-images' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'details', 'items', ) ),
					'type' => array(
						'CLS',
					),
					'instruction' => 'To be determined',
				),
				'viewport' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
			),
			'diagnostics' => array(
				'font-display' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'first-contentful-paint-3g' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'uses-long-cache-ttl' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'critical-request-chains' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'details', 'items', ) ),
					'type' => array(
						'FCP',
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'resource-summary' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'details', 'items', ) ),
					'instruction' => 'To be determined',
				),
				'largest-contentful-paint-element' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'details', 'items', ) ),
					'type' => array(
						'LCP',
					),
					'instruction' => 'To be determined',
				),
				'layout-shift-elements' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'details', 'items', ) ),
					'type' => array(
						'CLS',
					),
					'instruction' => 'To be determined',
				),
				'long-tasks' => array(
					'title' => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'title',  ) ),
					'description' => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'description',  ) ),
					'score' => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'score',  ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'displayValue',  ) ),
					'details' => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'details', 'items', ) ),
					'type' => array(
						'TBT',
					),
					'instruction' => 'To be determined',
				),
			)
		);
	}

	public function get_page_score( $url ) {
		$json = $this->_request( array(
				'url' => $url,
				'category' => 'performance',
				'strategy' => 'desktop'
			) );

		if ( !$json ) {
			return null;
		}

		$data = array();
		try {
			$data = json_decode( $json, true );
		} catch ( \Exception $e ) {
		}

		return $this->v( $data, array( 'lighthouseResult', 'categories', 'performance', 'score' ) );
	}



	/**
	 * Make API request
	 *
	 * @param string  $url
	 * @return string
	 */
	function _request( $query ) {
		$request_url = Util_Environment::url_format( W3TC_PAGESPEED_API_URL,
			array_merge( $query, array(
				'key' => $this->key
			) ) );

		$response = Util_Http::get( $request_url, array(
				'timeout' => 120,
				'headers' => array( 'Referer' => $this->key_restrict_referrer )
			) );

		if ( !is_wp_error( $response ) && $response['response']['code'] == 200 ) {
			return $response['body'];
		}

		return false;
	}



	function v( $data, $elements ) {
		if ( empty( $elements ) ) {
			return $data;
		}

		$key = array_shift( $elements );
		if ( !isset( $data[$key] ) ) {
			return null;
		}

		return $this->v( $data[$key], $elements );
	}
}
