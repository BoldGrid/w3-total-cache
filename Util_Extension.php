<?php
/**
 * File: Util_Extension.php
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

namespace W3TC;

/**
 * Class: Util_Extension
 *
 * Helper for safe extension file inclusion.
 *
 * Closes the file-inclusion RCE primitive in the `extensions.active`
 * config array. The legacy config shape stored the extension's
 * relative file path as the value of each slug entry, and the loaders
 * (Root_Loader::run_extensions, PgCache_ContentGrabber::run_extensions_dropin)
 * concatenated that path into `include_once`. A mass-assignment write
 * to `extensions.active` could therefore include any file under
 * W3TC_EXTENSION_DIR (i.e. WP_PLUGIN_DIR).
 *
 * This helper drops the raw-concat sink in favour of a slug -> path
 * allowlist, with realpath() canonicalization to confirm the resolved
 * file lives under W3TC_EXTENSION_DIR. The legacy config shape is still
 * accepted at read time via convert_legacy_entries() -- unknown slugs
 * (and unknown reverse-mapped paths) are dropped, not loaded.
 *
 * @since 2.10.0
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Util_Extension {

	/**
	 * Slug -> relative-path allowlist.
	 *
	 * Paths are relative to W3TC_EXTENSION_DIR (i.e. WP_PLUGIN_DIR).
	 * Inventoried from the `path` value declared in each Extension_*_Plugin_Admin
	 * file's `w3tc_extensions` filter callback, plus the UserExperience_*
	 * sub-extensions registered by UserExperience_Plugin_Admin.
	 *
	 * Note: keep this in sync with new extension registrations -- any extension
	 * not listed here cannot be loaded by Root_Loader::run_extensions or
	 * PgCache_ContentGrabber::run_extensions_dropin.
	 *
	 * @since 2.10.0
	 *
	 * @var array<string,string>
	 */
	private static $known_extensions = array(
		'alwayscached'                     => 'w3-total-cache/Extension_AlwaysCached_Plugin.php',
		'amp'                              => 'w3-total-cache/Extension_Amp_Plugin.php',
		'cloudflare'                       => 'w3-total-cache/Extension_CloudFlare_Plugin.php',
		'fragmentcache'                    => 'w3-total-cache/Extension_FragmentCache_Plugin.php',
		'genesis.theme'                    => 'w3-total-cache/Extension_Genesis_Plugin.php',
		'imageservice'                     => 'w3-total-cache/Extension_ImageService_Plugin.php',
		'newrelic'                         => 'w3-total-cache/Extension_NewRelic_Plugin.php',
		'swarmify'                         => 'w3-total-cache/Extension_Swarmify_Plugin.php',
		'user-experience-defer-scripts'    => 'w3-total-cache/UserExperience_DeferScripts_Extension.php',
		'user-experience-emoji'            => 'w3-total-cache/UserExperience_Emoji_Extension.php',
		'user-experience-oembed'           => 'w3-total-cache/UserExperience_OEmbed_Extension.php',
		'user-experience-preload-requests' => 'w3-total-cache/UserExperience_Preload_Requests_Extension.php',
		'user-experience-remove-cssjs'     => 'w3-total-cache/UserExperience_Remove_CssJs_Extension.php',
		'wordpress-seo'                    => 'w3-total-cache/Extension_WordPressSeo_Plugin.php',
		'wpml'                             => 'w3-total-cache/Extension_Wpml_Plugin.php',
	);

	/**
	 * Returns the slug -> relative-path allowlist.
	 *
	 * @since 2.10.0
	 *
	 * @return array<string,string>
	 */
	public static function known_extensions() {
		return self::$known_extensions;
	}

	/**
	 * Resolves an extension slug to its absolute, canonicalized file path.
	 *
	 * Layer 1 -- consult the hard-coded `$known_extensions` allowlist
	 *            first. This is the fast path and the only path that
	 *            works in pre-filter-chain contexts (e.g. the page-cache
	 *            content grabber early-bootstrap), where third-party
	 *            `w3tc_extensions` filter callbacks have not yet
	 *            registered.
	 * Layer 2 -- if the slug is unknown to the hard-coded map, consult
	 *            the runtime `w3tc_extensions` filter result via
	 *            `Extensions_Util::get_extensions()`. This restores the
	 *            documented third-party extension API: any extension that
	 *            ships with a `_Plugin_Admin::w3tc_extensions` filter
	 *            callback declaring its `'path'` will resolve through
	 *            this branch.
	 * Layer 3 -- realpath() canonicalization on the chosen candidate;
	 *            the resolved file MUST live under W3TC_EXTENSION_DIR
	 *            regardless of which lookup source produced it. The
	 *            trust posture matches `Extensions_Util::activate_extension()`,
	 *            which already accepts `$w3tc_meta['path']` from the same
	 *            filter source — but unlike that older code, this
	 *            validator never concatenates the raw value into an
	 *            include without the realpath + prefix check.
	 *
	 * @since 2.10.0
	 *
	 * @param string $slug Extension slug.
	 *
	 * @return string|false Absolute path on success, false on failure.
	 */
	public static function resolve( $slug ) {
		if ( ! \is_string( $slug ) || '' === $slug ) {
			return false;
		}

		$relative = self::expected_relative_path( $slug );

		if ( false === $relative ) {
			return false;
		}

		$real = \realpath( W3TC_EXTENSION_DIR . '/' . $relative );

		if ( false === $real ) {
			return false;
		}

		$base = \realpath( W3TC_EXTENSION_DIR );

		if ( false === $base || 0 !== \strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
			return false;
		}

		return $real;
	}

	/**
	 * Returns the canonical relative-to-W3TC_EXTENSION_DIR path for a
	 * given slug, preferring the hard-coded `$known_extensions` map and
	 * falling back to the runtime `w3tc_extensions` filter result.
	 *
	 * Shared by `resolve()` (which applies realpath + prefix check) and
	 * `convert_legacy_entries()` (which uses the value for the
	 * path-shape comparison against the raw config value). Keeping a
	 * single source of truth means a third-party extension's slug shows
	 * up at both sites or at neither — there is no path where the
	 * legacy-converter accepts a slug that the resolver later refuses.
	 *
	 * @since 2.10.0
	 *
	 * @param string $slug Extension slug.
	 *
	 * @return string|false Relative path (forward-slash, no leading
	 *                      slash) on success, false on failure.
	 */
	private static function expected_relative_path( $slug ) {
		if ( isset( self::$known_extensions[ $slug ] ) ) {
			return self::$known_extensions[ $slug ];
		}

		/**
		 * Filter-driven fallback (third-party extension API). The
		 * `w3tc_extensions` filter is fired by `Extensions_Util::get_extensions()`
		 * and aggregates registrations from every loaded extension's
		 * `_Plugin_Admin::w3tc_extensions` callback. Those callbacks are not
		 * registered until `plugins_loaded`, so consulting the filter any
		 * earlier yields nothing useful.
		 *
		 * We therefore gate on `did_action( 'plugins_loaded' )` rather than the
		 * mere presence of `apply_filters()`. The page-cache drop-in
		 * (advanced-cache.php) is included from wp-settings.php *after*
		 * plugin.php (which defines `apply_filters()`) but *before*
		 * functions.php (which defines core helpers such as
		 * `__return_empty_array()` used inside `get_extensions()`). A
		 * presence-of-`apply_filters` check therefore passes during that
		 * pre-bootstrap window and faults on the not-yet-loaded core helper.
		 * Before `plugins_loaded` this fallback is a true no-op — the
		 * hard-coded map above is the only source. The leading
		 * `function_exists( 'did_action' )` guard keeps the check itself safe
		 * if it is ever reached before the hook API is loaded.
		 */
		if ( ! \function_exists( 'did_action' ) || ! \did_action( 'plugins_loaded' ) || ! \class_exists( '\W3TC\Dispatcher' ) || ! \class_exists( '\W3TC\Extensions_Util' ) ) {
			return false;
		}

		$w3tc_config = Dispatcher::config();
		if ( null === $w3tc_config ) {
			return false;
		}

		$all = Extensions_Util::get_extensions( $w3tc_config );
		if ( ! \is_array( $all ) || ! isset( $all[ $slug ] ) ) {
			return false;
		}

		$w3tc_meta = $all[ $slug ];
		if ( ! \is_array( $w3tc_meta ) || empty( $w3tc_meta['path'] ) || ! \is_string( $w3tc_meta['path'] ) ) {
			return false;
		}

		/**
		 * Reject obvious traversal attempts at the filter-source boundary.
		 * `realpath()` + the prefix check in `resolve()` would catch these
		 * too, but dropping them early keeps the rejection visible to a
		 * defender reading this code and avoids any chance of an exotic
		 * platform resolving `..` segments in a way the prefix check
		 * misses.
		 */
		if ( false !== \strpos( $w3tc_meta['path'], '..' ) ) {
			return false;
		}

		return \str_replace( '\\', '/', \trim( $w3tc_meta['path'], '/' ) );
	}

	/**
	 * Loads an extension file by slug, gated by resolve().
	 *
	 * @since 2.10.0
	 *
	 * @param string $slug Extension slug.
	 *
	 * @return bool True if the file was resolved (note: include_once still
	 *              returns void; bool here reflects resolution, not load result).
	 */
	public static function include_once( $slug ) {
		$path = self::resolve( $slug );

		if ( false === $path ) {
			return false;
		}

		include_once $path;

		return true;
	}

	/**
	 * Normalizes a legacy `extensions.active` (or `extensions.active_frontend`,
	 * `extensions.active_dropin`) array to a slug-keyed shape.
	 *
	 * Legacy shapes accepted:
	 *   - `slug => relative-path` (the original `extensions.active` format)
	 *   - `slug => '*'`           (the original `extensions.active_frontend` marker)
	 *   - `slug => true`          (some call sites store booleans to mark active)
	 *   - `slug => anything-truthy` (entry is preserved if slug is known)
	 *
	 * Entries whose key is not in the known-extensions allowlist are dropped.
	 * Entries whose value is a relative path that doesn't reverse-map to the
	 * key's known path are also dropped (defence against a malicious path
	 * value supplied under a legitimate slug).
	 *
	 * Falsy non-string values (`false`, `null`, `0`) are dropped. No
	 * first-party writer stores these — deactivation goes through
	 * `unset( $active[ $slug ] )`. Dropping them keeps the read-side
	 * semantics aligned with the writer contract: a falsy value reads as
	 * "deactivated" rather than being coerced to "load the canonical path".
	 *
	 * Read-side only: callers do not write the normalized result back to
	 * master.php. Existing installs continue to work; unknown legacy entries
	 * simply don't load anymore.
	 *
	 * @since 2.10.0
	 *
	 * @param array $active Raw `extensions.active*` array from Config.
	 *
	 * @return array<string,string> Slug-keyed map; values are the canonical
	 *                              relative path (so call sites needing the
	 *                              path can still access it).
	 */
	public static function convert_legacy_entries( $active ) {
		if ( ! \is_array( $active ) ) {
			return array();
		}

		$out = array();

		foreach ( $active as $w3tc_key => $w3tc_value ) {
			if ( ! \is_string( $w3tc_key ) || '' === $w3tc_key ) {
				continue;
			}

			/**
			 * Drop falsy non-string values up front. Without this, the
			 * path-shape check below (which only runs for strings) would
			 * fall through and coerce `slug => false` / `null` / `0` into
			 * "load the canonical path" — surprising, given that the
			 * first-party deactivate path is `unset( $active[ $slug ] )`
			 * and a falsy value reads as "deactivated" at every other
			 * call site.
			 */
			if ( false === $w3tc_value || null === $w3tc_value || 0 === $w3tc_value ) {
				continue;
			}

			$expected = self::expected_relative_path( $w3tc_key );
			if ( false === $expected ) {
				continue;
			}

			/**
			 * Accept `slug => '*'` (frontend marker) and `slug => true`
			 * as-is. Reject `slug => alien-path`, where "alien" includes
			 * the empty string: there is no legitimate write surface that
			 * produces `slug => ''` and treating it as accept would skip
			 * the path-shape check entirely.
			 */
			if ( \is_string( $w3tc_value ) && '*' !== $w3tc_value ) {
				$normalized = \str_replace( '\\', '/', \trim( $w3tc_value, '/' ) );
				if ( $normalized !== $expected ) {
					continue;
				}
			}

			$out[ $w3tc_key ] = $expected;
		}

		return $out;
	}
}
