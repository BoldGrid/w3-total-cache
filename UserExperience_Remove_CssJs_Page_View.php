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
			'url_pattern' => $match,
			'action'      => isset( $data['action'] ) ? $data['action'] : 'exclude',
			'includes'    => $data['includes'],
		);
	}
	$remove_cssjs_singles = $new_array;
}

Util_Ui::postbox_header( esc_html__( 'Remove CSS/JS On Homepage', 'w3-total-cache' ), '', 'remove-cssjs' );
?>
<p>
	<?php esc_html_e( 'CSS/JS entries added to the below textarea will be removed from the homepage if present.', 'w3-total-cache' ); ?>
</p>
<table class="form-table">
	<?php
	Util_Ui::config_item_pro(
		array(
			'key'               => array( 'user-experience-remove-cssjs', 'includes' ),
			'label'             => esc_html__( 'Remove list:', 'w3-total-cache' ),
			'control'           => 'textarea',
			'disabled'          => ! UserExperience_Remove_CssJs_Extension::is_enabled(),
			'description'       => array(),
			'excerpt'           => esc_html__( 'Specify absolute or relative URLs, or file names to be excluded from loading on the homepage. Include one entry per line, e.g. (googletagmanager.com, /wp-content/plugins/woocommerce/, myscript.js, name="myscript", etc.)', 'w3-total-cache' ),
			'show_learn_more'   => false,
			'score'             => '+27',
			'score_description' => wp_kses(
				sprintf(
					// translators: 1  opening HTML a tag, 2 closing HTML a tag, 3 two HTML br tags followed by a HTML input button to purchase pro license.
					__(
						'In a recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score! %1$sReview the testing results%2$s to see how.%3$s and improve your PageSpeed Scores today!',
						'w3-total-cache'
					),
					'<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof' ) . '">',
					'</a>',
					'<br /><br /><input type="button" class="button-primary btn button-buy-plugin" data-src="test_score_upgrade" value="' . esc_attr__( 'Upgrade to', 'w3-total-cache' ) . ' W3 Total Cache Pro">'
				),
				array(
					'a'      => array(
						'href'   => array(),
						'target' => array(),
					),
					'br'     => array(),
					'input'  => array(
						'type'     => array(),
						'class'    => array(),
						'data-src' => array(),
						'value'    => array(),
					),
				)
			),
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

Util_Ui::postbox_footer();

Util_Ui::postbox_header( esc_html__( 'Remove CSS/JS Individually', 'w3-total-cache' ), '', 'remove-cssjs-singles' );
?>
<p>
	<?php esc_html_e( 'Use this area to manage individual CSS/JS entries. The target CSS/JS for each rule can be either an absolute/relative URL or file name, e.g. (googletagmanager.com, /wp-content/plugins/woocommerce/, myscript.js, name="myscript", etc.)', 'w3-total-cache' ); ?>
</p>
<div class="w3tc-gopro-manual-wrap">
	<?php Util_Ui::pro_wrap_maybe_start(); ?>
	<p>
		<input id="w3tc_remove_cssjs_singles_add" type="button" class="button" value="<?php esc_html_e( 'Add', 'w3-total-cache' ); ?>" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled="disabled"'; ?>/>
	</p>
	<ul id="remove_cssjs_singles" class="w3tc_remove_cssjs_singles">

		<?php
		if ( ! empty( $remove_cssjs_singles ) ) {
			foreach ( $remove_cssjs_singles as $single_id => $single_config ) {
				?>
				<li id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>">
					<table class="form-table">
						<tr>
							<th>
								<?php esc_html_e( 'Target CSS/JS:', 'w3-total-cache' ); ?>
							</th>
							<td>
								<input class="remove_cssjs_singles_path" type="text" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][url_pattern]" value="<?php echo esc_attr( $single_config['url_pattern'] ); ?>" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>>
								<input type="button" class="button remove_cssjs_singles_delete" value="<?php esc_html_e( 'Delete', 'w3-total-cache' ); ?>" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>/>
							</td>
						</tr>
						<tr>
							<th>
								<label for="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_action">
									<?php esc_html_e( 'Behavior:', 'w3-total-cache' ); ?>
								</label>
							</th>
							<td>
								<label class="remove_cssjs_singles_behavior">
									<input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][action]" value="exclude" <?php echo 'exclude' === $single_config['action'] ? 'checked="checked"' : ''; ?> <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>>
									<?php esc_html_e( 'Exclude', 'w3-total-cache' ); ?>
								</label>
								<label class="remove_cssjs_singles_behavior">
									<input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][action]" value="include" <?php echo 'include' === $single_config['action'] ? 'checked="checked"' : ''; ?> <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>>
									<?php esc_html_e( 'Include', 'w3-total-cache' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Exclude will only remove match(es) from the specified URLs.', 'w3-total-cache' ); ?></p>
								<p class="description"><?php esc_html_e( 'Include will NOT remove match(es) from the specified URLs but will remove it everywhere else.', 'w3-total-cache' ); ?></p>
							</td>
						</tr>
						<tr id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_option">
							<th>
								<label class="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_label" for="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes">
									<?php
									$label = 'exclude' === $single_config['action'] ? __( 'Exclude on these pages:', 'w3-total-cache' ) : __( 'Include on these pages:', 'w3-total-cache' );
									echo esc_html( $label );
									?>
								</label>
							</th>
							<td>
								<textarea id="remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single_id ); ?>][includes]" rows="5" cols="50" <?php echo UserExperience_Remove_CssJs_Extension::is_enabled() ? '' : 'disabled'; ?>><?php echo esc_textarea( implode( "\r\n", (array) $single_config['includes'] ) ); ?></textarea>
								<p class="description remove_cssjs_singles_<?php echo esc_attr( $single_id ); ?>_includes_description">
									<?php
									echo esc_html(
										sprintf(
											// translators: 1 action description based on behavior selector.
											__(
												'Specify the absolute or relative URLs from which the above CSS/JS file should be %1$s. Include one entry per line.',
												'w3-total-cache'
											),
											'exclude' === $single_config['action'] ? __( 'excluded', 'w3-total-cache' ) : __( 'included', 'w3-total-cache' )
										)
									);
									?>
								</p>
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
			'+27',
			wp_kses(
				sprintf(
					// translators: 1  opening HTML a tag, 2 closing HTML a tag, 3 two HTML br tags followed by a HTML input button to purchase pro license.
					__(
						'In a recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score! %1$sReview the testing results%2$s to see how.%3$s and improve your PageSpeed Scores today!',
						'w3-total-cache'
					),
					'<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof' ) . '">',
					'</a>',
					'<br /><br /><input type="button" class="button-primary btn button-buy-plugin" data-src="test_score_upgrade" value="' . esc_attr__( 'Upgrade to', 'w3-total-cache' ) . ' W3 Total Cache Pro">'
				),
				array(
					'a'      => array(
						'href'   => array(),
						'target' => array(),
					),
					'br'     => array(),
					'input'  => array(
						'type'     => array(),
						'class'    => array(),
						'data-src' => array(),
						'value'    => array(),
					),
				)
			)
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
