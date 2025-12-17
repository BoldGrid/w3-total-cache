<?php
/**
 * FIle: Generic_ConfigLabels.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_ConfigLabels
 */
class Generic_ConfigLabels {
	/**
	 * Get config labels
	 *
	 * @param array $config_labels Config labels.
	 *
	 * @return array
	 */
	public function config_labels( $config_labels ) {
		return array_merge(
			$config_labels,
			array(
				'cluster.messagebus.enabled'          => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for SNS, 2: closing acronym tag, 3: SNS abbreviation.
						__(
							'Enable cache purge via Amazon %1$s%3$s%2$s',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Simple Notification Service', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'SNS', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cluster.messagebus.sns.region'       => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for SNS, 2: closing acronym tag, 3: SNS abbreviation.
						__(
							'Amazon %1$s%3$s%2$s region:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Simple Notification Service', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'SNS', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cluster.messagebus.sns.api_key'      => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for API, 2: closing acronym tag, 3: API abbreviation.
						__(
							'%1$s%3$s%2$s key:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Application Programming Interface', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'API', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cluster.messagebus.sns.api_secret'   => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for API, 2: closing acronym tag, 3: API abbreviation.
						__(
							'%1$s%3$s%2$s secret:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Application Programming Interface', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'API', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cluster.messagebus.sns.topic_arn'    => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for ID, 2: closing acronym tag, 3: ID abbreviation.
						__(
							'Topic %1$s%3$s%2$s:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Identification', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'ID', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cluster.messagebus.debug'            => __( 'Message Bus', 'w3-total-cache' ),
				'widget.pagespeed.access_token'       => __( 'Authorize :', 'w3-total-cache' ),
				'widget.pagespeed.w3tc_pagespeed_key' => __( 'W3 API Key:', 'w3-total-cache' ),
				'common.force_master'                 => __( 'Use single network configuration file for all sites.', 'w3-total-cache' ),
				'config.path'                         => __( 'Nginx server configuration file path', 'w3-total-cache' ),
				'config.check'                        => __( 'Verify rewrite rules', 'w3-total-cache' ),
				'plugin.license_key'                  => __( 'License:', 'w3-total-cache' ),
				'referrer.enabled'                    => __( 'Referrers:', 'w3-total-cache' ),
				'referrer.rgroups'                    => __( 'Referrer groups', 'w3-total-cache' ),
				'mobile.enabled'                      => __( 'User Agents:', 'w3-total-cache' ),
				'mobile.rgroups'                      => __( 'User Agent groups', 'w3-total-cache' ),
				'varnish.enabled'                     => __( 'Enable reverse proxy caching via varnish', 'w3-total-cache' ),
				'varnish.debug'                       => __( 'Reverse Proxy', 'w3-total-cache' ),
				'varnish.servers'                     => __( 'Varnish servers:', 'w3-total-cache' ),
			)
		);
	}
}
