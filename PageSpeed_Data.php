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
			'score'                    => $this->get_value( $data, array( 'lighthouseResult', 'categories', 'performance', 'score' ) ) * 100,
			'first-contentful-paint'   => array(
				'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'score' ) ),
				'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'displayValue' ) ),
			),
			'largest-contentful-paint' => array(
				'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'score' ) ),
				'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'displayValue' ) ),
			),
			'interactive'              => array(
				'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'interactive', 'score' ) ),
				'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'interactive', 'displayValue' ) ),
			),
			'cumulative-layout-shift'  => array(
				'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'score' ) ),
				'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'displayValue' ) ),
			),
			'total-blocking-time'      => array(
				'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'score' ) ),
				'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'displayValue' ) ),
			),
			'speed-index'              => array(
				'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'score' ) ),
				'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'displayValue' ) ),
			),
			'screenshots'              => array(
				'final' => array(
					'title'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'title' ) ),
					'screenshot' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'details', 'data' ) ),
				),
				'other' => array(
					'title'       => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'title' ) ),
					'screenshots' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'details', 'items' ) ),
				),
			),
			'opportunities'            => array(
				'render-blocking-resources'    => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unused-css-rules'             => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unminified-css'               => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unminified-javascript'        => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'unused-javascript'            => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'uses-responsive-images'       => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'details', 'items' ) ),
				),
				'offscreen-images'             => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'details', 'items' ) ),
				),
				'uses-optimized-images'        => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'details', 'items' ) ),
				),
				'modern-image-formats'         => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'details', 'items' ) ),
				),
				'uses-text-compression'        => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'uses-rel-preconnect'          => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'server-response-time'         => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'redirects'                    => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'redirects', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'uses-rel-preload'             => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'efficient-animated-content'   => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'duplicated-javascript'        => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'legacy-javascript'            => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'preload-lcp-image'            => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'total-byte-weight'            => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'dom-size'                     => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'user-timings'                 => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'details', 'items' ) ),
				),
				'bootup-time'                  => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'mainthread-work-breakdown'    => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'third-party-summary'          => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'third-party-facades'          => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
				'lcp-lazy-loaded'              => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'details', 'items' ) ),
				),
				'uses-passive-event-listeners' => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'details', 'items' ) ),
				),
				'no-document-write'            => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'details', 'items' ) ),
				),
				'non-composited-animations'    => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
				),
				'unsized-images'               => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
				),
				'viewport'                     => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'viewport', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
				),
			),
			'diagnostics'              => array(
				'font-display'                     => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'font-display', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'first-contentful-paint-3g'        => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'details', 'items' ) ),
				),
				'uses-long-cache-ttl'              => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'details', 'items' ) ),
				),
				'critical-request-chains'          => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
				),
				'resource-summary'                 => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'details', 'items' ) ),
				),
				'largest-contentful-paint-element' => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
				),
				'layout-shift-elements'            => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
				),
				'long-tasks'                       => array(
					'title'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'title' ) ),
					'description'  => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'description' ) ),
					'score'        => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'score' ) ),
					'displayValue' => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'displayValue' ) ),
					'details'      => $this->get_value( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'details', 'items' ) ),
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
	public function get_value( $data, $elements ) {
		if ( empty( $elements ) ) {
			return $data;
		}

		$key = array_shift( $elements );
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return $this->get_value( $data[ $key ], $elements );
	}
}
