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
			'metrics' => $this->v( $data, array( 'loadingExperience', 'metrics' ) )
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
