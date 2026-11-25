<?php
/**
 * CloudFront distribution listing follows pagination markers.
 *
 * @package W3TC\Tests
 */

use W3TC\CdnEngine_CloudFront;
use W3TC\CdnEngine_CloudFront_Distributions;
use W3TC\CdnEngine_Mirror_CloudFront;

/**
 * Fake CloudFront client that serves pages keyed by marker.
 *
 * @since X.X.X
 */
class W3tc_Test_CloudFront_Fake_Client {
	/**
	 * Pages keyed by request marker ('' for the first page).
	 *
	 * @var array
	 */
	private $pages;

	/**
	 * Parameters of each listDistributions() call.
	 *
	 * @var array
	 */
	public $calls = array();

	/**
	 * Constructor.
	 *
	 * @param array $pages Pages keyed by request marker.
	 */
	public function __construct( array $pages ) {
		$this->pages = $pages;
	}

	/**
	 * Returns the page for the requested marker.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array
	 */
	public function listDistributions( $params = array() ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->calls[] = $params;

		$marker = isset( $params['Marker'] ) ? $params['Marker'] : '';

		return isset( $this->pages[ $marker ] ) ? $this->pages[ $marker ] : array();
	}
}

/**
 * Fake S3 engine whose connection test always passes.
 *
 * @since X.X.X
 */
class W3tc_Test_CloudFront_Fake_S3 {
	/**
	 * Passes the S3 test.
	 *
	 * @param string $error Error message.
	 *
	 * @return bool
	 */
	public function test( &$error ) {
		return true;
	}
}

/**
 * Mirror CloudFront engine with a fixed origin, so tests do not depend on HTTP_HOST.
 *
 * @since X.X.X
 */
class W3tc_Test_CloudFront_Mirror_Engine extends CdnEngine_Mirror_CloudFront {
	/**
	 * Returns a fixed origin.
	 *
	 * @return string
	 */
	public function _get_origin() {
		return 'origin.example.com';
	}
}

/**
 * S3-origin CloudFront engine with a fixed origin.
 *
 * @since X.X.X
 */
class W3tc_Test_CloudFront_S3_Engine extends CdnEngine_CloudFront {
	/**
	 * Returns a fixed origin.
	 *
	 * @return string
	 */
	public function _get_origin() {
		return 'origin.example.com';
	}
}

/**
 * Tests for CloudFront distribution pagination.
 */
class Cdn_CloudFront_Pagination_Test extends WP_UnitTestCase {
	/**
	 * Engines under test.
	 *
	 * @return array
	 */
	public function engine_provider() {
		return array(
			'mirror' => array( 'mirror' ),
			'cf'     => array( 'cf' ),
		);
	}

	/**
	 * Builds a distribution summary.
	 *
	 * @param string $id     Distribution ID.
	 * @param string $origin Origin domain name.
	 *
	 * @return array
	 */
	private function make_dist( $id, $origin ) {
		return array(
			'Id'         => $id,
			'Status'     => 'Deployed',
			'Enabled'    => true,
			'DomainName' => strtolower( $id ) . '.cloudfront.net',
			'Origins'    => array(
				'Items' => array(
					array( 'DomainName' => $origin ),
				),
			),
		);
	}

	/**
	 * Sets a private property on an object.
	 *
	 * @param string $class_name Class that declares the property.
	 * @param object $instance   Object to change.
	 * @param string $name       Property name.
	 * @param mixed  $value      New value.
	 *
	 * @return void
	 */
	private function set_private( $class_name, $instance, $name, $value ) {
		$property = new ReflectionProperty( $class_name, $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( $instance, $value );
	}

	/**
	 * Creates an engine that uses the given fake client.
	 *
	 * @param string                           $type   Engine type: 'mirror' or 'cf'.
	 * @param W3tc_Test_CloudFront_Fake_Client $client Fake client.
	 *
	 * @return CdnEngine_Mirror_CloudFront|CdnEngine_CloudFront
	 */
	private function make_engine( $type, $client ) {
		if ( 'cf' === $type ) {
			$engine = new W3tc_Test_CloudFront_S3_Engine( array( 'bucket_location' => 'us-east-1' ) );
			$this->set_private( CdnEngine_CloudFront::class, $engine, 'api', $client );
			$this->set_private( CdnEngine_CloudFront::class, $engine, 's3', new W3tc_Test_CloudFront_Fake_S3() );
		} else {
			$engine = new W3tc_Test_CloudFront_Mirror_Engine( array() );
			$this->set_private( CdnEngine_Mirror_CloudFront::class, $engine, 'api', $client );
		}

		return $engine;
	}

	/**
	 * Calls the engine's private _get_distribution().
	 *
	 * @param object $engine Engine.
	 *
	 * @return array
	 */
	private function get_distribution( $engine ) {
		$method = new ReflectionMethod( get_parent_class( $engine ), '_get_distribution' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $engine );
	}

	/**
	 * A distribution on the second page is found.
	 *
	 * @dataProvider engine_provider
	 *
	 * @param string $type Engine type.
	 *
	 * @return void
	 */
	public function test_finds_distribution_on_second_page( $type ) {
		$client = new W3tc_Test_CloudFront_Fake_Client(
			array(
				''      => array(
					'DistributionList' => array(
						'IsTruncated' => true,
						'NextMarker'  => 'page2',
						'Items'       => array( $this->make_dist( 'EOTHER', 'other.example.com' ) ),
					),
				),
				'page2' => array(
					'DistributionList' => array(
						'IsTruncated' => false,
						'Items'       => array( $this->make_dist( 'EMATCH', 'origin.example.com' ) ),
					),
				),
			)
		);
		$engine = $this->make_engine( $type, $client );

		$dist = $this->get_distribution( $engine );

		$this->assertSame( 'EMATCH', $dist['Id'] );
		$this->assertCount( 2, $client->calls );
		$this->assertSame( 'page2', $client->calls[1]['Marker'] );

		$error = '';
		$this->assertTrue( $engine->test( $error ), $error );
	}

	/**
	 * A truncated page without NextMarker does not loop.
	 *
	 * @dataProvider engine_provider
	 *
	 * @param string $type Engine type.
	 *
	 * @return void
	 */
	public function test_truncated_page_without_next_marker_stops( $type ) {
		$client = new W3tc_Test_CloudFront_Fake_Client(
			array(
				'' => array(
					'DistributionList' => array(
						'IsTruncated' => true,
						'Items'       => array( $this->make_dist( 'EMATCH', 'origin.example.com' ) ),
					),
				),
			)
		);
		$engine = $this->make_engine( $type, $client );

		$dist = $this->get_distribution( $engine );

		$this->assertSame( 'EMATCH', $dist['Id'] );
		$this->assertCount( 1, $client->calls );
	}

	/**
	 * An account with no distributions reports "No distributions found."
	 *
	 * @dataProvider engine_provider
	 *
	 * @param string $type Engine type.
	 *
	 * @return void
	 */
	public function test_empty_account_reports_no_distributions( $type ) {
		$client = new W3tc_Test_CloudFront_Fake_Client(
			array(
				'' => array(
					'DistributionList' => array(
						'IsTruncated' => false,
						'Items'       => array(),
					),
				),
			)
		);
		$engine = $this->make_engine( $type, $client );

		$error = '';
		$this->assertFalse( $engine->test( $error ) );
		$this->assertSame( 'No distributions found.', $error );
		$this->assertCount( 1, $client->calls );
	}

	/**
	 * A NextMarker that does not advance does not loop.
	 *
	 * @return void
	 */
	public function test_unchanged_next_marker_stops() {
		$page   = array(
			'DistributionList' => array(
				'IsTruncated' => true,
				'NextMarker'  => 'same',
				'Items'       => array( $this->make_dist( 'EMATCH', 'origin.example.com' ) ),
			),
		);
		$client = new W3tc_Test_CloudFront_Fake_Client(
			array(
				''     => $page,
				'same' => $page,
			)
		);

		CdnEngine_CloudFront_Distributions::list_all( $client );

		$this->assertCount( 2, $client->calls );
	}

	/**
	 * A marker cycle (a -> b -> a) stops at the page cap.
	 *
	 * @return void
	 */
	public function test_marker_cycle_stops_at_page_cap() {
		$client = new W3tc_Test_CloudFront_Fake_Client(
			array(
				''  => array(
					'DistributionList' => array(
						'IsTruncated' => true,
						'NextMarker'  => 'a',
					),
				),
				'a' => array(
					'DistributionList' => array(
						'IsTruncated' => true,
						'NextMarker'  => 'b',
					),
				),
				'b' => array(
					'DistributionList' => array(
						'IsTruncated' => true,
						'NextMarker'  => 'a',
					),
				),
			)
		);

		CdnEngine_CloudFront_Distributions::list_all( $client );

		$this->assertCount( CdnEngine_CloudFront_Distributions::MAX_PAGES, $client->calls );
	}
}
