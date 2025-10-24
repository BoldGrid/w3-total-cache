<?php
/**
 * File: Cdn_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Page
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdn_Page extends Base_Page_Settings {
	/**
	 * Current page.
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_cdn';

	/**
	 * Displays the CDN settings page.
	 *
	 * This method retrieves the CDN-related configuration settings and checks if the CDN is enabled and authorized.
	 * It also checks if the engine supports mirroring and purging, as well as whether minification and browser cache
	 * settings are enabled. The necessary settings are then passed to the view for rendering the CDN options page.
	 *
	 * @return void
	 */
	public function view() {
		$config               = Dispatcher::config();
		$cdn_engine           = $config->get_string( 'cdn.engine' );
		$cdn_enabled          = $config->get_boolean( 'cdn.enabled' );
		$cdnfsd_engine        = $config->get_string( 'cdnfsd.engine' );
		$cdnfsd_enabled       = $config->get_boolean( 'cdnfsd.enabled' );
		$cdn_mirror           = Cdn_Util::is_engine_mirror( $cdn_engine );
		$cdn_mirror_purge_all = Cdn_Util::can_purge_all( $cdn_engine );
		$cdn_common           = Dispatcher::component( 'Cdn_Core' );
		$cdn                  = $cdn_common->get_cdn();
		$cdn_supports_header  = W3TC_CDN_HEADER_MIRRORING === $cdn->headers_support();
		$minify_enabled       = (
			$config->get_boolean( 'minify.enabled' ) &&
			Util_Rule::can_check_rules() &&
			$config->get_boolean( 'minify.rewrite' ) &&
			( ! $config->get_boolean( 'minify.auto' ) || Cdn_Util::is_engine_mirror( $config->get_string( 'cdn.engine' ) ) )
		);
		$cookie_domain        = $this->get_cookie_domain();
		$set_cookie_domain    = $this->is_cookie_domain_enabled();

		// Required for Update Media Query String button.
		$browsercache_enabled         = $config->get_boolean( 'browsercache.enabled' );
		$browsercache_update_media_qs = ( $config->get_boolean( 'browsercache.cssjs.replace' ) || $config->get_boolean( 'browsercache.other.replace' ) );

		// Get CDN and CDN FSD status.
		$cdn_core             = new Cdn_Core();
		$is_cdn_authorized    = $cdn_core->is_cdn_authorized();
		$is_cdnfsd_authorized = $cdn_core->is_cdnfsd_authorized();

		include W3TC_INC_DIR . '/options/cdn.php';
	}

	/**
	 * Retrieves the domain for the site's cookie.
	 *
	 * This method retrieves the domain of the site where the cookie will be set, typically used to determine the
	 * correct domain for cookies. It first attempts to parse the site URL from the WordPress settings, and if that
	 * fails, it uses the HTTP_HOST from the server.
	 *
	 * @return string The domain name of the site for cookies.
	 */
	public function get_cookie_domain() {
		$site_url  = get_option( 'siteurl' );
		$parse_url = @wp_parse_url( $site_url );

		if ( $parse_url && ! empty( $parse_url['host'] ) ) {
			return $parse_url['host'];
		}

		return isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	}

	/**
	 * Checks if the cookie domain is enabled.
	 *
	 * This method compares the site's cookie domain to the value defined in the `COOKIE_DOMAIN` constant.
	 * It returns true if the `COOKIE_DOMAIN` constant is defined and matches the site's cookie domain.
	 *
	 * @return bool True if the cookie domain is enabled, false otherwise.
	 */
	public function is_cookie_domain_enabled() {
		$cookie_domain = $this->get_cookie_domain();

		return defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN === $cookie_domain;
	}
}
