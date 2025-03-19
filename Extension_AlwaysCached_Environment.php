<?php
/**
 * File: Extension_AlwaysCached_Environment.php
 *
 * Controller for AlwaysCached extension environment.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Admin Environment.
 *
 * @since 2.8.0
 */
class Extension_AlwaysCached_Environment {

	/**
	 * Fixes environment on admin request.
	 *
	 * @since 2.8.0
	 *
	 * @return void|null
	 */
	public static function fix_on_wpadmin_request() {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			return null;
		}

		Extension_AlwaysCached_Queue::create_table();
	}

	/**
	 * Fixes environment once event occurs.
	 *
	 * @since 2.8.0
	 *
	 * @param Config      $config     Config data.
	 * @param string      $event      Event key.
	 * @param Config|null $old_config Old config data.
	 *
	 * @throws Util_Environment_Exceptions Exception.
	 *
	 * @return void|null
	 */
	public static function fix_on_event( $config, $event, $old_config = null ) {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			return null;
		}

		$exs = new Util_Environment_Exceptions();

		try {
			// Drop AlwaysCached DB table on activation and recreate.
			self::handle_tables(
				'activate' === $event,
				true
			);
		} catch ( \Exception $ex ) {
			$exs->push( $ex );
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes environment after plugin deactivation.
	 *
	 * @since 2.8.0
	 *
	 * @throws Util_Environment_Exceptions Exception.
	 *
	 * @return void
	 */
	public static function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		try {
			// Drop AlwaysCached DB table on deactivation.
			self::handle_tables( true, false );
		} catch ( \Exception $ex ) {
			$exs->push( $ex );
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Sets requirment instructions for AlwaysCached on install page.
	 *
	 * @since 2.8.0
	 *
	 * @param Config $config Config data.
	 *
	 * @return array|null
	 */
	public function get_instructions( $config ) {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			return null;
		}

		$instructions = array();

		$instructions[] = array(
			'title'   => __( 'Always Cached module: Required Database SQL', 'w3-total-cache' ),
			'content' => Extension_AlwaysCached_Queue::drop_table_sql() . ";\n" . Extension_AlwaysCached_Queue::create_table_sql() . ';',
			'area'    => 'database',
		);

		return $instructions;
	}

	/**
	 * Create table
	 *
	 * @param bool $drop   Drop flag.
	 * @param bool $create Create flag.
	 *
	 * @throws Util_Environment_Exception Exception.
	 */
	private static function handle_tables( $drop, $create ) {
		if ( ! Extension_AlwaysCached_Plugin::is_enabled() ) {
			return null;
		}

		if ( $drop ) {
			$sql = Extension_AlwaysCached_Queue::drop_table();
		}

		if ( $create ) {
			Extension_AlwaysCached_Queue::create_table();
		}
	}
}
