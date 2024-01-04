<?php
/**
 * File: UserExperience_DeferScripts_Page_View.php
 *
 * Renders the delay scripts setting block on the UserExperience advanced settings page.
 *
 * @since 2.4.2
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c = Dispatcher::config();
if ( is_null( $c->get( array( 'user-experience-defer-scripts', 'timeout' ) ) ) ) {
	$c->set( array( 'user-experience-defer-scripts', 'timeout' ), 5000 );
	$c->save();
}

?>
<?php Util_Ui::postbox_header( esc_html__( 'Delay Scripts', 'w3-total-cache' ), '', 'defer-scripts' ); ?>
<p><?php esc_html_e( 'For best results it is recommended to enable the Minify feature to optimize internal sources and to then use this feature to handle external sources and/or any internal sources excluded from Minify.', 'w3-total-cache' ); ?></p>
<p>
	<?php
	echo wp_kses(
		sprintf(
			__(
				'To identify render-blocking JavaScript sources, use the %1$sGoogle PageSpeed%2$s tool and add appropirate URLs from the "Eliminate render-blocking resources" section to the Delay List textarea below.',
				'w3-total-cache'
			),
			'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_pagespeed' ) . '" target="_blank">',
			'</a>'
		),
		array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
			),
		)
	);
	?>
</p>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-defer-scripts', 'timeout' ),
			'label'       => esc_html__( 'Timeout:', 'w3-total-cache' ),
			'control'     => 'textbox',
			'description' => esc_html__( 'Timeout (in milliseconds) to delay the loading of delayed scripts if no user action is taken during page load', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-defer-scripts', 'includes' ),
			'label'       => esc_html__( 'Delay list:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify keywords to match any attribute of script tags containing the "src" attribute. Include one entry per line, e.g. (googletagmanager.com, gtag/js, myscript.js, and name="myscript")', 'w3-total-cache' ),
		)
	);

	?>
</table>
<?php Util_Ui::postbox_footer(); ?>
