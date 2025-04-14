<?php
/**
 * File: Cache_Apcu.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Apcu
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cache_Apcu extends Cache_Base {
	/**
	 * Used for faster flushing
	 *
	 * @var integer $_key_version
	 */
	private $_key_version = array();

	/**
	 * Adds data
	 *
	 * @param string  $key    Key.
	 * @param mixed   $value  Value.
	 * @param integer $expire Time to expire.
	 * @param string  $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function add( $key, &$value, $expire = 0, $group = '' ) {
		if ( $this->get( $key, $group ) === false ) {
			return $this->set( $key, $value, $expire, $group );
		}

		return false;
	}

	/**
	 * Sets data
	 *
	 * @param string  $key    Key.
	 * @param mixed   $value  Value.
	 * @param integer $expire Time to expire.
	 * @param string  $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function set( $key, $value, $expire = 0, $group = '' ) {
		if ( ! isset( $value['key_version'] ) ) {
			$value['key_version'] = $this->_get_key_version( $group );
		}

		$storage_key = $this->get_item_key( $key );

		return apcu_store( $storage_key, serialize( $value ), $expire );
	}

	/**
	 * Returns data
	 *
	 * @param string $key   Key.
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return mixed
	 */
	public function get_with_old( $key, $group = '' ) {
		$has_old_data = false;
		$storage_key  = $this->get_item_key( $key );

		$v = @unserialize( apcu_fetch( $storage_key ) );
		if ( ! is_array( $v ) || ! isset( $v['key_version'] ) ) {
			return array( null, $has_old_data );
		}

		$key_version = $this->_get_key_version( $group );
		if ( $v['key_version'] === $key_version ) {
			return array( $v, $has_old_data );
		}

		if ( $v['key_version'] > $key_version ) {
			if ( ! empty( $v['key_version_at_creation'] ) && $v['key_version_at_creation'] !== $key_version ) {
				$this->_set_key_version( $v['key_version'], $group );
			}
			return array( $v, $has_old_data );
		}

		// key version is old.
		if ( ! $this->_use_expired_data ) {
			return array( null, $has_old_data );
		}

		// if we have expired data - update it for future use and let current process recalculate it.
		$expires_at = isset( $v['expires_at'] ) ? $v['expires_at'] : null;
		if ( null === $expires_at || time() > $expires_at ) {
			$v['expires_at'] = time() + 30;
			apcu_store( $storage_key, serialize( $v ), 0 );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// return old version.
		return array( $v, $has_old_data );
	}


	/**
	 * Replaces data
	 *
	 * @param string  $key    Key.
	 * @param mixed   $value  Value.
	 * @param integer $expire Time to expire.
	 * @param string  $group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function replace( $key, &$value, $expire = 0, $group = '' ) {
		if ( $this->get( $key, $group ) !== false ) {
			return $this->set( $key, $value, $expire, $group );
		}

		return false;
	}

	/**
	 * Deletes data
	 *
	 * @param string $key   Key.
	 * @param string $group Group.
	 *
	 * @return boolean
	 */
	public function delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );

		if ( $this->_use_expired_data ) {
			$v = @unserialize( apcu_fetch( $storage_key ) );
			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				apcu_store( $storage_key, serialize( $v ), 0 );
				return true;
			}
		}

		return apcu_delete( $storage_key );
	}

	/**
	 * Deletes _old and primary if exists.
	 *
	 * @param string $key   Key.
	 * @param string $group Group.
	 *
	 * @return bool
	 */
	public function hard_delete( $key, $group = '' ) {
		$storage_key = $this->get_item_key( $key );
		return apcu_delete( $storage_key );
	}

	/**
	 * Flushes all data
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function flush( $group = '' ) {
		$this->_get_key_version( $group );  // initialize $this->_key_version.
		++$this->_key_version[ $group ];
		$this->_set_key_version( $this->_key_version[ $group ], $group );

		return true;
	}

	/**
	 * Gets a key extension for "ahead generation" mode.
	 * Used by AlwaysCached functionality to regenerate content
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_ahead_generation_extension( $group ) {
		$v = $this->_get_key_version( $group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Flushes group with before condition
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 * @param array  $extension Used to set a condition what version to flush.
	 *
	 * @return void
	 */
	public function flush_group_after_ahead_generation( $group, $extension ) {
		$v = $this->_get_key_version( $group );
		if ( $extension['key_version'] > $v ) {
			$this->_set_key_version( $extension['key_version'], $group );
		}
	}

	/**
	 * Checks if engine can function properly in this environment
	 *
	 * @return bool
	 */
	public function available() {
		return function_exists( 'apcu_store' );
	}

	/**
	 * Returns key postfix
	 *
	 * @param string $group Used to differentiate between groups of cache values.
	 *
	 * @return integer
	 */
	private function _get_key_version( $group = '' ) {
		if ( ! isset( $this->_key_version[ $group ] ) || $this->_key_version[ $group ] <= 0 ) {
			$v = apcu_fetch( $this->_get_key_version_key( $group ) );
			$v = intval( $v );

			$this->_key_version[ $group ] = ( $v > 0 ? $v : 1 );
		}

		return $this->_key_version[ $group ];
	}

	/**
	 * Sets new key version
	 *
	 * @param unknown $v     Key.
	 * @param string  $group Used to differentiate between groups of cache values.
	 *
	 * @return void
	 */
	private function _set_key_version( $v, $group = '' ) {
		apcu_store( $this->_get_key_version_key( $group ), $v, 0 );
	}

	/**
	 * Used to replace as atomically as possible known value to new one
	 *
	 * @param string $key       Key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 *
	 * @return bool
	 */
	public function set_if_maybe_equals( $key, $old_value, $new_value ) {
		// apc_cas doesnt fit here, since we are float but it works with
		// int only cant guarantee atomic action here, filelocks fail often.
		$value = $this->get( $key );
		if ( isset( $old_value['content'] ) && $value['content'] !== $old_value['content'] ) {
			return false;
		}

		return $this->set( $key, $new_value );
	}

	/**
	 * Use key as a counter and add integet value to it
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	public function counter_add( $key, $value ) {
		if ( 0 === $value ) {
			return true;
		}

		$storage_key = $this->get_item_key( $key );
		$r           = apcu_inc( $storage_key, $value );

		// it doesnt initialize counter by itself.
		if ( ! $r ) {
			$this->counter_set( $key, 0 );
		}

		return $r;
	}

	/**
	 * Use key as a counter and add integet value to it
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 *
	 * @return bool
	 */
	public function counter_set( $key, $value ) {
		$storage_key = $this->get_item_key( $key );
		return apcu_store( $storage_key, $value );
	}

	/**
	 * Get counter's value
	 *
	 * @param string $key Key.
	 *
	 * @return int
	 */
	public function counter_get( $key ) {
		$storage_key = $this->get_item_key( $key );
		$v           = (int) apcu_fetch( $storage_key );

		return $v;
	}
}
