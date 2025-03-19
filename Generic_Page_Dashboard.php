<?php
/**
 * FIle: Generic_Page_Dashboard.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Page_Dashboard
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Generic_Page_Dashboard extends Base_Page_Settings {
	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_dashboard';

	/**
	 * Print CSS for dashboard page
	 *
	 * @return void
	 */
	public static function admin_print_styles_w3tc_dashboard() {
		wp_enqueue_style(
			'w3tc-dashboard',
			plugins_url( 'Generic_Page_Dashboard_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Dashboard tab
	 *
	 * @return void
	 */
	public function view() {
		$module_status = Dispatcher::component( 'ModuleStatus' );

		Util_Widget::setup();

		$current_user  = wp_get_current_user();
		$config_master = $this->_config_master;

		$browsercache_enabled = $module_status->is_enabled( 'browsercache' );

		$enabled = $module_status->plugin_is_enabled();

		$can_empty_memcache = $module_status->can_empty_memcache();

		$can_empty_opcode = $module_status->can_empty_opcode();

		$can_empty_file = $module_status->can_empty_file();

		$can_empty_varnish = $module_status->can_empty_varnish();

		$cdn_enabled      = $module_status->is_enabled( 'cdn' );
		$cdn_mirror_purge = Cdn_Util::can_purge_all( $module_status->get_module_engine( 'cdn' ) );

		// Required for Update Media Query String button.
		$browsercache_update_media_qs = ( $this->_config->get_boolean( 'browsercache.cssjs.replace' ) || $this->_config->get_boolean( 'browsercache.other.replace' ) );

		include W3TC_INC_DIR . '/options/dashboard.php';
	}
}
