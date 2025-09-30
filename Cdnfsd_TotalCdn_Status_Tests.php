<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Tests.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

return array(
	array(
		'title'  => esc_html__( 'Hostname', 'w3-total-cache' ),
		'id'     => 'fsd_totalcdn_hostname',
		'filter' => 'w3tc_fsd_totalcdn_hostname',
	),
	array(
		'title'  => esc_html__( 'DNS', 'w3-total-cache' ),
		'id'     => 'fsd_totalcdn_dns',
		'filter' => 'w3tc_fsd_totalcdn_dns',
	),
	array(
		'title'  => esc_html__( 'Certificate', 'w3-total-cache' ),
		'id'     => 'fsd_totalcdn_ssl',
		'filter' => 'w3tc_fsd_totalcdn_ssl',
	),
	array(
		'title'  => esc_html__( 'Headers', 'w3-total-cache' ),
		'id'     => 'fsd_totalcdn_headers',
		'filter' => 'w3tc_fsd_totalcdn_headers',
	),
);
