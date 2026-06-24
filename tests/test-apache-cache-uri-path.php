<?php
/**
 * Standalone regression test for PgCache_Environment::apache_cache_uri_path logic.
 *
 * Run with: php tests/test-apache-cache-uri-path.php
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$acup_pass = 0;
$acup_fail = 0;

/**
 * Mirrors {@see W3TC\PgCache_Environment::apache_cache_uri_path()} path resolution.
 *
 * @param string $cache_dir Normalized absolute cache directory.
 * @param string $abspath   WordPress ABSPATH (no trailing slash).
 * @param string $site_uri  network_site_url('/') path component.
 * @param string $site_path Util_Environment::site_path() (Apache .htaccess root).
 * @return string
 */
function acup_resolve_cache_uri_path( $cache_dir, $abspath, $site_uri, $site_path ) {
	$cache_dir = str_replace( '\\', '/', $cache_dir );
	$site_root = rtrim( $abspath, '/' );
	$site_uri  = rtrim( $site_uri, '/' );

	if ( $site_root && 0 === strpos( $cache_dir, $site_root . '/' ) ) {
		return $site_uri . substr( $cache_dir, strlen( $site_root ) );
	}

	$doc_root = null;
	if (
		$site_root && '' !== $site_uri &&
		substr( $site_root, -strlen( $site_uri ) ) === $site_uri
	) {
		$doc_root = substr( $site_root, 0, -strlen( $site_uri ) );
	} else {
		$doc_root = rtrim( $site_path, '/' );
	}

	if ( $doc_root && 0 === strpos( $cache_dir, $doc_root . '/' ) ) {
		return substr( $cache_dir, strlen( $doc_root ) );
	}

	return $cache_dir;
}

/**
 * @param bool   $expect_pass Expected pass/fail.
 * @param string $label       Case label.
 * @param string $expected    Expected URI path.
 * @param string $cache_dir   Cache directory input.
 * @param string $abspath     ABSPATH input.
 * @param string $site_uri    Site URI input.
 * @param string $site_path   Site path input.
 */
function acup_case( $expect_pass, $label, $expected, $cache_dir, $abspath, $site_uri, $site_path ) {
	global $acup_pass, $acup_fail;

	$result = acup_resolve_cache_uri_path( $cache_dir, $abspath, $site_uri, $site_path );
	$ok     = $expect_pass ? ( $expected === $result ) : ( $expected !== $result );

	if ( $ok ) {
		++$acup_pass;
		echo "  \033[32m✔\033[0m  {$label}\n";
		return;
	}

	++$acup_fail;
	echo "  \033[31m✘\033[0m  {$label} (expected " . var_export( $expected, true ) . ', got ' . var_export( $result, true ) . ")\n";
}

$root      = '/var/www/wp-sandbox';
$wp        = $root . '/wp';
$moved     = $root . '/moved-folders/moved-content/cache/page_enhanced';
$standard  = $wp . '/wp-content/cache/page_enhanced';

echo "\n=== Apache cache URI path resolution ===\n\n";

acup_case(
	true,
	'pathmoved-subdomain: moved cache under home',
	'/moved-folders/moved-content/cache/page_enhanced',
	$moved,
	$wp,
	'',
	$root . '/'
);
acup_case(
	true,
	'pathmoved-single: moved cache under home with /wp site URI',
	'/moved-folders/moved-content/cache/page_enhanced',
	$moved,
	$wp,
	'/wp',
	$root . '/'
);
acup_case(
	true,
	'pathwp: cache under ABSPATH keeps /wp prefix',
	'/wp/wp-content/cache/page_enhanced',
	$standard,
	$wp,
	'/wp',
	$wp . '/'
);
acup_case(
	true,
	'default install: cache relative to ABSPATH',
	'/wp-content/cache/page_enhanced',
	$standard,
	$wp,
	'',
	$wp . '/'
);

echo "\n  Total: " . ( $acup_pass + $acup_fail ) . "  Passed: \033[32m{$acup_pass}\033[0m  Failed: \033[31m{$acup_fail}\033[0m\n\n";

exit( $acup_fail > 0 ? 1 : 0 );
