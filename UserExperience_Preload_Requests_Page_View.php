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
<p><?php esc_html_e( 'DNS prefetching, preconnecting, and preloading are essential web optimization techniques that enhance website performance by proactively resolving network-related tasks. DNS prefetching involves resolving domain names to IP addresses before they are needed, reducing the time it takes for the browser to initiate a connection. Preconnecting establishes early connections to servers to expedite resource fetching, anticipating the need for subsequent requests. Preloading involves instructing the browser to fetch and cache critical resources in advance, ensuring a smoother user experience during page load.', 'w3-total-cache' ); ?></p>
<p><?php esc_html_e( 'However, it\'s important to note a significant caveat: if a webpage requires connections to numerous third-party domains, indiscriminate preconnecting to all of them can actually hinder performance. Preconnecting to an excessive number of domains can overwhelm the browser and degrade overall speed, as each connection consumes resources. It\'s crucial for web developers to judiciously implement preconnecting, considering the optimal number and relevance of third-party domains to ensure efficient website loading.', 'w3-total-cache' ); ?></p>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'dns-prefetch' ),
			'label'       => esc_html__( 'DNS Prefetch Domains:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify domains whose DNS should be prefetched by browsers via the "dns-prefetch" header. Domains entries with no protocol (//somedomain.com) will default to non-SSL HTTP (http://somedomain). Include one domain entry per line, e.g. (https://cdn.domain.com, https://fonts.googleapis.com, https://www.google-ananlytics.com, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preconnect' ),
			'label'       => esc_html__( 'Preconnect Domains:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify domains that should be preloaded by browsers via the "preconnect" header. Domains entries with no protocol (//somedomain.com) will default to non-SSL HTTP (http://somedomain). Include one domain entry per line, e.g. (https://cdn.domain.com, https://fonts.googleapis.com, https://www.google-ananlytics.com, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-css' ),
			'label'       => esc_html__( 'Preload CSS:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key CSS URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (styles-url.css, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-js' ),
			'label'       => esc_html__( 'Preload JavaScript:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key JavaScript URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (js-url.js, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-fonts' ),
			'label'       => esc_html__( 'Preload Fonts:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key Font URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (woff-url.woff, woff2-url.woff2, ttf-url.tff, etc.)', 'w3-total-cache' ),
		)
	);
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-images' ),
			'label'       => esc_html__( 'Preload Images:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key Image URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (png-url.png, jpg-url.jpg, gif-url.gif, etc.)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-videos' ),
			'label'       => esc_html__( 'Preload Videos:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key Video URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (mp4-url.mp4, webm-url.webm, mov-url.mov, etc.)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-audio' ),
			'label'       => esc_html__( 'Prelaod Audio:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key Audio URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (mp3-url.mp3, ogg-url.ogg, wav-url.wav, etc.)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-preload-requests', 'preload-documents' ),
			'label'       => esc_html__( 'Preload Documents:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify key Document URLs that should be preloaded by browsers via the "preload" header. Include one URL entry per line, e.g. (pdf-url.pdf, docx-url.docx, txt-url.txt, etc.)', 'w3-total-cache' ),
		)
	);
	?>
</table>
<?php Util_Ui::postbox_footer(); ?>
