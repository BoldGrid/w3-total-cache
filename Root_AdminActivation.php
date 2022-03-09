<?php
namespace W3TC;

/**
 * W3 Total Cache plugin
 */

/**
 * Class Root_AdminActivation
 */
class Root_AdminActivation {
	/**
	 * Activate plugin action
	 *
	 * @param bool $network_wide
	 * @return void
	 */
	public static function activate( $network_wide ) {
		// decline non-network activation at WPMU.
		if ( Util_Environment::is_wpmu() ) {
			if ( $network_wide ) {
				// we are in network activation.
			} elseif ( 'error_scrape' === $_GET['action'] && strpos( $_SERVER['REQUEST_URI'], '/network/' ) !== false ) {
				// workaround for error_scrape page called after error really we are in network activation and going to throw some error.
			} else {
				echo wp_kses(
					sprintf(
						// translators: 1 opening HTML a tag to plugin admin page, 2 closing HTML a tag.
						__(
							'Please %1$snetwork activate%2$s W3 Total Cache when using WordPress Multisite.',
							'w3-total-cache'
						),
						'<a href="' . esc_url( network_admin_url( 'plugins.php' ) ) . '">',
						'</a>'
					),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				);
				die;
			}
		}

		try {
			$e      = Dispatcher::component( 'Root_Environment' );
			$config = Dispatcher::config();
			$e->fix_in_wpadmin( $config, true );
			$e->fix_on_event( $config, 'activate' );

			// try to save config file if needed, optional thing so exceptions hidden.
			if ( ! ConfigUtil::is_item_exists( 0, false ) ) {
				try {
					// create folders.
					$e->fix_in_wpadmin( $config );
				} catch ( \Exception $ex ) {
					// missing exception handle?
				}

				try {
					Util_Admin::config_save( Dispatcher::config(), $config );
				} catch ( \Exception $ex ) {
					// missing exception handle?
				}
			}
		} catch ( Util_Environment_Exceptions $e ) {
			// missing exception handle?
		} catch ( \Exception $e ) {
			Util_Activation::error_on_exception( $e );
		}
	}

	/**
	 * Deactivate plugin action
	 *
	 * @return void
	 */
	public static function deactivate() {
		try {
			Util_Activation::enable_maintenance_mode();
		} catch ( \Exception $ex ) {
			// missing exception handle?
		}

		try {
			$e = Dispatcher::component( 'Root_Environment' );
			$e->fix_after_deactivation();
		} catch ( Util_Environment_Exceptions $exs ) {
			$r = Util_Activation::parse_environment_exceptions( $exs );

			if ( strlen( $r['required_changes'] ) > 0 ) {
				$changes_style = 'border: 1px solid black; background: white; margin: 10px 30px 10px 30px; padding: 10px;';

				$error = wp_kses(
					sprintf(
						// translators: 1 opening HTML strong tag, 2 closing HTML strong tag, 3 html line break tag,
						// translators: 4 HTML line break tag, 5 opening HTML div tag, 6 list of required changes,
						// translators: 7 closing HTML div tag.
						__(
							'%1$sW3 Total Cache Error:%2$s Files and directories could not be automatically removed to complete the deactivation. %3$sPlease execute commands manually:%4$s%5$s%6$s',
							'w3-total-cache'
						),
						'<strong>',
						'</strong>',
						'<br />',
						'<br /><div style="' . esc_attr( $changes_style ) . '">',
						esc_html( $r['required_changes'] ),
						'</div>'
					),
					array()
				);

				// this is not shown since wp redirects from that page not solved now.
				echo '<div class="error"><p>' . esc_html( $error ) . '</p></div>';
			}
		}

		try {
			Util_Activation::disable_maintenance_mode();
		} catch ( \Exception $ex ) {
			// missing exception handle?
		}

		// Delete cron events.
		require_once __DIR__ . '/Extension_ImageService_Cron.php';
		Extension_ImageService_Cron::delete_cron();
	}
}
