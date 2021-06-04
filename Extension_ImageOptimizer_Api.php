<?php
/**
 * File: Extension_ImageOptimizer_Api.php
 *
 * @since X.X.X
 */

 /**
  * Class: Extension_ImageOptimizer_Api
  *
  * @since X.X.X
  */
 class Extension_ImageOptimizer_Api {
	/**
	 * API Base URL.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $base_url = 'https://api2.w3-edge.com/';

	/**
	 * W3TC Pro license key.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $license_key;

	/**
	 * W3TC Pro licensed home URL.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $home_url;

	/**
	 * W3TC Pro licensed product item name.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $item_name;

	/**
	 * API endpoints.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var array
	 */
	private $endpoints = array(
		'convert'  => array(
			'method' => 'post',
			'uri'    => 'image/convert',
		),
		'status'   => array(
			'method' => 'get',
			'uri'    => 'job/status',
		),
		'download' => array(
			'method' => 'get',
			'uri'    => 'image/download',
		),
	);

	/**
	 * Convert an image; submit a job request.
	 *
	 * @since X.X.X
	 *
	 * @param string $filepath Image file path.
	 * @param array  $options  Optional array of options.  Overrides settings.
	 * @return bool
	 */
	public function convert( $filepath, $options = array() ) {
		return true;
	}

	/**
	 * Get job status.
	 *
	 * @param int    $job_id
	 * @param string $signature
	 * @return array
	 */
	public function get_status( $job_id, $signature ) {
		return array(
			'status' = 'test',
		);
	}

	/**
	 * Download a processed image.
	 *
	 * @param int    $job_id
	 * @param string $signature
	 * @return array
	 */
	public function download( $job_id, $signature ) {
		return array(
			'filepath' => '/path/to/file',
		);
	}
 }
