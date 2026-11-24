<?php
/**
 * CloudFront distribution listing follows pagination markers.
 *
 * @package W3TC\Tests
 */

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
 * CloudFront engine with a fixed origin, so tests do not depend on HTTP_HOST.
 *
 * @since X.X.X
 */
class W3tc_Test_CloudFront_Engine extends CdnEngine_Mirror_CloudFront {
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
	 * Creates an engine that uses the given fake client.
	 *
	 * @param W3tc_Test_CloudFront_Fake_Client $client Fake client.
	 *
	 * @return W3tc_Test_CloudFront_Engine
	 */
	private function make_engine( $client ) {
		$engine   = new W3tc_Test_CloudFront_Engine( array() );
		$property = new ReflectionProperty( CdnEngine_Mirror_CloudFront::class, 'api' );
		$property->setAccessible( true );
		$property->setValue( $engine, $client );

		return $engine;
	}

	/**
	 * Calls a private method on the engine.
	 *
	 * @param object $engine Engine.
	 * @param string $method Method name.
	 *
	 * @return mixed
	 */
	private function call_private( $engine, $method ) {
		$reflection = new ReflectionMethod( CdnEngine_Mirror_CloudFront::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( $engine );
	}

	/**
	 * A distribution on the second page is found.
	 *
	 * @return void
	 */
	public function test_finds_distribution_on_second_page() {
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
		$engine = $this->make_engine( $client );

		$dist = $this->call_private( $engine, '_get_distribution' );

		$this->assertSame( 'EMATCH', $dist['Id'] );
		$this->assertCount( 2, $client->calls );
		$this->assertSame( 'page2', $client->calls[1]['Marker'] );

		$error = '';
		$this->assertTrue( $engine->test( $error ), $error );
	}

	/**
	 * A truncated page without NextMarker does not loop.
	 *
	 * @return void
	 */
	public function test_truncated_page_without_next_marker_stops() {
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
		$engine = $this->make_engine( $client );

		$dists = $this->call_private( $engine, 'list_all_distributions' );

		$this->assertCount( 1, $dists );
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
		$engine = $this->make_engine( $client );

		$this->call_private( $engine, 'list_all_distributions' );

		$this->assertCount( 2, $client->calls );
	}

	/**
	 * An account with no distributions reports "No distributions found."
	 *
	 * @return void
	 */
	public function test_empty_account_reports_no_distributions() {
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
		$engine = $this->make_engine( $client );

		$error = '';
		$this->assertFalse( $engine->test( $error ) );
		$this->assertSame( 'No distributions found.', $error );
		$this->assertCount( 1, $client->calls );
	}
}
