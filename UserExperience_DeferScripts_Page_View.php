<?php
/**
 * File: UserExperience_DeferScripts_Page_View.php
 *
 * Renders the defer scripts setting block on the UserExperience advanced settings page.
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
<?php Util_Ui::postbox_header( esc_html__( 'Defer Scripts', 'w3-total-cache' ), '', 'application' ); ?>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-defer-scripts', 'timeout' ),
			'label'       => esc_html__( 'Timeout:', 'w3-total-cache' ),
			'control'     => 'textbox',
			'description' => esc_html__( 'Timeout (in milliseconds) to delay the loading of deferred scripts if no user action is taken during page load', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-defer-scripts', 'includes' ),
			'label'       => esc_html__( 'Defer list:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Specify keywords to match any attribute of script tags containing the "src" attribute. Include one entry per line, e.g. (googletagmanager.com, gtag/js, myscript.js, and name="myscript")', 'w3-total-cache' ),
		)
	);

	?>
</table>
<?php Util_Ui::postbox_footer(); ?>
