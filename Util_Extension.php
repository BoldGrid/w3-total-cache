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
		'alwayscached'                    => 'w3-total-cache/Extension_AlwaysCached_Plugin.php',
		'amp'                             => 'w3-total-cache/Extension_Amp_Plugin.php',
		'cloudflare'                      => 'w3-total-cache/Extension_CloudFlare_Plugin.php',
		'fragmentcache'                   => 'w3-total-cache/Extension_FragmentCache_Plugin.php',
		'genesis.theme'                   => 'w3-total-cache/Extension_Genesis_Plugin.php',
		'imageservice'                    => 'w3-total-cache/Extension_ImageService_Plugin.php',
		'newrelic'                        => 'w3-total-cache/Extension_NewRelic_Plugin.php',
		'swarmify'                        => 'w3-total-cache/Extension_Swarmify_Plugin.php',
		'user-experience-defer-scripts'   => 'w3-total-cache/UserExperience_DeferScripts_Extension.php',
		'user-experience-emoji'           => 'w3-total-cache/UserExperience_Emoji_Extension.php',
		'user-experience-oembed'          => 'w3-total-cache/UserExperience_OEmbed_Extension.php',
		'user-experience-preload-requests' => 'w3-total-cache/UserExperience_Preload_Requests_Extension.php',
		'user-experience-remove-cssjs'    => 'w3-total-cache/UserExperience_Remove_CssJs_Extension.php',
		'wordpress-seo'                   => 'w3-total-cache/Extension_WordPressSeo_Plugin.php',
		'wpml'                            => 'w3-total-cache/Extension_Wpml_Plugin.php',
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
	 * Layer 1 -- slug must appear in the hard-coded allowlist.
	 * Layer 2 -- realpath() canonicalization; the resolved file must
	 *            live under W3TC_EXTENSION_DIR.
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

		if ( ! isset( self::$known_extensions[ $slug ] ) ) {
			return false;
		}

		$candidate = W3TC_EXTENSION_DIR . '/' . self::$known_extensions[ $slug ];
		$real      = \realpath( $candidate );

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
	 * include_once an extension by slug, gated by resolve().
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

			if ( ! isset( self::$known_extensions[ $key ] ) ) {
				continue;
			}

			$expected = self::$known_extensions[ $key ];

			// Accept `slug => '*'` (frontend marker), `slug => bool`,
			// or `slug => expected-path`. Reject `slug => alien-path`.
			if ( \is_string( $value ) && '' !== $value && '*' !== $value ) {
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
