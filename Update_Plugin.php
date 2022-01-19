<?php
/**
 * File: Update_Plugin.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Update_Plugin
 *
 * @since X.X.X
 */
class Update_Plugin {
	/**
	 * Free plugin key/slug.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private static $key = 'w3-total-cache/w3-total-cache.php';

	/**
	 * Rubicon version.
	 *
	 * The last version allowed before blocking the update.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private static $rubicon_version = '2.1.4';

	/**
	 * Pro lugin key/slug.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private static $pro_key = 'w3-total-cache-pro/w3-total-cache-pro.php';

	/**
	 * Run; add hooks.
	 *
	 * @since X.X.X
	 */
	public function run() {
		add_filter( 'site_transient_update_plugins', array( $this, 'site_transient_update_plugins' ) );
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_settings_page-w3tc_update', array( $this, 'w3tc_settings_page_w3tc_update' ) );
		add_action( 'after_plugin_row_' . self::$key, array( $this, 'after_plugin_row' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Insert markup after plugin row.
	 *
	 * @since X.X.X
	 */
	public function after_plugin_row() {
		$v = get_site_transient( 'update_plugins' );

		if ( ! isset( $v->w3tc_new_available_version ) ) {
			return;
		}

		require_once __DIR__ . '/Update_PluginsRow_View.php';
	}

	/**
	 * Filter the "update_plugins" site transient.
	 *
	 * @since X.X.X
	 *
	 * @param object $v Version information.
	 * @return object
	 */
	public function site_transient_update_plugins( $v ) {
		if ( isset( $v->response[ self::$key ] ) ) {
			$new_version           = $v->response[ self::$key ]->new_version;
			$update_version_higher = version_compare( $new_version, self::$rubicon_version ) > 0;
			$pro_plugin_active     = is_plugin_active( self::$pro_key );

			if ( $update_version_higher && ! $pro_plugin_active ) {
				$v->w3tc_new_available_version = $new_version;
				unset( $v->response[ self::$key ] );
			}
		}

		return $v;
	}

	/**
	 * Add a menu item.
	 *
	 * @since X.X.X
	 *
	 * @param array $menu Menu.
	 * @return array
	 */
	public function w3tc_admin_menu( $menu ) {
		$v = get_site_transient( 'update_plugins' );

		if ( isset( $v->w3tc_new_available_version ) ) {
			$menu['w3tc_update'] = array(
				'page_title'     => __( 'Update', 'w3-total-cache' ),
				'menu_text'      => __( 'Update', 'w3-total-cache' ),
				'visible_always' => true,
				'order'          => 50,
			);
		}

		return $menu;
	}

	/**
	 * Enqueue styles.
	 *
	 * @since X.X.X
	 */
	public function enqueue_styles() {
		wp_register_style(
			'w3tc_update_view',
			esc_url( plugin_dir_url( __FILE__ ) . 'Update_Plugin_View.css' ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Update information page.
	 *
	 * @since X.X.X
	 */
	public function w3tc_settings_page_w3tc_update() {
		wp_enqueue_style( 'w3tc_update_view' );

		require_once __DIR__ . '/Update_Page_View.php';
	}
}
