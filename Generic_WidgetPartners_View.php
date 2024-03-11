<?php
/**
 * File: Generic_WidgetPartners_View.php
 *
 * @since   2.7.0
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p><?php esc_html_e( 'Our Partner Hosts have supplied rich tutorials that can help you run faster on ', 'w3-total-cache' ) . 'W3 Total Cache:'; ?></p>
<ul id="partner-list">
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/imh.png' ) ); ?>" width="25"></span>
		<a href="<?php echo esc_url( W3TC_PARTNER_IMH ); ?>" target="_blank">InMotion Hosting</a>
	</li>
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/a2.png' ) ); ?>" width="25"></span>
		<a href="<?php echo esc_url( W3TC_PARTNER_A2 ); ?>" target="_blank">A2 Hosting</a>
	</li>
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/convesio.png' ) ); ?>" width="25"></span>
		<a href="<?php echo esc_url( W3TC_PARTNER_CONVESIO ); ?>" target="_blank">Convesio</a>
	</li>
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/dreamhost.png' ) ); ?>" width="25"></span>
		<a href="<?php echo esc_url( W3TC_PARTNER_DREAMHOST ); ?>" target="_blank">Dreamhost</a>
	</li>
</ul>
<p><?php esc_html_e( 'Shopping around for better hosting? Checkout our', 'w3-total-cache' ); ?> <a href="<?php echo esc_url( 'https://www.boldgrid.com/wordpress-hosting/' ); ?>" target="_blank"><?php esc_html_e( 'WordPress Hosting Recommendations', 'w3-total-cache' ); ?></a></p>
<hr>
<p id="partner-bottom"><a href="mailto: partners@boldgrid.com"><?php esc_html_e( 'Are you a host', 'w3-total-cache' ); ?>?</a></p>
