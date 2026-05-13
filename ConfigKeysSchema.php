<?php
/**
 * File: ConfigKeysSchema.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

/**
 * Class ConfigKeysSchema
 *
 * Read-only accessor for the configuration-key schema in `ConfigKeys.php`.
 *
 * The schema file itself is a flat `$keys` array — every legitimate W3TC
 * configuration key, with its declared type, default value, and (since the
 * mass-assignment hardening pass) optional `flags` map. Treat the file as
 * the source of truth: any inbound write that originates from user input
 * MUST validate the key against this schema before reaching `Config::set()`.
 *
 * This class exposes:
 *  - `get_keys()`      — the full schema array (loaded once and cached).
 *  - `is_known()`      — whether a key is in the schema.
 *  - `descriptor()`    — the per-key descriptor (or null).
 *  - `can_import()`    — whether a key may be written by bulk-import flows
 *                        (Config::import). High-impact keys (java executable
 *                        paths, the active-extensions array, cache-engine
 *                        switches) are marked `no_import => true` and are
 *                        rejected here even though they are otherwise
 *                        legitimate keys.
 *  - `coerce()`        — type-coerce a raw value to its declared type
 *                        (boolean/integer/string/array). Anything outside
 *                        the declared type is reduced to the type's safe
 *                        default; this stops object payloads (POP gadgets)
 *                        and PHP-source strings from landing in toggles
 *                        and counts.
 *
 * `Config::set()` is intentionally NOT gated by this schema. Internal
 * callers (Mobile_Base, Extensions_Util, etc.) write keys computed at
 * runtime from per-engine slugs that aren't reflected in the static
 * schema; making `set()` strict would break extension activation. The
 * correct gate sits at the user-input boundary — `Config::import()`,
 * `read_request()`, the dbcluster-save handler, and the overloaded
 * toggle endpoints — where the trust changes.
 *
 * @since X.X.X
 */
class ConfigKeysSchema {
	/**
	 * Cached schema array. Loaded lazily from `ConfigKeys.php` on first
	 * access and reused for the rest of the request.
	 *
	 * @var array<string,array>|null
	 */
	private static $cache = null;

	/**
	 * Set of state keys writable by `w3tc_default_config_state` /
	 * `w3tc_default_config_state_master`. These are persisted into the
	 * `w3tc_state` option (not the main W3TC config) and are written by
	 * dismissable-notice / wizard handlers that previously accepted any
	 * key name from `$_GET`. Lock the set to known operational toggles.
	 *
	 * @var string[]
	 */
	private static $state_key_allowlist = array(
		'common.hide_note_no_general_settings_modified',
		'common.hide_note_php_is_old',
		'common.show_note_setup_guide_on_settings_save',
		'common.support_us',
		'license.community_terms',
		'license.next_check',
		'license.status',
		'license.terms',
		'minify.show_note.need_flush',
		'minify.show_note.need_empty_cache_dir',
		'objectcache.show_note.flush_needed',
		'pagecache.show_note.flush_needed',
		'common.install',
		'common.install_version',
		'license.has_pro_features',
		'license.expires',
		'extension.swarmify.hide_note_swarmify_active',
		'extension.amp.hide_note_amp_active',
		'common.show_note.activated',
		'common.show_note.cleanup_needed',
		'common.show_note.notes_disabled',
		'license.expiration_date',
		'license.last_check',
	);

	/**
	 * Returns the full configuration-key schema.
	 *
	 * Loaded from `ConfigKeys.php` (which defines a flat `$keys` array)
	 * once per request. The result is identical to the legacy
	 * `include W3TC_DIR . '/ConfigKeys.php'; // defines $keys` pattern
	 * used in `read_request()` and elsewhere — callers can replace
	 * scattered `include`s with a single call here.
	 *
	 * @since X.X.X
	 *
	 * @return array<string,array>
	 */
	public static function get_keys() {
		if ( null === self::$cache ) {
			$keys = array();
			include __DIR__ . '/ConfigKeys.php';
			self::$cache = \is_array( $keys ) ? $keys : array();
		}

		return self::$cache;
	}

	/**
	 * Returns true if the given key is documented in the schema.
	 *
	 * Compound keys (extension subkeys, expressed as a `[parent, child]`
	 * tuple) are accepted unconditionally because the parent slot in the
	 * schema is `extensions.settings` and the child slot is owned by the
	 * extension. The gate for those lives in the extension's own handler.
	 *
	 * @since X.X.X
	 *
	 * @param string|array $key Single-string or [parent, child] compound key.
	 *
	 * @return bool
	 */
	public static function is_known( $key ) {
		if ( \is_array( $key ) ) {
			return true;
		}

		if ( ! \is_string( $key ) || '' === $key ) {
			return false;
		}

		$keys = self::get_keys();

		return \array_key_exists( $key, $keys );
	}

	/**
	 * Returns the descriptor for a key, or null if not present.
	 *
	 * @since X.X.X
	 *
	 * @param string|array $key Single-string or [parent, child] compound key.
	 *
	 * @return array|null
	 */
	public static function descriptor( $key ) {
		if ( ! self::is_known( $key ) || \is_array( $key ) ) {
			return null;
		}

		$keys = self::get_keys();

		return $keys[ $key ];
	}

	/**
	 * Returns true if the key may be written by a bulk-import flow
	 * (`Config::import`). High-impact keys carry `flags.no_import = true`
	 * and are rejected at the import boundary even when known, because
	 * an exported JSON blob is an attacker-influenceable surface and
	 * these keys can prompt RCE via their downstream code path:
	 *
	 *  - `extensions.active*`    — file-inclusion target.
	 *  - `minify.*.path.java`    — OS command-injection target.
	 *  - `minify.*.path.jar`     — same shell-cmd surface.
	 *  - `*.engine`              — switches code into RCE-prone engines.
	 *
	 * The legitimate way to change these keys is through their dedicated
	 * UI page, where the page-specific validator runs.
	 *
	 * @since X.X.X
	 *
	 * @param string|array $key Single-string or [parent, child] compound key.
	 *
	 * @return bool
	 */
	public static function can_import( $key ) {
		if ( \is_array( $key ) ) {
			// Compound (extension) keys are not part of the bulk-import
			// blob; importers shouldn't reach this branch with one.
			return false;
		}

		$descriptor = self::descriptor( $key );
		if ( null === $descriptor ) {
			return false;
		}

		if ( isset( $descriptor['flags'] ) && \is_array( $descriptor['flags'] ) ) {
			if ( ! empty( $descriptor['flags']['no_import'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Coerces a raw value to the type declared in the descriptor.
	 *
	 * Each type folds invalid payloads down to its safe default, so a
	 * crafted object payload (POP gadget) or PHP-source string cannot
	 * end up in a boolean toggle or integer counter at the storage
	 * layer:
	 *
	 *  - boolean → strict (bool) cast.
	 *  - integer → strict (int) cast.
	 *  - string  → preserved if scalar; non-scalar collapses to ''.
	 *  - array   → preserved if array; non-array collapses to [].
	 *
	 * Unknown / missing types fall through unchanged (the call site has
	 * already validated the key against the allowlist, so this should
	 * never happen with a well-formed schema).
	 *
	 * @since X.X.X
	 *
	 * @param mixed $value      Raw value (request or imported JSON).
	 * @param array $descriptor Descriptor as returned by `descriptor()`.
	 *
	 * @return mixed Coerced value.
	 */
	public static function coerce( $value, $descriptor ) {
		if ( ! \is_array( $descriptor ) || ! isset( $descriptor['type'] ) ) {
			return $value;
		}

		// For every type, fold non-scalar / non-array payloads to the
		// type's safe default BEFORE casting. PHP 8 raises a TypeError
		// when casting `(int) new SomeClass()` if the class lacks
		// `__toString`, so an attacker-shaped object payload would
		// otherwise become a fatal instead of a silent drop.
		$is_object = \is_object( $value );

		switch ( $descriptor['type'] ) {
			case 'boolean':
				return $is_object ? false : (bool) $value;

			case 'integer':
				if ( $is_object || \is_array( $value ) ) {
					return 0;
				}
				return (int) $value;

			case 'string':
				return \is_scalar( $value ) ? (string) $value : '';

			case 'array':
				return \is_array( $value ) ? $value : array();
		}

		return $value;
	}

	/**
	 * Returns true if a key is on the allowlist for the
	 * `w3tc_default_config_state` / `_state_master` handlers.
	 *
	 * @since X.X.X
	 *
	 * @param string $key Candidate state key.
	 *
	 * @return bool
	 */
	public static function is_known_state_key( $key ) {
		if ( ! \is_string( $key ) || '' === $key ) {
			return false;
		}

		return \in_array( $key, self::$state_key_allowlist, true );
	}

	/**
	 * Returns the state-key allowlist (read-only).
	 *
	 * Exposed for tests and for the few admin views that want to render
	 * a list of state toggles.
	 *
	 * @since X.X.X
	 *
	 * @return string[]
	 */
	public static function state_key_allowlist() {
		return self::$state_key_allowlist;
	}
}
