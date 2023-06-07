<?php
namespace W3TC;

/**
 * always-cached environment set up, table creation
 */
class Extension_AlwaysCached_Environment {

	static public function w3tc_environment_fix_on_wpadmin_request() {
		// todo: remove when arranged as activateable module
		Extension_AlwaysCached_Queue::create_table();
	}

	/**
	 * Fixes environment once event occurs
	 *
	 * @param Config  $config
	 * @param string  $event
	 * @param Config|null $old_config
	 * @throws Util_Environment_Exceptions
	 */

	static public function w3tc_environment_fix_on_event( $config, $event, $old_config = null ) {
		$exs = new Util_Environment_Exceptions();

		/* if ( $config->get_boolean( '.enabled' ) ) */
		try {
			Extension_AlwaysCached_Environment::handle_tables(
				$event == 'activate' /* drop state on activation */,
				true );
		} catch ( \Exception $ex ) {
			$exs->push( $ex );
		}

		if ( count( $exs->exceptions() ) > 0 )
			throw $exs;
	}

	/**
	 * Fixes environment after plugin deactivation
	 */
	static public function w3tc_environment_fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		try {
			Extension_AlwaysCached_Environment::handle_tables( true, false );
		} catch ( \Exception $ex ) {
			$exs->push( $ex );
		}

		if ( count( $exs->exceptions() ) > 0 )
			throw $exs;
	}

	/**
	 *
	 *
	 * @param Config  $config
	 * @return array|null
	 */
	// todo: add filter handler in Root_env for that
	public function get_other_instructions( $config ) {
		/*
		if ( !$config->get_boolean( '.enabled' ) )
			return null;
		*/

		$instructions = [];
		$instructions[] = [
			'title'=>__( 'Always-cached module: Required Database SQL', 'w3-total-cache' ),
			'content' => Extension_AlwaysCached_Queue::create_table_sql(),
			'area' => 'database'
		];

		return $instructions;
	}




	/**
	 * Create table
	 *
	 * @param bool    $drop
	 * @throws Util_Environment_Exception
	 */
	static private function handle_tables( $drop, $create ) {
		if ( $drop ) {
			$sql = Extension_AlwaysCached_Queue::drop_table();
		}
		if ( $create ) {
			Extension_AlwaysCached_Queue::create_table();
		}
	}
}
