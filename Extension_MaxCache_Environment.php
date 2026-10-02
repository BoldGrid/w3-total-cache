<?php
/**
 * File: Extension_MaxCache_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

/**
 * Writes and withdraws the module's directive block.
 *
 * @since X.X.X
 */
class Extension_MaxCache_Environment {
	/**
	 * Fixes the environment on a wp-admin request.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config      W3TC Config containing relevant settings.
	 * @param bool   $force_all_checks Whether to run every check.
	 *
	 * @throws Util_Environment_Exceptions When the rules file cannot be written.
	 *
	 * @return void
	 */
	public static function fix_on_wpadmin_request( $w3tc_config, $force_all_checks ) {
		// Same gate W3TC puts on its own rules pass: writing ours where that didn't run leaves both rule sets live.
		if ( ! $w3tc_config->get_boolean( 'config.check' ) && ! $force_all_checks ) {
			return;
		}

		// Config::__construct() reads the preview cookie, not the request: a stale cookie would rewrite the live block on every admin page.
		if ( Util_Environment::is_preview_mode() ) {
			return;
		}

		$exs = new Util_Environment_Exceptions();

		self::apply( $w3tc_config, $exs );

		if ( \count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes the environment when the configuration changes.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 * @param string $event       Event key.
	 *
	 * @throws Util_Environment_Exceptions When the rules file cannot be written.
	 *
	 * @return void
	 */
	public static function fix_on_event( $w3tc_config, $event ) {
		// The one event W3TC rewrites its own rules on; writing ours on the others would leave both rule sets, or neither.
		if ( 'config_change' !== $event ) {
			return;
		}

		$exs = new Util_Environment_Exceptions();

		self::apply( $w3tc_config, $exs );

		if ( \count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Takes the block out and forgets what was stored, on deactivation of the plugin.
	 *
	 * @since X.X.X
	 *
	 * @throws Util_Environment_Exceptions When the block could not be taken out.
	 *
	 * @return void
	 */
	public static function fix_after_deactivation() {
		$exs = self::withdraw();

		Extension_MaxCache_Core::forget_stored();

		if ( \count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Takes the block out when the owner switches the extension off.
	 *
	 * Nothing is thrown: the caller turns an exception into an audit line and answers false, which
	 * drops the confirmation for a deactivation that did persist.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function deactivate_extension() {
		self::withdraw();

		// The last pass this extension makes: switched off, no later pass can correct a record remove() left standing.
		$path = Util_Rule::get_apache_rules_path();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$left = ( $path && @\is_readable( $path ) ) ? @\file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged

		// Only on a file that could be read and no longer carries the block - unreadable keeps the record as remove() left it.
		if ( false !== $left && false === \strpos( $left, W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE ) ) {
			Extension_MaxCache_Core::note_delivering( false );
		}

		// The observation stays: it describes the infrastructure, not the switch.
		Extension_MaxCache_Core::forget();
	}

	/**
	 * Takes the block out, collecting what the removal reported.
	 *
	 * @since X.X.X
	 *
	 * @return Util_Environment_Exceptions What the removal reported.
	 */
	private static function withdraw() {
		$exs = new Util_Environment_Exceptions();

		self::remove( $exs );

		// remove() already drops the record when the block is gone; a second delete here is one round trip for nothing.

		return $exs;
	}

	/**
	 * Writes the block, or takes it out when this configuration cannot be handed over.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 * @param object $exs         Exception handler object.
	 *
	 * @return void
	 */
	private static function apply( $w3tc_config, $exs ) {
		// The I/O checks run where the rules are written, so the block is built from a current verdict, not an hour-old one.
		Extension_MaxCache_Core::refresh( $w3tc_config );

		if ( Extension_MaxCache_Core::is_enabled( $w3tc_config ) ) {
			self::add( $w3tc_config, $exs );

			return;
		}

		self::remove( $exs );
	}

	/**
	 * Adds the block.
	 *
	 * @since X.X.X
	 *
	 * @param Config $w3tc_config W3TC Config containing relevant settings.
	 * @param object $exs         Exception handler object.
	 *
	 * @return void
	 */
	private static function add( $w3tc_config, $exs ) {
		$path = Util_Rule::get_apache_rules_path();

		// Keyed on whether NGINX is installed, not on whether it answers this request - cPanel fronts Apache with it.
		$notify    = Extension_MaxCache_Core::nginx_module_installed();
		$signature = $notify ? Extension_MaxCache_Configd::rules_file_signature( $path ) : '';
		$before    = \count( $exs->exceptions() );
		$rules     = Extension_MaxCache_Core::get_rules( $w3tc_config );

		// An empty block puts add_rules() into removal mode, reporting nothing - the record would wrongly claim delivery.
		if ( '' === $rules ) {
			self::remove( $exs );

			return;
		}

		Util_Rule::add_rules(
			$exs,
			$path,
			$rules,
			W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE,
			W3TC_MARKER_END_PGCACHE_MAXCACHE,
			// Immediately before the WordPress block; no fallback anchor needed since position doesn't affect per-directory directives.
			array( W3TC_MARKER_BEGIN_WORDPRESS => 0 )
		);

		// On NGINX the block only takes effect once the daemon reads it - otherwise nothing serves while the record claims it does.
		$reached = $notify ? Extension_MaxCache_Configd::notify_if_changed( $path, $signature ) : true;

		// The record follows the file: an unreached daemon serves the previous config regardless of what was written.
		if ( $reached && \count( $exs->exceptions() ) === $before ) {
			Extension_MaxCache_Core::note_delivering( true );
		}
	}

	/**
	 * Takes the block out.
	 *
	 * @since X.X.X
	 *
	 * @param object $exs Exception handler object.
	 *
	 * @return void
	 */
	private static function remove( $exs ) {
		$path = Util_Rule::get_apache_rules_path();

		// Same reasoning as add(): keyed on whether the NGINX build is installed, not on mode.
		$notify    = Extension_MaxCache_Core::nginx_module_installed();
		$signature = $notify ? Extension_MaxCache_Configd::rules_file_signature( $path ) : '';
		$reported  = \count( $exs->exceptions() );

		Util_Rule::remove_rules( $exs, $path, W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE, W3TC_MARKER_END_PGCACHE_MAXCACHE );

		// Read back, not trusted: erase_rules() anchors on "\n", so a CRLF file keeps the block but reports success.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$exists = $path && @\file_exists( $path );
		$left   = ( $exists && @\is_readable( $path ) ) ? @\file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged

		// Mirrors add(): until the daemon reads this, it keeps serving the old view, so the record must not hand rules back yet.
		$reached = $notify ? Extension_MaxCache_Configd::notify_if_changed( $path, $signature ) : true;

		// An unreadable file is unknown, not "gone" - reading it as gone would hand rules back with our block still there.
		$still_there = false === $left
			? $exists
			: false !== \strpos( $left, W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE );

		// Unreadable branch only: telling the owner to delete markers from a file that may never have had them would never clear.
		$report = false !== $left || Extension_MaxCache_Core::is_delivering();

		if ( $still_there && $report && \count( $exs->exceptions() ) === $reported ) {
			$exs->push(
				new Util_WpFile_FilesystemModifyException(
					\__( 'The MAx Cache block could not be removed.', 'w3-total-cache' ),
					// Null, not '': the collection adopts the first non-null form, and an empty one would hide a real one pushed later.
					null,
					\sprintf(
						/* translators: 1 path, 2 starting marker, 3 ending marker. */
						\__( 'Edit file %1$s and remove all lines between and including %2$s and %3$s markers.', 'w3-total-cache' ),
						$path,
						W3TC_MARKER_BEGIN_PGCACHE_MAXCACHE,
						W3TC_MARKER_END_PGCACHE_MAXCACHE
					),
					$path
				)
			);

			return;
		}

		if ( ! $still_there && $reached ) {
			Extension_MaxCache_Core::note_delivering( false );
		}
	}
}
