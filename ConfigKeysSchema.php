<?php
/**
 * File: ConfigKeysSchema.php
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

namespace W3TC;

/**
 * Class ConfigKeysSchema
 *
 * Read-only accessor for the configuration-key schema in `ConfigKeys.php`.
 *
 * The schema file itself is a flat `$w3tc_keys` array — every legitimate W3TC
 * configuration key, with its declared type, default value, and (since the
 * import-allowlist pass) optional `flags` map. Treat the file as the
 * source of truth: any inbound write that originates from user input MUST
 * validate the key against this schema before reaching `Config::set()`.
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
 *                        default; this stops objects and PHP-source
 *                        strings from landing in toggles and counts.
 *
 * `Config::set()` is intentionally NOT gated by this schema. Internal
 * callers (Mobile_Base, Extensions_Util, etc.) write keys computed at
 * runtime from per-engine slugs that aren't reflected in the static
 * schema; making `set()` strict would break extension activation. The
 * correct gate sits at the user-input boundary — `Config::import()`,
 * `read_request()`, the dbcluster-save handler, and the overloaded
 * toggle endpoints — where the trust changes.
 *
 * @since 2.10.0
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
	 * Returns the full configuration-key schema.
	 *
	 * Loaded from `ConfigKeys.php` (which defines a flat `$w3tc_keys` array)
	 * once per request. The result is identical to the legacy
	 * `include W3TC_DIR . '/ConfigKeys.php'; // defines $w3tc_keys` pattern
	 * used in `read_request()` and elsewhere — callers can replace
	 * scattered `include`s with a single call here.
	 *
	 * @since 2.10.0
	 *
	 * @return array<string,array>
	 */
	public static function get_keys() {
		if ( null === self::$cache ) {
			$w3tc_keys = array();
			include __DIR__ . '/ConfigKeys.php';
			self::$cache = \is_array( $w3tc_keys ) ? $w3tc_keys : array();
		}

		return self::$cache;
	}

	/**
	 * Cached set of registered extension ids (from `w3tc_extensions`
	 * filter source via `Extensions_Util::get_extensions()`). Used by
	 * `is_known()` to admit `extension.<id>.<setting>` keys whose
	 * `<id>` is a registered extension. Loaded lazily on first lookup
	 * and reused for the rest of the request.
	 *
	 * @var array<string,bool>|null
	 */
	private static $extension_ids = null;

	/**
	 * Returns true if the given key is documented in the schema or is
	 * an `extension.<id>.<setting>` key whose `<id>` matches a
	 * registered extension.
	 *
	 * Compound keys (extension subkeys, expressed as a `[parent, child]`
	 * tuple) are accepted unconditionally because the parent slot in the
	 * schema is `extensions.settings` and the child slot is owned by the
	 * extension. The gate for those lives in the extension's own handler.
	 *
	 * `Config::import()` is the primary caller for extension-prefix keys:
	 * extensions store their own settings under `extension.<id>.<setting>`
	 * and the schema cannot statically enumerate them, so without the
	 * prefix branch every active extension's settings would be silently
	 * dropped on Export → Import round-trip. The id branch is gated on
	 * `Extensions_Util::get_extensions()`, itself the canonical filter-
	 * driven source of registered extensions; new extensions that ship
	 * with a `_Plugin_Admin::w3tc_extensions` callback are admitted
	 * automatically.
	 *
	 * @since 2.10.0
	 *
	 * @param string|array $w3tc_key Single-string or [parent, child] compound key.
	 *
	 * @return bool
	 */
	public static function is_known( $w3tc_key ) {
		if ( \is_array( $w3tc_key ) ) {
			return true;
		}

		if ( ! \is_string( $w3tc_key ) || '' === $w3tc_key ) {
			return false;
		}

		$w3tc_keys = self::get_keys();

		if ( \array_key_exists( $w3tc_key, $w3tc_keys ) ) {
			return true;
		}

		/**
		 * Extension-prefix branch: `extension.<id>` (enable flag) and
		 * `extension.<id>.<...>` (per-extension settings).
		 */
		if ( 0 === \strpos( $w3tc_key, 'extension.' ) ) {
			$rest = \substr( $w3tc_key, \strlen( 'extension.' ) );
			if ( '' === $rest ) {
				return false;
			}

			$dot_pos = \strpos( $rest, '.' );
			$id      = false === $dot_pos ? $rest : \substr( $rest, 0, $dot_pos );

			return self::is_registered_extension_id( $id );
		}

		return false;
	}

	/**
	 * Returns true if `$id` is a currently-registered extension id.
	 *
	 * The set is loaded from `Extensions_Util::get_extensions()` once
	 * per request and cached. Pre-bootstrap callers (where `Dispatcher`
	 * or `Extensions_Util` is not yet loaded, or no config is reachable)
	 * see an empty set — the prefix branch in `is_known()` falls
	 * through to "unknown" in that case, which is the same behaviour as
	 * before this widening, so nothing that worked before regresses.
	 *
	 * @since 2.10.0
	 *
	 * @param string $id Candidate extension id.
	 *
	 * @return bool
	 */
	private static function is_registered_extension_id( $id ) {
		if ( ! \is_string( $id ) || '' === $id ) {
			return false;
		}

		if ( null === self::$extension_ids ) {
			self::$extension_ids = array();

			if ( \class_exists( '\W3TC\Dispatcher' ) && \class_exists( '\W3TC\Extensions_Util' ) ) {
				$w3tc_config = Dispatcher::config();
				if ( null !== $w3tc_config ) {
					$all = Extensions_Util::get_extensions( $w3tc_config );
					if ( \is_array( $all ) ) {
						foreach ( $all as $registered_id => $_meta ) {
							if ( \is_string( $registered_id ) && '' !== $registered_id ) {
								self::$extension_ids[ $registered_id ] = true;
							}
						}
					}
				}
			}
		}

		return isset( self::$extension_ids[ $id ] );
	}

	/**
	 * Returns the descriptor for a key, or null if not present.
	 *
	 * @since 2.10.0
	 *
	 * @param string|array $w3tc_key Single-string or [parent, child] compound key.
	 *
	 * @return array|null
	 */
	public static function descriptor( $w3tc_key ) {
		if ( ! self::is_known( $w3tc_key ) || \is_array( $w3tc_key ) ) {
			return null;
		}

		$w3tc_keys = self::get_keys();

		/**
		 * `is_known()` returns true for `extension.<id>...` prefix keys
		 * that are not in the static schema. Return null for those rather
		 * than triggering an "undefined array key" notice; callers (e.g.
		 * `can_import()`, `Config::import()`'s `coerce()` call) already
		 * treat a null descriptor as "no static schema entry — pass the
		 * value through unchanged".
		 */
		return \array_key_exists( $w3tc_key, $w3tc_keys ) ? $w3tc_keys[ $w3tc_key ] : null;
	}

	/**
	 * Returns true if the key may be written by a bulk-import flow
	 * (`Config::import`). High-impact keys carry `flags.no_import = true`
	 * and are rejected at the import boundary even when known, because
	 * an exported JSON blob carries externally-supplied content and
	 * these keys feed code paths that execute or load arbitrary files
	 * at runtime:
	 *
	 *  - `extensions.active*`    — feeds a require_once path.
	 *  - `minify.*.path.java`    — feeds an exec() argument.
	 *  - `minify.*.path.jar`     — feeds the same shell argv.
	 *  - `*.engine`              — switches the runtime to a different engine.
	 *
	 * The legitimate way to change these keys is through their dedicated
	 * UI page, where the page-specific validator runs.
	 *
	 * @since 2.10.0
	 *
	 * @param string|array $w3tc_key Single-string or [parent, child] compound key.
	 *
	 * @return bool
	 */
	public static function can_import( $w3tc_key ) {
		if ( \is_array( $w3tc_key ) ) {
			/**
			 * Compound (extension) keys are not part of the bulk-import
			 * blob; importers shouldn't reach this branch with one.
			 */
			return false;
		}

		/**
		 * An unknown key cannot be imported regardless of descriptor
		 * presence.
		 */
		if ( ! self::is_known( $w3tc_key ) ) {
			return false;
		}

		$w3tc_descriptor = self::descriptor( $w3tc_key );
		if ( null === $w3tc_descriptor ) {
			/**
			 * `is_known()` returned true but no static descriptor exists.
			 * The only path to that combination today is the
			 * `extension.<id>...` prefix branch in `is_known()`, which is
			 * gated on `Extensions_Util::get_extensions()`. Allow these
			 * through so Export → Import round-trips an active extension's
			 * own settings. `Config::import()` already calls `coerce()`
			 * with a null descriptor — that path passes values through
			 * unchanged, so no coerce change is required.
			 */
			return true;
		}

		if ( isset( $w3tc_descriptor['flags'] ) && \is_array( $w3tc_descriptor['flags'] ) ) {
			if ( ! empty( $w3tc_descriptor['flags']['no_import'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Coerces a raw value to the type declared in the descriptor.
	 *
	 * Each type folds invalid values down to its safe default, so an
	 * unexpected object or PHP-source string cannot end up in a
	 * boolean toggle or integer counter at the storage layer:
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
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_value      Raw value (request or imported JSON).
	 * @param array $w3tc_descriptor Descriptor as returned by `descriptor()`.
	 *
	 * @return mixed Coerced value.
	 */
	public static function coerce( $w3tc_value, $w3tc_descriptor ) {
		if ( ! \is_array( $w3tc_descriptor ) || ! isset( $w3tc_descriptor['type'] ) ) {
			return $w3tc_value;
		}

		/**
		 * For every type, fold non-scalar / non-array values to the
		 * type's safe default BEFORE casting. PHP 8 raises a TypeError
		 * when casting `(int) new SomeClass()` if the class lacks
		 * `__toString`, so an unexpected object value would otherwise
		 * become a fatal instead of a silent drop.
		 */
		$is_object = \is_object( $w3tc_value );
		$is_array  = \is_array( $w3tc_value );

		switch ( $w3tc_descriptor['type'] ) {
			case 'boolean':
				/**
				 * Boolean keys accept only scalars. `(bool) ['x'=>1]`
				 * would otherwise be `true`, smuggling a structured
				 * value through a toggle.
				 */
				if ( $is_object || $is_array ) {
					return false;
				}
				return (bool) $w3tc_value;

			case 'integer':
				if ( $is_object || $is_array ) {
					return 0;
				}
				return (int) $w3tc_value;

			case 'string':
				return \is_scalar( $w3tc_value ) ? (string) $w3tc_value : '';

			case 'array':
				return $is_array ? $w3tc_value : array();
		}

		return $w3tc_value;
	}

	/**
	 * Returns true if the given key is admissible to the
	 * `w3tc_default_config_state` / `_state_master` / `_state_note`
	 * handlers.
	 *
	 * The gated handlers receive `key=...&value=...` from URL builders
	 * that the plugin (and its extensions) generate for every
	 * "dismiss this notice" link. Enumerating the literal set drifts
	 * out of sync every time a new notice ships, so the gate is
	 * structural: a key is admissible when it is shaped like a dotted
	 * 2–4-segment config identifier AND contains `hide_note` or
	 * `show_note`. That covers the whole dismissable-notice idiom
	 * without an explicit list, and survives extension authors adding
	 * new notices.
	 *
	 * Non-notice state keys (`license.*`, `common.install*`, etc.)
	 * are intentionally NOT admitted here. Internal code writes those
	 * directly through `Dispatcher::config_state*()->set( … )`; they
	 * never reach this handler, so admitting them would only widen the
	 * attack surface without enabling any legitimate flow.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_key Candidate state key.
	 *
	 * @return bool
	 */
	public static function is_known_state_key( $w3tc_key ) {
		if ( ! \is_string( $w3tc_key ) || '' === $w3tc_key ) {
			return false;
		}

		if ( 1 !== \preg_match( '/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+){1,3}$/', $w3tc_key ) ) {
			return false;
		}

		return false !== \strpos( $w3tc_key, 'hide_note' )
			|| false !== \strpos( $w3tc_key, 'show_note' );
	}
}
