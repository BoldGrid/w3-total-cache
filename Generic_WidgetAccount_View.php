<?php
/**
 * File: Generic_WidgetAccount_View.php
 *
 * @package W3TC
 *
 * @since X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$licensing   = new Licensing_Plugin_Admin();
$license_key = $licensing->get_license_key();
$license     = Licensing_Core::check_license( $license_key, W3TC_VERSION );
?>
<table>
	<tr>
		<td><b><?php esc_html_e( 'License:', 'w3-total-cache' ); ?></b></td>
		<td><?php echo ! empty( $license ) && $license->license_status === 'active.by_rooturi' ? __( 'Pro', 'w3-total-cache' ) : __( 'Free', 'w3-total-cache' ); ?></td>
	</tr>
	<tr>
		<td><b><?php esc_html_e( 'Status:', 'w3-total-cache' ); ?></b></td>
		<td>
			<?php
			if ( ! empty( $license ) ) {
				switch ( $license ) {
					case 'active.by_rooturi' === $license->license_status:
						esc_html_e( 'Active', 'w3-total-cache' );
						break;
					case 'inactive.expired' === $license->license_status:
						esc_html_e( 'Expired', 'w3-total-cache' );
						break;
					case 'invalid.not_present' === $license->license_status:
						esc_html_e( 'Free license, no expiration', 'w3-total-cache' );
						break;
					default:
						esc_html_e( 'Unknown', 'w3-total-cache' );
				}
			} else {
				esc_html_e( 'Free license, no expiration', 'w3-total-cache' );
			}
			?>
		</td>
	</tr>
	<!--
	<tr>
		<td><b><?php esc_html_e( 'Renewal Date:', 'w3-total-cache' ); ?></b></td>
		<td>TBD</td>
	</tr>
	-->
</table>
<?php
if ( empty( $license ) || ( ! empty( $license ) && $license->license_status === 'invalid.not_present' ) ) {
	?>
	<p>
		<?php
		echo wp_kses(
			sprintf(
				// Translators: 1 opening HTML strong tag, 2 closing HTML strong tag.
				__( 'Upgrade to %1$sW3 Total Cache Pro%2$s now to unlock additional settings and features that can further improve your site\'s performance and Google PageSpeed ratings.', 'w3-total-cache' ),
				'<strong>',
				'</strong>'
			),
			array( 'strong' => array() )
		);
		?>
	</p>
	<p>
		<input type="button" class="button-primary button-buy-plugin" data-src="account_widget" value="<?php esc_attr_e( 'Learn more about Pro', 'w3-total-cache' ); ?>" />
	</p>
	<?php
}
?>
