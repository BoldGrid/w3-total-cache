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
 *  - `get_keys()`         — the full schema array (loaded once and cached).
 *  - `is_known()`         — whether a key is in the schema.
 *  - `descriptor()`       — the per-key descriptor (or null).
 *  - `can_import()`       — whether a key may be written by bulk-import flows
 *                           (Config::import). High-impact keys (java executable
 *                           paths, the active-extensions array, cache-engine
 *                           switches) are marked `no_import => true` and are
 *                           rejected here even though they are otherwise
 *                           legitimate keys.
 *  - `coerce()`           — type-coerce a raw value to its declared type
 *                           (boolean/integer/string/array). Anything outside
 *                           the declared type is reduced to the type's safe
 *                           default; this stops objects and PHP-source
 *                           strings from landing in toggles and counts.
 *  - `dedicated_page()`   — admin page slug that may write the key via
 *                           `read_request()` (from `flags.dedicated_page` /
 *                           compound leaf map), or null when unconstrained.
 *  - `normalize_key_string()` — dotted form of string or compound keys.
 *  - `is_no_import()`     — whether the key carries `flags.no_import`.
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
		/**
		 * Compound keys (`[parent, child]` from `___` HTTP names) are only
		 * admitted when the parent slot is a registered extension id. The
		 * previous unconditional `return true` let arbitrary compound
		 * namespaces bypass the schema allowlist in `read_request()`.
		 * Extensions that need a descriptor without a registration row can
		 * still supply one via `w3tc_config_key_descriptor`.
		 */
		if ( \is_array( $w3tc_key ) ) {
			if ( \count( $w3tc_key ) < 2 || ! \is_string( $w3tc_key[0] ) || '' === $w3tc_key[0] ) {
				return false;
			}

			return self::is_registered_extension_id( $w3tc_key[0] );
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
	 *  - `plugin.type`           — Pro entitlement; license path only
	 *                              (`Licensing_Plugin_Admin` → `Config::set`).
	 *
	 * The legitimate way to change these keys is through their dedicated
	 * UI page (or the license path for `plugin.type`), where the page-
	 * specific validator runs.
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

	/**
	 * Whether `$note_id` may be written under the `notes.` config prefix
	 * by {@see Generic_AdminActions_Default::w3tc_default_hide_note()}.
	 *
	 * Built-in IDs are listed below; extensions may append via the
	 * `w3tc_known_note_ids` filter. Unknown IDs are refused so request
	 * parameters cannot invent arbitrary `notes.*` keys.
	 *
	 * @since X.X.X
	 *
	 * @param string $note_id Bare note identifier (no `notes.` prefix).
	 *
	 * @return bool
	 */
	public static function is_known_note_id( $note_id ) {
		if ( ! \is_string( $note_id ) || '' === $note_id ) {
			return false;
		}

		if ( 1 !== \preg_match( '/^[a-z][a-z0-9_]*$/', $note_id ) ) {
			return false;
		}

		$allowed = array(
			'cloudflare_plugin',
			'need_empty_fragmentcache',
		);

		/**
		 * Filter the allowlist of dismissable `notes.*` identifiers.
		 *
		 * @since X.X.X
		 *
		 * @param string[] $allowed Known note IDs.
		 */
		$allowed = \apply_filters( 'w3tc_known_note_ids', $allowed );
		if ( ! \is_array( $allowed ) ) {
			return false;
		}

		return \in_array( $note_id, $allowed, true );
	}

	/**
	 * Cached map of dotted-key prefix → dedicated admin page slug,
	 * built once from every ConfigKeys entry that declares
	 * `flags.dedicated_page`.
	 *
	 * @var array<string,string>|null
	 */
	private static $dedicated_page_index = null;

	/**
	 * Normalize a config key to a dotted string for page-boundary lookup.
	 *
	 * Compound keys `array( 'cloudflare', 'key' )` become `cloudflare.key`
	 * so leaf ownership metadata and the schema index share one shape.
	 * Nested child segments that already contain dots (e.g. `accept.roles`)
	 * are preserved as-is after the parent.
	 *
	 * @since 2.10.6
	 *
	 * @param string|array $w3tc_key Single-string or compound key.
	 *
	 * @return string Empty string when the key cannot be normalized.
	 */
	public static function normalize_key_string( $w3tc_key ) {
		if ( \is_string( $w3tc_key ) ) {
			return $w3tc_key;
		}

		if ( ! \is_array( $w3tc_key ) || empty( $w3tc_key ) ) {
			return '';
		}

		$parts = array();
		foreach ( $w3tc_key as $part ) {
			if ( ! \is_scalar( $part ) ) {
				return '';
			}
			$parts[] = (string) $part;
		}

		return \implode( '.', $parts );
	}

	/**
	 * Returns the admin page slug that may write `$w3tc_key`, or null when
	 * no page-boundary constraint applies.
	 *
	 * Resolution order:
	 *  1. `$descriptor['flags']['dedicated_page']` (filter-supplied or
	 *     caller-provided).
	 *  2. Exact / longest-prefix match against ConfigKeys entries that
	 *     declare `dedicated_page`.
	 *  3. Compound-extension leaf map for secrets that live outside the
	 *     flat schema (Cloudflare / New Relic API credentials).
	 *
	 * @since 2.10.6
	 *
	 * @param string|array $w3tc_key        Single-string or compound key.
	 * @param array|null   $w3tc_descriptor Optional descriptor already
	 *                                      resolved by the caller (may
	 *                                      include filter overrides).
	 *
	 * @return string|null
	 */
	public static function dedicated_page( $w3tc_key, $w3tc_descriptor = null ) {
		if ( \is_array( $w3tc_descriptor )
			&& isset( $w3tc_descriptor['flags'] )
			&& \is_array( $w3tc_descriptor['flags'] )
			&& isset( $w3tc_descriptor['flags']['dedicated_page'] )
			&& \is_string( $w3tc_descriptor['flags']['dedicated_page'] )
			&& '' !== $w3tc_descriptor['flags']['dedicated_page']
		) {
			return $w3tc_descriptor['flags']['dedicated_page'];
		}

		$normalized = self::normalize_key_string( $w3tc_key );
		if ( '' === $normalized ) {
			return null;
		}

		$index = self::dedicated_page_index();
		if ( isset( $index[ $normalized ] ) ) {
			return $index[ $normalized ];
		}

		/**
		 * Longest-prefix match so a future engine can mark only the
		 * namespace root (or any parent key) and still cover leaves.
		 * Exact entries above win when present.
		 */
		$best_prefix = '';
		$best_page   = null;
		foreach ( $index as $prefix => $page ) {
			if ( 0 !== \strpos( $normalized, $prefix ) ) {
				continue;
			}
			/**
			 * Require a namespace boundary: either an exact match (already
			 * handled) or `prefix.` as a true parent of the candidate.
			 */
			$prefix_len = \strlen( $prefix );
			if ( $prefix_len > \strlen( $best_prefix )
				&& (
					$normalized === $prefix
					|| (
						$prefix_len < \strlen( $normalized )
						&& '.' === $normalized[ $prefix_len ]
					)
					|| (
						'.' === \substr( $prefix, -1 )
						&& 0 === \strpos( $normalized, $prefix )
					)
				)
			) {
				$best_prefix = $prefix;
				$best_page   = $page;
			}
		}
		if ( null !== $best_page ) {
			return $best_page;
		}

		$compound = self::compound_dedicated_pages();
		if ( isset( $compound[ $normalized ] ) ) {
			return $compound[ $normalized ];
		}

		return null;
	}

	/**
	 * Returns true when the key carries `flags.no_import` in the static
	 * schema (used by `read_request()` to refuse POST writes for
	 * no_import keys that lack a dedicated_page escape hatch).
	 *
	 * @since 2.10.6
	 *
	 * @param string|array $w3tc_key        Config key.
	 * @param array|null   $w3tc_descriptor Optional already-resolved descriptor.
	 *
	 * @return bool
	 */
	public static function is_no_import( $w3tc_key, $w3tc_descriptor = null ) {
		if ( ! \is_array( $w3tc_descriptor ) ) {
			$w3tc_descriptor = self::descriptor( $w3tc_key );
		}
		if ( ! \is_array( $w3tc_descriptor )
			|| ! isset( $w3tc_descriptor['flags'] )
			|| ! \is_array( $w3tc_descriptor['flags'] )
		) {
			return false;
		}

		return ! empty( $w3tc_descriptor['flags']['no_import'] );
	}

	/**
	 * Build / return the dedicated_page index from ConfigKeys.
	 *
	 * @since 2.10.6
	 *
	 * @return array<string,string>
	 */
	private static function dedicated_page_index() {
		if ( null !== self::$dedicated_page_index ) {
			return self::$dedicated_page_index;
		}

		self::$dedicated_page_index = array();
		foreach ( self::get_keys() as $key => $descriptor ) {
			if ( ! \is_string( $key ) || ! \is_array( $descriptor ) ) {
				continue;
			}
			if ( empty( $descriptor['flags']['dedicated_page'] )
				|| ! \is_string( $descriptor['flags']['dedicated_page'] )
			) {
				continue;
			}
			self::$dedicated_page_index[ $key ] = $descriptor['flags']['dedicated_page'];
		}

		return self::$dedicated_page_index;
	}

	/**
	 * Compound-extension leaves that are not enumerated in the flat
	 * ConfigKeys schema but still need page-boundary discipline. Kept
	 * here (not in ConfigKeys.php) so Export/Import round-trips stay
	 * unchanged while `read_request()` can still refuse cross-page
	 * credential writes via `cloudflare___key` / `newrelic___api_key`.
	 *
	 * ENG7-4278 may move more extension-owned keys onto this map (or
	 * onto filter-supplied descriptors).
	 *
	 * @since 2.10.6
	 *
	 * @return array<string,string>
	 */
	private static function compound_dedicated_pages() {
		return array(
			'cloudflare.email'   => 'w3tc_extensions',
			'cloudflare.key'     => 'w3tc_extensions',
			'cloudflare.zone_id' => 'w3tc_extensions',
			'newrelic.api_key'  => 'w3tc_monitoring',
		);
	}

	/**
	 * Returns true when a bulk-import key shape is admissible.
	 *
	 * Export → Import blobs are JSON objects whose keys must be
	 * non-empty strings. Integer keys (JSON array payloads), empty
	 * strings, and PHP array/compound keys are structural failures —
	 * {@see Config::import_from_array()} aborts the whole import when
	 * any such key is present so nothing is partially applied.
	 *
	 * @since 2.10.6
	 *
	 * @param mixed $w3tc_key Candidate import key.
	 *
	 * @return bool
	 */
	public static function is_import_key_shape( $w3tc_key ) {
		return \is_string( $w3tc_key ) && '' !== $w3tc_key;
	}

	/**
	 * Returns true when the descriptor (or key) carries
	 * `flags.directive_string` — i.e. the value feeds server-config
	 * rule generation and must be list-of-scalars (or a scalar string)
	 * before persistence.
	 *
	 * @since 2.10.6
	 *
	 * @param string     $w3tc_key        Config key.
	 * @param array|null $w3tc_descriptor Optional pre-loaded descriptor.
	 *
	 * @return bool
	 */
	public static function is_directive_string_key( $w3tc_key, $w3tc_descriptor = null ) {
		if ( null === $w3tc_descriptor ) {
			$w3tc_descriptor = self::descriptor( $w3tc_key );
		}

		return \is_array( $w3tc_descriptor )
			&& isset( $w3tc_descriptor['flags'] )
			&& \is_array( $w3tc_descriptor['flags'] )
			&& ! empty( $w3tc_descriptor['flags']['directive_string'] );
	}

	/**
	 * Validates (and for directive-string arrays, normalises) a value
	 * that will be written by bulk import.
	 *
	 * Contract:
	 *  - Non-directive keys: type-coerce only; never a hard failure.
	 *  - Directive-string **string** keys: coerce; Config::set strips
	 *    forbidden bytes afterwards.
	 *  - Directive-string **array** keys: must be a list (or coerce to
	 *    one). Nested arrays/objects inside the list are a structural
	 *    failure — those shapes cannot be safely reduced to a
	 *    RewriteCond / location alternation without guessing, so the
	 *    import aborts rather than applying a partial bad value.
	 *
	 * @since 2.10.6
	 *
	 * @param string     $w3tc_key        Config key.
	 * @param mixed      $w3tc_value      Raw imported value.
	 * @param array|null $w3tc_descriptor Schema descriptor (or null).
	 *
	 * @return array{ok:bool,value:mixed,error:?string}
	 */
	public static function validate_import_value( $w3tc_key, $w3tc_value, $w3tc_descriptor = null ) {
		if ( null === $w3tc_descriptor ) {
			$w3tc_descriptor = self::descriptor( $w3tc_key );
		}

		$coerced = self::coerce( $w3tc_value, $w3tc_descriptor );

		if ( ! self::is_directive_string_key( $w3tc_key, $w3tc_descriptor ) ) {
			return array(
				'ok'    => true,
				'value' => $coerced,
				'error' => null,
			);
		}

		if ( \is_array( $coerced ) ) {
			foreach ( $coerced as $entry ) {
				if ( \is_array( $entry ) || \is_object( $entry ) ) {
					return array(
						'ok'    => false,
						'value' => null,
						'error' => 'nested_rule_generation_value',
					);
				}
			}
		}

		return array(
			'ok'    => true,
			'value' => $coerced,
			'error' => null,
		);
	}
}
