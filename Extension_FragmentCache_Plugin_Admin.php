<?php
/**
 * File: Extension_FragmentCache_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_FragmentCache_Plugin_Admin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Extension_FragmentCache_Plugin_Admin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Adds the Fragment Cache extension details to the list of W3TC extensions.
	 *
	 * @param array  $extensions List of existing extensions.
	 * @param object $config     Configuration object for W3 Total Cache.
	 *
	 * @return array Updated list of extensions including Fragment Cache.
	 */
	public static function w3tc_extensions( $extensions, $config ) {
		$requirements = array();

		if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
			$requirements[] = __( 'Valid W3 Total Cache Pro license', 'w3-total-cache' );
		}

		$extensions['fragmentcache'] = array(
			'name'            => 'Fragment Cache',
			'author'          => 'W3 EDGE',
			'description'     => 'Caching of page fragments.',
			'author_uri'      => 'https://www.w3-edge.com/',
			'extension_uri'   => 'https://www.w3-edge.com/',
			'extension_id'    => 'fragmentcache',
			'pro_feature'     => true,
			'pro_excerpt'     => __( 'Increase the performance of dynamic sites that cannot benefit from the caching of entire pages.', 'w3-total-cache' ),
			'pro_description' => array(
				__( 'Fragment caching extends the core functionality of WordPress by enabling caching policies to be set on groups of objects that are cached. This allows you to optimize various elements in themes and plugins to use caching to save resources and reduce response times. You can also use caching methods like Memcached or Redis (for example) to scale. Instructions for use are available in the FAQ available under the help menu. This feature also gives you control over the caching policies by the group as well as visibility into the configuration by extending the WordPress Object API with additional functionality.', 'w3-total-cache' ),
				__( 'Fragment caching is a powerful, but advanced feature. If you need help, take a look at our premium support, customization and audit services.', 'w3-total-cache' ),
			),
			'settings_exists' => true,
			'version'         => '1.0',
			'enabled'         => empty( $requirements ),
			'requirements'    => implode( ', ', $requirements ),
			'path'            => 'w3-total-cache/Extension_FragmentCache_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Initializes the Fragment Cache plugin.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Sets up actions, filters, and hooks required for the Fragment Cache extension.
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_objectcache_addin_required', array( $this, 'w3tc_objectcache_addin_required' ) );

		add_action( 'w3tc_environment_fix_on_event', array( '\W3TC\Extension_FragmentCache_Environment', 'fix_on_event' ), 10, 2 );
		add_action( 'w3tc_deactivate_extension_fragmentcache', array( '\W3TC\Extension_FragmentCache_Environment', 'deactivate_extension' ) );

		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );
		add_filter( 'w3tc_extension_plugin_links_fragmentcache', array( $this, 'w3tc_extension_plugin_links' ) );
		add_action( 'w3tc_settings_page-w3tc_fragmentcache', array( $this, 'w3tc_settings_page_w3tc_fragmentcache' ) );

		add_action( 'admin_init_w3tc_general', array( '\W3TC\Extension_FragmentCache_GeneralPage', 'admin_init_w3tc_general' ) );

		add_action( 'w3tc_config_save', array( $this, 'w3tc_config_save' ), 10, 1 );

		add_filter( 'w3tc_usage_statistics_summary_from_history', array( $this, 'w3tc_usage_statistics_summary_from_history' ), 10, 2 );
	}

	/**
	 * Determines if the object cache add-in is required for the Fragment Cache extension.
	 *
	 * @param bool $addin_required Whether the object cache add-in is required.
	 *
	 * @return bool True if required; otherwise, the passed-in value.
	 */
	public function w3tc_objectcache_addin_required( $addin_required ) {
		if ( $this->_config->is_extension_active_frontend( 'fragmentcache' ) ) {
			return true;
		}

		return $addin_required;
	}

	/**
	 * Adds plugin-specific links for the Fragment Cache extension.
	 *
	 * @param array $links Existing plugin links.
	 *
	 * @return array Updated plugin links for the Fragment Cache extension.
	 */
	public function w3tc_extension_plugin_links( $links ) {
		$links = array();

		if ( $this->_config->is_extension_active( 'fragmentcache' ) && Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$links[] = '<a class="edit" href="' . esc_attr( Util_Ui::admin_url( 'admin.php?page=w3tc_fragmentcache' ) ) . '">'
				. __( 'Settings', 'w3-total-cache' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Adds the Fragment Cache extension to the W3TC admin menu.
	 *
	 * @param array $menu Existing admin menu items.
	 *
	 * @return array Updated admin menu items with Fragment Cache.
	 */
	public function w3tc_admin_menu( $menu ) {
		if ( $this->_config->is_extension_active( 'fragmentcache' ) && Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$menu['w3tc_fragmentcache'] = array(
				'page_title'     => __( 'Fragment Cache', 'w3-total-cache' ),
				'menu_text'      => '<span class="w3tc_menu_item_pro">' . __( 'Fragment Cache', 'w3-total-cache' ) . '</span>',
				'visible_always' => false,
				'order'          => 1100,
			);
		}

		return $menu;
	}

	/**
	 * Adds the Fragment Cache extension to the W3TC admin bar menu.
	 *
	 * @param array $menu_items Existing admin bar menu items.
	 *
	 * @return array Updated admin bar menu items with Fragment Cache.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		if ( $this->_config->is_extension_active( 'fragmentcache' ) && Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$menu_items['20510.fragmentcache'] = array(
				'id'     => 'w3tc_flush_fragmentcache',
				'parent' => 'w3tc_flush',
				'title'  => __( 'Fragment Cache', 'w3-total-cache' ),
				'href'   => wp_nonce_url( admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_fragmentcache' ), 'w3tc' ),
			);
		}

		return $menu_items;
	}

	/**
	 * Renders the settings page for the Fragment Cache extension.
	 *
	 * @return void
	 */
	public function w3tc_settings_page_w3tc_fragmentcache() {
		$v = new Extension_FragmentCache_Page();
		$v->render_content();
	}

	/**
	 * Updates the configuration when the W3TC settings are saved.
	 *
	 * @param object $config Configuration object being saved.
	 *
	 * @return void
	 */
	public function w3tc_config_save( $config ) {
		// frontend activity.
		$is_frontend_active = (
			$config->is_extension_active( 'fragmentcache' ) &&
			! empty( $config->get_string( array( 'fragmentcache', 'engine' ) ) ) &&
			Util_Environment::is_w3tc_pro( $config )
		);
		$config->set_extension_active_frontend( 'fragmentcache', $is_frontend_active );
	}

	/**
	 * Updates the usage statistics summary for the Fragment Cache extension.
	 *
	 * @param array $summary Existing usage statistics summary.
	 * @param array $history Historical usage statistics data.
	 *
	 * @return array Updated usage statistics summary including Fragment Cache data.
	 */
	public function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		if ( ! $this->_config->is_extension_active_frontend( 'fragmentcache' ) ) {
			return $summary;
		}

		// memcached servers.
		$c = Dispatcher::config();
		if ( 'memcached' === $c->get_string( array( 'fragmentcache', 'engine' ) ) ) {
			$summary['memcached_servers']['fragmentcache'] = array(
				'servers'  => $c->get_array( array( 'fragmentcache', 'memcached.servers' ) ),
				'username' => $c->get_string( array( 'fragmentcache', 'memcached.username' ) ),
				'password' => $c->get_string( array( 'fragmentcache', 'memcached.password' ) ),
				'name'     => __( 'Fragment Cache', 'w3-total-cache' ),
			);
		} elseif ( 'redis' === $c->get_string( array( 'fragmentcache', 'engine' ) ) ) {
			$summary['redis_servers']['fragmentcache'] = array(
				'servers'  => $c->get_array( array( 'fragmentcache', 'redis.servers' ) ),
				'username' => $c->get_boolean( array( 'fragmentcache', 'redis.username' ) ),
				'dbid'     => $c->get_integer( array( 'fragmentcache', 'redis.dbid' ) ),
				'password' => $c->get_string( array( 'fragmentcache', 'redis.password' ) ),
				'name'     => __( 'Fragment Cache', 'w3-total-cache' ),
			);
		}

		// counters.
		$fragmentcache_calls_total = Util_UsageStatistics::sum( $history, 'fragmentcache_calls_total' );
		$fragmentcache_calls_hits  = Util_UsageStatistics::sum( $history, 'fragmentcache_calls_hits' );

		$summary['fragmentcache'] = array(
			'calls_total'      => Util_UsageStatistics::integer( $fragmentcache_calls_total ),
			'calls_per_second' => Util_UsageStatistics::value_per_period_seconds( $fragmentcache_calls_total, $summary ),
			'hit_rate'         => Util_UsageStatistics::percent( $fragmentcache_calls_total, $fragmentcache_calls_total ),
		);

		return $summary;
	}
}
