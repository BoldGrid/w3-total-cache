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
				'cdn.enabled'             => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for CDN, 2: closing acronym tag, 3: CDN abbreviation.
						__(
							'%1$s%3$s%2$s:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'CDN', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.engine'              => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for CDN, 2: closing acronym tag, 3: CDN abbreviation, 4: type label.
						__(
							'%1$s%3$s%2$s %4$s',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'CDN', 'w3-total-cache' ),
						esc_html__( 'Type:', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.debug'               => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for CDN, 2: closing acronym tag.
						__(
							'%1$sCDN%2$s',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdnfsd.debug'            => wp_kses(
					sprintf(
						// translators: 1: opening acronym tag for FSD, 2: closing acronym tag, 3: opening acronym tag for CDN, 4: closing acronym tag for CDN, 5: FSD acronym, 6: CDN acronym.
						__(
							'%1$s%5$s%2$s %3$s%6$s%4$s',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Full Site Delivery', 'w3-total-cache' ) . '">',
						'</acronym>',
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'</acronym>',
						esc_html__( 'FSD', 'w3-total-cache' ),
						esc_html__( 'CDN', 'w3-total-cache' )
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.uploads.enable'      => __( 'Host attachments', 'w3-total-cache' ),
				'cdn.includes.enable'     => __( 'Host wp-includes/ files', 'w3-total-cache' ),
				'cdn.theme.enable'        => __( 'Host theme files', 'w3-total-cache' ),
				'cdn.minify.enable'       => wp_kses(
					sprintf(
						// translators: 1: acronym tag for CSS, 2: acronym tag for JS.
						__(
							'Host minified %1$s and %2$s files',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Cascading Style Sheet', 'w3-total-cache' ) . '">' . esc_html__( 'CSS', 'w3-total-cache' ) . '</acronym>',
						'<acronym title="' . esc_attr__( 'JavaScript', 'w3-total-cache' ) . '">' . esc_html__( 'JS', 'w3-total-cache' ) . '</acronym>'
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
						// translators: 1: acronym tag for CDN, 2: acronym tag for SSL.
						__(
							'Disable %1$s on %2$s pages',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . esc_html__( 'CDN', 'w3-total-cache' ) . '</acronym>',
						'<acronym title="' . esc_attr__( 'Secure Sockets Layer', 'w3-total-cache' ) . '">' . esc_html__( 'SSL', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.admin.media_library' => wp_kses(
					sprintf(
						// translators: 1: acronym tag for CDN.
						__(
							'Use %1$s links for the Media Library on admin pages',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . esc_html__( 'CDN', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.reject.logged_roles' => wp_kses(
					sprintf(
						// translators: 1: acronym tag for CDN.
						__(
							'Disable %1$s for the following roles',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . esc_html__( 'CDN', 'w3-total-cache' ) . '</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				),
				'cdn.reject.uri'          => wp_kses(
					sprintf(
						// translators: 1: acronym tag for CDN.
						__(
							'Disable %1$s on the following pages:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . esc_html__( 'CDN', 'w3-total-cache' ) . '</acronym>'
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
