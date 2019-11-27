<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';
?>
<style>
.w3tc_purge_log_table {
	display: grid;
	grid-template-columns: auto 1fr;
	grid-gap: 10px;
}
.w3tc_purge_log_date {
	white-space: nowrap;
}

.w3tc_purge_log_backtrace {
	grid-column-start: 2;
	grid-column-end: 3;
	overflow: hidden;
	word-wrap: break-word;
	padding-left: 2em;
	text-indent: -2em;
	font-family: courier;
}
</style>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( __( 'Purges Log', 'w3-total-cache' ) ); ?>

	<h1>Purges Log</h1>

	<div class="w3tc_purge_log_table">
		<?php foreach ( $lines as $line ): ?>
			<div class="w3tc_purge_log_date"><?php echo esc_html( $line['date'] ) ?></div>
			<div class="w3tc_purge_log_message"><td><?php echo esc_html( $line['message'] ) ?></div>
			<div class="w3tc_purge_log_backtrace">
					<?php foreach ( $line['backtrace'] as $backtrace_line ): ?>
						<div class="w3tc_purge_log_traceline">
							<?php echo esc_html( $backtrace_line ) ?>
						</div>
					<?php endforeach ?>
			</div>
		<?php endforeach ?>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>
