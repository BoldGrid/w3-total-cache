<?php
/**
 * File: Mobile_Redirect.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Mobile_Redirect
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Mobile_Redirect {
	/**
	 * Mobile
	 *
	 * @var Mobile_UserAgent
	 */
	private $_mobile = null;

	/**
	 * Referrer
	 *
	 * @var Mobile_Referrer
	 */
	private $_referrer = null;

	/**
	 * Constructor for the Mobile_Redirect class.
	 *
	 * Initializes mobile and referrer components if their respective features are enabled in the configuration.
	 *
	 * @return void
	 */
	public function __construct() {
		$config = Dispatcher::config();
		if ( $config->get_boolean( 'mobile.enabled' ) ) {
			$this->_mobile = Dispatcher::component( 'Mobile_UserAgent' );
		}

		if ( $config->get_boolean( 'referrer.enabled' ) ) {
			$this->_referrer = Dispatcher::component( 'Mobile_Referrer' );
		}
	}

	/**
	 * Processes the request to determine and execute mobile or referrer redirections.
	 *
	 * Skips processing for certain predefined conditions such as AJAX requests, cron jobs, or admin pages.
	 *
	 * @return void
	 */
	public function process() {
		// Skip some pages.
		switch ( true ) {
			case defined( 'DOING_AJAX' ):
			case defined( 'DOING_CRON' ):
			case defined( 'APP_REQUEST' ):
			case defined( 'XMLRPC_REQUEST' ):
			case defined( 'WP_ADMIN' ):
			case ( defined( 'SHORTINIT' ) && SHORTINIT ):
				return;
		}

		// Handle mobile or referrer redirects.
		if ( $this->_mobile || $this->_referrer ) {
			$mobile_redirect = '';
			if ( $this->_mobile ) {
				$mobile_redirect = $this->_mobile->get_redirect();
			}

			$referrer_redirect = '';
			if ( $this->_referrer ) {
				$referrer_redirect = $this->_referrer->get_redirect();
			}

			$redirect = ( $mobile_redirect ? $mobile_redirect : $referrer_redirect );

			if ( $redirect ) {
				Util_Environment::redirect( $redirect );
				exit();
			}
		}
	}
}
