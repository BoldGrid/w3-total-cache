<?php
/**
 * File: Root_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Root_AdminActivation
 *
 * W3 Total Cache plugin
 *
 * phpcs:disable Generic.CodeAnalysis.EmptyStatement
 */
class Root_AdminActivation {
	/**
	 * Activates the plugin and performs necessary configuration tasks.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide in a multisite environment.
	 *
	 * @return void
	 *
	 * @throws \Exception If an error occurs during activation.
	 */
	public static function activate( $network_wide ) {
		// Decline non-network activation at WPMU.
		if ( Util_Environment::is_wpmu() ) {
			if ( $network_wide ) {
				// We are in network activation.
			} elseif (
				'error_scrape' === Util_Request::get_string( 'action' ) &&
				false !== strpos(
					isset( $_SERVER['REQUEST_URI'] ) ?
						sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) :
						'',
					'/network/'
				)
			) {
				// Workaround for error_scrape page called after error really we are in network activation and going to throw some error.
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

		$e      = Dispatcher::component( 'Root_Environment' );
		$config = Dispatcher::config();
		$debug  = \defined( 'WP_DEBUG' ) && WP_DEBUG && ! \defined( 'W3D_TESTING' );

		try {
			$e->fix_in_wpadmin( $config, true );
		} catch ( Util_Environment_Exceptions $exs ) {
			$r = Util_Activation::parse_environment_exceptions( $exs );

			if ( \strlen( $r['required_changes'] ) > 0 ) {
				// Log the error for debugging purposes.
				if ( $debug ) {
					\error_log( 'W3 Total Cache environment exception: ' . $r['required_changes'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		} catch ( \Exception $e ) {
			// Log the exception for debugging purposes.
			if ( $debug ) {
				\error_log( 'W3 Total Cache exception: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// Handle the exception gracefully.
			Util_Activation::error_on_exception( $e );
		}

		try {
			$e->fix_on_event( $config, 'activate' );
		} catch ( Util_Environment_Exceptions $exs ) {
			$r = Util_Activation::parse_environment_exceptions( $exs );

			if ( \strlen( $r['required_changes'] ) > 0 ) {
				// Log the error for debugging purposes.
				if ( $debug ) {
					\error_log( 'W3 Total Cache environment exception: ' . $r['required_changes'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		} catch ( \Exception $e ) {
			// Log the exception for debugging purposes.
			if ( $debug ) {
				\error_log( 'W3 Total Cache exception: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// Handle the exception gracefully.
			Util_Activation::error_on_exception( $e );
		}

		// try to save config file if needed, optional thing so exceptions hidden.
		if ( ! ConfigUtil::is_item_exists( 0, false ) ) {
			try {
				// create folders.
				$e->fix_in_wpadmin( $config );
			} catch ( Util_Environment_Exceptions $exs ) {
				$r = Util_Activation::parse_environment_exceptions( $exs );

				if ( \strlen( $r['required_changes'] ) > 0 ) {
					// Log the error for debugging purposes.
					if ( $debug ) {
						\error_log( 'W3 Total Cache environment exception: ' . $r['required_changes'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
				}
			}

			try {
				Util_Admin::config_save( Dispatcher::config(), $config );
			} catch ( \Exception $ex ) {
				// Log the exception for debugging purposes.
				if ( $debug ) {
					\error_log( 'W3 Total Cache exception: ' . $ex->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		// Set the installation date if it is not already set.
		if ( ! get_option( 'w3tc_install_date' ) ) {
			update_option( 'w3tc_install_date', current_time( 'mysql' ) );
		}
	}

	/**
	 * Deactivates the plugin and cleans up necessary tasks.
	 *
	 * @return void
	 *
	 * @throws \Exception If an error occurs during deactivation.
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

				// this is not shown since wp redirects from that page not solved now.
				echo wp_kses(
					sprintf(
						// translators: 1 opening HTML div tag followed by opening HTML p tag, 2 opening HTML strong tag,
						// translators: 3 closing HTML strong tag, 4 html line break tags (x2), 5 opening HTML div tag,
						// translators: 6 list of required changes, 7 closing HTML div tag,
						// translators: 8 closing HTML p tag followed by closing HTML div tag.
						__(
							'%1$s%2$sW3 Total Cache Error:%3$s Files and directories could not be automatically removed to complete the deactivation. %4$sPlease execute commands manually:%5$s%6$s%7$s%8$s',
							'w3-total-cache'
						),
						'<div class="' . esc_attr__( 'error', 'w3-total-cache' ) . '"><p>',
						'<strong>',
						'</strong>',
						'<br /><br />',
						'<div style="' . esc_attr( $changes_style ) . '">',
						esc_html( $r['required_changes'] ),
						'</div>',
						'</p></div>'
					),
					array(
						'div'    => array(
							'class' => array(),
							'style' => array(),
						),
						'strong' => array(),
						'br'     => array(),
						'p'      => array(),
					)
				);
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

		// Check if data cleanup is required.
		if ( get_option( 'w3tc_remove_data' ) ) {
			$config = Dispatcher::config();
			Root_Environment::delete_plugin_data( $config );
		}
	}
}
