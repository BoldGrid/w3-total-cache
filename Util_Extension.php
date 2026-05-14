<?php
/**
 * File: Util_Extension.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class: Util_Extension
 *
 * Helper for safe extension file inclusion.
 *
 * Closes the file-inclusion RCE primitive in the `extensions.active`
 * config array (rt9-99). The legacy config shape stored the extension's
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
 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 *            which already accepts `$meta['path']` from the same
	 *            filter source — but unlike that older code, this
	 *            validator never concatenates the raw value into an
	 *            include without the realpath + prefix check.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
		 * `_Plugin_Admin::w3tc_extensions` callback. We only consult it
		 * when WordPress is loaded (otherwise the filter machinery is
		 * unavailable) and when a `Dispatcher::config()` is reachable.
		 * In the page-cache pre-bootstrap path neither holds, so the
		 * fallback is a no-op there — the hard-coded map above is the
		 * only source.
		 */
		if ( ! \function_exists( 'apply_filters' ) || ! \class_exists( '\W3TC\Dispatcher' ) || ! \class_exists( '\W3TC\Extensions_Util' ) ) {
			return false;
		}

		$config = Dispatcher::config();
		if ( null === $config ) {
			return false;
		}

		$all = Extensions_Util::get_extensions( $config );
		if ( ! \is_array( $all ) || ! isset( $all[ $slug ] ) ) {
			return false;
		}

		$meta = $all[ $slug ];
		if ( ! \is_array( $meta ) || empty( $meta['path'] ) || ! \is_string( $meta['path'] ) ) {
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
		if ( false !== \strpos( $meta['path'], '..' ) ) {
			return false;
		}

		return \str_replace( '\\', '/', \trim( $meta['path'], '/' ) );
	}

	/**
	 * Loads an extension file by slug, gated by resolve().
	 *
	 * @since X.X.X
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
	 *   - `slug => bool`          (some call sites store booleans)
	 *   - `slug => anything-truthy` (entry is preserved if slug is known)
	 *
	 * Entries whose key is not in the known-extensions allowlist are dropped.
	 * Entries whose value is a relative path that doesn't reverse-map to the
	 * key's known path are also dropped (defence against an attacker who
	 * knows a legitimate slug but supplies a malicious path value).
	 *
	 * Read-side only: callers do not write the normalized result back to
	 * master.php. Existing installs continue to work; unknown legacy entries
	 * simply don't load anymore.
	 *
	 * @since X.X.X
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

		foreach ( $active as $key => $value ) {
			if ( ! \is_string( $key ) || '' === $key ) {
				continue;
			}

			$expected = self::expected_relative_path( $key );
			if ( false === $expected ) {
				continue;
			}

			/**
			 * Accept `slug => '*'` (frontend marker) and `slug => bool`
			 * as-is. Reject `slug => alien-path`, where "alien" includes
			 * the empty string: there is no legitimate write surface that
			 * produces `slug => ''` and treating it as accept would skip
			 * the path-shape check entirely (rt9-99 defense-in-depth).
			 */
			if ( \is_string( $value ) && '*' !== $value ) {
				$normalized = \str_replace( '\\', '/', \trim( $value, '/' ) );
				if ( $normalized !== $expected ) {
					continue;
				}
			}

			$out[ $key ] = $expected;
		}

		return $out;
	}
}
