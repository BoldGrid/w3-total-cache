<?php
/**
 * File: Extension_MaxCache_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

/**
 * What the MAx Cache module can do on this host, and the directives it needs.
 *
 * @since X.X.X
 */
class Extension_MaxCache_Core {

	const VERSION_FILE_APACHE = '/opt/cloudlinux/maxcache/.version';

	const VERSION_FILE_NGINX = '/opt/cloudlinux/maxcache/.nginx-version';

	const SLUG = 'maxcache';

	const VERSION_MARKER_PREFIX = '/opt/cloudlinux/maxcache/.version-';

	const VERSION_MARKER_PREFIX_NGINX = '/opt/cloudlinux/maxcache/.nginx-version-';

	// Nothing declares this level on disk; distinguishes a real answer from an EACCES directory answering yes to everything.
	const LEVEL_SENTINEL = '0.0.0';

	const LEVEL_PATH_TOKENS = '1.2.6';

	const ALWAYS_EXCLUDE_URI = array( '/embed/', '/wp-json', 'wp-login', 'wp-register' );

	const XML_EXCLUDE_URI = array( '/feed(?:/|$)', '\.xml$', '\.xsl' );

	// MAX_STRING_LEN in httpd.h.
	const MAX_DIRECTIVE_LINE_LENGTH = 8192;

	const DEEP_REASON_TRANSIENT = 'w3tc_maxcache_deep_reason';

	const DELIVERING_OPTION = 'w3tc_maxcache_delivering';

	const SERVED_HANDLER = 'maxcache-cached-file';

	/**
	 * Deliberately not persisted: a stored "installed" can only go stale in the dangerous direction.
	 *
	 * @since X.X.X
	 *
	 * @var string|null
	 */
	private static $mode = null;

	/**
	 * Whether an NGINX build is installed.
	 *
	 * @since X.X.X
	 *
	 * @var bool|null
	 */
	private static $nginx_installed = null;

	/**
	 * Whether an Apache build is installed.
	 *
	 * @since X.X.X
	 *
	 * @var bool|null
	 */
	private static $apache_installed = null;

	/**
	 * Configuration the deep verdict was worked out for.
	 *
	 * @since X.X.X
	 *
	 * @var Config|null
	 */
	private static $refreshed_config = null;

	/**
	 * Value last written to the delivering option.
	 *
	 * @since X.X.X
	 *
	 * @var string|null
	 */
	private static $delivering_noted = null;

	/**
	 * Cache directory as a URL path.
	 *
	 * @since X.X.X
	 *
	 * @var string|null
	 */
	private static $cache_root = null;

	/**
	 * Capability level answers, memoized for the request.
	 *
	 * @since X.X.X
	 *
	 * @var array<string,bool>
	 */
	private static $levels = array();

	/**
	 * Keyed by the instance, not spl_object_id: ids are recycled once an object is collected.
	 *
	 * @since X.X.X
	 *
	 * @var array<string,array{value:mixed,config:Config}>
	 */
	private static $memo = array();

	/**
	 * Re-entrancy guard for get_unsupported_reason().
	 *
	 * @since X.X.X
	 *
	 * @var bool
	 */
	private static $reason_computing = false;

	/**
	 * The saved configuration, read once per request while previewing.
	 *
	 * @since X.X.X
	 *
	 * @var Config|null
	 */
	private static $saved_config = null;

	/**
	 * Returns the server mode the module runs in on this host.
	 *
	 * @since X.X.X
	 *
	 * @return string One of `apache`, `nginx`, `none`.
	 */
	public static function get_mode() {
		if ( null !== self::$mode ) {
			return self::$mode;
		}

		// Apache takes priority when both builds are installed.
		if ( self::apache_module_installed() ) {
			self::$mode = 'apache';
		} elseif ( self::nginx_module_installed() ) {
			self::$mode = 'nginx';
		} else {
			self::$mode = 'none';
		}

		return self::$mode;
	}

	/**
	 * Whether the module is installed on this host.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_available() {
		return 'none' !== self::get_mode();
	}

	/**
	 * Whether the module is installed on an NGINX host, where a daemon parses `.htaccess` as flat text and
	 * silently loses `<If>`, `<Files>` and `<Directory>` wrappers.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_nginx() {
		return 'nginx' === self::get_mode();
	}

	/**
	 * Whether an NGINX build is installed, regardless of which server answers this request.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function nginx_module_installed() {
		self::detect_installed_builds();

		return self::$nginx_installed;
	}

	/**
	 * Whether an Apache build of the module is installed - see nginx_module_installed().
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function apache_module_installed() {
		self::detect_installed_builds();

		return self::$apache_installed;
	}

	/**
	 * Settles $nginx_installed/$apache_installed once, guarded against the same EACCES trap as get_mode().
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private static function detect_installed_builds() {
		if ( null !== self::$nginx_installed && null !== self::$apache_installed ) {
			return;
		}

		// One probe covers every host without the module: the markers live in this directory.
		if ( ! self::path_exists( \dirname( self::VERSION_FILE_APACHE ) )
			|| self::path_exists( self::VERSION_MARKER_PREFIX . self::LEVEL_SENTINEL )
		) {
			self::$nginx_installed  = false;
			self::$apache_installed = false;

			return;
		}

		self::$nginx_installed  = self::path_exists( self::VERSION_FILE_NGINX );
		self::$apache_installed = self::path_exists( self::VERSION_FILE_APACHE );
	}

	/**
	 * Returns why the installed module's directives cannot reach this request, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private static function get_web_server_unsupported_reason() {
		if ( self::is_nginx() ) {
			return '';
		}

		// Empty SERVER_SOFTWARE reads as Apache, so WP-CLI/cron are accepted like a real request would be.
		if ( ! Util_Environment::is_apache() ) {
			// Runs before the "not installed" answer, so this must not claim a build is installed.
			return \__( 'This web server is neither Apache nor an NGINX host with the MAx Cache module, so .htaccess directives would not reach it.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Whether the installed module is at or above a given capability level.
	 *
	 * @since X.X.X
	 *
	 * @param string $level Version the wanted feature arrived in.
	 *
	 * @return bool
	 */
	public static function module_supports( $level ) {
		if ( ! self::is_available() ) {
			return false;
		}

		if ( isset( self::$levels[ $level ] ) ) {
			return self::$levels[ $level ];
		}

		$marker_paths = self::marker_paths( $level );
		$supported    = ! empty( $marker_paths );

		// Supported only when every installed build declares the level: the rules file is shared.
		foreach ( $marker_paths as $marker_path ) {
			if ( ! self::path_exists( $marker_path ) ) {
				$supported = false;
				break;
			}
		}

		self::$levels[ $level ] = $supported;

		return $supported;
	}

	/**
	 * Returns the marker path for every build installed on this host, for one capability level.
	 *
	 * @since X.X.X
	 *
	 * @param string $level Version the wanted feature arrived in.
	 *
	 * @return string[]
	 */
	private static function marker_paths( $level ) {
		$paths = array();

		if ( self::apache_module_installed() ) {
			$paths[] = self::VERSION_MARKER_PREFIX . $level;
		}

		if ( self::nginx_module_installed() ) {
			$paths[] = self::VERSION_MARKER_PREFIX_NGINX . $level;
		}

		return $paths;
	}

	/**
	 * The saved configuration the live rules file answers to, which every public entry point converts
	 * through.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return Config The same instance unless it is a preview draft.
	 */
	public static function saved_config( $w3tc_config ) {
		// The filters this reaches are public, so what arrives is not always a Config.
		if ( ! \method_exists( $w3tc_config, 'is_preview' ) || ! $w3tc_config->is_preview() ) {
			return $w3tc_config;
		}

		if ( null !== self::$saved_config ) {
			return self::$saved_config;
		}

		// Config takes the preview flag from the cookie, so the saved one is read with it out of sight.
		$held = $_COOKIE['w3tc_preview'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		unset( $_COOKIE['w3tc_preview'] );

		try {
			self::$saved_config = new Config();
		} finally {
			if ( null !== $held ) {
				$_COOKIE['w3tc_preview'] = $held;
			}
		}

		return self::$saved_config;
	}

	/**
	 * Whether MAx Cache is switched on and usable for the current configuration.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return bool
	 */
	public static function is_enabled( $w3tc_config ) {
		$w3tc_config = self::saved_config( $w3tc_config );

		return $w3tc_config->is_extension_active( self::SLUG )
			&& '' === self::get_unsupported_reason( $w3tc_config );
	}

	/**
	 * Whether the module is actually being handed the delivery, as of the last rules pass.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_delivering() {
		if ( null === self::$delivering_noted ) {
			self::$delivering_noted = '1' === (string) \get_option( self::DELIVERING_OPTION, '' ) ? '1' : '0';
		}

		return '1' === self::$delivering_noted;
	}

	/**
	 * Records what the rules pass just did.
	 *
	 * @since X.X.X
	 *
	 * @param bool $delivering Whether the block was written.
	 *
	 * @return void
	 */
	public static function note_delivering( $delivering ) {
		$value = $delivering ? '1' : '0';

		// Through is_delivering(), not the raw latch: null never equals "0", so a raw check deleted the option on every pass.
		if ( self::is_delivering() === (bool) $delivering ) {
			return;
		}

		self::$delivering_noted = $value;

		// Dropped, not written as "0": is_delivering() reads both the same, and withdraw() deletes it right after anyway.
		if ( ! $delivering ) {
			\delete_option( self::DELIVERING_OPTION );

			return;
		}

		\update_option( self::DELIVERING_OPTION, $value, false );
	}

	/**
	 * Returns why a MAx Cache block this plugin did not write stops the handover, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private static function foreign_block_reason() {
		$path = Util_Rule::get_apache_rules_path();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! @\file_exists( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged
		$contents = @\is_readable( $path ) ? @\file_get_contents( $path ) : false;

		// Present and unreadable refuses: answering "no objection" is how a second block gets written.
		if ( false === $contents ) {
			return \sprintf(
				/* translators: %s: path of the rules file. */
				\__( 'The rules file %s cannot be read, so whether another MAx Cache block is already in it is unknown and delivery is not handed over.', 'w3-total-cache' ),
				$path
			);
		}

		// A partial write/hand edit can leave the opening marker without its closing one; the anchored strip won't catch it.
		if ( false !== \strpos( $contents, W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE )
			&& false === \strpos( $contents, W3TC_MARKER_END_PGCACHE_MAXCACHE )
		) {
			return \__( 'An unfinished MAx Cache block is left in .htaccess: it has a beginning marker but no end marker, so this plugin can neither read nor remove it. Delete the lines from "# BEGIN W3TC Page Cache MAx Cache" onwards and save the file.', 'w3-total-cache' );
		}

		$ours = \preg_quote( W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE, '~' )
			. '.*?' . \preg_quote( W3TC_MARKER_END_PGCACHE_MAXCACHE, '~' );

		$foreign = \preg_replace( '~' . $ours . '~s', '', $contents );

		// null means PCRE gave up (pcre.backtrack_limit); casting it to string would wrongly answer "no foreign block".
		if ( null === $foreign ) {
			return self::unsearchable_file_message();
		}

		// Apache and the daemon both accept extra whitespace and a module's source-file name as its identifier.
		$found = \preg_match( '~<IfModule\s+(?:maxcache_module|mod_maxcache\.c)\s*>~i', $foreign );

		// false means PCRE gave up; reading it as "no foreign block" would let a second one get written.
		if ( false === $found ) {
			return self::unsearchable_file_message();
		}

		if ( 1 === $found ) {
			return \__( 'Another MAx Cache block is already present in .htaccess. Two of them would serve different cache files depending on which web server answers, so this one is left alone until the other is removed.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Returns the refusal for a rules file PCRE gave up on.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private static function unsearchable_file_message() {
		return \__( 'The .htaccess file could not be searched for an existing MAx Cache block, and delivery is not handed over on an unchecked file.', 'w3-total-cache' );
	}

	/**
	 * Whether the file the directives go into can be written.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	private static function rules_file_writable() {
		$path = Util_Rule::get_apache_rules_path();

		// Direct filesystem calls: the question is whether the web-server user can write, not the configured WP_Filesystem transport.
		if ( \file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			return \is_writable( $path );
		}

		// Not there yet is fine as long as it can be created.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		return \is_writable( \dirname( $path ) );
	}

	/**
	 * Returns why this configuration cannot be handed to the module, or an empty string when it can.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Empty string when the configuration is supported.
	 */
	public static function get_unsupported_reason( $w3tc_config ) {
		$w3tc_config = self::saved_config( $w3tc_config );

		// Answered once per request: a rules-required listener calling back here would otherwise recurse.
		if ( self::$reason_computing ) {
			// A filter called back in mid-computation; refuse rather than hand delivery over on an unknown answer.
			return \__( 'Whether MAx Cache can serve this site could not be determined.', 'w3-total-cache' );
		}

		$memo = self::memo_get( 'reason', $w3tc_config );

		if ( null !== $memo ) {
			return $memo;
		}

		self::$reason_computing = true;

		try {
			$reason = self::memo_set( 'reason', self::gate_verdict( $w3tc_config ), $w3tc_config );
		} finally {
			self::$reason_computing = false;
		}

		return $reason;
	}

	/**
	 * Returns a memo worked out for this configuration, or null when there is none.
	 *
	 * @since X.X.X
	 *
	 * @param string $key         Memo name.
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return mixed|null
	 */
	private static function memo_get( $key, $w3tc_config ) {
		// Config::set() mutates in place, so an identity-keyed memo can survive a change between two
		// admin-save handlers - and an admin-ajax save is two such handlers as much as a page load is.
		if ( \is_admin() ) {
			return null;
		}

		if ( ! isset( self::$memo[ $key ] ) || self::$memo[ $key ]['config'] !== $w3tc_config ) {
			return null;
		}

		return self::$memo[ $key ]['value'];
	}

	/**
	 * Holds a memo against the configuration it describes, and hands it back.
	 *
	 * @since X.X.X
	 *
	 * @param string $key         Memo name.
	 * @param mixed  $value       What was worked out.
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return mixed The same value.
	 */
	private static function memo_set( $key, $value, $w3tc_config ) {
		self::$memo[ $key ] = array(
			'value'  => $value,
			'config' => $w3tc_config,
		);

		return $value;
	}

	/**
	 * Drops what a configuration change invalidates: the verdict memos, the per-request latches and the cache
	 * root.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function forget() {
		self::$memo = array();

		// Per-request latches only - not $mode/$levels/$nginx_installed/$apache_installed, which describe the host.
		self::$delivering_noted = null;
		self::$cache_root       = null;
		self::$refreshed_config = null;
	}

	/**
	 * Drops everything this integration keeps outside the configuration, for deactivation.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function forget_stored() {
		// The delivering record stays: whether the block is still in the file is the caller's finding, not this method's.
		\delete_transient( self::DEEP_REASON_TRANSIENT );

		self::$saved_config = null;

		self::forget();
	}

	/**
	 * The locale the stored reason was translated in: one row holds the verdict, and a mismatch
	 * here is what makes gate_verdict() fall back to the untranslated sentence.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private static function current_locale() {
		// The locale strings actually translate in: the viewing administrator's in wp-admin, not the site's.
		if ( \function_exists( 'determine_locale' ) ) {
			$locale = (string) \determine_locale();
		} elseif ( \function_exists( 'get_locale' ) ) {
			$locale = (string) \get_locale();
		} else {
			$locale = '';
		}

		return '' === $locale ? 'default' : \preg_replace( '/[^A-Za-z0-9_]/', '', $locale );
	}

	/**
	 * Re-runs the checks that cost I/O, so one pass does not repeat them.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return void
	 */
	public static function refresh( $w3tc_config ) {
		$w3tc_config = self::saved_config( $w3tc_config );

		// Once per request per configuration: three callers ask per admin request, each a full rule-set generation.
		if ( self::$refreshed_config === $w3tc_config ) {
			return;
		}

		// A rules-required listener calling back here would recurse without this guard.
		if ( self::$reason_computing ) {
			return;
		}

		self::$reason_computing = true;

		try {
			// Cheap gate first: when it already refuses, computing the deep verdict too is pure cost nobody reads.
			$cheap = self::cheap_unsupported_reason( $w3tc_config );
			$deep  = '' === $cheap ? self::deep_unsupported_reason( $w3tc_config ) : null;
		} finally {
			self::$reason_computing = false;
		}

		// Only on a changed answer: a momentary cheap refusal (chmod, deploy) shouldn't drop the stored row.
		if ( null !== $deep ) {
			self::store_deep_verdict( $deep );
		}

		// Last, not first: a rules-required listener reaching back here must not memoize a stale answer.
		self::forget();

		self::memo_set( 'gate_verdict', '' !== $cheap ? $cheap : (string) $deep, $w3tc_config );
		self::$refreshed_config = $w3tc_config;
	}

	/**
	 * Stores the verdict of the checks that cost I/O and hands it back.
	 *
	 * One transient carrying locale and verdict, replaced whole: keyed by locale because the
	 * verdict is a translated sentence, in one row so a config change drops all of it.
	 *
	 * @since X.X.X
	 *
	 * @param string $deep The verdict.
	 *
	 * @return string The same verdict.
	 */
	private static function store_deep_verdict( $deep ) {
		$stored = \get_transient( self::DEEP_REASON_TRANSIENT );

		$same_state = \is_array( $stored )
			&& isset( $stored['blocked'], $stored['reason'], $stored['locale'] )
			&& ( '' !== $deep ) === $stored['blocked'];

		// Rewriting an unchanged verdict costs two options-table writes per admin page; locale only matters where printed.
		if ( $same_state
			&& ( ! \is_admin() || ( $deep === $stored['reason'] && self::current_locale() === $stored['locale'] ) )
		) {
			return (string) $deep;
		}

		\set_transient(
			self::DEEP_REASON_TRANSIENT,
			array(
				'blocked' => '' !== $deep,
				'reason'  => $deep,
				'locale'  => self::current_locale(),
			),
			\HOUR_IN_SECONDS
		);

		return (string) $deep;
	}

	/**
	 * Returns why this configuration cannot be handed over, asking the questions that cost I/O.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function deep_unsupported_reason( $w3tc_config ) {
		$reason = self::foreign_block_reason();

		if ( '' !== $reason ) {
			return $reason;
		}

		return self::get_directive_length_reason( $w3tc_config );
	}

	/**
	 * Returns why a filtered rules layout stops the handover, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private static function get_filtered_layout_reason() {
		// Only the filters that can move the cache file or its serving condition - not every rules filter.
		if ( \has_filter( 'w3tc_pagecache_rules_apache_uri_prefix' )
			|| \has_filter( 'w3tc_pagecache_rules_nginx_uri_prefix' )
		) {
			return \__( 'Another plugin changes where W3TC keeps its cache files, and the server module can only be given the layout W3TC describes by itself.', 'w3-total-cache' );
		}

		// A serving condition, not a cache-key dimension: adds no marker, and the module can't reproduce a request header.
		if ( \has_filter( 'w3tc_pagecache_rules_apache_rewrite_cond' )
			|| \has_filter( 'w3tc_pagecache_rules_nginx_rewrite_cond' )
		) {
			return \__( 'Another plugin decides when a visitor may be served from the page cache, and the server module cannot be given that condition.', 'w3-total-cache' );
		}

		// Rewrite the condition an accepted query string folds under; the directive itself takes bare names, no condition.
		if ( \has_filter( 'w3tc_pagecache_rules_apache_accept_qs_rules' )
			|| \has_filter( 'w3tc_pagecache_rules_nginx_accept_qs_rules' )
		) {
			return \__( 'Another plugin changes when an accepted query string is ignored, and the server module can only be given the names, not the condition.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Works out why this configuration cannot be handed over.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function cheap_unsupported_reason( $w3tc_config ) {
		// Asked from two places (is_enabled(), refresh()); each firing two public filters and rebuilding every list.
		$memo = self::memo_get( 'cheap_verdict', $w3tc_config );

		if ( null !== $memo ) {
			return $memo;
		}

		return self::memo_set( 'cheap_verdict', self::compute_cheap_unsupported_reason( $w3tc_config ), $w3tc_config );
	}

	/**
	 * Works out why this configuration cannot be handed over, without the memo.
	 *
	 * The four groups answer in order, and the order is what the owner reads: the host first,
	 * then what W3TC is set to do, then Browser Cache, then what the directives can carry.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function compute_cheap_unsupported_reason( $w3tc_config ) {
		$reason = self::host_unsupported_reason();

		if ( '' !== $reason ) {
			return $reason;
		}

		$reason = self::delivery_unsupported_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		$reason = self::browsercache_unsupported_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		return self::directives_unsupported_reason( $w3tc_config );
	}

	/**
	 * Refuses hosts the module cannot serve from: wrong web server, absent or too old a module, multisite.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private static function host_unsupported_reason() {
		// Before "not installed": a wrong-web-server host can still have the module installed, just unreachable.
		$reason = self::get_web_server_unsupported_reason();

		if ( '' !== $reason ) {
			return $reason;
		}

		if ( ! self::is_available() ) {
			return \__( 'The MAx Cache module is not installed on this server.', 'w3-total-cache' );
		}

		// A module without this level can't expand W3TC's cache-file tokens at all; the answer names the version needed.
		if ( ! self::module_supports( self::LEVEL_PATH_TOKENS ) ) {
			return \sprintf(
				// translators: %s is a version number, e.g. 1.2.6.
				\__( 'The installed MAx Cache module is older than %s, the version this integration needs to reproduce the cache file names W3TC writes. Ask your hosting provider to update it.', 'w3-total-cache' ),
				self::LEVEL_PATH_TOKENS
			);
		}

		if ( \is_multisite() ) {
			return \__( 'Multisite installations are not supported.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Refuses configurations where W3TC is not the one delivering, or its rules file cannot be written.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function delivery_unsupported_reason( $w3tc_config ) {
		if (
			! $w3tc_config->get_boolean( 'pgcache.enabled' )
			|| 'file_generic' !== $w3tc_config->get_string( 'pgcache.engine' )
		) {
			return \__( 'Page Cache is not set to "Disk: Enhanced", so there are no cache files a web server module could serve.', 'w3-total-cache' );
		}

		// After the engine check, the way is_rules_required() asks it: the filter can only suppress.
		if ( ! (bool) \apply_filters( 'w3tc_pgcache_rules_required', true, $w3tc_config ) ) {
			return \__( 'Another component is handling page cache delivery on this site, so there are no W3TC rules to hand over.', 'w3-total-cache' );
		}

		// Asked of the gate: handing delivery over removes W3TC's rules first, so a failed write leaves nothing serving.
		if ( ! self::rules_file_writable() ) {
			return \__( 'The server configuration file cannot be written, so the directives would never reach the module.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Refuses Browser Cache settings the module cannot reproduce on what it serves.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function browsercache_unsupported_reason( $w3tc_config ) {
		// Apache never hands a served file to sendfile(); NGINX has no such guarantee - keyed on install, not on serving.
		if ( $w3tc_config->get_boolean( 'pgcache.file.nfs' ) && self::nginx_module_installed() ) {
			return \__( 'Page Cache is set to use NFS-safe methods, which W3TC applies to its cache files through EnableSendfile, and that cannot be attached to what the module serves.', 'w3-total-cache' );
		}

		// ExpiresByType has no condition and no Header-line equivalent; with it on, W3TC drops max-age too, leaving neither.
		if ( $w3tc_config->get_boolean( 'browsercache.enabled' )
			&& $w3tc_config->get_boolean( 'browsercache.html.expires' )
		) {
			return \__( 'Browser Cache is set to send an expiry header for HTML, which W3TC applies to its cache files through mod_expires, and that cannot be attached to what the module serves.', 'w3-total-cache' );
		}

		// FileETag None needs only pgcache.compatibility (Browser Cache off included), Apache-only.
		$etag_withheld_by_browsercache  = $w3tc_config->get_boolean( 'browsercache.enabled' ) && ! $w3tc_config->get_boolean( 'browsercache.html.etag' );
		$etag_withheld_by_compatibility = ! $w3tc_config->get_boolean( 'browsercache.enabled' ) && $w3tc_config->get_boolean( 'pgcache.compatibility' ) && self::apache_module_installed();

		if ( $etag_withheld_by_browsercache || $etag_withheld_by_compatibility ) {
			// extra_header_lines() only re-emits the unset in this same combination.
			$etag_carried_on_apache = self::apache_module_installed() && $w3tc_config->get_boolean( 'pgcache.compatibility' );

			if ( ! $etag_carried_on_apache ) {
				if ( $etag_withheld_by_browsercache ) {
					return \__( 'Browser Cache is set to withhold the entity tag for HTML, which W3TC applies to its cache files by file name, and that cannot reach what the module serves.', 'w3-total-cache' );
				}

				return \__( 'Page Cache compatibility mode withholds the entity tag for HTML with Browser Cache off, which W3TC applies to its cache files by file name, and that cannot reach what the module serves.', 'w3-total-cache' );
			}
		}

		if ( $w3tc_config->get_boolean( 'browsercache.enabled' ) ) {
			// No compatibility gate here - Apache carries this one unconditionally.
			if ( ! $w3tc_config->get_boolean( 'browsercache.html.last_modified' ) && ! self::apache_module_installed() ) {
				return \__( 'Browser Cache is set to ignore the last-modified date for HTML, which W3TC applies to its cache files by file name, and that cannot reach what the module serves.', 'w3-total-cache' );
			}

			// Not carried on either engine.
			if ( $w3tc_config->get_boolean( 'browsercache.html.cache.control' ) ) {
				return \__( 'Browser Cache is set to send a Cache-Control policy for HTML, which W3TC applies to its cache files by file name, and that cannot reach what the module serves.', 'w3-total-cache' );
			}

			// extra_header_lines() carries this one wherever Apache is installed, same as last_modified.
			if ( $w3tc_config->get_boolean( 'browsercache.html.w3tc' ) && ! self::apache_module_installed() ) {
				return \__( 'Browser Cache is set to send the W3TC header for HTML, which W3TC applies to its cache files by file name, and that cannot reach what the module serves.', 'w3-total-cache' );
			}
		}

		return '';
	}

	/**
	 * Refuses configurations the directive block cannot express: file names, patterns, query strings, paths.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function directives_unsupported_reason( $w3tc_config ) {
		$reason = self::get_file_name_unsupported_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		$reason = self::get_unwritable_pattern_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		$reason = self::get_unwritable_charset_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		$reason = self::get_unrepresentable_uri_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		if ( '' === self::get_cache_root() ) {
			return \__( 'The page cache directory is outside the part of the filesystem the web server addresses by URL.', 'w3-total-cache' );
		}

		// Asked per request, not stored: a front-end-only filter registration would be invisible under a stale verdict.
		$reason = self::get_filtered_layout_reason();

		if ( '' !== $reason ) {
			return $reason;
		}

		// MaxCachePath takes exactly one directive argument; an unquoted value cannot carry whitespace or a quote.
		if ( 1 === \preg_match( '/[\s"]/', self::get_path_template( $w3tc_config ) ) ) {
			return \__( 'The page cache directory is at a path that cannot be written into the server configuration as a single directive argument.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Returns the gate's verdict: the cheap checks, then the stored deep ones.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function gate_verdict( $w3tc_config ) {
		// Worked out by refresh() earlier; held here, not as final, so the filter still runs on every read.
		$memo = self::memo_get( 'gate_verdict', $w3tc_config );

		if ( null !== $memo ) {
			return $memo;
		}

		$reason = self::cheap_unsupported_reason( $w3tc_config );

		if ( '' !== $reason ) {
			return $reason;
		}

		$stored_deep = \get_transient( self::DEEP_REASON_TRANSIENT );

		// Refusing here deadlocks: the pass that computes this belongs to the extension this answer would switch on.
		if ( ! \is_array( $stored_deep ) || ! isset( $stored_deep['blocked'] ) ) {
			return self::store_deep_verdict( self::deep_unsupported_reason( $w3tc_config ) );
		}

		if ( $stored_deep['blocked'] ) {
			return isset( $stored_deep['locale'], $stored_deep['reason'] ) && self::current_locale() === $stored_deep['locale']
				? $stored_deep['reason']
				: \__( 'The current configuration cannot be handed to MAx Cache.', 'w3-total-cache' );
		}

		return '';
	}

	/**
	 * Returns why the cache file NAME cannot be reproduced, or an empty string when it can.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Empty string when the file name is reproducible.
	 */
	private static function get_file_name_unsupported_reason( $w3tc_config ) {
		// The group name is chosen by the site owner and varies per request, so no literal expresses it.
		$groups = array(
			'mobile.enabled'               => \__( 'mobile user agent', 'w3-total-cache' ),
			'referrer.enabled'             => \__( 'referrer', 'w3-total-cache' ),
			'pgcache.cookiegroups.enabled' => \__( 'cookie', 'w3-total-cache' ),
		);

		foreach ( $groups as $key => $kind ) {
			if ( $w3tc_config->get_boolean( $key ) ) {
				return \sprintf(
					/* translators: %s: the kind of group, e.g. "mobile user agent". */
					\__( 'Separate caches for %s groups put a group name into the cache file name, which the server module cannot reproduce.', 'w3-total-cache' ),
					$kind
				);
			}
		}

		return '';
	}

	/**
	 * Names what this site never caches by a rule that cannot be written into a directive, if any.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Empty string when every exclusion can be written.
	 */
	private static function get_unwritable_pattern_reason( $w3tc_config ) {
		$patterns = array(
			'uri'     => self::get_exclude_uri( $w3tc_config ),
			'ua'      => self::get_exclude_ua( $w3tc_config ),
			'cookies' => self::get_exclude_cookie( $w3tc_config ),
		);

		foreach ( $patterns as $name => $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}

			// Cookie/UA lists are sanitised already; the URI list is raw (sanitising would rewrite the owner's regex).
			if ( false !== \strpos( $pattern, '"' ) || 1 === \preg_match( '/[\x00-\x1f\x7f]/', $pattern ) ) {
				return self::unwritable_pattern_message( $name );
			}

			// An uncompilable pattern is logged and skipped, not refused, so it doesn't silently stop applying.
			if ( false === @\preg_match( "\1{$pattern}\1", '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return self::unwritable_pattern_message( $name );
			}

			// Joined into one alternation, a group reference would renumber and name another entry's group.
			if ( 'uri' === $name && self::pattern_carries_group_reference( $pattern ) ) {
				return self::unwritable_pattern_message( $name );
			}

			// Only this list reaches the directive unescaped, and a closing tag in it ends the block early.
			if ( 'uri' === $name && 1 === \preg_match( '~<\s*/~', $pattern ) ) {
				return self::unwritable_pattern_message( $name );
			}
		}

		return '';
	}

	/**
	 * Whether a pattern refers back to a capturing group.
	 *
	 * @since X.X.X
	 *
	 * @param string $pattern Assembled exclusion pattern.
	 *
	 * @return bool
	 */
	private static function pattern_carries_group_reference( $pattern ) {
		// Every PCRE backreference/subroutine-call spelling; read loosely on purpose.
		return 1 === \preg_match( '~\\\\[1-9gk]|\\(\\?(?:R\\)|P[>=]|&|[0-9]|[-+][0-9])~', (string) $pattern );
	}

	/**
	 * Returns why the charset cannot be written as a Header line, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_unwritable_charset_reason( $w3tc_config ) {
		// Same signal as get_response_header_rules(), which this refusal has to stay in step with.
		if ( ! self::apache_module_installed() || $w3tc_config->get_boolean( 'pgcache.remove_charset' ) ) {
			return '';
		}

		// Not refused when unwritable in another way: a missing charset is not user-visible, the page's own <meta charset> tag still tells the browser.

		$charset = (string) \get_option( 'blog_charset' );

		// Only what get_response_header_rules()'s shape check drops: a backslash is encoded, a quote/control byte can't be.
		if ( 1 !== \preg_match( '~["\x00-\x1f\x7f]~', $charset ) ) {
			return '';
		}

		// Otherwise dropped by the shape check; a page served without this charset leaves the browser guessing.
		return \__( 'The site character set contains a character that cannot be written into a server directive, so the module-served pages would go out without the charset W3TC sets.', 'w3-total-cache' );
	}

	/**
	 * Returns the human-readable reason for an unwritable exclusion list.
	 *
	 * @since X.X.X
	 *
	 * @param string $name One of `uri`, `ua`, `cookies`.
	 *
	 * @return string
	 */
	private static function unwritable_pattern_message( $name ) {
		$lists = array(
			'uri'     => \__( 'URLs', 'w3-total-cache' ),
			'ua'      => \__( 'user agents', 'w3-total-cache' ),
			'cookies' => \__( 'cookies', 'w3-total-cache' ),
		);

		return \sprintf(
			/* translators: %s: URLs, user agents, or cookies. */
			\__( 'The %s this site never caches are listed in a form that cannot be written into the server configuration, and the module would serve visitors W3TC keeps out.', 'w3-total-cache' ),
			isset( $lists[ $name ] ) ? $lists[ $name ] : $name
		);
	}

	/**
	 * Returns the directive lines this integration writes, keyed by directive name.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return array
	 */
	private static function get_directive_lines( $w3tc_config ) {
		// Asked three times per pass (length gate, unwritable-pattern gate, writer); each re-derives every list otherwise.
		$memo = self::memo_get( 'directive_lines', $w3tc_config );

		if ( null !== $memo ) {
			return $memo;
		}

		$lines = array(
			'MaxCache'              => '    MaxCache On',
			'MaxCacheExcludeURI'    => '    MaxCacheExcludeURI "' . self::as_directive_argument( self::get_exclude_uri( $w3tc_config ) ) . '"',
			'MaxCacheExcludeCookie' => '    MaxCacheExcludeCookie "' . self::as_directive_argument( self::get_exclude_cookie( $w3tc_config ) ) . '"',
		);

		$exclude_ua = self::get_exclude_ua( $w3tc_config );

		if ( '' !== $exclude_ua ) {
			$lines['MaxCacheExcludeUA'] = '    MaxCacheExcludeUA "' . self::as_directive_argument( $exclude_ua ) . '"';
		}

		$ignored_qs = self::get_ignored_qs( $w3tc_config );

		if ( '' !== $ignored_qs ) {
			// W3TC screens these names case-insensitively; matching byte-for-byte would disagree on "?FBCLID=1".
			$lines['MaxCacheOptions'] = '    MaxCacheOptions +FoldQSIgnoredCase';

			// Unquoted, one line: the NGINX daemon assigns this field (not appends), and doesn't strip quotes here.
			$lines['MaxCacheQSIgnoredParams'] = '    MaxCacheQSIgnoredParams ' . self::as_directive_argument( $ignored_qs );
		}

		// {SLASH_SUFFIX}/{SSL_SUFFIX}/{ENC_SUFFIX} take their literal from one key=value directive, not a colon-delimited token.
		$suffix_tokens = array( 'slash=_slash' );

		if ( $w3tc_config->get_boolean( 'pgcache.cache.ssl' ) ) {
			$suffix_tokens[] = 'ssl=_ssl';
		}

		foreach ( self::get_encoding_literals( $w3tc_config ) ?? array() as $key => $literal ) {
			$suffix_tokens[] = $key . '=' . $literal;
		}

		$lines['MaxCacheSuffixLiterals'] = '    MaxCacheSuffixLiterals ' . \implode( ' ', $suffix_tokens );

		// No directive mirrors W3TC's POST/query-string conditions: both builds refuse non-GET and a surviving query.
		$lines['MaxCachePath'] = '    MaxCachePath ' . self::as_directive_argument( self::get_path_template( $w3tc_config ) );

		return self::memo_set( 'directive_lines', $lines, $w3tc_config );
	}

	/**
	 * Returns why one of the directive lines this integration would write is unsafe, or an empty string when every
	 * one of them fits.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_directive_length_reason( $w3tc_config ) {
		$lines = self::get_directive_lines( $w3tc_config );

		// Re-emitted headers go into the site-root file, where an over-long line is a 500 for the whole site.
		foreach ( \explode( "\n", self::get_response_header_rules( $w3tc_config ) ) as $header_line ) {
			$header_line = \rtrim( $header_line );

			if ( 0 === \strpos( \ltrim( $header_line ), 'Header ' ) ) {
				$named = array();

				// Keyed by the directive, not its value, so the owner has something to search the config for.
				$key = \preg_match( '~^\s*(Header\s+\S+\s+\S+)~', $header_line, $named ) ? $named[1] : 'Header';

				$lines[ $key ] = $header_line;
			}
		}

		foreach ( $lines as $directive => $line ) {
			$length = \strlen( $line );

			if ( $length >= self::MAX_DIRECTIVE_LINE_LENGTH ) {
				return \sprintf(
					/* translators: 1: directive name, e.g. MaxCacheExcludeCookie. 2: line length in bytes. 3: the Apache line-length limit in bytes. */
					\__( 'The %1$s directive this configuration would write is %2$d bytes long, at or over the %3$d-byte line limit Apache\'s own config parser enforces. Past it Apache fails to read .htaccess at all, taking the whole site down rather than just this acceleration.', 'w3-total-cache' ),
					$directive,
					$length,
					self::MAX_DIRECTIVE_LINE_LENGTH
				);
			}
		}

		return '';
	}

	/**
	 * Returns the page cache directory as a URL path under the document root, through the helper W3TC uses for its
	 * own rules: stripping a DOCUMENT_ROOT prefix here breaks on subdirectory installs.
	 *
	 * @since X.X.X
	 *
	 * @return string Empty string when the directory is not addressable by URL.
	 */
	private static function get_cache_root() {
		// Its inputs are process constants, and one gate pass asks three times.
		if ( null !== self::$cache_root ) {
			return self::$cache_root;
		}

		$cache_dir = Util_Environment::normalize_path( W3TC_CACHE_PAGE_ENHANCED_DIR );
		$path      = Dispatcher::component( 'PgCache_Environment' )->apache_cache_uri_path( W3TC_CACHE_PAGE_ENHANCED_DIR );
		$path      = \is_string( $path ) ? \rtrim( Util_Environment::normalize_path( $path ), '/' ) : '';

		// That helper's last resort is a str_replace() of the document root - a no-op when the cache directory lives outside it.
		if ( '' === $path || $path === $cache_dir ) {
			self::$cache_root = '';

			return '';
		}

		self::$cache_root = $path;

		return $path;
	}

	/**
	 * Returns the `MaxCachePath` template for the current configuration, naming the same cache
	 * files the page-cache rewrite rules do.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	public static function get_path_template( $w3tc_config ) {
		$w3tc_config = self::saved_config( $w3tc_config );

		$template = self::get_cache_root() . '/{HTTP_HOST}{REQUEST_URI}/_index';

		$template .= '{SLASH_SUFFIX}';

		if ( $w3tc_config->get_boolean( 'pgcache.cache.ssl' ) ) {
			$template .= '{SSL_SUFFIX}';
		}

		$template .= '.html';

		return $template . self::get_encoding_suffix( $w3tc_config );
	}

	/**
	 * Returns the cache-file suffix that carries the content encoding.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_encoding_suffix( $w3tc_config ) {
		return null === self::get_encoding_literals( $w3tc_config ) ? '' : '{ENC_SUFFIX}';
	}

	/**
	 * Returns the `enc-gzip=`/`enc-br=` keys `MaxCacheSuffixLiterals` writes, or null when
	 * neither coding is written at all - `{ENC_SUFFIX}` then does not appear in the template,
	 * so no directive is needed either.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string[]|null Map of key ('enc-gzip'/'enc-br') to literal, holding only the
	 *                        codings actually written; or null.
	 */
	private static function get_encoding_literals( $w3tc_config ) {
		if ( ! $w3tc_config->get_boolean( 'browsercache.enabled' ) ) {
			return null;
		}

		// Same questions PgCache_ContentGrabber::_get_compression() asks: debug mode and store-compression-off stop it outright.
		if ( $w3tc_config->get_boolean( 'pgcache.debug' ) || \defined( 'W3TC_PAGECACHE_STORE_COMPRESSION_OFF' ) ) {
			return null;
		}

		$gzip   = $w3tc_config->get_boolean( 'browsercache.html.compression' ) && \function_exists( 'gzencode' );
		$brotli = $w3tc_config->get_boolean( 'browsercache.html.brotli' ) && \function_exists( 'brotli_compress' );

		if ( ! $gzip && ! $brotli ) {
			return null;
		}

		// A coding not written is a key left out, not a sentinel value - an absent key never reaches the directive as "".
		$literals = array();

		if ( $gzip ) {
			$literals['enc-gzip'] = '_gzip';
		}

		if ( $brotli ) {
			$literals['enc-br'] = '_br';
		}

		return $literals;
	}

	/**
	 * Returns the URI exclusion pattern for the current configuration.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_exclude_uri( $w3tc_config ) {
		$patterns = self::ALWAYS_EXCLUDE_URI;

		// Tracks the option: with XML storage off, W3TC writes `.html` for these too, served like any other page.
		if ( $w3tc_config->get_boolean( 'pgcache.cache.nginx_handle_xml' ) ) {
			$patterns = \array_merge( $patterns, self::XML_EXCLUDE_URI );
		}

		// Not sanitised: these are regexes, and stripping "<"/">" turns a lookbehind into a lookahead, matching differently.
		foreach ( (array) $w3tc_config->get_array( 'pgcache.reject.uri' ) as $pattern ) {
			$directive = self::uri_pattern_as_directive( $pattern );

			if ( '' !== $directive ) {
				$patterns[] = $directive;
			}
		}

		// Case-insensitive here too: PgCache_ContentGrabber::_passed_reject_uri() matches this list with "~...~i".
		return self::alternation( $patterns );
	}

	/**
	 * Returns the query-string parameters the module must subtract before it names a cache file.
	 *
	 * W3TC strips these from the query string and serves the base cache file when nothing is left;
	 * the module does the same with the names this directive gives it, so both sides pick one file.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Space-separated names, or an empty string.
	 */
	private static function get_ignored_qs( $w3tc_config ) {
		$names = array();

		foreach ( self::accepted_qs( $w3tc_config ) as $name ) {
			$name = (string) $name;

			if ( '' !== $name && self::qs_name_is_emitted( $name ) ) {
				$names[] = $name;
			}
		}

		return \implode( ' ', \array_unique( $names ) );
	}

	/**
	 * Returns the accepted query strings as the rules generator sees them.
	 *
	 * Through the same filter it applies: a plugin can add names the stored option does not carry,
	 * and W3TC subtracts those too.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string[]
	 */
	private static function accepted_qs( $w3tc_config ) {
		// Apache takes priority when both builds are installed, same as get_mode().
		$filter = self::apache_module_installed() ? 'w3tc_pagecache_rules_apache_accept_qs' : 'w3tc_pagecache_rules_nginx_accept_qs';

		// Not sanitised: the rules generator doesn't sanitise this list either; what sanitising would remove is refused by name.
		$names = (array) \apply_filters( $filter, $w3tc_config->get_array( 'pgcache.accept.qs' ) );

		// Same trim the generator applies after this filter: drops every empty()-empty entry, "0" included.
		Util_Rule::array_trim( $names );

		return $names;
	}

	/**
	 * Whether an accepted query string reaches the directive at all.
	 *
	 * The rules generator replaces a space with "+" before matching and the module compares the decoded
	 * key, so an entry carrying either is left out rather than refused: the request reaches PHP.
	 *
	 * @since X.X.X
	 *
	 * @param string $name One entry of the accepted list, as stored.
	 *
	 * @return bool
	 */
	private static function qs_name_is_emitted( $name ) {
		return '' !== $name
			&& false === \strpos( $name, '+' )
			&& false === \strpos( $name, '=' )
			&& 1 !== \preg_match( '/[\s"<>\x00-\x1f\x7f]/', $name );
	}

	/**
	 * Whether an exclusion entry names something only the request being served decides.
	 *
	 * Read by the gate and by the emitter, from one place: an entry the gate accepts and the
	 * emitter then drops leaves the module serving pages W3TC excludes.
	 *
	 * @since X.X.X
	 *
	 * @param string $pattern One entry of the exclusion list.
	 *
	 * @return bool
	 */
	private static function uri_pattern_is_request_bound( $pattern ) {
		// %HOST%/%POST_ID% would freeze the directive to whichever hostname/post generated the rules.
		return false !== \strpos( (string) $pattern, '%POST_ID%' ) || false !== \strpos( (string) $pattern, '%HOST%' );
	}

	/**
	 * Returns a reject-URI entry as the module would have to read it, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @param string $pattern One `pgcache.reject.uri` entry.
	 *
	 * @return string
	 */
	private static function uri_pattern_as_directive( $pattern ) {
		$pattern = \trim( (string) $pattern );

		if ( '' === $pattern ) {
			return '';
		}

		if ( self::uri_pattern_is_request_bound( $pattern ) ) {
			return '';
		}

		// Same expansion PgCache_ContentGrabber::_passed_reject_uri() applies, so %BLOG_ID% means what it means there.
		$pattern = \trim( Util_Environment::parse_path( $pattern ) );

		// Checked after expanding, because that is what can leave `^` or `()` behind.
		if ( '' === $pattern ) {
			return '';
		}

		// Nothing screened for being broad: both sides read it unanchored and case-insensitively already.
		return $pattern;
	}

	/**
	 * Returns why a URI the owner excluded cannot be excluded in a directive, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_unrepresentable_uri_reason( $w3tc_config ) {
		// Raw, like the same list above: the dangerous bytes are refused by name, not stripped.
		foreach ( (array) $w3tc_config->get_array( 'pgcache.reject.uri' ) as $pattern ) {
			if ( ! self::uri_pattern_is_request_bound( $pattern ) ) {
				continue;
			}

			// Dropping it quietly would leave the module serving pages PHP now refuses, some already cached.
			return \sprintf(
				/* translators: %s: one entry of the "Never cache the following pages" list. */
				\__( 'The page exclusion "%s" cannot be written as a server directive, so the module would go on serving pages that W3TC itself excludes.', 'w3-total-cache' ),
				$pattern
			);
		}

		return '';
	}

	/**
	 * Returns the cookie exclusion pattern for the current configuration.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_exclude_cookie( $w3tc_config ) {
		// True like the Apache generator: the handover only runs on the disk-enhanced engine.
		$cookies = PgCache_Environment::reject_cookies( $w3tc_config, true );

		// Added on top of W3TC's list: the preview cookie makes W3TC write a "_preview" file-name variant.
		$cookies[] = 'w3tc_preview';

		$cookies = \array_map( array( '\W3TC\Util_Environment', 'preg_quote' ), $cookies );

		// Case-insensitive like W3TC's [NC]; written inline since the module compiles this case-sensitively.
		return self::alternation( $cookies );
	}

	/**
	 * Returns the user-agent exclusion pattern for the current configuration.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Empty string when the site excludes no user agent.
	 */
	private static function get_exclude_ua( $w3tc_config ) {
		$agents = PgCache_Environment::reject_user_agents( $w3tc_config );

		if ( empty( $agents ) ) {
			return '';
		}

		// Escaped like W3TC's RewriteCond for the same list: "Chrome (Bot)" must match as a literal, not a regex group.
		$agents = \array_map( array( '\W3TC\Util_Environment', 'preg_quote' ), $agents );

		// Unanchored like W3TC's RewriteCond: a token appearing anywhere in the header matches. (?i) carries [NC].
		return self::alternation( $agents );
	}

	/**
	 * Returns the directive block for `.htaccess`, as the only block of its kind: every directive
	 * precedes the Apache-only sections, so a reader that stops at the first closing tag has them all.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Empty string when the configuration is not supported.
	 */
	public static function get_rules( $w3tc_config ) {
		$w3tc_config = self::saved_config( $w3tc_config );

		if ( ! self::is_enabled( $w3tc_config ) ) {
			return '';
		}

		// The markers frame the block: Util_Rule::add_rules() and foreign_block_reason() both key off them.
		$rules = W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE . "\n";

		$rules .= "<IfModule maxcache_module>\n";

		// No mobile handling, on purpose: the gate refuses `mobile.enabled`, so the template carries no `{MOBILE_SUFFIX}`.

		$rules .= \implode( "\n", self::get_directive_lines( $w3tc_config ) ) . "\n";

		// After every directive, so a reader that stops at the first closing tag still has them all.
		$rules .= self::get_encoding_rules( $w3tc_config );
		$rules .= self::get_response_header_rules( $w3tc_config );
		$rules .= "</IfModule>\n";
		$rules .= W3TC_MARKER_END_PGCACHE_MAXCACHE . "\n";

		return $rules;
	}

	/**
	 * Returns the mod_headers section that follows what the module served, or an empty string.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function get_response_header_rules( $w3tc_config ) {
		// Apache only: the NGINX daemon reads `.htaccess` as flat text, no mod_headers. Keyed on install, not on serving.
		if ( ! self::apache_module_installed() ) {
			return '';
		}

		$lines = self::extra_header_lines( $w3tc_config );

		if ( '' === $lines ) {
			return '';
		}

		// Quoted as one argument since the expression carries a space. No `always`: that targets err_headers_out, not this.
		$condition = ' "expr=%{HANDLER} == \'' . self::SERVED_HANDLER . '\'"';
		$emitted   = '';

		foreach ( \explode( "\n", $lines ) as $line ) {
			$line = \rtrim( $line );

			if ( '' === \trim( $line ) ) {
				continue;
			}

			// An owner-set value: a newline in it would split into a new line, possibly read as its own directive.
			if ( 0 !== \strpos( $line, '    Header ' )
				|| ! \preg_match( '~^\s*Header(?:\s+(?:"[^"\x00-\x1f\x7f]*"|[^\s"\x00-\x1f\x7f]+)){1,3}\s*$~', $line )
			) {
				continue;
			}

			$emitted .= $line . $condition . "\n";
		}

		// Every candidate line was refused: an empty section says nothing and hides that.
		if ( '' === $emitted ) {
			return '';
		}

		return "<IfModule mod_headers.c>\n" . $emitted . "</IfModule>\n";
	}

	/**
	 * Returns the Header lines standing in for the cache directory's non-Header directives.
	 *
	 * AddDefaultCharset and FileETag take no condition, so in the site root they would touch every
	 * other static file too; as Header lines they ride the handler condition.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string
	 */
	private static function extra_header_lines( $w3tc_config ) {
		$lines = '';

		// Matches PgCache_Environment's own, wider condition: compatibility alone, Browser Cache off included.
		if ( $w3tc_config->get_boolean( 'pgcache.compatibility' )
			&& ! ( $w3tc_config->get_boolean( 'browsercache.enabled' ) && $w3tc_config->get_integer( 'browsercache.html.etag' ) )
		) {
			$lines .= "    Header unset ETag\n";
		}

		// No compatibility gate here - W3TC unsets Last-Modified whenever this is off, unconditionally.
		if ( $w3tc_config->get_boolean( 'browsercache.enabled' )
			&& ! $w3tc_config->get_boolean( 'browsercache.html.last_modified' )
		) {
			$lines .= "    Header unset Last-Modified\n";
		}

		if ( $w3tc_config->get_boolean( 'browsercache.enabled' )
			&& $w3tc_config->get_boolean( 'browsercache.html.w3tc' )
		) {
			$lines .= '    Header set X-Powered-By "' . Util_Environment::w3tc_header() . "\"\n";
		}

		if ( $w3tc_config->get_boolean( 'pgcache.remove_charset' ) ) {
			return $lines;
		}

		$charset = (string) \get_option( 'blog_charset' );
		$charset = '' === $charset ? 'utf-8' : $charset;

		// substring_conf() reads "\\\\" as one backslash even inside quotes, so a trailing one escapes the closing quote.
		return $lines . '    Header set Content-Type "text/html; charset='
			. self::apache_escape( $charset ) . "\"\n";
	}

	/**
	 * Returns the MIME rule a compressed cache file needs when nothing else names its Content-Type.
	 *
	 * The module sets Content-Encoding itself, so AddEncoding here would only duplicate it. AddType is
	 * still needed under remove_charset: measured, the response otherwise carries no Content-Type.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 *
	 * @return string Empty string when no compressed variant is served, or the Header line covers it.
	 */
	private static function get_encoding_rules( $w3tc_config ) {
		// Apache only: nothing on the NGINX side reads mod_mime lines. Same "installed, not answering" reasoning as above.
		if ( ! self::apache_module_installed() ) {
			return '';
		}

		if ( ! $w3tc_config->get_boolean( 'pgcache.remove_charset' ) ) {
			return '';
		}

		// Same answer the path template is built on: a coding named here but not in the template marks a file never written.
		$literals = self::get_encoding_literals( $w3tc_config );

		if ( null === $literals ) {
			return '';
		}

		$mime = '';

		foreach ( $literals as $suffix ) {
			$mime .= '    AddType text/html .html' . $suffix . "\n";
		}

		return "<IfModule mod_mime.c>\n" . $mime . "</IfModule>\n";
	}

	/**
	 * Returns a pattern in the form the reader of this file has to carry for the module to receive it unchanged.
	 *
	 * @since X.X.X
	 *
	 * @param string $pattern Pattern as the module must compile it.
	 *
	 * @return string
	 */
	private static function as_directive_argument( $pattern ) {
		if ( self::is_nginx() ) {
			return (string) $pattern;
		}

		return self::apache_escape( $pattern );
	}

	/**
	 * Doubles a backslash the way Apache's own config-file parser undoes it once, inside quotes.
	 *
	 * @since X.X.X
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private static function apache_escape( $value ) {
		return \str_replace( '\\', '\\\\', $value );
	}

	/**
	 * Joins escaped entries into the one case-insensitive alternation the directives take.
	 *
	 * @since X.X.X
	 *
	 * @param string[] $entries Escaped pattern entries.
	 *
	 * @return string Empty string when there is nothing to exclude.
	 */
	private static function alternation( array $entries ) {
		if ( empty( $entries ) ) {
			return '';
		}

		$entries = \array_map(
			static function ( $entry ) {
				return '(?:' . $entry . ')';
			},
			$entries
		);

		// Non-capturing: an outer capture would renumber every group inside an owner-written regex.
		return '(?i)(?:' . \implode( '|', $entries ) . ')';
	}

	/**
	 * Whether a path exists on disk, without reading it.
	 *
	 * @since X.X.X
	 *
	 * @param string $path Absolute path to probe.
	 *
	 * @return bool
	 */
	private static function path_exists( $path ) {
		if ( '' === $path || ! \function_exists( 'stream_socket_client' ) ) {
			return false;
		}

		$errno = 0;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		\set_error_handler(
			static function () {
				return true;
			}
		);
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$socket = @\stream_socket_client( 'unix://' . $path, $errno, $errstr, 0.1 );
		} finally {
			// In finally: a throw here would otherwise leave an error-swallowing handler installed for the rest of the request.
			\restore_error_handler();
		}

		if ( \is_resource( $socket ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			\fclose( $socket );

			return true;
		}

		// errno for a present path (measured PHP 7.4/8.2/8.3, Linux only); unlisted reads as absent.
		$present = array(
			13,  // EACCES: a directory on the way cannot be searched by this user.
			111, // ECONNREFUSED: what a regular file and a directory both answer.
		);

		return \in_array( $errno, $present, true );
	}
}
