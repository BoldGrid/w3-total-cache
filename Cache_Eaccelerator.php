<?php
/**
 * File: Cache_Eaccelerator.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_Eaccelerator
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cache_Eaccelerator extends Cache_Base {

	/**
	 * Used for faster flushing
	 *
	 * @var integer $_key_postfix
	 */
	private $_key_version = array();

	/**
	 * Adds data
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_value  Value.
	 * @param integer $expire Time to expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function add( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( $this->get( $w3tc_key, $w3tc_group ) === false ) {
			return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
		}

		return false;
	}

	/**
	 * Sets data
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_value  Value.
	 * @param integer $expire Time to expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function set( $w3tc_key, $w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( ! isset( $w3tc_value['key_version'] ) ) {
			$w3tc_value['key_version'] = $this->_get_key_version( $w3tc_group );
		}

		$storage_key = $this->get_item_key( $w3tc_key );
		return eaccelerator_put( $storage_key, serialize( $w3tc_value ), $expire );
	}

	/**
	 * Returns data
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return mixed
	 */
	public function get_with_old( $w3tc_key, $w3tc_group = '' ) {
		$has_old_data = false;
		$storage_key  = $this->get_item_key( $w3tc_key );

		$v = $this->_unserialize(
			eaccelerator_get( $storage_key ),
			array(
				'group' => $w3tc_group,
				'key'   => $w3tc_key,
			)
		);
		if ( ! is_array( $v ) || ! isset( $v['key_version'] ) ) {
			return array( null, $has_old_data );
		}

		$key_version = $this->_get_key_version( $w3tc_group );
		if ( $v['key_version'] === $key_version ) {
			return array( $v, $has_old_data );
		}

		if ( $v['key_version'] > $key_version ) {
			if ( ! empty( $v['key_version_at_creation'] ) && $v['key_version_at_creation'] !== $key_version ) {
				$this->_set_key_version( $v['key_version'], $w3tc_group );
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
			eaccelerator_put( $storage_key, serialize( $v ), 0 );
			$has_old_data = true;

			return array( null, $has_old_data );
		}

		// return old version.
		return array( $v, $has_old_data );
	}

	/**
	 * Replaces data
	 *
	 * @param string  $w3tc_key    Key.
	 * @param mixed   $w3tc_value  Value.
	 * @param integer $expire Time to expire.
	 * @param string  $w3tc_group  Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function replace( $w3tc_key, &$w3tc_value, $expire = 0, $w3tc_group = '' ) {
		if ( $this->get( $w3tc_key, $w3tc_group ) !== false ) {
			return $this->set( $w3tc_key, $w3tc_value, $expire, $w3tc_group );
		}

		return false;
	}

	/**
	 * Deletes data
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Group.
	 *
	 * @return boolean
	 */
	public function delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );

		if ( $this->_use_expired_data ) {
			$v = $this->_unserialize(
				eaccelerator_get( $storage_key ),
				array(
					'group' => $w3tc_group,
					'key'   => $w3tc_key,
				)
			);
			if ( is_array( $v ) ) {
				$v['key_version'] = 0;
				eaccelerator_put( $storage_key, serialize( $v ), 0 );
				return true;
			}
		}

		return eaccelerator_rm( $w3tc_key . '_' . $this->_blog_id );
	}


	/**
	 * Deletes _old and primary if exists.
	 *
	 * @param string $w3tc_key   Key.
	 * @param string $w3tc_group Group.
	 *
	 * @return bool
	 */
	public function hard_delete( $w3tc_key, $w3tc_group = '' ) {
		$storage_key = $this->get_item_key( $w3tc_key );
		return eaccelerator_rm( $storage_key );
	}
	/**
	 * Flushes all data
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	public function flush( $w3tc_group = '' ) {
		$this->_get_key_version( $w3tc_group ); // initialize $this->_key_version.
		++$this->_key_version[ $w3tc_group ];
		$this->_set_key_version( $this->_key_version[ $w3tc_group ], $w3tc_group );

		return true;
	}

	/**
	 * Gets a key extension for "ahead generation" mode.
	 * Used by AlwaysCached functionality to regenerate content
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return array
	 */
	public function get_ahead_generation_extension( $w3tc_group ) {
		$v = $this->_get_key_version( $w3tc_group );
		return array(
			'key_version'             => $v + 1,
			'key_version_at_creation' => $v,
		);
	}

	/**
	 * Flushes group with before condition
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 * @param array  $w3tc_extension Used to set a condition what version to flush.
	 *
	 * @return void
	 */
	public function flush_group_after_ahead_generation( $w3tc_group, $w3tc_extension ) {
		$v = $this->_get_key_version( $w3tc_group );
		if ( $w3tc_extension['key_version'] > $v ) {
			$this->_set_key_version( $w3tc_extension['key_version'], $w3tc_group );
		}
	}

	/**
	 * Checks if engine can function properly in this environment
	 *
	 * @return bool
	 */
	public function available() {
		return function_exists( 'eaccelerator_put' );
	}

	/**
	 * Returns key postfix
	 *
	 * @param string $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return integer
	 */
	private function _get_key_version( $w3tc_group = '' ) {
		if ( ! isset( $this->_key_version[ $w3tc_group ] ) || $this->_key_version[ $w3tc_group ] <= 0 ) {
			$v = eaccelerator_get( $this->_get_key_version_key( $w3tc_group ) );
			$v = intval( $v );

			$this->_key_version[ $w3tc_group ] = ( $v > 0 ? $v : 1 );
		}

		return $this->_key_version[ $w3tc_group ];
	}

	/**
	 * Sets new key version
	 *
	 * @param unknown $v     Key.
	 * @param string  $w3tc_group Used to differentiate between groups of cache values.
	 *
	 * @return boolean
	 */
	private function _set_key_version( $v, $w3tc_group = '' ) {
		// cant guarantee atomic action here, filelocks fail often.
		$w3tc_value = $this->get( $w3tc_key );
		if ( isset( $old_value['content'] ) && $w3tc_value['content'] !== $old_value['content'] ) {
			return false;
		}

		return $this->set( $w3tc_key, $new_value );
	}

	/**
	 * Used to replace as atomically as possible known value to new one
	 *
	 * @param string $w3tc_key       Key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 *
	 * @return bool
	 */
	public function set_if_maybe_equals( $w3tc_key, $old_value, $new_value ) {
		// eaccelerator cache not supported anymore by its authors.
		return false;
	}

	/**
	 * Use key as a counter and add integet value to it
	 *
	 * @param string $w3tc_key   Key.
	 * @param mixed  $w3tc_value Value.
	 *
	 * @return bool
	 */
	public function counter_add( $w3tc_key, $w3tc_value ) {
		// eaccelerator cache not supported anymore by its authors.
		return false;
	}

	/**
	 * Use key as a counter and add integet value to it
	 *
	 * @param string $w3tc_key   Key.
	 * @param mixed  $w3tc_value Value.
	 *
	 * @return bool
	 */
	public function counter_set( $w3tc_key, $w3tc_value ) {
		// eaccelerator cache not supported anymore by its authors.
		return false;
	}

	/**
	 * Get counter's value
	 *
	 * @param string $w3tc_key Key.
	 *
	 * @return bool
	 */
	public function counter_get( $w3tc_key ) {
		// eaccelerator cache not supported anymore by its authors.
		return false;
	}
}
