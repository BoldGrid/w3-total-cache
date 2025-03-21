<?php
/**
 * File: Cdn_RackSpaceCdn_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpaceCdn_AdminActions
 */
class Cdn_RackSpaceCdn_AdminActions {
	/**
	 * Reloads the Rackspace CDN domains (CNAMEs).
	 *
	 * This method retrieves the latest Rackspace CDN domains (CNAMEs) from the CDN service and updates the configuration.
	 * If the domains cannot be retrieved due to an exception, an error message is displayed and the process is halted.
	 * On successful retrieval, the domains are saved to the configuration, and a success message is displayed.
	 *
	 * @return void
	 */
	public function w3tc_cdn_rackspace_cdn_domains_reload() {
		$c    = Dispatcher::config();
		$core = Dispatcher::component( 'Cdn_Core' );
		$cdn  = $core->get_cdn();

		try {
			// try to obtain CNAMEs.
			$domains = $cdn->service_domains_get();
		} catch ( \Exception $ex ) {
			Util_Admin::redirect_with_custom_messages2(
				array(
					'errors' => array( 'Failed to obtain <acronym title="Canonical Name">CNAME</acronym>s: ' . $ex->getMessage() ),
				),
				true
			);
			return;
		}

		$c->set( 'cdn.rackspace_cdn.domains', $domains );
		$c->save();

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array( 'CNAMEs are reloaded successfully' ),
			),
			true
		);
	}
}
