<?php
/**
 * File: Cdn_TotalCdn_Auto_Configure.php
 *
 * @since   x.x.x
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_TotalCdn_Auto_Configure
 *
 * @since x.x.x
 */
class Cdn_TotalCdn_Auto_Configure {

	/**
	 * Configuration.
	 *
	 * @var Config
	 *
	 * @since x.x.x
	 */
	protected $config;

	/**
	 * Api key.
	 *
	 * @var string
	 *
	 * @since x.x.x
	 */
	protected $api_key = '';

	/**
	 * Api.
	 *
	 * @var Cdn_TotalCdn_Api
	 *
	 * @since x.x.x
	 */
	protected $api;

	/**
	 * Account ID.
	 *
	 * @var string
	 *
	 * @since x.x.x
	 */
	protected $account_id = '';

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 *
	 * @param Config $config Configuration.
	 *
	 * @return void
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Handles the AJAX request to confirm auto-configuration.
	 *
	 * This method retrieves the account API key and pull zone ID from the
	 * configuration and renders the confirmation page.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_confirm_auto_config() {
		$result = $this->run();
		?>
		<form class="w3tc_cdn_totalcdn_form">
			<div class="metabox-holder">
				<?php
				Util_Ui::postbox_header(
					\sprintf(
						'%1$s %2$s',
						\esc_attr( W3TC_CDN_NAME ),
						\esc_html__( 'Auto-Configuration', 'w3-total-cache' )
					)
				);
				?>
				<input
					type="hidden"
					class="cdn-totalcdn-auto-config result-success"
					value="<?php echo \esc_attr( $result['success'] ? 'true' : 'false' ); ?>" />
				<div style="text-align: center">
					<p class="cdn-totalcdn-auto-config result-message">
						<?php echo \esc_html( $result['message'] ); ?>
					</p>
				</div>
			</div>
		</form>
		<?php
		\wp_die();
	}

	/**
	 * Handles the AJAX request to auto-configure.
	 *
	 * This method retrieves the account API key and pull zone ID from the
	 * configuration and renders the auto-configuration page.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_auto_config() {
		?>
		<p></p>
		<?php
		\wp_die();
	}

	/**
	 * Check and see if Total CDN is active and enabled.
	 * If it is not, run auto configuration.
	 *
	 * @param bool $applied Whether the CDN is applied or not.
	 *
	 * @return bool True if the CDN is auto-configured, false otherwise.
	 *
	 * @since x.x.x
	 */
	public function w3tc_totalcdn_auto_configured( $applied ) {
		// Check if the CDN is enabled, and the pull zone is configured.
		if ( $this->config->get( 'cdn.enabled' ) && 'totalcdn' === $this->config->get( 'cdn.engine' ) && $this->config->get( 'cdn.totalcdn.pull_zone_id' ) ) {
			return true;
		}

		$auto_configured = $this->run();

		if ( ! $auto_configured['success'] ) {
			// If auto-configuration failed, return false.
			return false;
		}

		// Return the original value.
		return $applied;
	}

	/**
	 * Runs the auto-configuration process.
	 *
	 * @since x.x.x
	 *
	 * @return string Response Message.
	 */
	public function run() {
		// Check and verify that the account API key is set.
		$api_key_result = $this->check_api_key();
		if ( false === $api_key_result['success'] ) {
			return $api_key_result;
		}

		// Setup Pull Zone.
		$setup_pullzone_result = $this->setup_pull_zone();
		if ( false === $setup_pullzone_result['success'] ) {
			return $setup_pullzone_result;
		}

		// Enable the CDN and return the result.
		return $this->enable_cdn();
	}

	/**
	 * Check API Key
	 *
	 * Checks that the API key is set in the configs.
	 *
	 * @since x.x.x
	 */
	public function check_api_key() {
		$api_key = $this->config->get( 'cdn.totalcdn.account_api_key' );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => \sprintf(
					// translators: 1: CDN name.
					\__( 'API key is not set. Please enter your %1$s API key.', 'w3-total-cache' ),
					\esc_html( W3TC_CDN_NAME )
				),
			);
		}

		$this->api_key = $api_key;

		$this->api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $this->api_key ) );

		try {
			$response = $this->api->get_user();
		} catch ( \Exception $ex ) {
			return array(
				'success' => false,
				'message' => \sprintf(
					// translators: 1: Error message.
					\__( 'Failed to verify API key: %1$s', 'w3-total-cache' ),
					$ex->getMessage()
				),
			);
		}

		$this->account_id = $response['AccountId'];

		return array(
			'success' => true,
			'message' => \__( 'API key is set.', 'w3-total-cache' ),
		);
	}

	/**
	 * Setup the pull zone.
	 *
	 * Creates a pull zone using the Total CDN API.
	 *
	 * @since x.x.x
	 */
	public function setup_pull_zone() {
		$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $this->api_key ) );

		// Origin URL is the URL of the current site.
		$origin_url = \home_url();

		// Origin URL host.
		$origin_url_host = \wp_parse_url( $origin_url, PHP_URL_HOST );

		// Pull site's domain with periods turned into hyphens.
		$name = \str_replace( '.', '-', $origin_url_host ) . '-' . \hash( 'crc32b', $this->api_key );

		// List all existing pull zones to check if the pull zone already exists.
		try {
			$pull_zones = $api->list_pull_zones();

			foreach ( $pull_zones as $pull_zone ) {
				if ( $pull_zone['Name'] === $name ) {
						$pull_zone_id = (int) $pull_zone['Id'];
						$name         = $pull_zone['Name'];
						$cdn_hostname = $pull_zone['ExtCdnDomain'];

						$this->config->set( 'cdn.totalcdn.pull_zone_id', $pull_zone_id );
						$this->config->set( 'cdn.totalcdn.name', $name );
						$this->config->set( 'cdn.totalcdn.origin_url', $origin_url );
						$this->config->set( 'cdn.totalcdn.cdn_hostname', $cdn_hostname );
						$this->config->save();
					return array(
						'success' => true,
						'message' => \sprintf(
							// translators: 1: Pull Zone ID, 2: CDN Hostname.
							\__( 'Pull zone already exists. Pull Zone ID: %1$s, CDN Hostname: %2$s', 'w3-total-cache' ),
							$pull_zone['Id'],
							$pull_zone['ExtCdnDomain']
						),
					);
				}
			}
		} catch ( \Exception $ex ) {
			return array(
				'success' => false,
				'message' => \sprintf(
					// translators: 1: Error message.
					\__( 'Failed to list pull zones: %1$s', 'w3-total-cache' ),
					$ex->getMessage()
				),
			);
		}

		// Try to create a new pull zone.
		try {
			$response = $api->add_pull_zone(
				array(
					'Name'                  => $name, // The name/hostname for the pull zone where the files will be accessible; only letters, numbers, and dashes.
					'OriginUrl'             => $origin_url, // Origin URL or IP (with optional port number).
					'AccountId'             => $this->account_id, // Account ID.
					'CacheErrorResponses'   => true, // If enabled, total.net will temporarily cache error responses (304+ HTTP status codes) from your servers for 5 seconds to prevent DDoS attacks on your origin. If disabled, error responses will be set to no-cache.
					'DisableCookies'        => false, // Determines if the Pull Zone should automatically remove cookies from the responses.
					'EnableTLS1'            => false, // TLS 1.0 was deprecated in 2018.
					'EnableTLS1_1'          => false, // TLS 1.1 was EOL's on March 31,2020.
					'ErrorPageWhitelabel'   => true, // Any total.net branding will be removed from the error page and replaced with a generic term.
					'OriginHostHeader'      => \wp_parse_url( \home_url(), PHP_URL_HOST ), // Sets the host header that will be sent to the origin.
					'UseStaleWhileUpdating' => true, // Serve stale content while updating.  If Stale While Updating is enabled, cache will not be refreshed if the origin responds with a non-cacheable resource.
					'UseStaleWhileOffline'  => true, // Serve stale content if the origin is offline.
				)
			);

			$pull_zone_id = (int) $response['Id'];
			$name         = $response['Name'];
			$cdn_hostname = $response['ExtCdnDomain'];

			$this->config->set( 'cdn.totalcdn.pull_zone_id', $pull_zone_id );
			$this->config->set( 'cdn.totalcdn.name', $name );
			$this->config->set( 'cdn.totalcdn.origin_url', $origin_url );
			$this->config->set( 'cdn.totalcdn.cdn_hostname', $cdn_hostname );
			$this->config->save();

			$setup_edge_rules_result = $this->setup_edge_rules();

			if ( false === $setup_edge_rules_result['success'] ) {
				return $setup_edge_rules_result;
			}

			return array(
				'success' => true,
				'message' => \sprintf(
					// translators: 1: Pull Zone ID, 2: CDN Hostname.
					\__( 'Pull zone created successfully. Pull Zone ID: %1$s, CDN Hostname: %2$s', 'w3-total-cache' ),
					$pull_zone_id,
					$cdn_hostname
				),
			);

		} catch ( \Exception $ex ) {
			return array(
				'success' => false,
				'message' => \sprintf(
					// translators: 1: Error message.
					\__( 'Failed to create pull zone: %1$s', 'w3-total-cache' ),
					$ex->getMessage()
				),
			);
		}
	}

	/**
	 * Setup Edge Rules.
	 *
	 * Sets up the edge rules for the pull zone.
	 *
	 * @since x.x.x
	 */
	public function setup_edge_rules() {
		$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $this->api_key ) );

		// Get the pull zone ID.
		$pull_zone_id = $this->config->get( 'cdn.totalcdn.pull_zone_id' );

		if ( empty( $pull_zone_id ) ) {
			return array(
				'success' => false,
				'message' => \sprintf(
					// translators: 1: Error message.
					\__( 'Pull zone ID is not set. Please create a pull zone first.', 'w3-total-cache' ),
					$pull_zone_id
				),
			);
		}

		$error_messages = array();

		// Add Edge Rules.
		foreach ( Cdn_TotalCdn_Api::get_default_edge_rules() as $edge_rule ) {
			try {
				$api->add_edge_rule( $edge_rule, $pull_zone_id );
			} catch ( \Exception $ex ) {
				$error_messages[] = \sprintf(
					// translators: 1: Edge Rule description/name.
					\__( 'Could not add Edge Rule "%1$s".', 'w3-total-cache' ) . '; ',
					\esc_html( $edge_rule['Description'] )
				) . $ex->getMessage();
			}
		}

		if ( ! empty( $error_messages ) ) {
			return array(
				'success' => false,
				'message' => \sprintf(
					// translators: 1: Error message.
					\__( 'Failed to add edge rules: %1$s', 'w3-total-cache' ),
					implode( ', ', $error_messages )
				),
			);
		}

		return array(
			'success' => true,
			'message' => \__( 'Edge rules set up successfully.', 'w3-total-cache' ),
		);
	}

	/**
	 * Enable CDN.
	 *
	 * Enables the CDN in the W3TC settings.
	 *
	 * @since x.x.x
	 */
	public function enable_cdn() {
		// Enable CDN in W3TC settings.
		$this->config->set( 'cdn.enabled', true );
		$this->config->set( 'cdn.engine', 'totalcdn' );
		$this->config->save();

		return array(
			'success' => true,
			'message' => \__( 'CDN enabled successfully.', 'w3-total-cache' ),
		);
	}

	/**
	 * Admin Notices.
	 *
	 * Displays admin notices for CDN Auto Configuration
	 * Issues.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function admin_notices() {
		$config = Dispatcher::config();
		$state  = Dispatcher::config_state();

		$cdn_enabled = $config->get_boolean( 'cdn.enabled' );
		$cdn_engine  = $config->get_string( 'cdn.engine' );
		$api_key     = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$tcdn_status = $state->get_string( 'cdn.totalcdn.status' );

		// If CDN is not enabled or the engine is not Total CDN and the API key IS set
		// then show a notice to the user that they need to enable the CDN.

		if ( self::maybe_show_auto_config_notice( $cdn_enabled, $cdn_engine, $api_key, $tcdn_status ) ) {
			return;
		} elseif ( ! $cdn_enabled || 'totalcdn' !== $cdn_engine ) {
			return;
		}

		// Check if the CDN is not authorized.
		$cdn_core          = new Cdn_Core();
		$is_cdn_authorized = $cdn_core->is_cdn_authorized();

		if ( ! $is_cdn_authorized ) {
			return;
		}

		// Check if the current site url matches the pullzone.
		$origin_url       = $config->get( 'cdn.totalcdn.origin_url' );
		$current_site_url = \home_url();

		if ( $origin_url !== $current_site_url ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					// Print a message indication that the pull zone and url do not match, and ask if they want to update it. Provide a Yes button and a No button.
					echo \wp_kses_post(
						\sprintf(
							// translators: 1: CDN name, 2: Pull zone URL, 3: Current site URL, 4: Update pull zone URL link.
							\__( '<p>The %1$s pull zone URL <strong>( %2$s )</strong> does not match your current Site URL <strong>( %3$s )</strong>.</p><p><a class="button button-secondary" href="%4$s">Click here to update pull zone URL</a></p>', 'w3-total-cache' ),
							\esc_html( W3TC_CDN_NAME ),
							\esc_html( $origin_url ),
							\esc_html( $current_site_url ),
							\wp_nonce_url( 'admin.php?page=w3tc_cdn&w3tc_cdn_update_w3tc_cdn_pullzone', 'w3tc' )
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Maybe show auto config notice.
	 *
	 * If the CDN is not enabled, or if the engine is not set to the Total CDN,
	 * and the API key is set, then show a notice to the user that they have
	 * an active Total CDN account and provide a button to auto-configure it.
	 *
	 * @since x.x.x
	 *
	 * @param bool   $cdn_enabled Whether the CDN is enabled.
	 * @param string $cdn_engine  The CDN engine.
	 * @param string $api_key     The API key.
	 * @param string $cdn_status The CDN status.
	 *
	 * @return bool True if the notice was shown, false otherwise.
	 */
	public static function maybe_show_auto_config_notice( $cdn_enabled, $cdn_engine, $api_key, $cdn_status ) {
		// If the CDN is enabled and the engine is set, do not show the notice.
		if ( $cdn_enabled && 'totalcdn' === $cdn_engine ) {
			return false;
		}

		// If the API key is not set, do not show the notice.
		if ( empty( $api_key ) ) {
			return false;
		}

		// If the CDN status is not set, do not show the notice.
		if ( empty( $cdn_status ) || 'active' !== $cdn_status ) {
			return false;
		}

		// Show the auto-configure notice.
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-warning is-dismissible">';
							echo '<p>' . \sprintf(
								// translators: 1: CDN name.
								\esc_html__( 'You have an active %1$s account. Click the button below to auto-configure it.', 'w3-total-cache' ),
								\esc_html( W3TC_CDN_NAME )
							) . '</p>';
							echo '<p><button class="button button-primary button-auto-tcdn">' . \sprintf(
								// translators: 1: CDN name.
								\esc_html__( 'Auto-Configure %1$s', 'w3-total-cache' ),
								\esc_html( W3TC_CDN_NAME )
							) . '</button></p>';
				echo '</div>';
			}
		);

		return true;
	}

	/**
	 * Updates the pull zone URL and Origin Host Header.
	 *
	 * @since x.x.x
	 */
	public static function update_pullzone() {
		$config = Dispatcher::config();

		// Get the pull zone ID.
		$pull_zone_id = $config->get( 'cdn.totalcdn.pull_zone_id' );

		try {
			$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $config->get( 'cdn.totalcdn.account_api_key' ) ) );
			$api->update_pull_zone(
				$pull_zone_id,
				array(
					'OriginUrl'        => \home_url(),
					'OriginHostHeader' => \wp_parse_url( \home_url(), PHP_URL_HOST ),
				)
			);
			$config->set( 'cdn.totalcdn.origin_url', \home_url() );
			$config->set( 'cdn.totalcdn.cdn_hostname', \wp_parse_url( \home_url(), PHP_URL_HOST ) );
			$config->save();
			return true;
		} catch ( \Exception $ex ) {
			return false;
		}
	}
}
