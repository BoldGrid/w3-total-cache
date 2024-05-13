<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

require W3TC_INC_DIR . '/options/common/header.php';
?>

<form id="w3tc_dashboard" action="admin.php?page=<?php echo esc_attr( $this->_page ); ?>" method="post">
	<div class="w3tc_dashboard_flush_container">
		<span><?php esc_html_e( 'Flush caches with ', 'w3-total-cache' ); ?></span>
		<div class="w3tc-button-control-container">
			<?php Util_Ui::print_flush_split_button(); ?>
		</div>
	</div>
</form>

<form id="w3tc_dashboard" action="admin.php?page=<?php echo esc_attr( $this->_page ); ?>" method="post">
	<?php
	echo wp_kses(
		Util_Ui::nonce_field( 'w3tc' ),
		array(
			'input' => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
			),
		)
	);
	?>
	<div id="w3tc-dashboard-widgets" class="clearfix widefat metabox-holder">
		<?php $screen = get_current_screen(); ?>
		<div id="postbox-container">
			<div class="content">
				<div class="widgets-container">
					<?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
				</div>
			</div>
		</div>

		<?php
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		?>
	</div>
</form>
