<?php
/**
 * File: Generic_Page_Install.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Page_Install
 */
class Generic_Page_Install extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_install'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Install tab
	 *
	 * @return void
	 */
	public function view() {
		$rewrite_rules_descriptors = array();
		$other_areas               = array();

		if ( Util_Rule::can_check_rules() ) {
			$e                         = Dispatcher::component( 'Root_Environment' );
			$rewrite_rules_descriptors = $e->get_required_rules( $this->_config );
			$other_areas               = $e->get_other_instructions( $this->_config );
			$other_areas               = apply_filters( 'w3tc_environment_get_other_instructions', $other_areas );
		}

		include W3TC_INC_DIR . '/options/install.php';
	}
}
