<?php
/**
 * File: Root_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Root_AdminActions
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Root_AdminActions {
	/**
	 * Constructor for Root_AdminActions class.
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Executes an action based on the given action string.
	 *
	 * @param string $action The action to execute.
	 *
	 * @return void
	 *
	 * @throws \Exception If the action does not exist.
	 */
	public function execute( $action ) {
		$handler_class          = $this->_get_handler( $action );
		$handler_class_fullname = '\\W3TC\\' . $handler_class;
		$handler_object         = new $handler_class_fullname();

		$action_details = explode( '~', $action );

		if ( count( $action_details ) > 1 ) {
			// action is in form "action~parameter".
			$method = $action_details[0];
			if ( method_exists( $handler_object, $method ) ) {
				$handler_object->$method( $action_details[1] );
				return;
			}
		} elseif ( method_exists( $handler_object, $action ) ) {
			$handler_object->$action();
			return;
		}

		throw new \Exception(
			\esc_html(
				sprintf(
					// Translators: 1 action name.
					\__( 'Action %1$s does not exist.', 'w3-total-cache' ),
					$action
				)
			)
		);
	}

	/**
	 * Checks if an action exists.
	 *
	 * @param string $action The action to check.
	 *
	 * @return bool True if the action exists, false otherwise.
	 */
	public function exists( $action ) {
		$handler = $this->_get_handler( $action );
		return '' !== $handler;
	}

	/**
	 * Retrieves the handler class for the given action.
	 *
	 * @param string $action The action to retrieve the handler for.
	 *
	 * @return string|null The handler class name or null if no handler exists.
	 */
	private function _get_handler( $action ) {
		static $handlers = null;
		if ( is_null( $handlers ) ) {
			$handlers = array(
				'boldgrid'         => 'Generic_WidgetBoldGrid_AdminActions',
				'cdn_google_drive' => 'Cdn_GoogleDrive_AdminActions',
				'cdn'              => 'Cdn_AdminActions',
				'config'           => 'Generic_AdminActions_Config',
				'default'          => 'Generic_AdminActions_Default',
				'extensions'       => 'Extensions_AdminActions',
				'flush'            => 'Generic_AdminActions_Flush',
				'licensing'        => 'Licensing_AdminActions',
				'support'          => 'Support_AdminActions',
				'test'             => 'Generic_AdminActions_Test',
				'ustats'           => 'UsageStatistics_AdminActions',
			);
			$handlers = apply_filters( 'w3tc_admin_actions', $handlers );
		}

		if ( 'w3tc_save_options' === $action ) {
			return $handlers['default'];
		}

		$candidate_prefix = '';
		$candidate_class  = '';

		foreach ( $handlers as $prefix => $class ) {
			$v1 = "w3tc_$prefix";
			$v2 = "w3tc_save_$prefix";

			if (
				substr( $action, 0, strlen( $v1 ) ) === $v1 ||
				substr( $action, 0, strlen( $v2 ) ) === $v2
			) {
				if ( strlen( $candidate_prefix ) < strlen( $prefix ) ) {
					$candidate_class  = $class;
					$candidate_prefix = $prefix;
				}
			}
		}

		return $candidate_class;
	}
}
