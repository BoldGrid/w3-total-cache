<?php
/**
 * File: class-w3tc-cdn-cors-scope-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Cdn_Environment;
use W3TC\Cdn_Environment_LiteSpeed;
use W3TC\Cdn_Environment_Nginx;
use W3TC\Cdn_Util;
use W3TC\CdnEngine_Base;
use W3TC\Config;

/**
 * Class: W3tc_Cdn_Cors_Scope_Test
 *
 * CDN CORS headers apply only to font resources. Apache, Nginx, and
 * LiteSpeed share one suffix list; generated rules stay in parity.
 *
 * @since 2.10.6
 */
class W3tc_Cdn_Cors_Scope_Test extends WP_UnitTestCase {

	/**
	 * Font suffixes the plugin treats as CORS-eligible.
	 *
	 * @since 2.10.6
	 *
	 * @var string[]
	 */
	private $font_suffixes = array( 'ttf', 'ttc', 'otf', 'eot', 'woff', 'woff2', 'font.css' );

	/**
	 * Representative non-font paths that must not receive font CORS.
	 *
	 * @since 2.10.6
	 *
	 * @var string[]
	 */
	private $non_font_paths = array(
		'/wp-content/themes/x/style.css',
		'/wp-content/themes/x/script.js',
		'/wp-content/uploads/2024/01/photo.png',
		'/wp-content/uploads/2024/01/photo.jpg',
		'/wp-content/uploads/2024/01/photo.gif',
		'/wp-content/uploads/doc.pdf',
		'/wp-content/uploads/archive.zip',
		'/readme.html',
		'/wp-content/uploads/icon.svg',
		'/wp-content/uploads/data.json',
	);

	/**
	 * Shared suffix list is the supported font set.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_cors_font_suffixes_are_the_supported_set() {
		$this->assertSame( $this->font_suffixes, Cdn_Util::cors_font_suffixes() );
	}

	/**
	 * MIME-map keys used to keep BrowserCache from owning font CORS.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_cors_font_browsercache_keys_match_mime_other() {
		$mime = include W3TC_INC_DIR . '/mime/other.php';

		foreach ( Cdn_Util::cors_font_browsercache_extension_keys() as $key ) {
			$this->assertArrayHasKey( $key, $mime, 'Missing MIME key: ' . $key );
		}
	}

	/**
	 * Supported fonts, mixed case, and font.css receive CORS.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_path_needs_cors_header_accepts_fonts_and_case() {
		$paths = array(
			'/fonts/family.ttf',
			'/fonts/family.ttc',
			'/fonts/family.otf',
			'/fonts/family.eot',
			'/fonts/family.woff',
			'/fonts/family.woff2',
			'/fonts/webfont.font.css',
			'/fonts/FAMILY.TTF',
			'/fonts/FAMILY.WOFF2',
			'/fonts/webfont.FONT.CSS',
			'C:\\fonts\\family.WOFF',
			'/fonts/family.woff2?ver=1.0',
		);

		foreach ( $paths as $path ) {
			$this->assertTrue(
				Cdn_Util::path_needs_cors_header( $path ),
				'Expected CORS for: ' . $path
			);
		}
	}

	/**
	 * CSS, JS, images, documents, and archives do not receive font CORS.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_path_needs_cors_header_rejects_non_fonts() {
		foreach ( $this->non_font_paths as $path ) {
			$this->assertFalse(
				Cdn_Util::path_needs_cors_header( $path ),
				'Did not expect CORS for: ' . $path
			);
		}

		$this->assertFalse( Cdn_Util::path_needs_cors_header( '' ) );
		$this->assertFalse( Cdn_Util::path_needs_cors_header( '/fonts/family.fontXcss' ) );
		$this->assertFalse( Cdn_Util::path_needs_cors_header( '/fonts/family.css' ) );
	}

	/**
	 * Apache CORS is FilesMatch-scoped for origin and FTP, including case.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_apache_cors_rules_are_font_filesmatch_scoped() {
		$config = $this->cors_config( true );

		foreach ( array( false, true ) as $cdnftp ) {
			$rules = $this->apache_rules( $config, $cdnftp );
			$group = Cdn_Util::cors_font_regex_group( true );

			$this->assertStringContainsString( 'Access-Control-Allow-Origin', $rules );
			$this->assertStringContainsString( '<FilesMatch "\\.(' . $group . ')$">', $rules );
			$this->assertStringContainsString( 'TTF', $rules );
			$this->assertStringContainsString( 'WOFF2', $rules );
			$this->assertStringContainsString( 'font\\.css', $rules );
			$this->assertStringContainsString( 'FONT\\.CSS', $rules );

			$outside = preg_replace( '/<FilesMatch[\s\S]*?<\/FilesMatch>/', '', $rules );
			$this->assertStringNotContainsString(
				'Access-Control-Allow-Origin',
				$outside,
				'Apache CORS leaked outside FilesMatch (cdnftp=' . (int) $cdnftp . ')'
			);

			$this->assert_generated_matcher_accepts_fonts_only(
				'~\.(' . $group . ')$~',
				$rules
			);
		}
	}

	/**
	 * Nginx CORS location uses the shared font group and is case-insensitive.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_nginx_cors_rules_are_font_location_scoped() {
		$config = $this->cors_config( true );
		$nginx  = new Cdn_Environment_Nginx( $config );
		$rules  = $nginx->generate( false );
		$group  = Cdn_Util::cors_font_regex_group();

		$this->assertStringContainsString( 'Access-Control-Allow-Origin', $rules );
		$this->assertStringContainsString( 'location ~* \\.(' . $group . ')$ {', $rules );
		$this->assertStringContainsString( 'font\\.css', $rules );

		$this->assert_generated_matcher_accepts_fonts_only(
			'~\.(' . $group . ')$~i',
			$rules
		);
	}

	/**
	 * LiteSpeed CORS context uses the same shared font group.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_litespeed_cors_rules_are_font_context_scoped() {
		$config = $this->cors_config( true );
		$ls     = new Cdn_Environment_LiteSpeed( $config );
		$rules  = $ls->generate( false );
		$group  = Cdn_Util::cors_font_regex_group();

		$this->assertStringContainsString( 'Access-Control-Allow-Origin', $rules );
		$this->assertStringContainsString( 'context exp:(?i)^.*\\.(' . $group . ')$ {', $rules );

		$this->assert_generated_matcher_accepts_fonts_only(
			'~\.(' . $group . ')$~i',
			$rules
		);
	}

	/**
	 * Apache and Nginx CORS matchers agree on every fixture path.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_apache_and_nginx_generated_rules_have_equivalent_scope() {
		$config       = $this->cors_config( true );
		$apache_rules = $this->apache_rules( $config, false );
		$nginx_rules  = ( new Cdn_Environment_Nginx( $config ) )->generate( false );

		$apache_re = '~\.(' . Cdn_Util::cors_font_regex_group( true ) . ')$~';
		$nginx_re  = '~\.(' . Cdn_Util::cors_font_regex_group() . ')$~i';

		$this->assertStringContainsString( Cdn_Util::cors_font_regex_group( true ), $apache_rules );
		$this->assertStringContainsString( Cdn_Util::cors_font_regex_group(), $nginx_rules );

		$paths = array_merge(
			array(
				'/fonts/family.ttf',
				'/fonts/FAMILY.TTF',
				'/fonts/family.woff2',
				'/fonts/FAMILY.WOFF2',
				'/fonts/webfont.font.css',
				'/fonts/webfont.FONT.CSS',
			),
			$this->non_font_paths
		);

		foreach ( $paths as $path ) {
			$apache_hit = (bool) preg_match( $apache_re, $path );
			$nginx_hit  = (bool) preg_match( $nginx_re, $path );

			$this->assertSame(
				$apache_hit,
				$nginx_hit,
				'Apache/Nginx CORS scope diverged for: ' . $path
			);
			$this->assertSame(
				Cdn_Util::path_needs_cors_header( $path ),
				$apache_hit,
				'Helper and generated Apache matcher diverged for: ' . $path
			);
		}
	}

	/**
	 * CORS disabled emits no Access-Control-Allow-Origin on Apache or Nginx.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_cors_disabled_emits_no_allow_origin_header() {
		$config = $this->cors_config( false );

		$apache = $this->apache_rules( $config, false );
		$nginx  = ( new Cdn_Environment_Nginx( $config ) )->generate( false );
		$ls     = ( new Cdn_Environment_LiteSpeed( $config ) )->generate( false );

		$this->assertStringNotContainsString( 'Access-Control-Allow-Origin', $apache );
		$this->assertStringNotContainsString( 'Access-Control-Allow-Origin', $nginx );
		$this->assertStringNotContainsString( 'Access-Control-Allow-Origin', $ls );
	}

	/**
	 * Object-upload headers add CORS only for font paths.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_upload_headers_add_cors_only_for_fonts() {
		$engine = new CdnEngine_Base();

		$font = $engine->get_headers_for_file(
			array(
				'local_path'   => '/tmp/family.woff2',
				'original_url' => 'https://example.com/fonts/family.woff2',
			)
		);
		$this->assertSame( '*', $font['Access-Control-Allow-Origin'] );

		$css = $engine->get_headers_for_file(
			array(
				'local_path'   => '/tmp/style.css',
				'original_url' => 'https://example.com/style.css',
			)
		);
		$this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $css );

		$pdf = $engine->get_headers_for_file(
			array(
				'local_path'   => '/tmp/doc.pdf',
				'original_url' => 'https://example.com/doc.pdf',
			)
		);
		$this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $pdf );
	}

	/**
	 * Seeded config for CORS rule generation.
	 *
	 * @since 2.10.6
	 *
	 * @param bool $cors Whether CORS headers are enabled.
	 *
	 * @return Config
	 */
	private function cors_config( $cors ) {
		$config = new Config();
		$config->set( 'cdn.enabled', true );
		$config->set( 'cdn.cors_header', $cors );
		$config->set( 'cdn.canonical_header', false );
		$config->set( 'browsercache.enabled', false );

		return $config;
	}

	/**
	 * Apache CDN rules for the given config.
	 *
	 * @since 2.10.6
	 *
	 * @param Config $config Configuration.
	 * @param bool   $cdnftp FTP wrapper flag.
	 *
	 * @return string
	 */
	private function apache_rules( Config $config, $cdnftp ) {
		$env = new Cdn_Environment();
		$ref = new \ReflectionMethod( Cdn_Environment::class, 'rules_generate_apache' );
		$ref->setAccessible( true );

		return (string) $ref->invoke( $env, $config, $cdnftp );
	}

	/**
	 * Assert a generated matcher accepts fonts and rejects non-fonts.
	 *
	 * @since 2.10.6
	 *
	 * @param string $regex Compiled path matcher used by the generated rules.
	 * @param string $rules Generated server rules (unused except as a witness).
	 *
	 * @return void
	 */
	private function assert_generated_matcher_accepts_fonts_only( $regex, $rules ) {
		$this->assertNotSame( '', $rules );

		foreach ( $this->font_suffixes as $suffix ) {
			$this->assertSame(
				1,
				preg_match( $regex, '/fonts/family.' . $suffix ),
				'Font suffix missing from matcher: ' . $suffix
			);
		}

		$this->assertSame( 1, preg_match( $regex, '/fonts/FAMILY.WOFF2' ) );

		foreach ( $this->non_font_paths as $path ) {
			$this->assertSame(
				0,
				preg_match( $regex, $path ),
				'Non-font matched CORS rules: ' . $path
			);
		}
	}
}
