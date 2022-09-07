<?php
/**
 * File: PageSpeed_Data.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed Data Config.
 */
class PageSpeed_Data {

	/**
	 * Prepare PageSpeed Data Config.
	 *
	 * @param array $data PageSpeed analysis data.
	 *
	 * @return array
	 */
	public static function prepare_pagespeed_data( $data ) {
		return array(
			'score'                    => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'categories', 'performance', 'score' ) ) * 100,
			'first-contentful-paint'   => array(
				'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'score' ) ),
				'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'displayValue' ) ),
			),
			'largest-contentful-paint' => array(
				'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'score' ) ),
				'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'displayValue' ) ),
			),
			'interactive'              => array(
				'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'interactive', 'score' ) ),
				'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'interactive', 'displayValue' ) ),
			),
			'cumulative-layout-shift'  => array(
				'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'score' ) ),
				'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'displayValue' ) ),
			),
			'total-blocking-time'      => array(
				'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'score' ) ),
				'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'displayValue' ) ),
			),
			'speed-index'              => array(
				'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'score' ) ),
				'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'displayValue' ) ),
			),
			'screenshots'              => array(
				'final' => array(
					'title'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'title' ) ),
					'screenshot' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'details', 'data' ) ),
				),
				'other' => array(
					'title'       => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'title' ) ),
					'screenshots' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'details', 'items' ) ),
				),
			),
			'opportunities'            => array(
				'render-blocking-resources'    => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unused-css-rules'             => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unminified-css'               => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unminified-javascript'        => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unused-javascript'            => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'uses-responsive-images'       => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'details', 'items' ) ),
				),
				'offscreen-images'             => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'details', 'items' ) ),
				),
				'uses-optimized-images'        => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'details', 'items' ) ),
				),
				'modern-image-formats'         => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'details', 'items' ) ),
				),
				'uses-text-compression'        => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'uses-rel-preconnect'          => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'server-response-time'         => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'redirects'                    => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'uses-rel-preload'             => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'efficient-animated-content'   => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'duplicated-javascript'        => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'legacy-javascript'            => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'preload-lcp-image'            => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'total-byte-weight'            => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'dom-size'                     => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'user-timings'                 => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'details', 'items' ) ),
				),
				'bootup-time'                  => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'mainthread-work-breakdown'    => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'third-party-summary'          => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'third-party-facades'          => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'lcp-lazy-loaded'              => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'details', 'items' ) ),
				),
				'uses-passive-event-listeners' => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'details', 'items' ) ),
				),
				'no-document-write'            => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'details', 'items' ) ),
				),
				'non-composited-animations'    => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
				),
				'unsized-images'               => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
				),
				'viewport'                     => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
			),
			'diagnostics'              => array(
				'font-display'                     => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'first-contentful-paint-3g'        => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'details', 'items' ) ),
				),
				'uses-long-cache-ttl'              => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'details', 'items' ) ),
				),
				'critical-request-chains'          => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'resource-summary'                 => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'details', 'items' ) ),
				),
				'largest-contentful-paint-element' => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'layout-shift-elements'            => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
				),
				'long-tasks'                       => array(
					'title'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'title' ) ),
					'description'  => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'description' ) ),
					'score'        => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'score' ) ),
					'displayValue' => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'displayValue' ) ),
					'details'      => PageSpeed_Data::get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
			),
		);
	}

	/**
	 * Recursively get value based on series of key decendents.
	 *
	 * @param array $data PageSpeed data.
	 * @param array $elements Array of key decendents.
	 *
	 * @return object | null
	 */
	public static function get_value( $data, $elements ) {
		if ( empty( $elements ) ) {
			return $data;
		}

		$key = array_shift( $elements );
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return PageSpeed_Data::get_value( $data[ $key ], $elements );
	}
}
