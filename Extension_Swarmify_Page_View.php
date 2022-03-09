<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p>
	Jump to:
	<a href="admin.php?page=w3tc_general"><?php esc_html_e( 'Main Menu', 'w3-total-cache' ); ?></a> |
	<a href="admin.php?page=w3tc_extensions"><?php esc_html_e( 'Extensions', 'w3-total-cache' ); ?></a>
</p>
<p>
	Swarmify extension is currently
	<?php
	if ( $config->is_extension_active_frontend( 'swarmify' ) ) {
		echo '<span class="w3tc-enabled">' . esc_html( __( 'enabled', 'w3-total-cache' ) ) . '</span>';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html( __( 'disabled', 'w3-total-cache' ) ) . '</span>';
	}
	?>
	.
<p>

<form action="admin.php?page=w3tc_extensions&amp;extension=swarmify&amp;action=view" method="post">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( __( 'Configuration', 'w3-total-cache' ), '', 'configuration' ); ?>
		<table class="form-table">
			<?php
			Util_Ui::config_item(
				array(
					'key'           => array( 'swarmify', 'api_key' ),
					'label'         => __( 'API Key:', 'w3-total-cache' ),
					'control'       => 'textbox',
					'control_after' => Util_Ui::button_link( 'Obtain one', $swarmify_signup_url ),
					'description'   => __( 'Swarmify API Key required in order to start optimizing your videos experience', 'w3-total-cache' ),
				)
			);
			?>
		</table>
		<?php Util_Ui::button_config_save( 'extension_swarmify_configuration' ); ?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php Util_Ui::postbox_header( __( 'Behavior Settings', 'w3-total-cache' ), '', 'behavior' ); ?>
		<table class="form-table">
			<?php
			Util_Ui::config_item(
				array(
					'key'            => array( 'swarmify', 'handle.htmlvideo' ),
					'label'          => __( '&lt;video&gt;:', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'checkbox_label' => __( 'Optimize &lt;video&gt; HTML tags', 'w3-total-cache' ),
					'description'    => __( 'Optimize videos delivered using &lt;video&gt; HTML tag.', 'w3-total-cache' ),
				)
			);

			Util_Ui::config_item(
				array(
					'key'            => array( 'swarmify', 'handle.jwplayer' ),
					'label'          => __( 'JWPlayer:', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'checkbox_label' => __( 'Optimize JWPlayer', 'w3-total-cache' ),
					'description'    => __( 'Optimize videos delivered using JWPlayer script.', 'w3-total-cache' ),
				)
			);

			Util_Ui::config_item(
				array(
					'key'            => array( 'swarmify', 'reject.logged' ),
					'label'          => __( 'Logged In:', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'checkbox_label' => __( 'Don\'t optimize videos for logged in users', 'w3-total-cache' ),
					'description'    => __( 'Only unauthenticated users will view optimized version of a given page.', 'w3-total-cache' ),
				)
			);
			?>
		</table>
		<?php Util_Ui::button_config_save( 'extension_swarmify_behaviour' ); ?>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
