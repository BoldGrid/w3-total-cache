<?php
/**
 * File: Extension_NewRelic_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_NewRelic_Page extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_monitoring';

	/**
	 * Renders the content for the New Relic extension page.
	 *
	 * @return void
	 */
	public function render_content() {
		$config          = Dispatcher::config();
		$monitoring_type = $config->get_string( array( 'newrelic', 'monitoring_type' ) );
		if ( 'browser' === $monitoring_type ) {
			return;
		}

		$nerser               = Dispatcher::component( 'Extension_NewRelic_Service' );
		$new_relic_configured = $config->get_string( array( 'newrelic', 'api_key' ) ) && $config->get_string( array( 'newrelic', 'apm.application_name' ) );
		$verify_running       = $nerser->verify_running();
		$application_settings = array();

		try {
			$application_settings = $nerser->get_application_settings();
		} catch ( \Exception $ex ) {
			$application_settings = array();
		}

		$view_metric = Util_Request::get_boolean( 'view_metric', false );
		if ( ! empty( $view_metric ) ) {
			$metric_names = $nerser->get_metric_names( Util_Request::get_string( 'regex', '' ) );
		}

		require W3TC_DIR . '/Extension_NewRelic_Page_View_Apm.php';
	}
}
