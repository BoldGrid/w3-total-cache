<?php
/**
 * File: Extension_AiCrawler_Page_View_Settings.php
 *
 * Render the AI Crawler settings page - Configuration box.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( esc_html__( 'Configuration', 'w3-total-cache' ), '', 'configuration' ); ?>
	<table class="form-table">
		<?php
		Util_Ui::config_item(
			array(
				'key'          => array(
					'aicrawler',
					'imh_central_client',
				),
				'label'        => sprintf(
					// translators: 1: InMotion Central brand name.
					__(
						'%1$s ID:',
						'w3-total-cache'
					),
					'InMotion Central'
				),
				'control'      => 'textbox',
				'description'  => sprintf(
					// translators: 1: InMotion Central brand name.
					__(
						'An %1$s Client ID is required.',
						'w3-total-cache'
					),
					'InMotion Central'
				),
				'textbox_size' => 40,
			)
		);

		Util_Ui::config_item(
			array(
				'key'           => array(
					'aicrawler',
					'imh_central_token',
				),
				'label'         => sprintf(
					// translators: 1: InMotion Central brand name.
					__(
						'%1$s Token:',
						'w3-total-cache'
					),
					'InMotion Central'
				),
				'control'       => 'textbox',
				'description'   => sprintf(
					// translators: 1: InMotion Central brand name.
					__(
						'An %1$s token is required.',
						'w3-total-cache'
					),
					'InMotion Central'
				),
				'control_after' => '<button id="w3tc-aicrawler-test-ctoken-button" class="button">' . esc_html__( 'Test', 'w3-total-cache' ) . '</button>',
				'textbox_size'  => 40,
			)
		);

		Util_Ui::config_item(
			array(
				'key'            => array(
					'aicrawler',
					'auto_generate',
				),
				'label'          => esc_html__( 'Regenerate Markdown On Page/Post Update:', 'w3-total-cache' ),
				'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
				'control'        => 'checkbox',
				'description'    => esc_html__( 'Enabling this will automatically regenerate markdown files when a page/post is updated. Leaving this disabled will require manual action to regenerate the markdown files.', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'         => array(
					'aicrawler',
					'exclusions',
				),
				'control'     => 'textarea',
				'label'       => esc_html__( 'Excluded URLs:', 'w3-total-cache' ),
				'description' => esc_html__( 'Absolute/Relative URLs or regular expression patterns defined here will be excluded from the AI Crawler service and will not be included in the markdown generation process. Include one entry per line.', 'w3-total-cache' ),
			)
		);

		Util_Ui::config_item(
			array(
				'key'     => array(
					'aicrawler',
					'exclusions_pts',
				),
				'label'   => esc_html__( 'Excluded Post Types:', 'w3-total-cache' ),
				'control' => 'checkbox_group',
				'values'  => get_post_types(
					array(
						'public'   => true,
						'_builtin' => true,
					)
				),
			)
		);

		Util_Ui::config_item(
			array(
				'key'         => array(
					'aicrawler',
					'exclusions_cpts',
				),
				'control'     => 'textarea',
				'label'       => esc_html__( 'Excluded Custom Post Types:', 'w3-total-cache' ),
				'description' => esc_html__( 'Custom post type slugs defined here will be excluded from the AI Crawler service and will not be included in the markdown generation process. Include one entry per line.', 'w3-total-cache' ),
			)
		);
		?>
	</table>
	<?php Util_Ui::postbox_footer(); ?>
</div>
