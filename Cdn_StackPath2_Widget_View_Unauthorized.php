<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p>
	<div class="notice notice-warning inline">
		<p>
			<?php
			// StackPath sunset is 12:00 am Central (UTC-6:00) on November, 22, 2023 (1700629200).
			$date_time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			printf(
				// translators: 1 StackPath sunset datetime.
				__(
					'StackPath will cease operations at %1$s.',
					'w3-total-cache'
				),
				wp_date( $date_time_format, '1700629200' )
			);
			?>
		</p>
	</div>
</p>
<div id="stackpath-widget" class="w3tcstackpath_signup">
	<?php if ( ! $c->get_boolean( 'cdn.enabled' ) ) : ?>
		<p class="notice notice-error">
			<?php
			w3tc_e(
				'cdn.stackpath.widget.v2.no_cdn',
				sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					__( 'W3 Total Cache has detected that you do not have a %1$s configured', 'w3-total-cache' ),
					'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
				)
			);
			?>
		</p>
	<?php endif ?>
	<p>
		<h4 class="w3tcstackpath_signup_h4"><?php esc_html_e( 'Current customers', 'w3-total-cache' ); ?></h4>
		<p>
			<?php
			w3tc_e(
				'cdn.stackpath2.widget.v2.existing',
				sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					__(
						'If you\'re an existing StackPath customer, enable %1$s and Authorize. If you need help configuring 
							your %1$s, we also offer Premium Services to assist you.',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
				)
			);
			?>
		</p>
		<a class="button-primary" href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ), 'w3tc' ) ); ?>" target="_blank">
			<?php esc_html_e( 'Authorize', 'w3-total-cache' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), 'w3tc' ) ); ?>">
			<?php esc_html_e( 'Premium Services', 'w3-total-cache' ); ?>
		</a>
</div>
