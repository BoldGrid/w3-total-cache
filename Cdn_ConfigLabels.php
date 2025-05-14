<?php
/**
 * File: Cdn_ConfigLabels.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_ConfigLabels
 */
class Cdn_ConfigLabels {
	/**
	 * Merges additional CDN configuration labels with the provided array.
	 *
	 * This method takes an array of configuration labels and merges them with predefined labels related to CDN functionality.
	 * The predefined labels include various settings for enabling and configuring the CDN, FSD (Full Site Delivery), and custom file handling.
	 * Each label is localized using WordPress's `__()` function to ensure proper translation support.
	 *
	 * @param array $config_labels The existing array of configuration labels to be merged with predefined labels.
	 *
	 * @return array The merged array of configuration labels.
	 */
	public function config_labels( $config_labels ) {
		return array_merge(
			$config_labels,
			array(
				'cdn.enabled'             => '<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>:',
				'cdn.engine'              => '<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>' . __( ' Type:', 'w3-total-cache' ),
				'cdn.debug'               => '<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>',
				'cdnfsd.debug'            => '<acronym title="' . __( 'Full Site Delivery', 'w3-total-cache' ) . '">' . __( 'FSD', 'w3-total-cache' ) . '</acronym> <acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>',
				'cdn.uploads.enable'      => __( 'Host attachments', 'w3-total-cache' ),
				'cdn.includes.enable'     => __( 'Host wp-includes/ files', 'w3-total-cache' ),
				'cdn.theme.enable'        => __( 'Host theme files', 'w3-total-cache' ),
				'cdn.minify.enable'       => wp_kses(
					sprintf(
						// Translators: 1 acronym for CSS, 2 acronym for JS.
						__(
							'Host minified %1$s and %2$s files',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Cascading Style Sheet', 'w3-total-cache' ) . '">' . __( 'CSS', 'w3-total-cache' ) . '</acronym>',
						'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">' . __( 'JS', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.custom.enable'       => __( 'Host custom files', 'w3-total-cache' ),
				'cdn.force.rewrite'       => __( 'Force over-writing of existing files', 'w3-total-cache' ),
				'cdn.import.external'     => __( 'Import external media library attachments', 'w3-total-cache' ),
				'cdn.canonical_header'    => __( 'Add canonical header', 'w3-total-cache' ),
				'cdn.reject.ssl'          => wp_kses(
					sprintf(
						// Translators: 1 acronym for CDN, 2 acroym for SSL.
						__(
							'Disable %1$s on %2$s pages',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>',
						'<acronym title="' . __( 'Secure Sockets Layer', 'w3-total-cache' ) . '">' . __( 'SSL', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.admin.media_library' => wp_kses(
					sprintf(
						// Translators: 1 acronym for CDN.
						__(
							'Use %1$s links for the Media Library on admin pages',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.reject.logged_roles' => wp_kses(
					sprintf(
						// Translators: 1 acronym for CDN.
						__(
							'Disable %1$s for the following roles',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.reject.uri'          => wp_kses(
					sprintf(
						// Translators: 1 acronym for CDN.
						__(
							'Disable %1$s on the following pages:',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.autoupload.enabled'  => __( 'Export changed files automatically', 'w3-total-cache' ),
				'cdn.autoupload.interval' => __( 'Auto upload interval:', 'w3-total-cache' ),
				'cdn.queue.interval'      => __( 'Re-transfer cycle interval:', 'w3-total-cache' ),
				'cdn.queue.limit'         => __( 'Re-transfer cycle limit:', 'w3-total-cache' ),
				'cdn.includes.files'      => __( 'wp-includes file types to upload:', 'w3-total-cache' ),
				'cdn.theme.files'         => __( 'Theme file types to upload:', 'w3-total-cache' ),
				'cdn.import.files'        => __( 'File types to import:', 'w3-total-cache' ),
				'cdn.custom.files'        => __( 'Custom file list:', 'w3-total-cache' ),
				'cdn.rscf.location'       => __( 'Location:', 'w3-total-cache' ),
				'cdn.reject.ua'           => __( 'Rejected user agents:', 'w3-total-cache' ),
				'cdn.reject.files'        => __( 'Rejected files:', 'w3-total-cache' ),
			)
		);
	}
}
