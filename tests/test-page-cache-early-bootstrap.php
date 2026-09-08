<?php
/**
 * Standalone regression test for page-cache early bootstrap.
 *
 * Run with: php tests/test-page-cache-early-bootstrap.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

namespace {
	if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
		return;
	}
}

namespace W3TC {
	/**
	 * File config stub for early bootstrap.
	 *
	 * @since X.X.X
	 */
	class PageCacheEarlyBootstrapConfigStub {
		/**
		 * Returns configured CIDRs.
		 *
		 * @param string|array $key Config key.
		 * @return array
		 */
		public function get_array( $key ) {
			if ( 'common.trusted_proxies' === $key ) {
				return array( '10.0.0.1/32' );
			}

			if ( array( 'cloudflare', 'ips.ip4' ) === $key ) {
				return array( '192.0.2.0/24' );
			}

			return array();
		}

		/**
		 * Reports the Cloudflare extension as active.
		 *
		 * @param string $extension_id Extension ID.
		 * @return bool
		 */
		public function is_extension_active( $extension_id ) {
			return 'cloudflare' === $extension_id;
		}
	}

	/**
	 * Dispatcher stub for early bootstrap.
	 *
	 * @since X.X.X
	 */
	class Dispatcher {
		/**
		 * Returns file configuration.
		 *
		 * @return PageCacheEarlyBootstrapConfigStub
		 */
		public static function config() {
			return new PageCacheEarlyBootstrapConfigStub();
		}

		/**
		 * Returns master state before the Options API is loaded.
		 *
		 * @return ConfigState
		 */
		public static function config_state_master() {
			return new ConfigState( true );
		}
	}
}

namespace {
	require_once __DIR__ . '/../ConfigState.php';
	require_once __DIR__ . '/../Util_Environment.php';

	$w3tc_early_bootstrap_failures = 0;

	function w3tc_early_bootstrap_assert_same( $label, $expected, $actual ) {
		global $w3tc_early_bootstrap_failures;

		if ( $expected === $actual ) {
			echo "[PASS] $label\n";
			return;
		}

		++$w3tc_early_bootstrap_failures;
		echo "[FAIL] $label\n";
		echo '  Expected: ' . \var_export( $expected, true ) . "\n";
		echo '  Actual:   ' . \var_export( $actual, true ) . "\n";
	}

	$state = new \W3TC\ConfigState( true );
	w3tc_early_bootstrap_assert_same(
		'Missing Options API produces empty state',
		array(),
		$state->get_array( 'extension.cloudflare.ips.ip4' )
	);
	$state->save();

	$direct_request = array(
		'REMOTE_ADDR'            => '203.0.113.10',
		'HTTP_X_FORWARDED_PROTO' => 'https',
		'HTTPS'                  => '',
		'SERVER_PORT'            => '80',
	);
	w3tc_early_bootstrap_assert_same(
		'Forwarded scheme is ignored for an unconfigured peer',
		false,
		\W3TC\Util_Environment::is_https( $direct_request )
	);

	$configured_request                = $direct_request;
	$configured_request['REMOTE_ADDR'] = '10.0.0.1';
	w3tc_early_bootstrap_assert_same(
		'File-configured proxy remains available during early bootstrap',
		true,
		\W3TC\Util_Environment::is_https( $configured_request )
	);

	$cloudflare_request                = $direct_request;
	$cloudflare_request['REMOTE_ADDR'] = '192.0.2.20';
	w3tc_early_bootstrap_assert_same(
		'File-configured Cloudflare range remains available during early bootstrap',
		true,
		\W3TC\Util_Environment::is_https( $cloudflare_request )
	);

	if ( $w3tc_early_bootstrap_failures > 0 ) {
		exit( 1 );
	}
}
