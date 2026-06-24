<?php
/**
 * File: list.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

/**
 * Extensions list template variables.
 *
 * @var string $extension_status
 * @var int $page
 * @var array $extensions list of extensions for current $extension_status
 * @var array $extensions_all list of all extensions
 * @var array $extensions_active list of all active extensions
 * @var array $extensions_inactive list of all inactive extensions
 * @var array $extensions_core list of all core extensions
 */
?>
<ul class="subsubsub">
	<li class="all"><a href="?page=w3tc_extensions&extension_status=all"<?php echo 'all' === $extension_status ? ' class="current"' : ''; ?>>All <span class="count">(<?php echo esc_html( count( $extensions_all ) ); ?>)</span></a> |</li>
	<li class="active"><a href="?page=w3tc_extensions&extension_status=active"<?php echo 'active' === $extension_status ? ' class="current"' : ''; ?>>Active <span class="count">(<?php echo esc_html( count( $extensions_active ) ); ?>)</span></a> |</li>
	<li class="inactive"><a href="?page=w3tc_extensions&extension_status=inactive"<?php echo 'inactive' === $extension_status ? ' class="current"' : ''; ?>>Inactive <span class="count">(<?php echo esc_html( count( $extensions_inactive ) ); ?>)</span></a></li>
</ul>

<div class="tablenav top">

	<?php if ( ! $this->_config->is_sealed( 'extensions.active' ) ) : ?>
		<div class="alignleft actions">
			<?php
			/**
			 * Bulk activate/deactivate nonce. Verified
			 * by Extensions_Plugin_Admin::change_extensions_status() via
			 * Util_Nonce::verify_admin( 'w3tc_extensions_bulk' ).
			 *
			 * @since 2.10.0
			 */
			wp_nonce_field( 'w3tc_extensions_bulk' );
			?>
			<select name="action">
				<option value="-1" selected="selected"><?php esc_html_e( 'Bulk Actions', 'w3-total-cache' ); ?></option>
				<option value="activate-selected"><?php esc_html_e( 'Activate', 'w3-total-cache' ); ?></option>
				<option value="deactivate-selected"><?php esc_html_e( 'Deactivate', 'w3-total-cache' ); ?></option>
			</select>
			<input type="submit" name="" id="doaction" class="w3tc-button-save button action" value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>">
		</div>
	<?php endif ?>

	<div class="tablenav-pages one-page">
		<span class="displaying-num">
			<?php
			echo esc_html(
				sprintf(
					translate_nooped_plural(
						// translators: 1 count of extensions.
						_n_noop(
							'%s extension',
							'%s extensions',
							'w3-total-cache'
						),
						count( $extensions ),
						'w3-total-cache'
					),
					count( $extensions )
				)
			);
			?>
		</span>
	</div>
	<br class="clear">
</div>
<table class="wp-list-table widefat plugins w3tc_extensions" cellspacing="0">
	<thead>
		<tr>
			<th scope="col" id="cb" class="w3tc_extensions_manage_column_check"><label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'w3-total-cache' ); ?></label><input id="cb-select-all-1" type="checkbox" class="w3tc_extensions_manage_input_checkall"></th><th scope="col" id="name" class="manage-column column-name" style=""><?php esc_html_e( 'Extension', 'w3-total-cache' ); ?></th><th scope="col" id="description" class="manage-column column-description" style=""><?php esc_html_e( 'Description', 'w3-total-cache' ); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th scope="col" class="w3tc_extensions_manage_column_check"><label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'w3-total-cache' ); ?></label><input id="cb-select-all-2" type="checkbox" class="w3tc_extensions_manage_input_checkall"></th><th scope="col" class="manage-column column-name" style=""><?php esc_html_e( 'Extension', 'w3-total-cache' ); ?></th><th scope="col" class="manage-column column-description" style=""><?php esc_html_e( 'Description', 'w3-total-cache' ); ?></th>
		</tr>
	</tfoot>
	<tbody id="the-list">
		<?php
		$w3tc_cb_id = 0;
		foreach ( $extension_keys as $w3tc_extension ) :
			$w3tc_meta = $extensions[ $w3tc_extension ];
			$w3tc_meta = $this->default_meta( $w3tc_meta );
			if ( ! $w3tc_meta['public'] ) {
				continue;
			}

			++$w3tc_cb_id;

			do_action( "w3tc_extension_before_row-{$w3tc_extension}" );

			?>
			<tr id="<?php echo esc_attr( $w3tc_extension ); ?>" class="<?php echo $this->_config->is_extension_active( $w3tc_extension ) ? 'active' : 'inactive'; ?>">
				<th scope="row" class="check-column">
					<label class="screen-reader-text" for="checkbox_<?php echo esc_attr( $w3tc_cb_id ); ?>"><?php echo esc_html( sprintf( /* translators: 1 label for Extension select/deselect checkobox */ __( 'Select %1$s', 'w3-total-cache' ), $w3tc_meta['name'] ) ); ?></label>
					<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $w3tc_extension ); ?>" id="checkbox_<?php echo esc_attr( $w3tc_cb_id ); ?>" class="w3tc_extensions_input_active" <?php disabled( ! $w3tc_meta['enabled'] ); ?>>
				</th>
				<td class="plugin-title">
					<strong><?php echo esc_html( $w3tc_meta['name'] ); ?></strong>
					<div class="row-actions-visible">
						<?php
						if ( $this->_config->is_extension_active( $w3tc_extension ) ) :
							$w3tc_extra_links = array();

							if ( isset( $w3tc_meta['settings_exists'] ) && $w3tc_meta['settings_exists'] ) {
								$w3tc_extra_links[] = '<a class="edit" href="' .
									esc_attr( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions&extension=%s&action=view', $w3tc_extension ) ) ) . '">' .
									esc_html__( 'Settings', 'w3-total-cache' ) . '</a>';
							}

							if ( isset( $w3tc_meta['extra_links'] ) && is_Array( $w3tc_meta['extra_links'] ) ) {
								$w3tc_extra_links = array_merge( $w3tc_extra_links, $w3tc_meta['extra_links'] );
							}

							$w3tc_extra_links = apply_filters( "w3tc_extension_plugin_links_{$w3tc_extension}", $w3tc_extra_links );
							$w3tc_links       = implode( ' | ', $w3tc_extra_links );

							if ( $w3tc_links ) {
								echo wp_kses(
									$w3tc_links,
									array(
										'a' => array(
											'href'   => array(),
											'class'  => array(),
											'target' => array(),
										),
									)
								);
							}
							?>

							<span class="0"></span>

							<?php if ( ! $this->_config->is_sealed( 'extensions.active' ) ) : ?>
								<?php echo $w3tc_links ? ' | ' : ''; ?>
								<span class="deactivate">
									<a href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions&action=deactivate&extension=%s&amp;extension_status=%s&amp;paged=%d', $w3tc_extension, $extension_status, $page ) ), 'w3tc_extension_deactivate_' . $w3tc_extension ) ); ?>" title="<?php esc_attr_e( 'Deactivate this extension', 'w3-total-cache' ); ?> ">
										<?php esc_html_e( 'Deactivate', 'w3-total-cache' ); ?>
									</a>
								</span>
							<?php endif ?>
						<?php else : ?>
							<span class="activate">
								<?php if ( $w3tc_meta['enabled'] ) : ?>
									<?php if ( ! $this->_config->is_sealed( 'extensions.active' ) ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( sprintf( 'admin.php?page=w3tc_extensions&action=activate&extension=%s&amp;extension_status=%s&amp;paged=%d', $w3tc_extension, $extension_status, $page ) ), 'w3tc_extension_activate_' . $w3tc_extension ) ); ?>" title="<?php esc_attr_e( 'Activate this extension', 'w3-total-cache' ); ?> ">
											<?php esc_html_e( 'Activate', 'w3-total-cache' ); ?>
										</a>
									<?php endif ?>
								<?php else : ?>
									<?php if ( ! empty( $w3tc_meta['disabled_message'] ) ) : ?>
										<?php echo esc_html( $w3tc_meta['disabled_message'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Disabled: see Requirements', 'w3-total-cache' ); ?>
									<?php endif; ?>
								<?php endif; ?>
							</span>
						<?php endif ?>
					</div>
				</td>
				<td class="column-description desc">
					<div class="plugin-description">
						<p>
							<?php if ( isset( $w3tc_meta['pro_feature'] ) && $w3tc_meta['pro_feature'] ) : ?>
								<?php Util_Ui::pro_wrap_maybe_start(); ?>
								<?php Util_Ui::pro_wrap_description( $w3tc_meta['pro_excerpt'], $w3tc_meta['pro_description'], 'extension-' . $w3tc_extension ); ?>
								<?php Util_Ui::pro_wrap_maybe_end( "extension_$w3tc_extension" ); ?>
							<?php else : ?>
								<?php echo wp_kses( $w3tc_meta['description'], Util_Ui::get_allowed_html_for_wp_kses_from_content( $w3tc_meta['description'] ) ); ?>
							<?php endif ?>

							<?php if ( ! empty( $w3tc_meta['requirements'] ) ) : ?>
								<p class="description">
									<?php
									echo esc_html(
										sprintf(
											// translators: 1 plugin requirements.
											__(
												'Requirements: %s',
												'w3-total-cache'
											),
											apply_filters( "w3tc_extension_requirements-{$w3tc_extension}", $w3tc_meta['requirements'] )
										)
									);
									?>
								</p>
								<?php do_action( "w3tc_extension_requirements-{$w3tc_extension}" ); ?>
							<?php endif ?>
						</p>
					</div>

					<div class="<?php echo $this->_config->is_extension_active( $w3tc_extension ) ? 'active' : 'inactive'; ?> second plugin-version-author-uri">
						<?php
						echo esc_html(
							sprintf(
								// translators: 1 extension version number.
								__(
									'Version %s',
									'w3-total-cache'
								),
								$w3tc_meta['version']
							)
						);
						?>
						|
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 HTML a tag to extension author page.
								__(
									'By %s',
									'w3-total-cache'
								),
								'<a href="' . esc_url( $w3tc_meta['author_uri'] ) . '" target="_blank" title="' .
									__( 'Visit author homepage', 'w3-total-cache' ) . '">' . esc_html( $w3tc_meta['author'] ) . '</a>'
							),
							array(
								'a' => array(
									'href'   => array(),
									'target' => array(),
								),
							)
						);
						?>
						|
						<a href="<?php echo esc_url( $w3tc_meta['extension_uri'] ); ?>" target="_blank"
							title="<?php esc_attr_e( 'Visit extension site', 'w3-total-cache' ); ?>">
							<?php esc_html_e( 'Visit extension site', 'w3-total-cache' ); ?></a>
					</div>
				</td>
			</tr>
			<?php do_action( 'w3tc_extension_after_row', $w3tc_extension ); ?>
			<?php do_action( "w3tc_extension_after_row-{$w3tc_extension}" ); ?>
		<?php endforeach ?>
	</tbody>
</table>
<div class="tablenav bottom">

	<?php if ( ! $this->_config->is_sealed( 'extensions.active' ) ) : ?>
		<div class="alignleft actions">
			<select name="action2">
				<option value="-1" selected="selected"><?php esc_html_e( 'Bulk Actions', 'w3-total-cache' ); ?></option>
				<option value="activate-selected"><?php esc_html_e( 'Activate', 'w3-total-cache' ); ?></option>
				<option value="deactivate-selected"><?php esc_html_e( 'Deactivate', 'w3-total-cache' ); ?></option>
			</select>
			<input type="submit" name="" id="doaction" class="w3tc-button-save button action" value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>">
		</div>
	<?php endif ?>

	<div class="tablenav-pages one-page">
		<span class="displaying-num">
			<?php
			echo esc_html(
				sprintf(
					translate_nooped_plural(
						// translators: 1 count of extensions.
						_n_noop(
							'%s extension',
							'%s extensions',
							'w3-total-cache'
						),
						count( $extensions ),
						'w3-total-cache'
					),
					count( $extensions )
				)
			);
			?>
		</span>
	</div>
	<br class="clear">
</div>
