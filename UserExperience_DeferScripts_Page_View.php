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

$c      = Dispatcher::config();
$is_pro = Util_Environment::is_w3tc_pro( $c );

if ( is_null( $c->get( array( 'user-experience-defer-scripts', 'timeout' ) ) ) ) {
	$c->set( array( 'user-experience-defer-scripts', 'timeout' ), 5000 );
	$c->save();
}

?>
<?php Util_Ui::postbox_header( esc_html__( 'Delay Scripts', 'w3-total-cache' ), '', 'defer-scripts' ); ?>
<div class="w3tc-gopro-manual-wrap">
	<?php Util_Ui::pro_wrap_maybe_start(); ?>
	<p><?php esc_html_e( 'For best results it is recommended to enable the Minify feature to optimize internal sources and to then use this feature to handle external sources and/or any internal sources excluded from Minify.', 'w3-total-cache' ); ?></p>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				// Translators: 1 opening HTML a tag to pagespeed settings page, 2 closing HTML a tag.
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
	<?php
	if ( ! $is_pro ) {
		Util_Ui::print_score_block(
			__( 'Potential Google PageSpeed Gain', 'w3-total-cache' ),
			'+18',
			__( 'Points', 'w3-total-cache' ),
			__( 'In a recent test, using the Delay Scripts feature added 18 points on mobile devices to the Google PageSpeed score!', 'w3-total-cache' ),
			'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/delay-scripts-test/?utm_source=w3tc&utm_medium=defer-js&utm_campaign=proof'
		);
	}
	?>
	<table class="form-table">
		<?php
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-defer-scripts', 'timeout' ),
				'label'           => esc_html__( 'Timeout:', 'w3-total-cache' ),
				'control'         => 'textbox',
				'disabled'        => ! UserExperience_DeferScripts_Extension::is_enabled(),
				'description'     => array(),
				'excerpt'         => esc_html__( 'Timeout (in milliseconds) to delay the loading of delayed scripts if no user action is taken during page load', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);

		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-defer-scripts', 'includes' ),
				'label'           => esc_html__( 'Delay list:', 'w3-total-cache' ),
				'control'         => 'textarea',
				'disabled'        => ! UserExperience_DeferScripts_Extension::is_enabled(),
				'description'     => array(),
				'excerpt'         => esc_html__( 'Specify keywords to match any attribute of script tags containing the "src" attribute. Include one entry per line, e.g. (googletagmanager.com, gtag/js, myscript.js, and name="myscript")', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		?>
	</table>
	<?php
	if ( $is_pro && ! UserExperience_DeferScripts_Extension::is_enabled() ) {
		echo wp_kses(
			sprintf(
				// translators: 1: Opening HTML em tag, 2: Closing HTML em tag, 3: Opening HTML a tag with a link to General Settings, 4: Closing HTML a tag.
				__(
					'%1$sDelay Scripts%2$s is not enabled in the %3$sGeneral Settings%4$s.',
					'w3-total-cache'
				),
				'<em>',
				'</em>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_general#userexperience' ) ) . '">',
				'</a>'
			),
			array(
				'a'  => array(
					'href' => array(),
				),
				'em' => array(),
			)
		);
	}
	Util_Ui::pro_wrap_maybe_end( 'defer_scripts', false );
	?>
</div>
<?php
Util_Ui::postbox_footer(); ?>
