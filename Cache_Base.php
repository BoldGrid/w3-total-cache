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
	 * @param array $config Config.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->_blog_id          = $config['blog_id'];
		$this->_use_expired_data = isset( $config['use_expired_data'] ) ? $config['use_expired_data'] : false;
		$this->_module           = isset( $config['module'] ) ? $config['module'] : 'default';
		$this->_host             = isset( $config['host'] ) ? $config['host'] : '';
		$this->_instance_id      = isset( $config['instance_id'] ) ? $config['instance_id'] : 0;
	}
	/**
	 * Adds data
	 *
	 * @abstract
	 *
	 * @param string  $key    Key.
	 * @param mixed   $data   Data.
	 * @param integer $expire Time to expire.
	 * @param string  $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function add( $key, &$data, $expire = 0, $group = '' ) {
		return false;
	}

	/**
	 * Sets data
	 *
	 * @abstract
	 *
	 * @param string  $key    Key.
	 * @param mixed   $data   Data.
	 * @param integer $expire Time to expire.
	 * @param string  $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function set( $key, $data, $expire = 0, $group = '' ) {
		return false;
	}

	/**
	 * Returns data
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return mixed
	 */
	public function get( $key, $group = '' ) {
		list( $data, $has_old ) = $this->get_with_old( $key, $group );
		return $data;
	}

	/**
	 * Return primary data and if old exists
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return array|mixed
	 */
	public function get_with_old( $key, $group = '' ) {
		return array( null, false );
	}

	/**
	 * Checks if entry exists
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return boolean true if exists, false otherwise
	 */
	public function exists( $key, $group = '' ) {
		list( $data, $has_old ) = $this->get_with_old( $key, $group );
		return ! empty( $data ) && ! $has_old;
	}

	/**
	 * Alias for get for minify cache
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return mixed
	 */
	public function fetch( $key, $group = '' ) {
		return $this->get( $key, $group = '' );
	}

	/**
	 * Replaces data
	 *
	 * @abstract
	 *
	 * @param string  $key    Key.
	 * @param mixed   $data   Data.
	 * @param integer $expire Time to expire.
	 * @param string  $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function replace( $key, &$data, $expire = 0, $group = '' ) {
		return false;
	}

	/**
	 * Deletes data
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function delete( $key, $group = '' ) {
		return false;
	}

	/**
	 * Deletes primary data and old data
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param string $group Group.
	 *
	 * @return boolean
	 */
	public function hard_delete( $key, $group = '' ) {
		return false;
	}

	/**
	 * Flushes all data
	 *
	 * @abstract
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function flush( $group = '' ) {
		return false;
	}

	/**
	 * Gets a key extension for "ahead generation" mode.
	 * Used by AlwaysCached functionality to regenerate content
	 *
	 * @abstract
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_ahead_generation_extension( $group ) {
		return array();
	}

	/**
	 * Flushes group with before condition
	 *
	 * @abstract
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 * @param array  $extension Used to set a condition what version to flush.
	 *
	 * @return boolean
	 */
	public function flush_group_after_ahead_generation( $group, $extension ) {
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
	 * @param unknown $group Group.
	 *
	 * @return string
	 */
	protected function _get_key_version_key( $group = '' ) {
		return sprintf(
			'w3tc_%d_%d_%s_%s_key_version',
			$this->_instance_id,
			$this->_blog_id,
			$this->_module,
			$group
		);
	}

	/**
	 * Constructs item key
	 *
	 * @abstract
	 *
	 * @param unknown $name Name.
	 *
	 * @return string
	 */
	public function get_item_key( $name ) {
		return sprintf(
			'w3tc_%d_%s_%d_%s_%s',
			$this->_instance_id,
			$this->_host,
			$this->_blog_id,
			$this->_module,
			$name
		);
	}

	/**
	 * Use key as a counter and add integer value to it
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param int    $value Value.
	 *
	 * @return bool
	 */
	public function counter_add( $key, $value ) {
		return false;
	}

	/**
	 * Use key as a counter and add integer value to it
	 *
	 * @abstract
	 *
	 * @param string $key   Key.
	 * @param int    $value Value.
	 *
	 * @return bool
	 */
	public function counter_set( $key, $value ) {
		return false;
	}

	/**
	 * Get counter's value
	 *
	 * @abstract
	 *
	 * @param string $key Key.
	 *
	 * @return bool
	 */
	public function counter_get( $key ) {
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
	 *      their classes in.
	 *   3. Walk the decoded value recursively and, if any
	 *      `__PHP_Incomplete_Class` survived the filter, signal a cache
	 *      miss (return false) so the caller regenerates the entry instead
	 *      of crashing on it.
	 *
	 * @since X.X.X
	 *
	 * @param string|false|null $data Serialized bytes from the storage backend.
	 *
	 * @return mixed The unserialized value, or false to signal a cache miss
	 *               (either real unserialize failure or an untrusted object).
	 */
	protected function _unserialize( $data ) {
		if ( ! is_string( $data ) || '' === $data ) {
			return false;
		}

		$allowed = self::_get_allowed_classes();
		$value   = @unserialize( $data, array( 'allowed_classes' => $allowed ) );

		if ( false === $value ) {
			return false;
		}

		if ( self::_contains_incomplete_class( $value ) ) {
			return false;
		}

		return $value;
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
	 * @since X.X.X
	 *
	 * @return array|true Class names allowed during unserialize, or true to allow all.
	 */
	private static function _get_allowed_classes() {
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
		 * @since X.X.X
		 *
		 * @param array $allowed Class names allowed during unserialize.
		 */
		$allowed = apply_filters( 'w3tc_cache_allowed_classes', $defaults );

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
	 * Iterates object properties via `(array) $value` rather than
	 * `get_object_vars()` so private/protected properties on filter-
	 * allowed wrapper classes are walked too — otherwise a disallowed
	 * object hidden in a non-public property of an allowed class would
	 * slip through. The `instanceof __PHP_Incomplete_Class` check above
	 * runs before this cast, so we never (array)-cast a stub.
	 *
	 * @since X.X.X
	 *
	 * @param mixed $value Decoded payload.
	 * @param int   $depth Recursion depth guard.
	 *
	 * @return bool
	 */
	private static function _contains_incomplete_class( $value, $depth = 0 ) {
		if ( $depth > 128 ) {
			// Fail closed: treat over-deep payloads as if a stub was found
			// so the caller drops the entry instead of returning an
			// un-inspected subtree.
			return true;
		}

		if ( $value instanceof \__PHP_Incomplete_Class ) {
			return true;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::_contains_incomplete_class( $item, $depth + 1 ) ) {
					return true;
				}
			}
			return false;
		}

		if ( is_object( $value ) ) {
			foreach ( (array) $value as $item ) {
				if ( self::_contains_incomplete_class( $item, $depth + 1 ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
