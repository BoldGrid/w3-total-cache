<?php
/**
 * File: Extension_Swarmify_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p>
	<?php esc_html_e( 'Swarmify extension is currently', 'w3-total-cache' ); ?>
	<?php
	if ( $config->is_extension_active_frontend( 'swarmify' ) ) {
		echo '<span class="w3tc-enabled">' . esc_html__( 'enabled', 'w3-total-cache' ) . '</span>.';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html__( 'disabled', 'w3-total-cache' ) . '</span>.';
	}
	?>
<p>

<form action="admin.php?page=w3tc_extensions&amp;extension=swarmify&amp;action=view" method="post">
	<?php Util_UI::print_control_bar( 'extension_swarmify_form_control' ); ?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Configuration', 'w3-total-cache' ), '', 'configuration' ); ?>
		<table class="form-table">
			<?php
			Util_Ui::config_item(
				array(
					'key'           => array( 'swarmify', 'api_key' ),
					'label'         => esc_html__( 'API Key:', 'w3-total-cache' ),
					'control'       => 'textbox',
					'control_after' => Util_Ui::button_link( 'Obtain one', $swarmify_signup_url ),
					'description'   => esc_html__( 'Swarmify API Key required in order to start optimizing your videos experience', 'w3-total-cache' ),
				)
			);
			?>
		</table>

		<?php Util_Ui::postbox_footer(); ?>

		<?php Util_Ui::postbox_header( esc_html__( 'Behavior Settings', 'w3-total-cache' ), '', 'behavior' ); ?>
		<table class="form-table">
			<?php
			Util_Ui::config_item(
				array(
					'key'            => array( 'swarmify', 'handle.htmlvideo' ),
					'label'          => esc_html__( '&lt;video&gt;:', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'checkbox_label' => esc_html__( 'Optimize &lt;video&gt; HTML tags', 'w3-total-cache' ),
					'description'    => esc_html__( 'Optimize videos delivered using &lt;video&gt; HTML tag.', 'w3-total-cache' ),
				)
			);

			Util_Ui::config_item(
				array(
					'key'            => array( 'swarmify', 'handle.jwplayer' ),
					'label'          => esc_html__( 'JWPlayer:', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'checkbox_label' => esc_html__( 'Optimize JWPlayer', 'w3-total-cache' ),
					'description'    => esc_html__( 'Optimize videos delivered using JWPlayer script.', 'w3-total-cache' ),
				)
			);

			Util_Ui::config_item(
				array(
					'key'            => array( 'swarmify', 'reject.logged' ),
					'label'          => esc_html__( 'Logged In:', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'checkbox_label' => esc_html__( 'Don\'t optimize videos for logged in users', 'w3-total-cache' ),
					'description'    => esc_html__( 'Only unauthenticated users will view optimized version of a given page.', 'w3-total-cache' ),
				)
			);
			?>
		</table>

		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
