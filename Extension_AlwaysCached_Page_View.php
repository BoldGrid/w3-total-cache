<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$count_pending = Extension_AlwaysCached_Queue::row_count_pending();
$count_postponed = Extension_AlwaysCached_Queue::row_count_postponed();

?>
<p id="w3tc-options-menu">
	<?php esc_html_e( 'Jump to:', 'w3-total-cache' ); ?>
	<a href="admin.php?page=w3tc_general"><?php esc_html_e( 'Main Menu', 'w3-total-cache' ); ?></a> |
	<a href="admin.php?page=w3tc_extensions"><?php esc_html_e( 'Extensions', 'w3-total-cache' ); ?></a> |
</p>
<p>
	<?php esc_html_e( 'AlwaysCached extension is currently ', 'w3-total-cache' ); ?>
	<?php
	if ( $config->is_extension_active_frontend( 'alwayscached' ) ) {
		echo '<span class="w3tc-enabled">' . esc_html__( 'enabled', 'w3-total-cache' ) . '</span>';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html__( 'disabled', 'w3-total-cache' ) . '</span>';
	}
	?>
	.
<p>

<form action="admin.php?page=w3tc_extensions&amp;extension=alwayscached&amp;action=view" method="post">
	<?php
		echo wp_kses(
			Util_Ui::nonce_field( 'w3tc' ), [
				'input' => [
					'type'  => [],
					'name'  => [],
					'value' => [],
				]
			]
		);
	?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Queue', 'w3-total-cache' ), '', 'credentials' ); ?>
		<table class="form-table">
			<tr>
				<th style="width: 300px;">
					<label>
						<?php esc_html_e( 'Pending pages in a queue:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<?php echo esc_html( $count_pending ); ?>
					<?php if ( $count_pending > 0 ): ?>
						&nbsp;
						<a href="#" class="w3tc_alwayscached_queue" data-mode="pending">View</a>
						<section></section>
					<?php endif ?>
				</td>
			</tr>
			<tr>
				<th>
					<label>
						<?php esc_html_e( 'Postponed pages in a queue:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<?php echo esc_html( $count_postponed ); ?>
					<?php if ( $count_postponed > 0 ): ?>
						&nbsp;
						<a href="#" class="w3tc_alwayscached_queue" data-mode="postponed">View</a>
						<section></section>
					<?php endif ?>
				</td>
			</tr>
			<tr>
				<th>

				</th>
				<td>
					<input type="submit" name="w3tc_alwayscached_empty"
						value="<?php esc_html_e( 'Empty Queue', 'w3-total-cache' ); ?>" class="button" />
				</td>
			</tr>
		</table>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
