<?php
/**
 * File: Extension_FragmentCache_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_FragmentCache_Core
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Extension_FragmentCache_Core {
	/**
	 * Fragment groups
	 *
	 * @var array
	 */
	private $_fragment_groups = array();

	/**
	 * Actions
	 *
	 * @var array
	 */
	private $_actions = array();

	/**
	 * Registers a fragment cache group with specific actions and expiration time.
	 *
	 * @param string $group     Name of the group to register.
	 * @param array  $actions   List of actions associated with the group.
	 * @param int    $expiration Expiration time for the group in seconds.
	 *
	 * @return void
	 */
	public function register_group( $group, $actions, $expiration ) {
		$this->_register_group( $group, $actions, $expiration, false );
	}

	/**
	 * Registers a global fragment cache group with specific actions and expiration time.
	 *
	 * @param string $group     Name of the global group to register.
	 * @param array  $actions   List of actions associated with the global group.
	 * @param int    $expiration Expiration time for the global group in seconds.
	 *
	 * @return void
	 */
	public function register_global_group( $group, $actions, $expiration ) {
		$this->_register_group( $group, $actions, $expiration, true );
	}

	/**
	 * Internal method for registering a fragment cache group.
	 *
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	 *
	 * @param string $group       Name of the group to register.
	 * @param array  $actions     List of actions associated with the group.
	 * @param int    $expiration  Expiration time for the group in seconds.
	 * @param bool   $global_flag Whether the group is global.
	 *
	 * @return void
	 */
	private function _register_group( $group, $actions, $expiration, $global_flag ) {
		if ( empty( $group ) ) {
			return;
		}

		if ( ! is_int( $expiration ) ) {
			$expiration = (int) $expiration;
			trigger_error( __METHOD__ . ' needs expiration parameter to be an int.', E_USER_WARNING );
		}

		$this->_fragment_groups[ $group ] = array(
			'actions'    => $actions,
			'expiration' => $expiration,
			'global'     => $global_flag,
		);

		foreach ( $actions as $action ) {
			if ( ! isset( $this->_actions[ $action ] ) ) {
				$this->_actions[ $action ] = array();
			}
			$this->_actions[ $action ][] = $group;
		}
	}

	/**
	 * Retrieves all registered fragment cache groups.
	 *
	 * @return array Associative array of registered fragment groups.
	 */
	public function get_registered_fragment_groups() {
		return $this->_fragment_groups;
	}

	/**
	 * Retrieves all registered actions for fragment cache groups.
	 *
	 * @return array Associative array of registered actions.
	 */
	public function get_registered_actions() {
		return $this->_actions;
	}

	/**
	 * Cleans up fragment cache files based on the current configuration.
	 *
	 * @return void
	 */
	public function cleanup() {
		$c      = Dispatcher::config();
		$engine = $c->get_string( array( 'fragmentcache', 'engine' ) );

		switch ( $engine ) {
			case 'file':
				$w3_cache_file_cleaner = new Cache_File_Cleaner(
					array(
						'cache_dir'       => Util_Environment::cache_blog_dir( 'fragment' ),
						'clean_timelimit' => $c->get_integer( 'timelimit.cache_gc' ),
					)
				);

				$w3_cache_file_cleaner->clean();
				break;
		}
	}
}
