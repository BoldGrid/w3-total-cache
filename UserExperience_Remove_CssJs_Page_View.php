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

$remove_cssjs_singles = array(
	'value' => $c->get_array( 'user-experience-remove-cssjs-singles' ),
);

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
			'disabled'          => ( $is_pro ? null : true ),
			'description'       => array(),
			'excerpt'           => esc_html__( 'Specify URLs that should be removed. Include one entry per line, e.g. (googletagmanager.com, gtag/js, myscript.js, and name="myscript")', 'w3-total-cache' ),
			'show_learn_more'   => false,
			'score'             => '27+',
			'score_description' => ! $is_pro
				? wp_kses(
					sprintf(
						// translators: 1  opening HTML a tag, 2 closing HTML a tag followed by two HTML br tags, 3 HTML input button to purchase pro license.
						__(
							'In a recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score! %1$sReview the testing results%2$s to see how.%3$s%4$s and improve your PageSpeed Scores today!',
							'w3-total-cache'
						),
						'<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof' ) . '">',
						'</a><br /><br />',
						'<input type="button" class="button-primary btn button-buy-plugin" data-src="test_score_upgrade" value="' . esc_attr__( 'Upgrade to', 'w3-total-cache' ) . ' W3 Total Cache Pro">'
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
				: '',
		)
	);

	?>
</table>
<?php
Util_Ui::postbox_footer();

Util_Ui::postbox_header( esc_html__( 'Remove CSS/JS Individually', 'w3-total-cache' ), '', 'remove-cssjs-singles' );
?>
<p>
	<?php esc_html_e( 'Use this area to manage individual CSS/JS entries. Defined CSS/JS URLs will include a textarea indicating which pages the given entry should be removed from.', 'w3-total-cache' ); ?>
</p>
<div class="w3tc-gopro-manual-wrap">
	<?php Util_Ui::pro_wrap_maybe_start(); ?>
	<p>
		<input id="w3tc_remove_cssjs_singles_add" type="button" class="button" value="<?php esc_html_e( 'Add', 'w3-total-cache' ); ?>" <?php echo $is_pro ? '' : 'disabled="disabled"'; ?>/>
	</p>
	<ul id="remove_cssjs_singles" class="w3tc_remove_cssjs_singles">
		<?php
		foreach ( $remove_cssjs_singles['value'] as $single => $single_config ) {
			?>
			<li id="remove_cssjs_singles_<?php echo esc_attr( $single ); ?>">
				<table class="form-table">
					<tr>
						<th>
							<?php esc_html_e( 'CSS/JS path to remove:', 'w3-total-cache' ); ?>
						</th>
						<td>
							<span class="remove_cssjs_singles_path"><?php echo htmlspecialchars( $single ); // phpcs:ignore ?></span>
							<input type="button" class="button w3tc_remove_cssjs_singles_delete" value="<?php esc_html_e( 'Delete', 'w3-total-cache' ); ?>" <?php echo $is_pro ? '' : 'disabled="disabled"'; ?>/>
						</td>
					</tr>
						<tr>
						<th>
							<label for="remove_cssjs_singles_<?php echo esc_attr( $single ); ?>_includes">
								<?php esc_html_e( 'Remove on these pages:', 'w3-total-cache' ); ?>
							</label>
						</th>
						<td>
							<textarea id="remove_cssjs_singles_<?php echo esc_attr( $single ); ?>_includes" name="user-experience-remove-cssjs-singles[<?php echo esc_attr( $single ); ?>][includes]" rows="5" cols="50" <?php echo $is_pro ? '' : 'disabled'; ?>><?php echo esc_textarea( implode( "\r\n", (array) $single_config['includes'] ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Specify relative/absolute page URLs that the above CSS/JS should be removed from. Include one entry per line.', 'w3-total-cache' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</li>
			<?php
		}
		?>
	</ul>
	<div id="remove_cssjs_singles_empty" style="display: none;"><?php esc_html_e( 'No CSS/JS removal entires added.', 'w3-total-cache' ); ?></div>
	<?php
	if ( ! $is_pro ) {
		Util_Ui::print_score_block(
			'27+',
			wp_kses(
				sprintf(
					// translators: 1  opening HTML a tag, 2 closing HTML a tag followed by two HTML br tags, 4 HTML input button to purchase pro license.
					__(
						'In a recent test, removing unused CSS and JS added over 27 points to the Google PageSpeed score! %1$sReview the testing results%2$s to see how.%3$s%4$s and improve your PageSpeed Scores today!',
						'w3-total-cache'
					),
					'<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/?utm_source=w3tc&utm_medium=remove-css-js&utm_campaign=proof' ) . '">',
					'</a><br /><br />',
					'<input type="button" class="button-primary btn button-buy-plugin" data-src="test_score_upgrade" value="' . esc_attr__( 'Upgrade to', 'w3-total-cache' ) . ' W3 Total Cache Pro">'
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
	}

	Util_Ui::pro_wrap_maybe_end( 'remove_cssjs_singles' );
	?>
</div>
<?php
Util_Ui::postbox_footer();
