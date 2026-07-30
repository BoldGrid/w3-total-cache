<?php
/**
 * File: Cdn_Plugin_Admin.php
 *
 * @since   2.0.0
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

		$w3tc_c             = Dispatcher::config();
		$w3tc_cdn_engine    = $w3tc_c->get_string( 'cdn.engine' );
		$w3tc_cdnfsd_engine = $w3tc_c->get_string( 'cdnfsd.engine' );
		$is_cdn_page        = 'w3tc_cdn' === Util_Request::get_string( 'page' );

		if ( $w3tc_c->get_boolean( 'cdn.enabled' ) ) {
			$admin_notes = new Cdn_AdminNotes();
			\add_filter( 'w3tc_notes', array( $admin_notes, 'w3tc_notes' ) );
			\add_filter( 'w3tc_errors', array( $admin_notes, 'w3tc_errors' ) );

			if ( $w3tc_c->get_boolean( 'cdn.admin.media_library' ) && $w3tc_c->get_boolean( 'cdn.uploads.enable' ) ) {
				\add_filter( 'wp_get_attachment_url', array( $this, 'wp_get_attachment_url' ), 0 );
				\add_filter( 'attachment_link', array( $this, 'wp_get_attachment_url' ), 0 );
			}
		}

		// Always show the Bunny CDN widget on dashboard.
		\add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Cdn_BunnyCdn_Widget', 'admin_init_w3tc_dashboard' ) );

		// Attach to actions without firing class loading at all without need.
		switch ( $w3tc_cdn_engine ) {
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
				\add_action( 'w3tc_ajax_cdn_bunnycdn_widgetdata', array( '\W3TC\Cdn_BunnyCdn_Widget', 'w3tc_ajax_cdn_bunnycdn_widgetdata' ) );
				if ( $is_cdn_page && $w3tc_c->get_boolean( 'cdn.enabled' ) ) {
					\add_action( 'w3tc_purge_urls_box', array( '\W3TC\Cdn_BunnyCdn_Page', 'w3tc_purge_urls_box' ) );
				}
				break;

			default:
				\add_action( 'admin_init_w3tc_dashboard', array( '\W3TC\Cdn_BunnyCdn_Widget', 'admin_init_w3tc_dashboard' ) );
				\add_action( 'w3tc_ajax_cdn_bunnycdn_widgetdata', array( '\W3TC\Cdn_BunnyCdn_Widget', 'w3tc_ajax_cdn_bunnycdn_widgetdata' ) );
				break;
		}

		switch ( $w3tc_cdnfsd_engine ) {
			case 'bunnycdn':
				if ( $is_cdn_page && $w3tc_c->get_boolean( 'cdnfsd.enabled' ) ) {
					\add_action( 'w3tc_purge_urls_box', array( '\W3TC\Cdn_BunnyCdn_Page', 'w3tc_purge_urls_box' ) );
				}
				break;
		}

		\add_action( 'w3tc_settings_general_boxarea_cdn', array( $this, 'w3tc_settings_general_boxarea_cdn' ) );
	}

	/**
	 * Adds configuration options for CDN settings in the general settings box area.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_cdn() {
		$w3tc_config        = Dispatcher::config();
		$engine_optgroups   = array();
		$engine_values      = array();
		$optgroup_pull      = count( $engine_optgroups );
		$engine_optgroups[] = \__( 'Origin Pull / Mirror:', 'w3-total-cache' );
		$optgroup_push      = count( $engine_optgroups );
		$engine_optgroups[] = \__( 'Origin Push:', 'w3-total-cache' );

		$engine_values[''] = array(
			'label' => 'Select a provider',
		);

		$engine_values['cf2'] = array(
			'label'    => \__( 'Amazon CloudFront', 'w3-total-cache' ),
			'disabled' => ! Util_Installed::curl() ? true : null,
			'optgroup' => $optgroup_pull,
		);

		$engine_values['bunnycdn'] = array(
			'label'    => \__( 'Bunny CDN (recommended)', 'w3-total-cache' ),
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

		$w3tc_cdn_enabled = $w3tc_config->get_boolean( 'cdn.enabled' );
		$w3tc_cdn_engine  = $w3tc_config->get_string( 'cdn.engine' );

		include W3TC_DIR . '/Cdn_GeneralPage_View.php';
	}

	/**
	 * Filters the attachment URL for the WordPress admin area based on CDN settings.
	 *
	 * @param string $w3tc_url The URL of the attachment.
	 *
	 * @return string The filtered URL of the attachment.
	 */
	public function wp_get_attachment_url( $w3tc_url ) {
		if ( defined( 'WP_ADMIN' ) ) {
			$w3tc_url = trim( $w3tc_url );

			if ( ! empty( $w3tc_url ) ) {
				$parsed          = \wp_parse_url( $w3tc_url );
				$uri             = ( isset( $parsed['path'] ) ? $parsed['path'] : '/' ) .
					( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
				$wp_upload_dir   = \wp_upload_dir();
				$upload_base_url = $wp_upload_dir['baseurl'];

				if ( \substr( $w3tc_url, 0, strlen( $upload_base_url ) ) === $upload_base_url ) {
					$common  = Dispatcher::component( 'Cdn_Core' );
					$new_url = $common->url_to_cdn_url( $w3tc_url, $uri );
					if ( ! is_null( $new_url ) ) {
						$w3tc_url = $new_url;
					}
				}
			}
		}

		return $w3tc_url;
	}
}
