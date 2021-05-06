<?php
namespace W3TC;

class Generic_ConfigLabels {
	public function config_labels( $config_labels ) {
		return array_merge( $config_labels, array(
				'widget.pagespeed.key' => __( 'Page Speed <acronym title="Application Programming Interface">API</acronym> Key:', 'w3-total-cache' ),
				'widget.pagespeed.key.restrict.referrer' => __( 'Key Restriction (Referrer):', 'w3-total-cache' ),
				'common.force_master' => __( 'Use single network configuration file for all sites.', 'w3-total-cache' ),
				'config.path' => __( 'Nginx server configuration file path', 'w3-total-cache' ),
				'config.check' => __( 'Verify rewrite rules', 'w3-total-cache' ),
				'plugin.license_key' => __( 'License:', 'w3-total-cache' ),

				'referrer.enabled' => __( 'Referrers:', 'w3-total-cache' ),
				'referrer.rgroups' => __( 'Referrer groups', 'w3-total-cache' ),
				'mobile.enabled' => __( 'User Agents:', 'w3-total-cache' ),
				'mobile.rgroups' => __( 'User Agent groups', 'w3-total-cache' ),

				'varnish.enabled' => __( 'Enable reverse proxy caching via varnish', 'w3-total-cache' ),
				'varnish.debug' => __( 'Reverse Proxy', 'w3-total-cache' ),
				'varnish.servers' => __( 'Varnish servers:', 'w3-total-cache' )
			) );
	}
}
