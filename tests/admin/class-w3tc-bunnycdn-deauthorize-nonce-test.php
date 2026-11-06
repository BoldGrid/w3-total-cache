<?php
/**
 * File: class-w3tc-bunnycdn-deauthorize-nonce-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Nonce;

/**
 * Class: W3tc_Bunnycdn_Deauthorize_Nonce_Test
 *
 * The Deauthorize button must mint the nonce for the action it requests.
 * Sending the authorize nonce makes the AJAX hub return 403.
 *
 * @since X.X.X
 */
class W3tc_Bunnycdn_Deauthorize_Nonce_Test extends WP_UnitTestCase {

	/**
	 * Nonce key / sub-action pairs: query-string URLs, then both key orders in `data: { ... }` objects.
	 *
	 * @since X.X.X
	 *
	 * @var string[]
	 */
	const PAIR_PATTERNS = array(
		'/w3tcGetAjaxNonce\(\s*["\'](?P<nonce>[a-z0-9_]+)["\']\s*\)\s*\+\s*["\']&w3tc_action=(?P<action>[a-z0-9_]+)["\']/',
		'/_wpnonce:\s*w3tcGetAjaxNonce\(\s*["\'](?P<nonce>[a-z0-9_]+)["\']\s*\)[^{}]*?w3tc_action:\s*["\'](?P<action>[a-z0-9_]+)["\']/',
		'/w3tc_action:\s*["\'](?P<action>[a-z0-9_]+)["\'][^{}]*?_wpnonce:\s*w3tcGetAjaxNonce\(\s*["\'](?P<nonce>[a-z0-9_]+)["\']\s*\)/',
	);

	/**
	 * CDN and Full Site Delivery deauthorize openers.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public function provider_deauthorize_openers(): array {
		return array(
			'cdn' => array(
				'Cdn_BunnyCdn_Page_View.js',
				'cdn_bunnycdn_deauthorization',
			),
			'fsd' => array(
				'Cdnfsd_BunnyCdn_Page_View.js',
				'cdn_bunnycdn_fsd_deauthorization',
			),
		);
	}

	/**
	 * The opener nonce key matches the requested action.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider provider_deauthorize_openers
	 *
	 * @param string $file   Page script.
	 * @param string $action AJAX sub-action.
	 *
	 * @return void
	 */
	public function test_deauthorize_opener_uses_its_own_nonce( string $file, string $action ) {
		$pairs = $this->get_pairs( W3TC_DIR . '/' . $file );

		$this->assertContains( array( $action, $action ), $pairs );
	}

	/**
	 * Every AJAX hub request in admin scripts sends the nonce minted for its own sub-action.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_all_ajax_requests_use_matching_known_nonce() {
		$files = \array_merge(
			\glob( W3TC_DIR . '/*.js' ),
			\glob( W3TC_DIR . '/pub/js/*.js' )
		);
		$known = Util_Nonce::known_ajax_actions();
		$all   = array();

		foreach ( $files as $file ) {
			if ( '.min.js' === \substr( $file, -7 ) ) {
				continue;
			}

			foreach ( $this->get_pairs( $file ) as $pair ) {
				list( $nonce_key, $action ) = $pair;
				$all[]                      = $pair;

				$this->assertSame( $action, $nonce_key, \basename( $file ) . ': w3tc_action=' . $action );
				$this->assertContains( $nonce_key, $known, \basename( $file ) . ': ' . $nonce_key . ' is not minted' );
			}
		}

		$this->assertContains( array( 'cdn_bunnycdn_deauthorize', 'cdn_bunnycdn_deauthorize' ), $all );
		$this->assertContains( array( 'cdn_bunnycdn_purge_url', 'cdn_bunnycdn_purge_url' ), $all );
	}

	/**
	 * A data object that sends one action's nonce for another action is reported as a mismatch.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_data_object_mismatch_is_detected() {
		$src = "data: {\n  _wpnonce: w3tcGetAjaxNonce(\"cdn_bunnycdn_intro\"),\n  action: \"w3tc_ajax\",\n  w3tc_action: \"cdn_bunnycdn_deauthorize\",\n}";

		$this->assertSame(
			array( array( 'cdn_bunnycdn_intro', 'cdn_bunnycdn_deauthorize' ) ),
			$this->match_pairs( $src )
		);
	}

	/**
	 * Read the nonce key and sub-action pairs from a script.
	 *
	 * @since X.X.X
	 *
	 * @param string $file Script path.
	 *
	 * @return array List of array( nonce key, w3tc_action ).
	 */
	private function get_pairs( string $file ): array {
		$src = \file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertNotFalse( $src );

		return $this->match_pairs( $src );
	}

	/**
	 * Extract nonce key and sub-action pairs from script source.
	 *
	 * @since X.X.X
	 *
	 * @param string $src Script source.
	 *
	 * @return array List of array( nonce key, w3tc_action ).
	 */
	private function match_pairs( string $src ): array {
		$pairs = array();

		foreach ( self::PAIR_PATTERNS as $pattern ) {
			\preg_match_all( $pattern, $src, $matches, PREG_SET_ORDER );

			foreach ( $matches as $m ) {
				$pairs[] = array( $m['nonce'], $m['action'] );
			}
		}

		return $pairs;
	}
}
