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
}
