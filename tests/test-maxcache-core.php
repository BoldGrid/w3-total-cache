<?php
/**
 * Standalone test for the CloudLinux MAx Cache extension.
 *
 * Covers what actually breaks a site: a configuration the module cannot serve handed to it anyway,
 * a hostile directive value escaping its block, a duplicated or surviving block, a stale verdict.
 *
 * Run with: php tests/test-maxcache-core.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

if ( ! \function_exists( 'apply_filters' ) ) {
	/**
	 * Minimal apply_filters() stub.
	 *
	 * @param string $tag   Filter name.
	 * @param mixed  $value Value being filtered.
	 *
	 * @return mixed
	 */
	function apply_filters( $tag, $value, ...$extra ) {
		foreach ( $GLOBALS['mt_filters'][ $tag ] ?? array() as $callback ) {
			$value = \call_user_func( $callback, $value, ...$extra );
		}

		return $value;
	}
}

if ( ! \function_exists( 'has_filter' ) ) {
	/**
	 * Minimal has_filter() stub.
	 *
	 * @param string $tag Filter name.
	 *
	 * @return bool
	 */
	function has_filter( $tag ) {
		return ! empty( $GLOBALS['mt_filters'][ $tag ] );
	}
}

if ( ! \function_exists( 'add_filter' ) ) {
	/**
	 * Minimal add_filter() stub.
	 *
	 * @param string   $tag      Filter name.
	 * @param callable $callback Callback.
	 *
	 * @return void
	 */
	function add_filter( $tag, $callback ) {
		$GLOBALS['mt_filters'][ $tag ][] = $callback;
	}
}

if ( ! \function_exists( 'add_action' ) ) {
	/**
	 * Minimal add_action() stub.
	 *
	 * @param string   $tag      Action name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 *
	 * @return void
	 */
	function add_action( $tag, $callback, $priority = 10 ) {
		$GLOBALS['mt_actions'][] = $tag . '@' . $priority;
	}
}

if ( ! \function_exists( 'is_admin' ) ) {
	/**
	 * Minimal is_admin() stub.
	 *
	 * @return bool
	 */
	function is_admin() {
		return (bool) ( $GLOBALS['mt_is_admin'] ?? false );
	}
}

if ( ! \function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * Minimal wp_doing_ajax() stub.
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return (bool) ( $GLOBALS['mt_doing_ajax'] ?? false );
	}
}

if ( ! \function_exists( 'wp_doing_cron' ) ) {
	/**
	 * Minimal wp_doing_cron() stub.
	 *
	 * @return bool
	 */
	function wp_doing_cron() {
		return false;
	}
}

if ( ! \function_exists( 'is_multisite' ) ) {
	/**
	 * Minimal is_multisite() stub.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return false;
	}
}

if ( ! \function_exists( 'get_transient' ) ) {
	/**
	 * Minimal get_transient() stub.
	 *
	 * @param string $name Transient name.
	 *
	 * @return mixed
	 */
	function get_transient( $name ) {
		return $GLOBALS['mt_transients'][ $name ] ?? false;
	}
}

if ( ! \function_exists( 'set_transient' ) ) {
	/**
	 * Minimal set_transient() stub.
	 *
	 * @param string $name       Transient name.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Ignored.
	 *
	 * @return bool
	 */
	function set_transient( $name, $value, $expiration = 0 ) {
		$GLOBALS['mt_transients'][ $name ] = $value;

		return true;
	}
}

if ( ! \function_exists( 'delete_transient' ) ) {
	/**
	 * Minimal delete_transient() stub.
	 *
	 * @param string $name Transient name.
	 *
	 * @return bool
	 */
	function delete_transient( $name ) {
		unset( $GLOBALS['mt_transients'][ $name ] );

		return true;
	}
}

if ( ! \function_exists( 'get_option' ) ) {
	/**
	 * Minimal get_option() stub.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return $GLOBALS['mt_options'][ $name ] ?? $default;
	}
}

if ( ! \function_exists( 'update_option' ) ) {
	/**
	 * Minimal update_option() stub.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	function update_option( $name, $value ) {
		$GLOBALS['mt_options'][ $name ] = $value;

		return true;
	}
}

if ( ! \function_exists( 'delete_option' ) ) {
	/**
	 * Minimal delete_option() stub.
	 *
	 * @param string $name Option name.
	 *
	 * @return bool
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['mt_options'][ $name ] );

		return true;
	}
}

if ( ! \function_exists( 'get_bloginfo' ) ) {
	/**
	 * Minimal get_bloginfo() stub.
	 *
	 * @param string $show Requested field.
	 *
	 * @return string
	 */
	function get_bloginfo( $show = '' ) {
		return 'version' === $show ? '6.5' : '';
	}
}

foreach ( array( 'home_url', 'site_url', 'network_home_url', 'network_site_url' ) as $mt_url_fn ) {
	if ( ! \function_exists( $mt_url_fn ) ) {
		eval( 'function ' . $mt_url_fn . '( $path = "" ) { return "http://example.test/" . ltrim( (string) $path, "/" ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}
}

if ( ! \function_exists( 'wp_parse_url' ) ) {
	/**
	 * Minimal wp_parse_url() stub.
	 *
	 * @param string $url       URL.
	 * @param int    $component Component.
	 *
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return \parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! \function_exists( 'esc_url_raw' ) ) {
	/**
	 * Minimal esc_url_raw() stub.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! \function_exists( 'set_url_scheme' ) ) {
	/**
	 * Minimal set_url_scheme() stub.
	 *
	 * @param string $url    URL.
	 * @param string $scheme Scheme, ignored.
	 *
	 * @return string
	 */
	function set_url_scheme( $url, $scheme = null ) {
		return (string) $url;
	}
}

if ( ! \function_exists( 'untrailingslashit' ) ) {
	/**
	 * Minimal untrailingslashit() stub.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return \rtrim( (string) $value, '/\\' );
	}
}

if ( ! \function_exists( 'trailingslashit' ) ) {
	/**
	 * Minimal trailingslashit() stub.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function trailingslashit( $value ) {
		return \rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! \function_exists( 'wp_unslash' ) ) {
	/**
	 * Minimal wp_unslash() stub.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return \is_string( $value ) ? \stripslashes( $value ) : $value;
	}
}

if ( ! \function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field() stub.
	 *
	 * @param string $value Value.
	 *
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return \trim( \strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}

if ( ! \function_exists( 'wp_get_current_user' ) ) {
	/**
	 * Minimal wp_get_current_user() stub: no roles, so the rules carry no role-based exclusions.
	 *
	 * @return object
	 */
	function wp_get_current_user() {
		return (object) array( 'roles' => array() );
	}
}

if ( ! \function_exists( 'wp_roles' ) ) {
	/**
	 * Minimal wp_roles() stub.
	 *
	 * @return object
	 */
	function wp_roles() {
		return (object) array( 'role_names' => array() );
	}
}

if ( ! \function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal wp_json_encode() stub.
	 *
	 * @param mixed $data Data.
	 *
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return (string) \json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! \function_exists( '__' ) ) {
	/**
	 * Minimal __() stub.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! \function_exists( 'esc_html__' ) ) {
	/**
	 * Minimal esc_html__() stub.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_html__( $text ) { // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment, WordPress.NamingConventions.PrefixAllGlobals
		return $text;
	}
}

if ( ! \function_exists( 'request_filesystem_credentials' ) ) {
	/**
	 * Minimal request_filesystem_credentials() stub: the cases that reach it are about a write that
	 * cannot succeed, so it always declines.
	 *
	 * @return bool
	 */
	function request_filesystem_credentials() {
		return false;
	}
}

if ( ! \function_exists( 'WP_Filesystem' ) ) {
	/**
	 * Minimal WP_Filesystem() stub.
	 *
	 * @return bool
	 */
	function WP_Filesystem() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return false;
	}
}

foreach ( array( 'wp_next_scheduled', 'wp_unschedule_event', 'wp_clear_scheduled_hook' ) as $mt_cron_fn ) {
	if ( ! \function_exists( $mt_cron_fn ) ) {
		eval( 'function ' . $mt_cron_fn . '() { return false; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}
}

$GLOBALS['mt_transients'] = array();
$GLOBALS['mt_options']    = array();
$GLOBALS['mt_filters']    = array();

// A dedicated temp directory: this file writes and removes a real .htaccess, so it must not be the
// plugin's own source tree.
$mt_root = \sys_get_temp_dir() . '/w3tc-mt-test-' . \getmypid();
\mkdir( $mt_root, 0777, true );

if ( ! \defined( 'ABSPATH' ) ) {
	\define( 'ABSPATH', $mt_root . '/' );
}

if ( ! \defined( 'HOUR_IN_SECONDS' ) ) {
	\define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! \defined( 'DAY_IN_SECONDS' ) ) {
	\define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! \defined( 'W3TC_CACHE_DIR' ) ) {
	\define( 'W3TC_CACHE_DIR', $mt_root . '/wp-content/cache' );
}

if ( ! \defined( 'W3TC_CACHE_PAGE_ENHANCED_DIR' ) ) {
	\define( 'W3TC_CACHE_PAGE_ENHANCED_DIR', W3TC_CACHE_DIR . '/page_enhanced' );
}

foreach (
	array(
		'W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE' => '# BEGIN W3TC Page Cache MAx Cache',
		'W3TC_MARKER_END_PGCACHE_MAXCACHE'   => '# END W3TC Page Cache MAx Cache',
		'W3TC_MARKER_BEGIN_PGCACHE_CORE'     => '# BEGIN W3TC Page Cache core',
		'W3TC_MARKER_END_PGCACHE_CORE'       => '# END W3TC Page Cache core',
		'W3TC_MARKER_BEGIN_WORDPRESS'        => '# BEGIN WordPress',
	) as $mt_marker_name => $mt_marker_value
) {
	if ( ! \defined( $mt_marker_name ) ) {
		\define( $mt_marker_name, $mt_marker_value );
	}
}

require_once \dirname( __DIR__ ) . '/Util_Environment.php';
require_once \dirname( __DIR__ ) . '/Util_Rule.php';
require_once \dirname( __DIR__ ) . '/Util_Environment_Exception.php';
require_once \dirname( __DIR__ ) . '/Util_Environment_Exceptions.php';
require_once \dirname( __DIR__ ) . '/Util_File.php';
require_once \dirname( __DIR__ ) . '/Util_WpFile_FilesystemOperationException.php';
require_once \dirname( __DIR__ ) . '/Util_WpFile_FilesystemModifyException.php';
require_once \dirname( __DIR__ ) . '/Util_WpFile_FilesystemWriteException.php';
require_once \dirname( __DIR__ ) . '/Util_WpFile.php';
require_once \dirname( __DIR__ ) . '/PgCache_Environment.php';

// Names this run as a standalone one, so the Dispatcher stub knows it was actually asked for.
if ( ! \defined( 'W3TC_MAXCACHE_STANDALONE_TEST' ) ) {
	\define( 'W3TC_MAXCACHE_STANDALONE_TEST', true );
}

require_once __DIR__ . '/maxcache-dispatcher-stub.php';

// The plugin's files guard on this constant and die without it.
\defined( 'W3TC' ) || \define( 'W3TC', true );

require_once \dirname( __DIR__ ) . '/Extension_MaxCache_Core.php';
require_once \dirname( __DIR__ ) . '/Extension_MaxCache_Configd.php';
require_once \dirname( __DIR__ ) . '/Extension_MaxCache_Environment.php';

$mt_cases = array();
$mt_pass  = 0;
$mt_fail  = 0;

/**
 * Records the outcome of one assertion.
 *
 * @param string $label       Case description.
 * @param bool   $expectation Assertion result.
 * @param string $detail      Optional detail printed on failure.
 *
 * @return void
 */
function mt_assert( string $label, bool $expectation, string $detail = '' ): void {
	global $mt_cases, $mt_pass, $mt_fail;

	if ( $expectation ) {
		++$mt_pass;
		$mt_cases[] = array( 'PASS', $label );

		return;
	}

	++$mt_fail;
	$mt_cases[] = array( 'FAIL', $label . ( $detail ? ' | ' . $detail : '' ) );
}

/**
 * Config stub exposing what the extension reads, with per-case overrides.
 */
class MT_Config_Stub {
	/**
	 * Whether the MAx Cache extension reads as active.
	 *
	 * @var bool
	 */
	public $maxcache_active = true;

	/**
	 * Values keyed by config key.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Whether this configuration is a preview draft.
	 *
	 * @since X.X.X
	 *
	 * @var bool
	 */
	private $preview;


	/**
	 * Constructor.
	 *
	 * @param array $overrides Values that differ from the all-supported baseline.
	 */
	public function __construct( array $overrides = array() ) {
		// Config reads the cookie once, in its constructor, and so does this.
		$this->preview = \W3TC\Util_Environment::is_preview_mode();

		$this->values = \array_merge(
			array(
				'pgcache.enabled'              => true,
				'pgcache.engine'               => 'file_generic',
				'mobile.enabled'               => false,
				'referrer.enabled'             => false,
				'pgcache.cookiegroups.enabled' => false,
				'pgcache.cache.ssl'            => false,
				'browsercache.enabled'         => false,
				'browsercache.html.brotli'     => false,
				'pgcache.accept.qs'            => array(),
				'pgcache.reject.uri'           => array(),
				'pgcache.reject.cookie'        => array(),
				'pgcache.reject.ua'            => array(),
				'pgcache.reject.logged'        => false,
				'config.check'                 => true,
			),
			$overrides
		);
	}

	/**
	 * Whether this configuration is a preview draft.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public function is_preview() {
		return $this->preview;
	}

	/**
	 * Whether an extension is active.
	 *
	 * @param string $id Extension id.
	 *
	 * @return bool
	 */
	public function is_extension_active( $id ) {
		return 'maxcache' === $id ? $this->maxcache_active : false;
	}

	/**
	 * Returns a boolean config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return bool
	 */
	public function get_boolean( $key ) {
		return (bool) ( $this->values[ $key ] ?? false );
	}

	/**
	 * Returns an integer config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return int
	 */
	public function get_integer( $key ) {
		return (int) ( $this->values[ $key ] ?? 0 );
	}

	/**
	 * Returns a string config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return string
	 */
	public function get_string( $key ) {
		return (string) ( $this->values[ $key ] ?? '' );
	}

	/**
	 * Returns an array config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return array
	 */
	public function get_array( $key ) {
		return (array) ( $this->values[ $key ] ?? array() );
	}

	/**
	 * Stores a config value, as the owner-change bookkeeping does.
	 *
	 * @param string $key   Config key.
	 * @param mixed  $value Value.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->values[ $key ] = $value;
	}

	/**
	 * Saving is a no-op here.
	 *
	 * @return void
	 */
	public function save() {
	}
}

/**
 * A configuration object from outside W3TC: the readers its own filter needs, and nothing else.
 */
class MT_Foreign_Config_Stub {
	/**
	 * Whether an extension is active.
	 *
	 * @param string $id Extension id.
	 *
	 * @return bool
	 */
	public function is_extension_active( $id ) {
		return false;
	}

	/**
	 * Returns a boolean config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return bool
	 */
	public function get_boolean( $key ) {
		return false;
	}

	/**
	 * Returns an integer config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return int
	 */
	public function get_integer( $key ) {
		return 0;
	}

	/**
	 * Returns a string config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return string
	 */
	public function get_string( $key ) {
		return '';
	}

	/**
	 * Returns an array config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return array
	 */
	public function get_array( $key ) {
		return array();
	}
}

/**
 * Resets the memoized mode/capability detection, pinned to a current single build.
 *
 * @param string $mode One of `apache`, `nginx`, `none`.
 *
 * @return void
 */
function mt_force_mode( string $mode ): void {
	\W3TC\test_reset_host();
	\W3TC\test_pin_single_build_mode( $mode );

	$property = new \ReflectionProperty( '\W3TC\Extension_MaxCache_Core', 'levels' );
	$property->setAccessible( true );
	$property->setValue(
		null,
		array(
			\W3TC\Extension_MaxCache_Core::LEVEL_SENTINEL    => false,
			\W3TC\Extension_MaxCache_Core::LEVEL_PATH_TOKENS => true,
		)
	);
}

/**
 * Calls get_unsupported_reason() with a stub config, refreshed first the way a real write does.
 *
 * @param array $overrides Config overrides.
 *
 * @return string
 */
function mt_reason( array $overrides = array() ): string {
	$config = new MT_Config_Stub( $overrides );
	\W3TC\Extension_MaxCache_Core::refresh( $config );

	return \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $config );
}

$mt_rules_path = \W3TC\Util_Rule::get_apache_rules_path();

/**
 * Reads the current rules file back, or '' if it is not there.
 *
 * @return string
 */
function mt_rules_file(): string {
	global $mt_rules_path;

	return \is_readable( $mt_rules_path ) ? (string) \file_get_contents( $mt_rules_path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}

// ============================================================================
// [1] The core question: can this configuration be handed to the module at all?
// ============================================================================

mt_force_mode( 'apache' );
unset( $_SERVER['SERVER_SOFTWARE'] );

$mt_baseline = new MT_Config_Stub();
\W3TC\Extension_MaxCache_Core::refresh( $mt_baseline );

mt_assert(
	'[1] a baseline configuration is supported and emits a block',
	'' === \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_baseline )
		&& false !== \strpos( \W3TC\Extension_MaxCache_Core::get_rules( $mt_baseline ), '<IfModule maxcache_module>' )
);

// [2] The directives the module cannot reproduce. A real backreference renumbers once every
// owner-written regex is joined into one alternation, so it is refused rather than mismatched.
mt_assert(
	'[2] a real backreference in an exclusion is refused, not silently merged',
	'' !== mt_reason( array( 'pgcache.reject.uri' => array( '(a)\1' ) ) )
);

// [3] Web-server/module-build mismatch: the Apache-only build's directives never reach a request a
// live server header says is not Apache.
$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.31.5';
mt_assert(
	'[3] the Apache build is refused when the live request says a different server answered it',
	'' !== mt_reason()
);
unset( $_SERVER['SERVER_SOFTWARE'] );

// [4] Apache's own .htaccess parser fails the whole line past this length; one byte under is the
// longest the generated directive may ever be.
$mt_cookie_line = new \ReflectionMethod( '\W3TC\Extension_MaxCache_Core', 'get_exclude_cookie' );
$mt_cookie_line->setAccessible( true );
$mt_arg_escape  = new \ReflectionMethod( '\W3TC\Extension_MaxCache_Core', 'as_directive_argument' );
$mt_arg_escape->setAccessible( true );

$mt_probe        = 10;
$mt_probe_length = \strlen( '    MaxCacheExcludeCookie "' . $mt_arg_escape->invoke( null, $mt_cookie_line->invoke( null, new MT_Config_Stub( array( 'pgcache.reject.cookie' => array( \str_repeat( 'a', $mt_probe ) ) ) ) ) ) . '"' );
$mt_max_ok       = \W3TC\Extension_MaxCache_Core::MAX_DIRECTIVE_LINE_LENGTH - 1;
$mt_min_refuse   = \W3TC\Extension_MaxCache_Core::MAX_DIRECTIVE_LINE_LENGTH;

mt_assert(
	'[4] a directive line at the measured length limit is accepted, one byte over is refused by name',
	'' === mt_reason( array( 'pgcache.reject.cookie' => array( \str_repeat( 'a', $mt_probe + ( $mt_max_ok - $mt_probe_length ) ) ) ) )
		&& false !== \strpos( mt_reason( array( 'pgcache.reject.cookie' => array( \str_repeat( 'a', $mt_probe + ( $mt_min_refuse - $mt_probe_length ) ) ) ) ), 'MaxCacheExcludeCookie' )
);

// With no stored row and no memo - the memo is off in wp-admin - the gate reaches the checks that
// cost I/O itself, rather than only ever seeing them through refresh().
$GLOBALS['mt_is_admin'] = true;
\W3TC\Extension_MaxCache_Core::forget_stored();

$mt_deep_only = new MT_Config_Stub( array( 'pgcache.reject.cookie' => array( \str_repeat( 'a', 10000 ) ) ) );
$mt_deep_read = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_deep_only );

$GLOBALS['mt_is_admin'] = false;

mt_assert(
	'[4] the gate runs the checks that cost I/O itself, with no refresh() to lean on',
	false !== \strpos( $mt_deep_read, 'MaxCacheExcludeCookie' ),
	'got: ' . ( '' === $mt_deep_read ? '(supported)' : $mt_deep_read )
);

// ============================================================================
// [5]-[6] Escaping: a hostile exclusion value must not break the directive it sits in.
// ============================================================================

// refresh() first, the way every real write does: is_enabled() inside get_rules() would otherwise
// read whatever site-wide deep-check verdict an earlier case left stored, not this config's own.
$mt_hostile_config = new MT_Config_Stub(
	array(
		'pgcache.reject.cookie' => array( 'a(b', '</IfModule>' ),
		'pgcache.reject.ua'     => array( 'Chrome (Bot)' ),
	)
);
\W3TC\Extension_MaxCache_Core::refresh( $mt_hostile_config );
$mt_hostile = \W3TC\Extension_MaxCache_Core::get_rules( $mt_hostile_config );

\preg_match( '~MaxCacheExcludeCookie "([^"]*)"~', $mt_hostile, $mt_cookie_match );
\preg_match( '~MaxCacheExcludeUA "([^"]*)"~', $mt_hostile, $mt_ua_match );

mt_assert(
	'[5] a reject-cookie value cannot close the <IfModule> block it sits in',
	isset( $mt_cookie_match[1] ) && false === \strpos( $mt_cookie_match[1], '</IfModule>' ),
	'got: ' . ( $mt_cookie_match[1] ?? '(no directive)' )
);

// Cookie and user-agent values are escaped on the way in, so they cannot close the block. URI
// patterns are regexes and must stay unescaped, so a closing tag in one refuses the configuration.
$mt_tag_config = new MT_Config_Stub( array( 'pgcache.reject.uri' => array( '/x</IfModule>y' ) ) );
\W3TC\Extension_MaxCache_Core::refresh( $mt_tag_config );

$mt_uri_tag = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_tag_config );

mt_assert(
	'[6] a reject-URI pattern carrying a closing tag is refused, not written into the block',
	'' !== $mt_uri_tag
		&& false === \strpos( \W3TC\Extension_MaxCache_Core::get_rules( $mt_tag_config ), '</IfModule>y' ),
	'got: ' . ( '' === $mt_uri_tag ? '(supported)' : 'refused' )
);

// A lookbehind also carries "<", and must still be accepted.
mt_assert(
	'[6] and a lookbehind in a reject-URI pattern is still accepted',
	'' === mt_reason( array( 'pgcache.reject.uri' => array( '(?<=/shop)/cart' ) ) )
);

// The directive doubles every backslash the way Apache's own quoted-argument parsing requires; the
// module un-escapes that back to single backslashes before compiling the result as PCRE.
$mt_as_module_reads = isset( $mt_ua_match[1] ) ? \str_replace( '\\\\', '\\', $mt_ua_match[1] ) : null;

mt_assert(
	'[6] a parenthesised user agent is matched as the literal it is, not as a regex group',
	null !== $mt_as_module_reads && 1 === \preg_match( '~' . $mt_as_module_reads . '~', 'Mozilla/5.0 Chrome (Bot)' ),
	'got: ' . ( $mt_ua_match[1] ?? '(no directive)' )
);

// ============================================================================
// [7]-[9] The single-block invariant: the one thing that corrupts a live, serving site if it slips.
// ============================================================================

mt_force_mode( 'apache' );
$mt_config = new MT_Config_Stub();

\W3TC\Extension_MaxCache_Environment::fix_on_event( $mt_config, 'config_change' );
\W3TC\Extension_MaxCache_Environment::fix_on_event( $mt_config, 'config_change' );

mt_assert(
	'[7] writing the same configuration twice does not duplicate the block',
	1 === \substr_count( mt_rules_file(), W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE )
);

\W3TC\Extension_MaxCache_Environment::fix_after_deactivation();

mt_assert(
	'[8] deactivation takes the block out of the file entirely',
	false === \strpos( mt_rules_file(), W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE )
);

// [9] Apache applies the LAST `<IfModule maxcache_module>` block, the NGINX daemon the FIRST: a
// second, unmarked block would mean two different things, so writing ours is refused instead.
\file_put_contents( $mt_rules_path, "<IfModule maxcache_module>\n    MaxCache On\n</IfModule>\n" );
$mt_foreign_method = new \ReflectionMethod( '\W3TC\Extension_MaxCache_Core', 'foreign_block_reason' );
$mt_foreign_method->setAccessible( true );

mt_assert(
	'[9] an unmarked maxcache_module block already in the file is refused, not silently duplicated',
	'' !== $mt_foreign_method->invoke( null )
);

@\unlink( $mt_rules_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

// ============================================================================
// [10]-[11] A verdict and a block must describe the configuration they were worked out for.
// ============================================================================

// [10] Config::set() mutates in place: a verdict memoized for an object before a later admin-save
// handler flips a setting on that same object must not survive to describe the new state.
mt_force_mode( 'apache' );
$GLOBALS['mt_is_admin'] = true;

$mt_mutated = new MT_Config_Stub();
$mt_before  = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_mutated );
$mt_mutated->set( 'mobile.enabled', true );
$mt_after = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_mutated );

mt_assert(
	'[10] a config mutated in place after it was memoized is read fresh, not from a stale memo',
	'' === $mt_before && '' !== $mt_after
);

// An admin-ajax save is two handlers on one object just as much as a page load is.
$GLOBALS['mt_doing_ajax'] = true;
\W3TC\Extension_MaxCache_Core::forget();

$mt_ajax_cfg    = new MT_Config_Stub();
$mt_ajax_before = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_ajax_cfg );
$mt_ajax_cfg->set( 'mobile.enabled', true );
$mt_ajax_after  = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_ajax_cfg );

$GLOBALS['mt_doing_ajax'] = false;

mt_assert(
	'[10] and the same holds on an admin-ajax save, where the memo used to survive',
	'' === $mt_ajax_before && '' !== $mt_ajax_after,
	'before: ' . ( '' === $mt_ajax_before ? '(supported)' : $mt_ajax_before ) . ' after: ' . ( '' === $mt_ajax_after ? '(supported)' : 'refused' )
);

$GLOBALS['mt_is_admin'] = false;

// run() registers the real rules filter; every case below asks it something.
require_once \dirname( __DIR__ ) . '/Extension_MaxCache_Plugin.php';

// [11] The preview cookie decides which configuration a pass is handed. The live file answers to the
// saved one, so pass and filter read that, and cannot describe two configurations at once.
mt_force_mode( 'apache' );
$GLOBALS['mt_is_admin'] = true;
\W3TC\Extension_MaxCache_Core::forget_stored();

// What the saved storage holds, for the Config saved_config() builds with the cookie out of sight.
$GLOBALS['w3tc_maxcache_test_storage']['saved'] = new MT_Config_Stub();

\W3TC\Extension_MaxCache_Environment::fix_on_event( $mt_config, 'config_change' );
$mt_live_before = mt_rules_file();

$_COOKIE['w3tc_preview'] = '*';
\W3TC\Extension_MaxCache_Core::forget_stored();

// A draft the gate refuses: read in place of the saved one, it withdraws the live block.
$mt_draft = new MT_Config_Stub(
	array(
		'browsercache.enabled'            => true,
		// Everything the checks before this one ask for, so the Cache-Control answer is the one left.
		'browsercache.html.etag'          => true,
		'browsercache.html.cache.control' => true,
	)
);

\W3TC\Extension_MaxCache_Environment::fix_on_event( $mt_draft, 'config_change' );
$mt_after_event = mt_rules_file();

\W3TC\Extension_MaxCache_Environment::fix_on_wpadmin_request( $mt_draft, true );
$mt_after_admin = mt_rules_file();

$mt_draft_required = (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, $mt_draft );

unset( $_COOKIE['w3tc_preview'] );

mt_assert(
	'[11] a pass handed a preview draft writes the live block from the saved configuration',
	false !== \strpos( $mt_live_before, '<IfModule maxcache_module>' )
		&& $mt_live_before === $mt_after_event
		&& $mt_live_before === $mt_after_admin
);

mt_assert(
	'[11] and the rules filter handed that draft answers for the saved configuration too',
	false === $mt_draft_required,
	'got: ' . \var_export( $mt_draft_required, true )
);

// The extensions screen reads the gate directly, with whatever configuration the cookie selected.
$_COOKIE['w3tc_preview'] = '*';
$mt_draft_off = new MT_Config_Stub();
$mt_draft_off->maxcache_active = false;

$mt_direct_reason = \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_draft );
$mt_switch_answer = (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, $mt_draft_off );

unset( $_COOKIE['w3tc_preview'] );

mt_assert(
	'[11] the gate read on its own with a draft still answers for the saved configuration',
	'' === $mt_direct_reason,
	'got: ' . $mt_direct_reason
);

mt_assert(
	'[11] and where the filter does run, a draft switching the extension off does not hand the rules back',
	false === $mt_switch_answer
);

// A draft refused by a check that costs I/O: its verdict belongs in the site-wide row, and that row
// is what the gate reads back for the saved configuration on the very same admin request.
$_COOKIE['w3tc_preview'] = '*';
$mt_deep_draft = new MT_Config_Stub( array( 'pgcache.reject.cookie' => array( \str_repeat( 'a', 10000 ) ) ) );

\W3TC\Extension_MaxCache_Core::refresh( $mt_deep_draft );
$mt_after_deep = (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, $mt_deep_draft );

unset( $_COOKIE['w3tc_preview'] );

mt_assert(
	'[11] a draft refused by the checks that cost I/O does not put its verdict on the saved configuration',
	false === $mt_after_deep,
	'got: ' . \var_export( $mt_after_deep, true )
);

// The path template is read on its own by the cheap checks, so it converts like every other one.
$_COOKIE['w3tc_preview'] = '*';
$mt_path_draft = new MT_Config_Stub( array( 'pgcache.cache.ssl' => true ) );
$mt_path_tpl   = \W3TC\Extension_MaxCache_Core::get_path_template( $mt_path_draft );
unset( $_COOKIE['w3tc_preview'] );

mt_assert(
	'[11] the path template is built from the saved configuration as well',
	false === \strpos( $mt_path_tpl, '{SSL_SUFFIX}' ),
	'got: ' . $mt_path_tpl
);

// Held for the request: the memos are keyed by instance, so a fresh object each call would miss.
$_COOKIE['w3tc_preview'] = '*';
$mt_held_once = \W3TC\Extension_MaxCache_Core::saved_config( $mt_draft );
$mt_held_twice = \W3TC\Extension_MaxCache_Core::saved_config( $mt_draft );
unset( $_COOKIE['w3tc_preview'] );

mt_assert(
	'[11] and the saved configuration is read once, not rebuilt on every question',
	$mt_held_once === $mt_held_twice
);

// The config.check gate still holds: nothing is written where W3TC would not write its own either.
\W3TC\Extension_MaxCache_Environment::fix_after_deactivation();
\W3TC\Extension_MaxCache_Environment::fix_on_wpadmin_request( new MT_Config_Stub( array( 'config.check' => false ) ), false );

mt_assert(
	'[11] and it stands aside where config.check is off, as W3TC does for its own rules',
	false === \strpos( mt_rules_file(), '<IfModule maxcache_module>' )
);

// The gate is read where W3TC reads it, from the configuration the pass was handed: answering from
// a different one than its own pass would write one rule set and not the other.
$_COOKIE['w3tc_preview'] = '*';
\W3TC\Extension_MaxCache_Environment::fix_on_wpadmin_request( new MT_Config_Stub( array( 'config.check' => false ) ), false );
unset( $_COOKIE['w3tc_preview'] );

mt_assert(
	'[11] and the gate follows the configuration the pass was handed, as W3TC\'s own pass does',
	false === \strpos( mt_rules_file(), '<IfModule maxcache_module>' )
);

// A config-like object from somewhere other than W3TC reaches the public rules filter. It answers
// the readers W3TC's own filter needs, but has no preview flag, and must pass through rather than fatal.
$mt_foreign_cfg = new MT_Foreign_Config_Stub();

mt_assert(
	'[11] a config object with no preview flag passes through instead of stopping the request',
	true === (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, $mt_foreign_cfg )
);

unset( $GLOBALS['w3tc_maxcache_test_storage'] );
$GLOBALS['mt_is_admin'] = false;

// ============================================================================
// [15] The stored verdict outlives a momentary state of the rules file.
// ============================================================================

// Util_Rule writes .htaccess with a plain file_put_contents, so a request can read it mid-write and
// see a block with no end marker. Answering that live would hand W3TC its rules back on the spot.
mt_force_mode( 'apache' );
$GLOBALS['mt_is_admin'] = false;
\W3TC\Extension_MaxCache_Core::forget_stored();

$mt_stable = new MT_Config_Stub();
\W3TC\Extension_MaxCache_Environment::fix_on_event( $mt_stable, 'config_change' );
\W3TC\Extension_MaxCache_Core::refresh( $mt_stable );

// Half a write: the beginning marker without its end.
\file_put_contents( $mt_rules_path, W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE . "\n<IfModule maxcache_module>\n" );

// Only the per-request latches, the way a later request starts.
\W3TC\Extension_MaxCache_Core::forget();

mt_assert(
	'[15] a file caught mid-write does not flip the verdict the last pass worked out',
	'' === \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_stable ),
	'got: ' . \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_stable )
);

// The half-written block has no end marker, so remove_rules() cannot take it out; the next case
// starts from an empty file instead.
@\unlink( $mt_rules_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
\W3TC\Extension_MaxCache_Core::forget_stored();

// ============================================================================
// [19] The handover rests on a write that happened, not on the intent to serve.
// ============================================================================

// Root_Environment runs W3TC's own pass before this extension's, so W3TC drops its rules first and
// the block is written after. If that write never lands, nothing serves the edge at all.
mt_force_mode( 'apache' );
$GLOBALS['mt_is_admin'] = false;
\W3TC\Extension_MaxCache_Core::forget_stored();

\W3TC\Extension_MaxCache_Environment::fix_on_event( new MT_Config_Stub(), 'config_change' );
$mt_after_write = (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, new MT_Config_Stub() );

// The same supported configuration, with no write confirmed behind it.
\W3TC\Extension_MaxCache_Core::note_delivering( false );
$mt_not_written = (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, new MT_Config_Stub() );

mt_assert(
	'[19] W3TC keeps its own rules until the block is confirmed written, and loses them only then',
	false === $mt_after_write && true === $mt_not_written,
	'written: ' . \var_export( $mt_after_write, true ) . ' not-written: ' . \var_export( $mt_not_written, true )
);

\W3TC\Extension_MaxCache_Environment::fix_after_deactivation();

// ============================================================================
// [17] An admin-ajax save is a save: both halves must read the verdict it produced.
// ============================================================================

// admin_init fires on admin-ajax too, so W3TC's own rules pass runs there and asks this filter. A
// verdict left from before the save would have one half acting on it while the other refreshes.
mt_force_mode( 'apache' );
$GLOBALS['mt_is_admin']   = true;
$GLOBALS['mt_doing_ajax'] = true;
\W3TC\Extension_MaxCache_Core::forget_stored();

// What a pass before this save stored: a refusal that no longer describes the configuration.
$GLOBALS['mt_transients'][ \W3TC\Extension_MaxCache_Core::DEEP_REASON_TRANSIENT ] = array(
	'blocked' => true,
	'reason'  => 'stale refusal from before the save',
	'locale'  => 'default',
);

\apply_filters( 'w3tc_pgcache_rules_required', true, new MT_Config_Stub() );

$mt_ajax_row = $GLOBALS['mt_transients'][ \W3TC\Extension_MaxCache_Core::DEEP_REASON_TRANSIENT ] ?? null;

$GLOBALS['mt_doing_ajax'] = false;
$GLOBALS['mt_is_admin']   = false;
\W3TC\Extension_MaxCache_Core::forget_stored();

mt_assert(
	'[17] an admin-ajax pass refreshes the verdict instead of leaving the one from before the save',
	\is_array( $mt_ajax_row ) && false === $mt_ajax_row['blocked'],
	'row: ' . \var_export( $mt_ajax_row, true )
);

// ============================================================================
// [18] A block that could not be taken out at deactivation is recorded.
// ============================================================================

// Nothing of this extension loads after the switch, so a failed removal has no later pass to report
// it; W3TC's own audit log is the only trace the owner is left with.
mt_force_mode( 'apache' );
\W3TC\Extension_MaxCache_Core::forget_stored();
$GLOBALS['w3tc_maxcache_test_audit'] = array();

// A block with a beginning marker and no end: remove_rules() cannot strip it.
\file_put_contents( $mt_rules_path, W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE . "\n<IfModule maxcache_module>\n" );
\W3TC\Extension_MaxCache_Core::note_delivering( true );

\W3TC\Extension_MaxCache_Environment::deactivate_extension();

mt_assert(
	'[18] a block left behind at deactivation is written to the audit log, not swallowed',
	\in_array( 'maxcache_block_left_behind', \array_column( $GLOBALS['w3tc_maxcache_test_audit'], 0 ), true ),
	'recorded: ' . \implode( ',', \array_column( $GLOBALS['w3tc_maxcache_test_audit'], 0 ) )
);

@\unlink( $mt_rules_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
\W3TC\Extension_MaxCache_Core::forget_stored();

// ============================================================================
// [12]-[13] What happens when the module itself is not there to serve.
// ============================================================================

// [12] Once the module is gone, W3TC's own page-cache rules must not be withheld, or the site
// serves nothing instead of falling back to PHP.

mt_force_mode( 'none' );
$GLOBALS['mt_is_admin'] = false;

mt_assert(
	'[12] W3TC is told its own rules are required once the module is gone',
	true === ( new \W3TC\Extension_MaxCache_Plugin() )->w3tc_pgcache_rules_required( true, new MT_Config_Stub() )
);

// [13] The NGINX daemon only notices a changed file on its own schedule; Apache reads .htaccess per
// request. Backwards, an NGINX host serves a stale configuration until the next rescan.
$mt_reload = new \ReflectionMethod( '\W3TC\Extension_MaxCache_Configd', 'reload_request' );
$mt_reload->setAccessible( true );

mt_force_mode( 'nginx' );
mt_assert(
	'[13] the daemon is asked to reload on NGINX, and not asked at all on Apache',
	'' !== (string) $mt_reload->invoke( null )
);

mt_force_mode( 'apache' );
mt_assert(
	'[13b] on Apache there is no daemon to tell, so nothing is sent',
	'' === (string) $mt_reload->invoke( null )
);

// ============================================================================
// [14] An accepted query string nobody can write as a directive argument.
// ============================================================================

// The list reaches this code through a filter as well as the option, so it is not this plugin's to
// guarantee. A name left out costs a hit - the module declines and PHP answers - so it is not refused.
mt_force_mode( 'apache' );
$mt_odd_qs_config = new MT_Config_Stub(
	array(
		'pgcache.accept.qs' => array( 'lang', 'and hsa_ver', 'currency' ),
	)
);
\W3TC\Extension_MaxCache_Core::refresh( $mt_odd_qs_config );
$mt_odd_qs_rules = \W3TC\Extension_MaxCache_Core::get_rules( $mt_odd_qs_config );

\preg_match( '~MaxCacheQSIgnoredParams (.*)~', $mt_odd_qs_rules, $mt_qs_match );

mt_assert(
	'[14] an accepted query string that is not a bare name is left out, and does not refuse the site',
	'' === \W3TC\Extension_MaxCache_Core::get_unsupported_reason( $mt_odd_qs_config )
		&& isset( $mt_qs_match[1] )
		&& false === \strpos( $mt_qs_match[1], 'hsa_ver' )
		&& false !== \strpos( $mt_qs_match[1], 'lang' )
		&& false !== \strpos( $mt_qs_match[1], 'currency' ),
	'got: ' . ( $mt_qs_match[1] ?? '(no directive)' )
);

// Clean up.
@\unlink( $mt_rules_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@\rmdir( \dirname( $mt_rules_path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@\rmdir( $mt_root ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

echo "\n  MAx Cache - core checks\n";
echo '  ' . \str_repeat( '-', 70 ) . "\n";

foreach ( $mt_cases as $mt_case ) {
	\printf( "  %s  %s\n", 'PASS' === $mt_case[0] ? 'PASS' : 'FAIL', $mt_case[1] );
}

echo "\n  Total: " . ( $mt_pass + $mt_fail )
	. "  Passed: {$mt_pass}  Failed: {$mt_fail}\n\n";

exit( $mt_fail > 0 ? 1 : 0 );
