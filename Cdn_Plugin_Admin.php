<?php
/**
 * File: Cdn_Plugin_Admin.php
 *
 * @since   0.9.5.4
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Plugin_Admin
 */
class Cdn_Plugin_Admin {
	/**
	 * Runs the CDN plugin by setting up various hooks and filters.
	 *
	 * @return void
	 */
	public function run() {
		$config_labels = new Cdn_ConfigLabels();
		\add_filter( 'w3tc_config_labels', array( $config_labels, 'config_labels' ) );

		$c          = Dispatcher::config();
		$cdn_engine = $c->get_string( 'cdn.engine' );

		\add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		\add_action( 'w3tc_ajax_cdn_totalcdn_fsd_enable_notice', array( $this, 'w3tc_ajax_cdn_totalcdn_fsd_enable_notice' ) );
		\add_action( 'w3tc_ajax_cdn_totalcdn_fsd_disable_notice', array( $this, 'w3tc_ajax_cdn_totalcdn_fsd_disable_notice' ) );

		if ( $c->get_boolean( 'cdn.enabled' ) ) {
			$admin_notes = new Cdn_AdminNotes();
			\add_filter( 'w3tc_notes', array( $admin_notes, 'w3tc_notes' ) );
			\add_filter( 'w3tc_errors', array( $admin_notes, 'w3tc_errors' ) );

			if ( $c->get_boolean( 'cdn.admin.media_library' ) && $c->get_boolean( 'cdn.uploads.enable' ) ) {
				\add_filter( 'wp_get_attachment_url', array( $this, 'wp_get_attachment_url' ), 0 );
				\add_filter( 'attachment_link', array( $this, 'wp_get_attachment_url' ), 0 );
			}
		}

		// Always show the Total CDN widget on dashboard.
		\add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Cdn_TotalCdn_Widget', 'admin_init_w3tc_dashboard' ) );

		// Attach to actions without firing class loading at all without need.
		switch ( $cdn_engine ) {
			case 'google_drive':
				\add_action( 'w3tc_settings_cdn_boxarea_configuration', array( '\W3TC\Cdn_GoogleDrive_Page', 'w3tc_settings_cdn_boxarea_configuration' ) );
				break;

			case 'rackspace_cdn':
				\add_filter( 'w3tc_admin_actions', array( '\W3TC\Cdn_RackSpaceCdn_Page', 'w3tc_admin_actions' ) );
				\add_action( 'w3tc_ajax', array( '\W3TC\Cdn_RackSpaceCdn_Popup', 'w3tc_ajax' ) );
				\add_action( 'w3tc_settings_cdn_boxarea_configuration', array( '\W3TC\Cdn_RackSpaceCdn_Page', 'w3tc_settings_cdn_boxarea_configuration' ) );
				break;

			case 'rscf':
				\add_action( 'w3tc_ajax', array( '\W3TC\Cdn_RackSpaceCloudFiles_Popup', 'w3tc_ajax' ) );
				\add_action( 'w3tc_settings_cdn_boxarea_configuration', array( '\W3TC\Cdn_RackSpaceCloudFiles_Page', 'w3tc_settings_cdn_boxarea_configuration' ) );
				break;

			case 'bunnycdn':
				\add_action( 'w3tc_ajax', array( '\W3TC\Cdn_BunnyCdn_Page', 'w3tc_ajax' ) );
				\add_action( 'w3tc_ajax', array( '\W3TC\Cdn_BunnyCdn_Popup', 'w3tc_ajax' ) );
				\add_action( 'w3tc_settings_cdn_boxarea_configuration', array( '\W3TC\Cdn_BunnyCdn_Page', 'w3tc_settings_cdn_boxarea_configuration' ) );
				\add_action( 'w3tc_purge_urls_box', array( '\W3TC\Cdn_BunnyCdn_Page', 'w3tc_purge_urls_box' ) );
				break;

			case 'totalcdn':
				\add_action( 'w3tc_ajax', array( '\W3TC\Cdn_TotalCdn_Page', 'w3tc_ajax' ) );
				\add_action( 'w3tc_ajax', array( '\W3TC\Cdn_TotalCdn_Popup', 'w3tc_ajax' ) );
				\add_action( 'w3tc_settings_cdn_boxarea_configuration', array( '\W3TC\Cdn_TotalCdn_Page', 'w3tc_settings_cdn_boxarea_configuration' ) );
				\add_action( 'w3tc_purge_urls_box', array( '\W3TC\Cdn_TotalCdn_Page', 'w3tc_purge_urls_box' ) );
				\add_filter( 'w3tc_dashboard_actions', array( '\W3TC\Cdn_TotalCdn_Page', 'total_cdn_dashboard_actions' ) );
				\add_action( 'w3tc_flush_all', array( $this, 'flush_cdn' ) );
				break;
		}

		\add_action( 'w3tc_settings_general_boxarea_cdn', array( $this, 'w3tc_settings_general_boxarea_cdn' ) );

		$w3tc_cdn_auto_configure = new Cdn_TotalCdn_Auto_Configure( Dispatcher::config() );

		\add_filter( 'w3tc_totalcdn_auto_configured', array( $w3tc_cdn_auto_configure, 'w3tc_totalcdn_auto_configured' ), 10, 1 );
		\add_action( 'w3tc_ajax_cdn_totalcdn_auto_config', array( $w3tc_cdn_auto_configure, 'w3tc_ajax_cdn_totalcdn_auto_config' ) );
		\add_action( 'w3tc_ajax_cdn_totalcdn_confirm_auto_config', array( $w3tc_cdn_auto_configure, 'w3tc_ajax_cdn_totalcdn_confirm_auto_config' ) );
	}

	/**
	 * Flushes the CDN cache when all caches are flushed.
	 *
	 * @param array $extras Optional extra parameters to pass to the CDN flush.
	 *
	 * @return bool True on success, false on failure.
	 * @since  x.x.x
	 */
	public function flush_cdn( $extras = array() ) {
		$cacheflush = Dispatcher::component( 'CacheFlush' );
		return $cacheflush->cdn_purge_all( $extras );
	}

	/**
	 * Enqueue admin scripts for the CDN general settings modal.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		$page_val = Util_Request::get_string( 'page' );

		if ( 'w3tc_general' !== $page_val ) {
			return;
		}

		wp_enqueue_script(
			'w3tc-cdn-totalcdn-fsd-popup',
			plugins_url( 'Cdn_TotalCdn_FsdPopup.js', W3TC_FILE ),
			array( 'jquery', 'w3tc-lightbox' ),
			W3TC_VERSION,
			false
		);
	}

	/**
	 * Adds configuration options for CDN settings in the general settings box area.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_cdn() {
		$config             = Dispatcher::config();
		$state              = Dispatcher::config_state();
		$engine_optgroups   = array();
		$engine_values      = array();
		$optgroup_rec       = count( $engine_optgroups );
		$engine_optgroups[] = \__( 'Recommended CDN:', 'w3-total-cache' );
		$optgroup_pull      = count( $engine_optgroups );
		$engine_optgroups[] = \__( 'Origin Pull / Mirror:', 'w3-total-cache' );
		$optgroup_push      = count( $engine_optgroups );
		$engine_optgroups[] = \__( 'Origin Push:', 'w3-total-cache' );

		$engine_values[''] = array(
			'label' => 'Select a provider',
		);

		$tcdn_status               = $state->get_string( 'cdn.totalcdn.status' );
		$engine_values['totalcdn'] = array(
			'label'    => esc_html( W3TC_CDN_NAME ),
			'disabled' => strpos( $tcdn_status, 'active' ) === 0 ? false : true,
			'optgroup' => $optgroup_rec,
		);

		$engine_values['akamai'] = array(
			'label'    => \__( 'Akamai', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['cf2'] = array(
			'label'    => \__( 'Amazon CloudFront', 'w3-total-cache' ),
			'disabled' => ! Util_Installed::curl() ? true : null,
			'optgroup' => $optgroup_pull,
		);

		$engine_values['att'] = array(
			'label'    => \__( 'AT&amp;T', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['bunnycdn'] = array(
			'label'    => \__( 'Bunny CDN', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['cotendo'] = array(
			'label'    => \__( 'Cotendo (Akamai)', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['mirror'] = array(
			'label'    => \__( 'Generic Mirror', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['rackspace_cdn'] = array(
			'label'    => \__( 'RackSpace CDN', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['edgecast'] = array(
			'label'    => \__( 'Verizon Digital Media Services (EdgeCast) / Media Temple ProCDN', 'w3-total-cache' ),
			'optgroup' => $optgroup_pull,
		);

		$engine_values['cf'] = array(
			'disabled' => ! Util_Installed::curl() ? true : null,
			'label'    => \__( 'Amazon CloudFront Over S3', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['s3'] = array(
			'disabled' => ! Util_Installed::curl() ? true : null,
			'label'    => \__( 'Amazon Simple Storage Service (S3)', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['s3_compatible'] = array(
			'disabled' => ! Util_Installed::curl() ? true : null,
			'label'    => \__( 'Amazon Simple Storage Service (S3) Compatible', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['google_drive'] = array(
			'label'    => \__( 'Google Drive', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['azure'] = array(
			'label'    => \__( 'Microsoft Azure Storage', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['azuremi'] = array(
			'disabled' => empty( getenv( 'APPSETTING_WEBSITE_SITE_NAME' ) ),
			'label'    => \__( 'Microsoft Azure Storage (Managed Identity)', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['rscf'] = array(
			'disabled' => ! Util_Installed::curl() ? true : null,
			'label'    => \__( 'Rackspace Cloud Files', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$engine_values['ftp'] = array(
			'disabled' => ! Util_Installed::ftp() ? true : null,
			'label'    => \__( 'Self-hosted / File Transfer Protocol Upload', 'w3-total-cache' ),
			'optgroup' => $optgroup_push,
		);

		$cdn_enabled = $config->get_boolean( 'cdn.enabled' );
		$cdn_engine  = $config->get_string( 'cdn.engine' );

		include W3TC_DIR . '/Cdn_GeneralPage_View.php';
	}

	/**
	 * Popup modal for Total CDN FSD enablement steps.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_fsd_enable_notice() {
		include W3TC_DIR . '/Cdn_TotalCdn_FsdEnablePopup_View.php';
	}

	/**
	 * Popup modal for Total CDN FSD disablement reminder.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_fsd_disable_notice() {
		include W3TC_DIR . '/Cdn_TotalCdn_FsdDisablePopup_View.php';
	}

	/**
	 * Filters the attachment URL for the WordPress admin area based on CDN settings.
	 *
	 * @param string $url The URL of the attachment.
	 *
	 * @return string The filtered URL of the attachment.
	 */
	public function wp_get_attachment_url( $url ) {
		if ( defined( 'WP_ADMIN' ) ) {
			$url = trim( $url );

			if ( ! empty( $url ) ) {
				$parsed          = \wp_parse_url( $url );
				$uri             = ( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) .
					( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
				$wp_upload_dir   = \wp_upload_dir();
				$upload_base_url = $wp_upload_dir['baseurl'];

				if ( \substr( $url, 0, strlen( $upload_base_url ) ) === $upload_base_url ) {
					$common  = Dispatcher::component( 'Cdn_Core' );
					$new_url = $common->url_to_cdn_url( $url, $uri );
					if ( ! is_null( $new_url ) ) {
						$url = $new_url;
					}
				}
			}
		}

		return $url;
	}
}
