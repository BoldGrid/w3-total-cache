<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div id="maxcdn-widget" class="w3tcmaxcdn_signup">
	<p>
		<?php
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
				__(
					'Dramatically increase website speeds in just a few clicks! Add the MaxCDN content delivery network (%1$sCDN%2$s) service to your site.',
					'w3-total-cache'
				),
				'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
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
	<h4 class="w3tcmaxcdn_signup_h4"><?php esc_html_e( 'New customers', 'w3-total-cache' ); ?></h4>
	<p><?php esc_html_e( 'MaxCDN works magically with W3 Total Cache.', 'w3-total-cache' ); ?></p>
	<a class="button-primary" href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard&w3tc_cdn_maxcdn_signup' ), 'w3tc' ) ); ?>" target="_blank"><?php esc_html_e( 'Create an Account', 'w3-total-cache' ); ?></a>
	<p><span class="desc"><?php esc_html_e( 'Exclusive offers availabel for W3TC users!', 'w3-total-cache' ); ?></span></p>
		<h4 class="w3tcmaxcdn_signup_h4"><?php esc_html_e( 'Current customers', 'w3-total-cache' ); ?></h4>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
					__(
						'Existing MaxCDN customers, enable %1$sCDN%2$s and:',
						'w3-total-cache'
					),
					'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
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
		<a class="button-primary" href="<?php echo esc_url( wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ), 'w3tc' ) ); ?>" target="_blank"><?php esc_html_e( 'Authorize', 'w3-total-cache' ); ?></a>
</div>
