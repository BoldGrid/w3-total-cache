<?php
/**
 * File: UserExperience_PartyTown_Page_View.php
 *
 * Renders the PartyTown setting block on the UserExperience advanced settings page.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c          = Dispatcher::config();
$is_pro     = Util_Environment::is_w3tc_pro( $c );
$is_enabled = UserExperience_PartyTown_Extension::is_enabled();
Util_Debug::debug('show pt',true);
$partytown_singles = $c->get_array( 'user-experience-partytown-includes' );

Util_Ui::postbox_header( esc_html__( 'PartyTown', 'w3-total-cache' ), '', 'partytown' );
?>
<p><?php esc_html_e( 'This feature allows you to optimize third-party scripts by offloading them to web workers using PartyTown. It significantly improves your site\'s performance by moving heavy JavaScript tasks off the main thread.', 'w3-total-cache' ); ?></p>
<div class="w3tc-gopro-manual-wrap">
	<?php Util_Ui::pro_wrap_maybe_start(); ?>
	<p>
		<?php esc_html_e( 'CSS/JS entries added to the below textarea will be removed from the homepage if present.', 'w3-total-cache' ); ?>
	</p>
	<table class="form-table">
		<?php
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-partytown', 'includes' ),
				'label'           => esc_html__( 'Include List:', 'w3-total-cache' ),
				'control'         => 'textarea',
				'disabled'        => ! $is_enabled,
				'description'     => array(),
				'excerpt'         => esc_html__( 'Enter each script URL or handle you wish to offload to PartyTown. Include one entry per line, e.g. (googletagmanager.com, /wp-content/plugins/woocommerce/, myscript.js, name="myscript", etc.)', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-partytown', 'preload' ),
				'label'           => esc_html__( 'Preload Resources:', 'w3-total-cache' ),
				'control'         => 'checkbox',
				'checkbox_label'  => esc_html__( 'Enable preload.', 'w3-total-cache' ),
				'disabled'        => ! $is_enabled,
				'description'     => array(),
				'excerpt'         => esc_html__( 'Preloading the PartyTown JavaScript assets further improves performance.', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-partytown', 'debug' ),
				'label'           => esc_html__( 'Debug Mode:', 'w3-total-cache' ),
				'control'         => 'checkbox',
				'checkbox_label'  => esc_html__( 'Enable debug mode.', 'w3-total-cache' ),
				'disabled'        => ! $is_enabled,
				'description'     => array(),
				'excerpt'         => esc_html__( 'Debug mode can help troubleshoot issues.', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-partytown', 'timeout' ),
				'label'           => esc_html__( 'Timeout:', 'w3-total-cache' ),
				'control'         => 'textbox',
				'textbox_type'    => 'number',
				'disabled'        => ! $is_enabled,
				'description'     => array(),
				'excerpt'         => esc_html__( 'Set the time (in milliseconds) after which PartyTown will stop waiting for a worker response and revert to the main thread.', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-partytown', 'workers' ),
				'label'           => esc_html__( 'Worker Concurrency Limit:', 'w3-total-cache' ),
				'control'         => 'textbox',
				'textbox_type'    => 'number',
				'disabled'        => ! $is_enabled,
				'description'     => array(),
				'excerpt'         => esc_html__( 'Limit the number of PartyTown workers that can run simultaneously.', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		?>
	</table>
	<?php

	if ( $is_pro && ! $is_enabled ) {
		echo wp_kses(
			sprintf(
				// translators: 1: Opening HTML em tag, 2: Closing HTML em tag, 3: Opening HTML a tag with a link to General Settings, 4: Closing HTML a tag.
				__(
					'%1$sPartyTown%2$s is not enabled in the %3$sGeneral Settings%4$s.',
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
	Util_Ui::pro_wrap_maybe_end( 'partytown_home', true );
	?>
</div>
<?php
Util_Ui::postbox_footer();
