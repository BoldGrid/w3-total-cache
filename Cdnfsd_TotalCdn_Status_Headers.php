<?php
/**
 * File: Cdnfsd_TotalCdn_Status_Headers.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

return array(
	'bunnycdn'       => array(
		'cdn-cache',
		'cdn-cachedat',
		'cdn-edgestorageid',
		'cdn-proxyver',
		'cdn-pullzone',
		'cdn-requestcountrycode',
		'cdn-requestid',
		'cdn-requestpullcode',
		'cdn-requestpullsuccess',
		'cdn-requesttime',
		'cdn-status',
		'cdn-uid',
	),
	'cloudflare'     => array(
		'cf-cache-status',
		'cf-ray',
		'cf-worker',
		'cf-apo-via',
		'cf-rocket-loader',
		'cf-edge-cache',
		'cf-polished',
		'cf-bgj',
		'cf-request-id',
	),
	'cloudfront'     => array(
		'x-amz-cf-id',
		'x-amz-cf-pop',
		'x-amz-cf-pop-country',
		'x-amz-cf-cache-status',
		'x-cache',
	),
	'totalcdn'       => array(
		'cdn-cache',
		'cdn-cachedat',
		'cdn-edgestorageid',
		'cdn-proxyver',
		'cdn-pullzone',
		'cdn-requestcountrycode',
		'cdn-requestid',
		'cdn-requestpullcode',
		'cdn-requestpullsuccess',
		'cdn-requesttime',
		'cdn-status',
		'cdn-uid',
	),
	'transparentcdn' => array(
		'x-tcdn-pop',
		'x-tcdn-pop-id',
		'x-tcdn-pop-country',
		'x-tcdn-cache',
		'x-tcdn-cache-status',
		'x-tcdn-request-id',
		'x-tcdn-debug',
		'x-served-by',
	),
);
