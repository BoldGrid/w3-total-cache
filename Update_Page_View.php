<?php
/**
 * File: Update_Page_View.php
 *
 * Update page.
 *
 * @since 3.0.0
 *
 * @package W3TC
 */

namespace W3TC;

$w3tc_pro_plugin_file = 'w3-total-cache-pro/w3-total-cache-pro.php';
$installed_plugins    = get_plugins();
$download_url         = 'https://repo.boldgrid.com/w3-total-cache-pro.zip';

?>
<p>
<?php

esc_html_e(
	'Thank you for being a W3 Total Cache Pro license holder.',
	'w3-total-cache'
);

?>
</p>
<p>
<?php

if ( empty( $installed_plugins[ $w3tc_pro_plugin_file ] ) ) {
	printf(
		// translators: 1: HTML anchor open tag for installation link, 2: HTML anchor close tag.
		esc_html__(
			'The W3 Total Cache Pro plugin must be installed and activated in order to update the W3 Total Cache.  Please %1$sdownload%2$s, %3$supload%2$s it, and %4$sactivate%2$s the Pro plugin.',
			'w3-total-cache'
		),
		'<a target="_blank" href="' . esc_url( $download_url ) . '">',
		'</a>',
		'<a href="' . esc_url( admin_url( 'plugin-install.php' ) ) . '">',
		'<a href="' . esc_url( admin_url( 'plugins.php?s=w3-total-cache-pro' ) ) . '">'
	);
} else {
	printf(
		// translators: 1: HTML anchor open tag for activation link, 2: HTML anchor close tag.
		esc_html__(
			'W3 Total Cache Pro must be %1$sactivated%2$s in order to update the W3 Total Cache.',
			'w3-total-cache'
		),
		'<a href="' . esc_url(
			wp_nonce_url(
				'plugins.php?action=activate&plugin=' . rawurlencode( $w3tc_pro_plugin_file ),
				'activate-plugin_' . $w3tc_pro_plugin_file
			)
		) . '">',
		'</a>'
	);
}

?>
</p>
<?php
