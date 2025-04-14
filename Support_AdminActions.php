<?php
/**
 * File: Support_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Support_AdminActions
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
class Support_AdminActions {
	/**
	 * Sends support details to a remote server.
	 *
	 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition
	 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
	 * phpcs:disable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_phpinfo
	 *
	 * @return void
	 */
	public function w3tc_support_send_details() {
		$c = Dispatcher::config();

		$post = array();

		foreach ( $_GET as $p => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post[ $p ] = Util_Request::get( $p );
		}

		$post['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$post['version']    = W3TC_VERSION;

		$license_level = 'community';
		if ( Util_Environment::is_w3tc_pro( $c ) ) {
			$license_level = 'pro';
		}

		$post['license_level'] = $license_level . ' ' . $c->get_string( 'plugin.license_key' );

		// Add attachments.
		$attachments = array();

		$attach_files = array(
			Util_Environment::wp_config_path(),
			Util_Rule::get_pgcache_rules_core_path(),
			Util_Rule::get_pgcache_rules_cache_path(),
			Util_Rule::get_browsercache_rules_cache_path(),
			Util_Rule::get_minify_rules_core_path(),
			Util_Rule::get_minify_rules_cache_path(),
		);

		// Attach config files.
		$handle = opendir( W3TC_CONFIG_DIR );
		if ( $handle ) {
			$entry = @readdir( $handle );
			while ( false !== $entry ) {
				if ( '.' === $entry || '..' === $entry || 'index.html' === $entry ) {
					$entry = @readdir( $handle );
					continue;
				}

				$attach_file[] = W3TC_CONFIG_DIR . '/' . $entry;

				$entry = @readdir( $handle );
			}
			closedir( $handle );
		}

		foreach ( $attach_files as $attach_file ) {
			if ( $attach_file && file_exists( $attach_file ) && ! in_array( $attach_file, $attachments, true ) ) {
				$attachments[] = array(
					'filename' => basename( $attach_file ),
					'content'  => file_get_contents( $attach_file ),
				);
			}
		}

		// Attach server info.
		$server_info = print_r( $this->get_server_info(), true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$server_info = str_replace( "\n", "\r\n", $server_info );

		$attachments[] = array(
			'filename' => 'server_info.txt',
			'content'  => $server_info,
		);

		// Attach phpinfo.
		ob_start();
		phpinfo();
		$php_info = ob_get_contents();
		ob_end_clean();

		$attachments[] = array(
			'filename' => 'php_info.html',
			'content'  => $php_info,
		);

		// Attach self-test.
		ob_start();
		$this->self_test();
		$self_test = ob_get_contents();
		ob_end_clean();

		$attachments[] = array(
			'filename' => 'self_test.html',
			'content'  => $self_test,
		);

		$post['attachments'] = $attachments;

		$response = wp_remote_post(
			W3TC_SUPPORT_REQUEST_URL,
			array(
				'body'    => $post,
				'timeout' => $c->get_integer( 'timelimit.email_send' ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$result = 200 === $response['response']['code'] && 'ok' === $response['body'];
		} else {
			$result = false;
		}

		echo $result ? 'ok' : 'error';
	}

	/**
	 * Performs a self-test to validate the current environment.
	 *
	 * @return void
	 */
	private function self_test() {
		include W3TC_INC_DIR . '/lightbox/self_test.php';
	}

	/**
	 * Retrieves detailed information about the server environment.
	 *
	 * @return array An array containing server, WordPress, and MySQL environment details.
	 */
	private function get_server_info() {
		global $wp_version, $wp_db_version, $wpdb;

		$wordpress_plugins        = get_plugins();
		$wordpress_plugins_active = array();

		foreach ( $wordpress_plugins as $wordpress_plugin_file => $wordpress_plugin ) {
			if ( is_plugin_active( $wordpress_plugin_file ) ) {
				$wordpress_plugins_active[ $wordpress_plugin_file ] = $wordpress_plugin;
			}
		}

		$mysql_version          = $wpdb->get_var( 'SELECT VERSION()' );
		$mysql_variables_result = (array) $wpdb->get_results( 'SHOW VARIABLES', ARRAY_N );
		$mysql_variables        = array();

		foreach ( $mysql_variables_result as $mysql_variables_row ) {
			$mysql_variables[ $mysql_variables_row[0] ] = $mysql_variables_row[1];
		}

		$server_info = array(
			'w3tc'  => array(
				'version'              => W3TC_VERSION,
				'server'               => ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' ),
				'dir'                  => W3TC_DIR,
				'cache_dir'            => W3TC_CACHE_DIR,
				'blog_id'              => Util_Environment::blog_id(),
				'home_domain_root_url' => Util_Environment::home_domain_root_url(),
				'home_url_maybe_https' => Util_Environment::home_url_maybe_https(),
				'site_path'            => Util_Environment::site_path(),
				'document_root'        => Util_Environment::document_root(),
				'site_root'            => Util_Environment::site_root(),
				'site_url_uri'         => Util_Environment::site_url_uri(),
				'home_url_host'        => Util_Environment::home_url_host(),
				'home_url_uri'         => Util_Environment::home_url_uri(),
				'network_home_url_uri' => Util_Environment::network_home_url_uri(),
				'host_port'            => Util_Environment::host_port(),
				'host'                 => Util_Environment::host(),
				'wp_config_path'       => Util_Environment::wp_config_path(),
			),
			'wp'    => array(
				'version'     => $wp_version,
				'db_version'  => $wp_db_version,
				'abspath'     => ABSPATH,
				'home'        => get_option( 'home' ),
				'siteurl'     => get_option( 'siteurl' ),
				'email'       => get_option( 'admin_email' ),
				'upload_info' => (array) Util_Http::upload_info(),
				'theme'       => Util_Theme::get_current_theme(),
				'wp_cache'    => ( ( defined( 'WP_CACHE' ) && WP_CACHE ) ? 'true' : 'false' ),
				'plugins'     => $wordpress_plugins_active,
			),
			'mysql' => array(
				'version'   => $mysql_version,
				'variables' => $mysql_variables,
			),
		);

		return $server_info;
	}
}
