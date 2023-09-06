<?php
/**
 * File: Cdn_BunnyCdn_Page_View.php
 *
 * BunnyCDN settings page section view.
 *
 * @since   X.X.X
 * @package W3TC
 *
 * @param array  $config          W3TC configuration.
 * @param string $account_api_key Account API key.
 * @param string $storage_api_key Storage API key.
 * @param string $stream_api_key  Stream API key.
 * @param bool   $is_authorized   Whether or not the account API key is authorized.
 */

namespace W3TC;

defined( 'W3TC' ) || die();

if ( ! $is_authorized ) {
	?>
	<tr>
		<th style="width: 300px;"><label><?php esc_html_e( 'Create account:', 'w3-total-cache' ); ?></label></th>
		<td>
			<p class="notice notice-error">
				<?php
				w3tc_e(
					'cdn.bunnycdn.widget.v2.no_cdn',
					sprintf(
						// translators: 1: HTML acronym for Content Delivery Network (CDN).
						__( 'W3 Total Cache has detected that you do not have a %1$s configured', 'w3-total-cache' ),
						'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
					)
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'Enhance your website Performance with BunnyCDN\'s services. BunnyCDN works magically with W3 Total Cache to speed up your site around the world for as little as $1 a month.', 'w3-total-cache' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( W3TC_BUNNYCDN_SIGNUP_URL ); ?>" target="_blank" id="bunnycdn-create-account" class="button-primary"><?php w3tc_e( 'cdn.bunnycdn.signUpAndSave', __( 'Sign Up Now and save!', 'w3-total-cache' ) ); ?></a>
			</p>
			<p class="description">
				<?php
				w3tc_e(
					'cdn.bunnycdn.signUpAndSave.description',
					__( 'BunnyCDN is a service that lets you speed up your site even more with W3 Total Cache. Sign up now to receive a special offer!', 'w3-total-cache' )
				);
				?>
			</p>
		</td>
	</tr>
	<?php
}
?>

<tr>
	<th style="width: 300px;">
		<label>
			<?php esc_html_e( 'Specify account credentials:', 'w3-total-cache' ); ?>
		</label>
	</th>
	<td>
		<p>
			<?php esc_html_e( 'If you\'re an existing BunnyCDN customer, enable CDN and Authorize. If you need help configuring your CDN, we also offer Premium Services to assist you.', 'w3-total-cache' ); ?>
		</p>

		<p>
			<?php if ( $is_authorized ) : ?>
				<input class="w3tc_cdn_bunnycdn_authorize button-primary"
					type="button"
					value="<?php esc_attr_e( 'Reauthorize', 'w3-total-cache' ); ?>" />
			<?php else : ?>
				<input class="w3tc_cdn_bunnycdn_authorize button-primary"
					type="button"
					value="<?php esc_attr_e( 'Authorize', 'w3-total-cache' ); ?>" />
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), 'w3tc' ) ); ?>"><?php esc_html_e( 'Premium Services', 'w3-total-cache' ); ?></a>
		</p>
	</td>
</tr>

<?php if ( $is_authorized ) : ?>
	<?php if ( ! is_null( $http_domain ) ) : ?>
		<tr>
			<th>
				<label>
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1: opening HTML acronym tag, 2: opening HTML acronym tag.
							// translators: 3: opening HTML acronym tag, 4: closing HTML acronym tag.
							__(
								'%1$sCDN%4$s %2$sHTTP%4$s %3$sCNAME%4$s:',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'HyperText Transfer Protocol', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'Canonical Name', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</label>
			</th>
			<td class="w3tc_config_value_text">
				<?php echo esc_html( $http_domain ); ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1: opening HTML acronym tag, 2: opening HTML acronym tag.
							// translators: 3: opening HTML acronym tag, 4: closing HTML acronym tag.
							__(
								'This website domain has to be %1$sCNAME%4$s pointing to this %2$sCDN%4$s domain for %3$sHTTP%4$s requests',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Canonical Name', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'HyperText Transfer Protocol', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</p>
			</td>
		</tr>
	<?php endif; ?>

	<?php if ( ! is_null( $https_domain ) ) : ?>
		<tr>
			<th>
				<label>
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1: opening HTML acronym tag, 2: opening HTML acronym tag.
							// translators: 3: opening HTML acronym tag, 4: closing HTML acronym tag.
							__(
								'%1$sCDN%4$s %2$sHTTPS%4$s %3$sCNAME%4$s:',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'HyperText Transfer Protocol over SSL', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'Canonical Name', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</label>
			</th>
			<td class="w3tc_config_value_text">
				<?php echo esc_html( $https_domain ); ?>
				<p class="description">
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1: opening HTML acronym tag, 2: opening HTML acronym tag.
							// translators: 3: opening HTML acronym tag, 4: closing HTML acronym tag.
							__(
								'This website domain has to be %1$sCNAME%4$s pointing to this %2$sCDN%4$s domain for %3$sHTTPS%4$s requests',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Canonical Name', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'<acronym title="' . esc_attr__( 'HyperText Transfer Protocol over SSL', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</p>
			</td>
		</tr>
	<?php endif; ?>

	<tr>
		<th>
			<label for="cdn_bunnycdn_ssl">
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: opening HTML acronym tag, 2: closing HTML acronym tag.
						__(
							'%1$sSSL%2$s support:',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Secure Sockets Layer', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
				?>
			</label>
		</th>
		<td>
			<select id="cdn_bunnycdn_ssl" name="cdn__bunnycdn__ssl" <?php Util_Ui::sealing_disabled( 'cdn.' ); ?>>
				<option value="auto"<?php selected( $config->get_string( 'cdn.bunnycdn.ssl' ), 'auto' ); ?>>
					<?php esc_html_e( 'Auto (determine connection type automatically)', 'w3-total-cache' ); ?>
				</option>
				<option value="enabled"<?php selected( $config->get_string( 'cdn.bunnycdn.ssl' ), 'enabled' ); ?>>
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1: opening HTML acronym tag, 2: closing HTML acronym tag.
							__(
								'Enabled (always use %1$sSSL%2$s)',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Secure Sockets Layer', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</option>
				<option value="disabled"<?php selected( $config->get_string( 'cdn.bunnycdn.ssl' ), 'disabled' ); ?>>
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1: opening HTML acronym tag, 2: closing HTML acronym tag.
							__(
								'Disabled (always use %1$sHTTP%2$s)',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'HyperText Transfer Protocol', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</option>
			</select>
			<p class="description">
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: opening HTML acronym tag, 2: opening HTML acronym tag, 3: closing HTML acronym tag.
						__(
							'Some %1$sCDN%3$s providers may or may not support %2$sSSL%3$s, contact your vendor for more information.',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'<acronym title="' . esc_attr__( 'Secure Sockets Layer', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Replace site\'s hostname with:', 'w3-total-cache' ); ?></th>
		<td>
			<?php
			$cnames = $config->get_array( 'cdn.bunnycdn.domain' );
			include W3TC_INC_DIR . '/options/cdn/common/cnames.php';
			?>
			<p class="description">
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1: opening HTML acronym tag, 2: opening HTML acronym tag, 3: closing HTML acronym tag.
						__(
							'Enter the hostname provided by your %1$sCDN%3$s provider, this value will replace your site\'s hostname in the %2$sHTML%3$s.',
							'w3-total-cache'
						),
						'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
						'<acronym title="' . esc_attr__( 'Hypertext Markup Language', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<input id="cdn_test" class="button {type: 'bunnycdn', nonce: '<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>'}" type="button" value="<?php esc_attr_e( 'Test BunnyCDN', 'w3-total-cache' ); ?>" /> <span id="cdn_test_status" class="w3tc-status w3tc-process"></span>
		</th>
	</tr>

<?php endif; ?>
