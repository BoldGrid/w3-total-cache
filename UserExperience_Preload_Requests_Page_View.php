<?php
/**
 * File: UserExperience_Preload_Requests_Page_View.php
 *
 * Renders the Preload Requests setting block on the UserExperience advanced settings page.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<?php Util_Ui::postbox_header( esc_html__( 'Preload Requests', 'w3-total-cache' ), '', 'preload-requests' ); ?>
<p><?php esc_html_e( 'DNS prefetching, pre-connecting, and preloading are essential web optimization techniques that enhance website performance by proactively resolving network-related tasks. DNS prefetching involves resolving domain names to IP addresses before they are needed, reducing the time it takes for the browser to initiate a connection. Pre-connecting establishes early connections to servers to expedite resource fetching, anticipating the need for subsequent requests. Preloading involves instructing the browser to fetch and cache critical resources in advance, ensuring a smoother user experience during page load.', 'w3-total-cache' ); ?></p>
<p><?php esc_html_e( 'However, it\'s important to note a significant caveat: if a webpage requires connections to numerous third-party domains, indiscriminate pre-connecting to all of them can actually hinder performance. Pre-connecting to an excessive number of domains can overwhelm the browser and degrade overall speed, as each connection consumes resources. It\'s crucial for web developers to judiciously implement pre-connecting, considering the optimal number and relevance of third-party domains to ensure efficient website loading.', 'w3-total-cache' ); ?></p>
<p><?php esc_html_e( 'Each of the below fields will default to non-HTTPS if the protocol is ommitted, e.g. (//example.com would become http://example.com). Include the protocol of the target if it is known.', 'w3-total-cache' ); ?></p>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'dns-prefetch' ),
			'label'         => esc_html__( 'DNS Prefetch Domains:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#dns-prefetch-domains' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify domains whose DNS should be prefetched by browsers. Include one entry per line, e.g. (https://cdn.domain.com, https://fonts.googleapis.com, https://www.google-ananlytics.com, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preconnect' ),
			'label'         => esc_html__( 'Preconnect Domains:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preconnect-domains' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify domains that browsers should preconnect to. Include one entry per line, e.g. (https://cdn.domain.com, https://fonts.googleapis.com, https://www.google-ananlytics.com, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-css' ),
			'label'         => esc_html__( 'Preload CSS:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key CSS URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.css, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-js' ),
			'label'         => esc_html__( 'Preload JavaScript:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key JavaScript URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.js, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-fonts' ),
			'label'         => esc_html__( 'Preload Fonts:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key Font URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.woff, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-images' ),
			'label'         => esc_html__( 'Preload Images:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key Image URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.png, etc.)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-videos' ),
			'label'         => esc_html__( 'Preload Videos:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key Video URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.mp4, etc.)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-audio' ),
			'label'         => esc_html__( 'Prelaod Audio:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key Audio URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.mp3, etc.)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'           => array( 'user-experience-preload-requests', 'preload-documents' ),
			'label'         => esc_html__( 'Preload Documents:', 'w3-total-cache' ),
			'control'       => 'textarea',
			'control_after' => '<a class="w3tc-control-after" target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/preload-requests#preload' ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
			'description'   => esc_html__( 'Specify key Document URLs that should be preloaded by browsers. Include one entry per line, e.g. (https://example.com/example.pdf, etc.)', 'w3-total-cache' ),
		)
	);
	?>
</table>
<?php Util_Ui::postbox_footer(); ?>
