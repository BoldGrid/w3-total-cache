<?php
/**
 * File: UserExperience_Remove_CssJs_Page_View.php
 *
 * Renders the remove CSS/JS setting block on the UserExperience advanced settings page.
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

$remove_cssjs_singles = $c->get_array( 'user-experience-remove-cssjs-singles' );

// If old data structure convert to new.
// Old data structure used url_pattern as the key for each block. New uses indicies and has url_pattern within.
if ( ! is_numeric( key( $remove_cssjs_singles ) ) ) {
	$new_array = array();
	foreach ( $remove_cssjs_singles as $match => $data ) {
		if ( empty( $data['includes'] ) ) {
			continue;
		}

		$new_array[] = array(
			'url_pattern'      => $match,
			'action'           => isset( $data['action'] ) ? $data['action'] : 'exclude',
			'includes'         => $data['includes'],
			'includes_content' => $data['includes_content'],
		);
	}
	$remove_cssjs_singles = $new_array;
}

Util_Ui::postbox_header( esc_html__( 'Remove CSS/JS On Homepage', 'w3-total-cache' ), '', 'remove-cssjs' );
?>
<div class="w3tc-gopro-manual-wrap">
	<?php Util_Ui::pro_wrap_maybe_start(); ?>
	<p>
		<?php esc_html_e( 'CSS/JS entries added to the below textarea will be removed from the homepage if present.', 'w3-total-cache' ); ?>
	</p>
	<?php
	if ( ! $is_pro ) {
		Util_Ui::print_score_block(
			__( 'Potential Google PageSpeed Gain', 'w3-total-cache' ),
			'+27',
			__( 'Points', 'w3-total-cache' ),
			__( 'In a recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score!', 'w3-total-cache' ),
			'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof'
		);
	}
	?>
	<table class="form-table">
		<?php
		Util_Ui::config_item_pro(
			array(
				'key'             => array( 'user-experience-remove-cssjs', 'includes' ),
				'label'           => esc_html__( 'Remove list:', 'w3-total-cache' ),
				'control'         => 'textarea',
				'disabled'        => ! UserExperience_Remove_CssJs_Extension::is_enabled(),
				'description'     => array(),
				'excerpt'         => esc_html__( 'Specify absolute or relative URLs, or file names to be excluded from loading on the homepage. Include one entry per line, e.g. (googletagmanager.com, /wp-content/plugins/woocommerce/, myscript.js, name="myscript", etc.)', 'w3-total-cache' ),
				'show_learn_more' => false,
				'no_wrap'         => true,
			)
		);
		?>
	</table>
	<?php

	if ( $is_pro && ! UserExperience_Remove_CssJs_Extension::is_enabled() ) {
		echo wp_kses(
			sprintf(
				// translators: 1: Opening HTML em tag, 2: Closing HTML em tag, 3: Opening HTML a tag with a link to General Settings, 4: Closing HTML a tag.
				__(
					'%1$sRemove Unwanted/Unused CSS/JS%2$s is not enabled in the %3$sGeneral Settings%4$s.',
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
	Util_Ui::pro_wrap_maybe_end( 'remove_cssjs_home', false );
	?>
</div>
<?php
Util_Ui::postbox_footer();

Util_Ui::postbox_header( esc_html__( 'Remove CSS/JS Individually', 'w3-total-cache' ), '', 'remove-cssjs-singles' );
?>
<div class="w3tc-gopro-manual-wrap">
	<?php Util_Ui::pro_wrap_maybe_start(); ?>
	<p>
		<?php esc_html_e( 'Use this area to manage individual CSS/JS entries. The target CSS/JS for each rule can be either an absolute/relative URL or file name.', 'w3-total-cache' ); ?>
	</p>
	<p>
		<input id="w3tc_remove_cssjs_singles_add" type="button" class="button" value="<?php esc_html_e( 'Add', 'w3-total-cache' ); ?>" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled="disabled"'; ?>/>
	</p>
	<ul id="remove_cssjs_singles" class="w3tc_remove_cssjs_singles">

		<?php
		if ( ! empty( $remove_cssjs_singles ) ) {
			foreach ( $remove_cssjs_singles as $single_id => $single_config ) {
				if ( ! is_array( $single_config ) ) {
					continue;
				}

				$single_config['includes']         = isset( $single_config['includes'] ) && ! empty( $single_config['includes'] ) ? implode( "\r\n", (array) $single_config['includes'] ) : '';
				$single_config['includes_content'] = isset( $single_config['includes_content'] ) && ! empty( $single_config['includes_content'] ) ? implode( "\r\n", (array) $single_config['includes_content'] ) : '';
				?>
				<li id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>">
					<table class="form-table">
						<tr class="accordion-header">
							<th>
								<?php esc_html_e( 'File Path:', 'w3-total-cache' ); ?>
							</th>
							<td>
								<input class="remove_cssjs_singles_path" type="text" required="required" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][url_pattern]" value="<?php echo esc_attr( $single_config['url_pattern'] ); ?>" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>>
								<input type="button" class="button remove_cssjs_singles_delete" value="<?php esc_html_e( 'Delete', 'w3-total-cache' ); ?>" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>/>
								<span class="accordion-toggle dashicons dashicons-arrow-down-alt2"></span>
								<p class="description">
									<?php esc_html_e( 'Enter the path of the CSS/JS file to be managed. If a directory is used, all CSS/JS files within that directory will be managed with this entry', 'w3-total-cache' ); ?>
								</p>
								<div class="description_example">
									<p class="description_example_trigger">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="description_example_text"><?php esc_html_e( 'View Examples', 'w3-total-cache' ); ?></span>
									</p>
									<div class="description">
										<strong><?php esc_html_e( 'Target all CSS/JS from a plugin/theme:', 'w3-total-cache' ); ?></strong>
										<code>
											<?php
											echo wp_kses(
												'https://example.com/wp-content/plugins/example-plugin/<br/>/wp-content/plugins/example-plugin/',
												array(
													'br' => array(),
												)
											);
											?>
										</code>
										<strong><?php esc_html_e( 'Target a specific CSS/JS file:', 'w3-total-cache' ); ?></strong>
										<code>
											<?php
											echo wp_kses(
												'https://example.com/wp-content/themes/example-theme/example-script.js<br/>/wp-content/themes/example-script.js<br/>example-script.js',
												array(
													'br' => array(),
												)
											);
											?>
										</code>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th>
								<label for="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_action">
									<?php esc_html_e( 'Action:', 'w3-total-cache' ); ?>
								</label>
							</th>
							<td>
								<p class="description">
									<?php esc_html_e( 'When the above CSS/JS file is found within your markup.', 'w3-total-cache' ); ?>
								</p>
								<label class="remove_cssjs_singles_behavior">
									<input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][action]" value="exclude" <?php echo 'exclude' === $single_config['action'] ? 'checked="checked"' : ''; ?> <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>>
									<?php
									echo wp_kses(
										sprintf(
											// translators: 1 HTML opening strong tag, 2 closing HTML strong tag.
											__(
												'%1$sExclude%2$s (Remove the script ONLY WHEN a condition below matches)',
												'w3-total-cache'
											),
											'<strong>',
											'</strong>'
										),
										array(
											'strong' => array(),
										)
									);
									?>
								</label>
								<br/>
								<label class="remove_cssjs_singles_behavior">
									<input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][action]" value="include" <?php echo 'include' === $single_config['action'] ? 'checked="checked"' : ''; ?> <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>>
									<?php
									echo wp_kses(
										sprintf(
											// translators: 1 HTML opening strong tag, 2 closing HTML strong tag.
											__(
												'%1$sInclude%2$s (Allow the script ONLY WHEN a condition below matches)',
												'w3-total-cache'
											),
											'<strong>',
											'</strong>'
										),
										array(
											'strong' => array(),
										)
									);
									?>
								</label>
							</td>
						</tr>
						<tr id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_option">
							<th>
								<label class="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_label" for="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes">
									<?php
									$label = 'exclude' === $single_config['action'] ? __( 'Exclude on URL Match:', 'w3-total-cache' ) : __( 'Include on URL Match:', 'w3-total-cache' );
									echo esc_html( $label );
									?>
								</label>
							</th>
							<td>
								<textarea id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][includes]" rows="5" cols="50" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>><?php echo wp_kses( $single_config['includes'], Util_UI::get_allowed_html_for_wp_kses_from_content( $single_config['includes'] ) ); ?></textarea>
								<p class="description remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_description">
									<?php
									echo esc_html(
										sprintf(
											// translators: 1 action description based on behavior selector.
											__(
												'Specify the conditions for which the target file should be %1$sd based on matching absolute/relative page URLs. Include one entry per line.',
												'w3-total-cache'
											),
											'exclude' === $single_config['action'] ? __( 'exclude', 'w3-total-cache' ) : __( 'include', 'w3-total-cache' )
										)
									);
									?>
								</p>
								<div class="description_example">
									<p class="description_example_trigger">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="description_example_text"><?php esc_html_e( 'View Examples', 'w3-total-cache' ); ?></span>
									</p>
									<div class="description">
										<code>
											<?php
											echo wp_kses(
												'https://example.com/example-page/<br/>/example-page/<br/>example-page?arg=example-arg',
												array(
													'br' => array(),
												)
											);
											?>
										</code>
									</div>
								</div>
							</td>
						</tr>
						<tr id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_content_option">
							<th>
								<label class="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_content_label" for="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_content">
									<?php
									$label = 'exclude' === $single_config['action'] ? __( 'Exclude on Content Match:', 'w3-total-cache' ) : __( 'Include on Content Match:', 'w3-total-cache' );
									echo esc_html( $label );
									?>
								</label>
							</th>
							<td>
								<textarea id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_content" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][includes_content]" rows="5" cols="50" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>><?php echo wp_kses( $single_config['includes_content'], Util_UI::get_allowed_html_for_wp_kses_from_content( $single_config['includes_content'] ) ); ?></textarea>
								<p class="description remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_content_description">
									<?php
									echo esc_html(
										sprintf(
											// translators: 1 action description based on behavior selector.
											__(
												'Specify the conditions for which the target file should be %1$sd based on matching page content. Include one entry per line.',
												'w3-total-cache'
											),
											'exclude' === $single_config['action'] ? __( 'exclude', 'w3-total-cache' ) : __( 'include', 'w3-total-cache' )
										)
									);
									?>
								</p>
								<div class="description_example">
									<p class="description_example_trigger">
										<span class="dashicons dashicons-editor-help"></span>
										<span class="description_example_text"><?php esc_html_e( 'View Examples', 'w3-total-cache' ); ?></span>
									</p>
									<div class="description">
										<code>
											<?php
											echo wp_kses(
												'&lt;div id="example-id"&gt;<br/>&lt;span class="example-class"&gt;<br/>name="example-name"',
												array(
													'br' => array(),
												)
											);
											?>
										</code>
									</div>
								</div>
							</td>
						</tr>
					</table>
				</li>
				<?php
			}
		} else {
			?>
			<li id="remove_cssjs_singles_empty">
				<?php esc_html_e( 'No CSS/JS entries added.', 'w3-total-cache' ); ?>
				<input type="hidden" name="user-experience-remove-cssjs-singles[]">
			</li>
			<?php
		}
		?>
	</ul>
	<?php
	if ( ! $is_pro ) {
		Util_Ui::print_score_block(
			__( 'Potential Google PageSpeed Gain', 'w3-total-cache' ),
			'+27',
			__( 'Points', 'w3-total-cache' ),
			__( 'In a recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score!', 'w3-total-cache' ),
			'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof'
		);
	} elseif ( ! UserExperience_Remove_CssJs_Extension::is_enabled() ) {
		echo wp_kses(
			sprintf(
				// translators: 1: Opening HTML em tag, 2: Closing HTML em tag, 3: Opening HTML a tag with a link to General Settings, 4: Closing HTML a tag.
				__(
					'%1$sRemove Unwanted/Unused CSS/JS%2$s is not enabled in the %3$sGeneral Settings%4$s.',
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

	Util_Ui::pro_wrap_maybe_end( 'remove_cssjs_singles', false );
	?>
</div>
<?php
Util_Ui::postbox_footer();
