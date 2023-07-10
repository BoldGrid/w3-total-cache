<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

/*
 * Display the header for our dashboard.
 *
 * If we're on the pro version, we'll show the standard W3TC logo and a message stating the user is
 * on pro. As of 0.14.3, the free version will instead show a really, really nice banner. Really terrific.
 * Just fantasic. Other banners, not so good. Everyone agrees, believe me.
 */
if ( Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
	require W3TC_INC_DIR . '/options/common/header.php';

	echo wp_kses(
		sprintf(
			// translators: 1 opening HTML p tag, 2 HTML span tag indicating plugin enabled/disabled,
			// translators: 3 HTML strong tag indicating W3TC version, 4 closing HTML p tag.
			__(
				'%1$sThe plugin is currently %2$s in %3$s mode.%4$s',
				'w3-total-cache'
			),
			'<p>',
			'<span class="w3tc-' . ( $enabled ? 'enabled' : 'disabled' ) . '">' . ( $enabled ? esc_html__( 'enabled', 'w3-total-cache' ) : esc_html__( 'disabled', 'w3-total-cache' ) ) . '</span>',
			'<strong>' . Util_Environment::w3tc_edition( $this->_config ) . '</strong>',
			'</p>'
		),
		array(
			'p'      => array(),
			'span'   => array(
				'class' => array(),
			),
			'strong' => array(),
		)
	);
} else {
	// When header.php is not included (above), we need to do our head action and open the wrap.
	do_action( 'w3tc-dashboard-head' );
	echo '<div class="wrap" id="w3tc">';
}
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
		<div id="postbox-container-left">
			<div class="content">
			<div id="dashboard-text" style="display:inline-block;">
				<h1><?php esc_html_e( 'Dashboard', 'w3-total-cache' ); ?></h1>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
							__(
								'Thanks for choosing W3TC as your Web Performance Optimization (%1$sWPO%2$s) framework!',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Web Performance Optimization', 'w3-total-cache' ) . '">',
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
			</div>
			<div id="widgets-container">
			<?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
			</div>
			</div>
		</div>
		<div id="postbox-container-right">
			<div id='postbox-container-3' class='postbox-container' style="width: 100%;">
				<?php do_meta_boxes( $screen->id, 'side', '' ); ?>
			</div>
		</div>
		<div style="clear:both"></div>

		<?php
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		?>
	</div>
</form>
