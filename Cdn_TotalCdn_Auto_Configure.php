<?php
/**
 * File: Cdn_TotalCdn_Auto_Configure.php
 *
 * @since   SINCEVERSION
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_TotalCdn_Auto_Configure
 *
 * @since SINCEVERSION
 */
class Cdn_TotalCdn_Auto_Configure {

	/**
	 * Configuration.
	 *
	 * @var array
	 *
	 * @since SINCEVERSION
	 */
	protected $config = array();

	/**
	 * Api key.
	 *
	 * @var string
	 *
	 * @since SINCEVERSION
	 */
	protected $api_key = '';

	/**
	 * Constructor.
	 *
	 * @since SINCEVERSION
	 *
	 * @param array $config Configuration.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * Runs the auto-configuration process.
	 *
	 * @since SINCEVERSION
	 *
	 * @return bool True on success, false on failure.
	 */
	public function run() {
		// 1. Check and verify that the Total CDN account API key is set.
		if ( ! $this->check_api_key() ) {
			error_log( 'Total CDN API key is not set.' );
			return false;
		}

		// 2. Setup Pull Zone.
		if ( ! $this->setup_pull_zone() ) {
			error_log( 'Failed to create pull zone.' );
			return false;
		}

		// 4. Setup the Edge Rules.
		$this->setup_edge_rules();

		// 5. Enable the CDN.
		$this->enable_cdn();
	}

	/**
	 * Check API Key
	 *
	 * Checks that the API key is set in the configs.
	 *
	 * @since SINCEVERSION
	 */
	public function check_api_key() {
		$api_key = $this->config->get( 'cdn.totalcdn.account_api_key' );

		if ( empty( $api_key ) ) {
			return false;
		}

		$this->api_key = $api_key;

		return true;
	}

	/**
	 * Setup the pull zone.
	 *
	 * Creates a pull zone using the Total CDN API.
	 *
	 * @since SINCEVERSION
	 */
	public function setup_pull_zone() {
		$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $this->api_key ) );

		// Origin URL is the URL of the current site.
		$origin_url       = \home_url();
		// Pull site's domain with periods turned into hyphens.
		$name             = \str_replace( '.', '-', \parse_url( $origin_url, PHP_URL_HOST ) );

		// Try to create a new pull zone.
		try {
			$response = $api->add_pull_zone(
				array(
					'Name'                  => $name, // The name/hostname for the pull zone where the files will be accessible; only letters, numbers, and dashes.
					'OriginUrl'             => $origin_url, // Origin URL or IP (with optional port number).
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

			error_log( 'Pull Zone Created: ' . json_encode( $response ) ) ;

			$pull_zone_id = (int) $response['Id'];
			$name         = $response['Name'];
			$cdn_hostname = $response['ZoneDomain'];

			$config->set( 'cdn.totalcdn.pull_zone_id', $pull_zone_id );
			$config->set( 'cdn.totalcdn.name', $name );
			$config->set( 'cdn.totalcdn.origin_url', $origin_url );
			$config->set( 'cdn.totalcdn.cdn_hostname', $cdn_hostname );
			$config->save();

			return true;

		} catch ( \Exception $ex ) {
			error_log( 'Failed to create pull zone: ' . $ex->getMessage() );
			return false;
		}
	}

	/**
	 * Setup Edge Rules.
	 *
	 * Sets up the edge rules for the pull zone.
	 *
	 * @since SINCEVERSION
	 */
	public function setup_edge_rules() {
		$api = new Cdn_TotalCdn_Api( array( 'account_api_key' => $this->api_key ) );

		// Get the pull zone ID.
		$pull_zone_id = $this->config->get( 'cdn.totalcdn.pull_zone_id' );

		if ( empty( $pull_zone_id ) ) {
			error_log( 'Pull zone ID is not set.' );
			return false;
		}

		$error_messages = array();

		// Add Edge Rules.
		foreach ( Cdn_TotalCdn_Api::get_default_edge_rules() as $edge_rule ) {
			try {
				$api->add_edge_rule( $edge_rule, $pull_zone_id );
			} catch ( \Exception $ex ) {
				$error_messages[] = sprintf(
					// translators: 1: Edge Rule description/name.
					\__( 'Could not add Edge Rule "%1$s".', 'w3-total-cache' ) . '; ',
					\esc_html( $edge_rule['Description'] )
				) . $ex->getMessage();
			}
		}

		if ( ! empty( $error_messages ) ) {
			error_log( 'Failed to add edge rules: ' . implode( ', ', $error_messages ) );
			return false;
		}

		return true;
	}

	/**
	 * Enable CDN.
	 *
	 * Enables the CDN in the W3TC settings.
	 * 
	 * @since SINCEVERSION
	 */
	public function enable_cdn() {
		// Enable CDN in W3TC settings.
		$this->config->set( 'cdn.enabled', true );
		$this->config->set( 'cdn.engine', 'totalcdn' );
		$this->config->save();

		error_log( 'CDN enabled successfully.' );
	}

}
