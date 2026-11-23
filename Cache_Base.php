<?php
/**
 * File: Cache_Base.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Base
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cache_Base {
	/**
	 * Blog id
	 *
	 * @var integer
	 */
	protected $_blog_id = 0;

	/**
	 * To separate the caching for different modules
	 *
	 * @var string
	 */
	protected $_module = '';

	/**
	 * Host
	 *
	 * @var string
	 */
	protected $_host = '';

	/**
	 * Host
	 *
	 * @var int
	 */
	protected $_instance_id = 0;

	/**
	 * If we are going to return expired data when some other process
	 * is working on new data calculation
	 *
	 * @var boolean
	 */
	protected $_use_expired_data = false;

	/**
	 * Constructor
	 *
	 * @param array $w3tc_config Config.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$this->_blog_id          = $w3tc_config['blog_id'];
		$this->_use_expired_data = isset( $w3tc_config['use_expired_data'] ) ? $w3tc_config['use_expired_data'] : false;
		$this->_module           = isset( $w3tc_config['module'] ) ? $w3tc_config['module'] : 'default';
		$this->_host             = isset( $w3tc_config['host'] ) ? $w3tc_config['host'] : '';
		$this->_instance_id      = isset( $w3tc_config['instance_id'] ) ? $w3tc_config['instance_id'] : 0;
	}
	/**
	 * Adds data
	 *
	 * @abstract
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_data   Data.
	 * @param integer $expire Time to expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function add( $w3tc_key, &$w3tc_data, $expire = 0, $w3tc_group = '' ) {
		return false;
	}

	/**
	 * Sets data
	 *
	 * @abstract
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_data   Data.
	 * @param integer $expire Time to expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function set( $w3tc_key, $w3tc_data, $expire = 0, $w3tc_group = '' ) {
		return false;
	}

	/**
	 * Returns data
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return mixed
	 */
	public function get( $w3tc_key, $w3tc_group = '' ) {
		list( $w3tc_data, $has_old ) = $this->get_with_old( $w3tc_key, $w3tc_group );
		return $w3tc_data;
	}

	/**
	 * Return primary data and if old exists
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return array|mixed
	 */
	public function get_with_old( $w3tc_key, $w3tc_group = '' ) {
		return array( null, false );
	}

	/**
	 * Checks if entry exists
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return boolean true if exists, false otherwise
	 */
	public function exists( $w3tc_key, $w3tc_group = '' ) {
		list( $w3tc_data, $has_old ) = $this->get_with_old( $w3tc_key, $w3tc_group );
		return ! empty( $w3tc_data ) && ! $has_old;
	}

	/**
	 * Alias for get for minify cache
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return mixed
	 */
	public function fetch( $w3tc_key, $w3tc_group = '' ) {
		return $this->get( $w3tc_key, $w3tc_group = '' );
	}

	/**
	 * Replaces data
	 *
	 * @abstract
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_data   Data.
	 * @param integer $expire Time to expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function replace( $w3tc_key, &$w3tc_data, $expire = 0, $w3tc_group = '' ) {
		return false;
	}

	/**
	 * Deletes data
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function delete( $w3tc_key, $w3tc_group = '' ) {
		return false;
	}

	/**
	 * Deletes primary data and old data
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Group.
	 *
	 * @return boolean
	 */
	public function hard_delete( $w3tc_key, $w3tc_group = '' ) {
		return false;
	}

	/**
	 * Flushes all data
	 *
	 * @abstract
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function flush( $w3tc_group = '' ) {
		return false;
	}

	/**
	 * Gets a key extension for "ahead generation" mode.
	 * Used by AlwaysCached functionality to regenerate content
	 *
	 * @abstract
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_ahead_generation_extension( $w3tc_group ) {
		return array();
	}

	/**
	 * Flushes group with before condition
	 *
	 * @abstract
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @param array  $w3tc_extension Used to set a condition what version to flush.
	 *
	 * @return boolean
	 */
	public function flush_group_after_ahead_generation( $w3tc_group, $w3tc_extension ) {
		return false;
	}

	/**
	 * Checks if engine can function properly in this environment
	 *
	 * @abstract
	 *
	 * @return bool
	 */
	public function available() {
		return true;
	}

	/**
	 * Constructs key version key
	 *
	 * @abstract
	 *
	 * @param unknown $w3tc_group Group.
	 *
	 * @return string
	 */
	protected function _get_key_version_key( $w3tc_group = '' ) {
		return sprintf(
			'w3tc_%d_%d_%s_%s_key_version',
			$this->_instance_id,
			$this->_blog_id,
			$this->_module,
			$w3tc_group
		);
	}

	/**
	 * Constructs item key
	 *
	 * @abstract
	 *
	 * @param unknown $w3tc_name Name.
	 *
	 * @return string
	 */
	public function get_item_key( $w3tc_name ) {
		return sprintf(
			'w3tc_%d_%s_%d_%s_%s',
			$this->_instance_id,
			$this->_host,
			$this->_blog_id,
			$this->_module,
			$w3tc_name
		);
	}

	/**
	 * Use key as a counter and add integer value to it
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param int    $w3tc_value Value.
	 *
	 * @return bool
	 */
	public function counter_add( $w3tc_key, $w3tc_value ) {
		return false;
	}

	/**
	 * Use key as a counter and add integer value to it
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key   Key.
	 * @param int    $w3tc_value Value.
	 *
	 * @return bool
	 */
	public function counter_set( $w3tc_key, $w3tc_value ) {
		return false;
	}

	/**
	 * Get counter's value
	 *
	 * @abstract
	 *
	 * @param string $w3tc_key Key.
	 *
	 * @return bool
	 */
	public function counter_get( $w3tc_key ) {
		return false;
	}

	/**
	 * Safely unserialize a cache payload.
	 *
	 * The deserialization hardening introduced in PR #1319 (ENG7-3003) passed
	 * `allowed_classes => false` to every backend read so the vendored Guzzle
	 * FileCookieJar __destruct gadget could not be reached. That hardening is
	 * preserved here — by default the allowlist contains only the WordPress
	 * core data classes (`stdClass`, `WP_Post`, `WP_Term`, `WP_User`,
	 * `WP_Comment`, `WP_Site`, `WP_Network`, `WP_Error`), which does not
	 * include FileCookieJar or any other vendored gadget, so the gadget
	 * remains unreachable — but rather than handing callers an unusable
	 * `__PHP_Incomplete_Class` (which fatals on the first property/method/
	 * array access in PHP 8), we:
	 *
	 *   1. Allow a curated set of WordPress core data classes by default.
	 *   2. Expose the `w3tc_cache_allowed_classes` filter so plugins that
	 *      store their own objects through the W3TC-backed object cache
	 *      (Advanced Custom Fields, WooCommerce sessions, etc.) can opt
	 *      their classes in. The filter is passed a context array
	 *      (`module`/`group`/`key`) so plugins can scope opt-ins to the
	 *      cache area that actually stores their objects instead of
	 *      broadening the allowlist across every W3TC-backed read.
	 *   3. Walk the decoded value recursively and, if any
	 *      `__PHP_Incomplete_Class` survived the filter, signal a cache
	 *      miss (return false) so the caller regenerates the entry instead
	 *      of crashing on it.
	 *
	 * @since 2.10.0
	 *
	 * @param string|false|null $w3tc_data    Serialized bytes from the storage backend.
	 * @param array             $context Optional. Cache-area context forwarded to the
	 *                                   `w3tc_cache_allowed_classes` filter. Keys:
	 *                                   `module` (defaults to the backend's module),
	 *                                   `group`, `key`.
	 *
	 * @return mixed The unserialized value, or false to signal a cache miss
	 *               (either real unserialize failure or an untrusted object).
	 */
	protected function _unserialize( $w3tc_data, $context = array() ) {
		if ( ! is_string( $w3tc_data ) || '' === $w3tc_data ) {
			return false;
		}

		$context = array_merge(
			array(
				'module' => $this->_module,
				'group'  => '',
				'key'    => '',
			),
			is_array( $context ) ? $context : array()
		);

		$allowed    = self::_get_allowed_classes( $context );
		$w3tc_value = @unserialize( $w3tc_data, array( 'allowed_classes' => $allowed ) );

		if ( false === $w3tc_value ) {
			return false;
		}

		if ( self::_contains_incomplete_class( $w3tc_value ) ) {
			return false;
		}

		return $w3tc_value;
	}

	/**
	 * Resolve the list of classes permitted when unserializing cache payloads.
	 *
	 * Defaults to the WordPress core data classes that core itself caches
	 * via `wp_cache_set()` (WP_Post / WP_Term / WP_User / WP_Comment /
	 * WP_Site / WP_Network / WP_Error, plus stdClass for the many places
	 * core and plugins cache plain `(object)` casts).
	 *
	 * Plugins extend the list with the `w3tc_cache_allowed_classes` filter.
	 * Returning `true` permits every class — equivalent to pre-PR #1319
	 * behavior, which re-opens the FileCookieJar deserialization gadget
	 * surface. Don't return `true` unless you have already removed every
	 * untrusted writer from your cache backend.
	 *
	 * @since 2.10.0
	 *
	 * @param array $context Cache-area context (module/group/key) forwarded to filters.
	 *
	 * @return array|true Class names allowed during unserialize, or true to allow all.
	 */
	private static function _get_allowed_classes( $context ) {
		$defaults = array(
			'stdClass',
			'WP_Post',
			'WP_Term',
			'WP_User',
			'WP_Comment',
			'WP_Site',
			'WP_Network',
			'WP_Error',
		);

		if ( ! function_exists( 'apply_filters' ) ) {
			return $defaults;
		}

		/**
		 * Filter the list of classes permitted when unserializing cache payloads.
		 *
		 * Plugins that store PHP objects in the WordPress object cache (or any
		 * W3TC-backed cache) should register their classes here so values
		 * survive the round-trip. Classes not on this list are decoded as
		 * `__PHP_Incomplete_Class` and cause the entry to be treated as a
		 * cache miss.
		 *
		 * The `$context` array identifies which cache area is reading the
		 * payload so the opt-in can be scoped narrowly:
		 *   - `module` — backend module identifier (e.g. `object`, `pgcache`,
		 *                `dbcache`, `minify`, `fragment`).
		 *   - `group`  — cache group passed by the caller (e.g. `posts`,
		 *                `transient`).
		 *   - `key`    — caller-provided cache key, before backend key
		 *                composition.
		 *
		 * Returning `true` permits every class — equivalent to pre-PR #1319
		 * behavior, which re-opens the FileCookieJar deserialization gadget
		 * surface. Returning a non-array, non-`true` value falls back to the
		 * default allowlist.
		 *
		 * @since 2.10.0
		 *
		 * @param array $allowed Class names allowed during unserialize.
		 * @param array $context Cache-area context with `module`, `group`, `key`.
		 */
		$allowed = apply_filters( 'w3tc_cache_allowed_classes', $defaults, $context );

		if ( true === $allowed ) {
			return true;
		}

		return is_array( $allowed ) ? $allowed : $defaults;
	}

	/**
	 * Walk a decoded value and return true if any `__PHP_Incomplete_Class`
	 * instance is reachable from it.
	 *
	 * An incomplete class is what `unserialize()` returns for any object
	 * whose class is not on the allowlist; touching one (property access,
	 * method call, array-cast) is a fatal in PHP 8. Detecting one anywhere
	 * in the decoded tree lets the caller drop the entry as a cache miss.
	 *
	 * Fails closed on depth overflow: if the value is more deeply nested
	 * than the recursion limit we return true (treat as if a stub was
	 * found), so the caller drops the entry. The cost of a false positive
	 * is a cache-miss + regen; the cost of a false negative is a PHP 8
	 * fatal when downstream code touches the un-inspected subtree.
	 *
	 * Cycles are handled via `SplObjectStorage`: each visited object is
	 * recorded and re-visits short-circuit `false` (no new stub on this
	 * branch) so legitimate self-referential graphs — `WP_User` carrying a
	 * `WP_User_Meta` back-reference, ORM entity caches that link parent ↔
	 * child, etc. — round-trip instead of becoming permanent cache misses.
	 * Without this, the depth guard would tip into the fail-closed branch
	 * on every read of a cyclic value.
	 *
	 * Iterates object properties via `(array) $w3tc_value` rather than
	 * `get_object_vars()` so private/protected properties on filter-
	 * allowed wrapper classes are walked too — otherwise a disallowed
	 * object hidden in a non-public property of an allowed class would
	 * slip through. The `instanceof __PHP_Incomplete_Class` check above
	 * runs before this cast, so we never (array)-cast a stub.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed                  $w3tc_value   Decoded payload.
	 * @param int                    $depth   Recursion depth guard.
	 * @param \SplObjectStorage|null $visited Set of objects already inspected on this walk.
	 *
	 * @return bool
	 */
	private static function _contains_incomplete_class( $w3tc_value, $depth = 0, $visited = null ) {
		if ( $depth > 128 ) {
			// Fail closed: treat over-deep payloads as if a stub was found
			// so the caller drops the entry instead of returning an
			// un-inspected subtree.
			return true;
		}

		if ( $w3tc_value instanceof \__PHP_Incomplete_Class ) {
			return true;
		}

		if ( is_object( $w3tc_value ) ) {
			if ( null === $visited ) {
				$visited = new \SplObjectStorage();
			}
			if ( $visited->offsetExists( $w3tc_value ) ) {
				// Already inspected on this walk — short-circuit so the
				// depth guard doesn't mistake a legitimate cycle for an
				// over-deep payload.
				return false;
			}
			$visited->offsetSet( $w3tc_value, null );

			foreach ( (array) $w3tc_value as $w3tc_item ) {
				if ( self::_contains_incomplete_class( $w3tc_item, $depth + 1, $visited ) ) {
					return true;
				}
			}
			return false;
		}

		if ( is_array( $w3tc_value ) ) {
			foreach ( $w3tc_value as $w3tc_item ) {
				if ( self::_contains_incomplete_class( $w3tc_item, $depth + 1, $visited ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
