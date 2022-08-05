<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<?php require W3TC_INC_DIR . '/popup/common/header.php'; ?>

<?php if ( $results ) : ?>
<h4><?php esc_html_e( 'Page Speed Score:', 'w3-total-cache' ); ?> <?php echo esc_html( $results['score'] ); ?>/100</h4>

<p>
	<input class="w3tc-widget-ps-nonce" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>" />
	<input class="button ps-expand-all" type="button" value="<?php esc_attr_e( 'Expand all', 'w3-total-cache' ); ?>" />
	<input class="button ps-collapse-all" type="button" value="<?php esc_attr_e( 'Collapse all', 'w3-total-cache' ); ?>" />
	<input class="button ps-refresh" type="button" value="<?php esc_attr_e( 'Refresh analysis', 'w3-total-cache' ); ?>" />
</p>

<ul class="ps-rules">
	<?php foreach ( $results['rules'] as $index => $rule ) : ?>
		<li class="ps-rule ps-priority-<?php echo esc_attr( $rule['priority'] ); ?>">
			<div class="ps-icon"><div></div></div>
			<div class="ps-expand"><?php echo count( $rule['blocks'] ) ? '<a href="#">+</a>' : ''; ?></div>
			<p><?php echo esc_html( $rule['name'] ); ?></p>

			<?php if ( count( $rule['blocks'] ) || count( $rule['resolution'] ) ) : ?>
				<div class="ps-expander">
					<?php if ( count( $rule['blocks'] ) ) : ?>
						<ul class="ps-blocks">
							<?php foreach ( $rule['blocks'] as $block ) : ?>
								<li class="ps-block">
									<p><?php echo esc_html( $block['header'] ); ?></p>

									<?php if ( count( $block['urls'] ) ) : ?>
										<ul class="ps-urls">
											<?php foreach ( $block['urls'] as $url ) : ?>
												<li class="ps-url"><?php echo esc_url( $url['result'] ); ?></li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( count( $rule['resolution'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Resolution:', 'w3-total-cache' ); ?></strong> <?php echo esc_html( $rule['resolution']['header'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
<?php else : ?>
	<p><?php esc_html_e( 'Unable to fetch Page Speed results.', 'w3-total-cache' ); ?></p>
	<p>
		<input class="button ps-refresh" type="button" value="<?php esc_attr_e( 'Refresh Analysis', 'w3-total-cache' ); ?>" />
	</p>
<?php endif; ?>

<?php require W3TC_INC_DIR . '/popup/common/footer.php'; ?>
