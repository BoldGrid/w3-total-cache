<?php
/**
 * File: Generic_WidgetServices_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<ul class="w3tc-visible-ul">
	<?php
	$services = Generic_WidgetServices::get_services();
	foreach ( $services as $service ) {
		echo '<li>' . esc_html( $service ) . '</li>';
	}
	?>
</ul>
<br/>
<a class="button-primary" href="<?php echo esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ) ); ?>">
	<?php esc_html_e( 'Learn More', 'w3-total-cache' ); ?>
</a>
