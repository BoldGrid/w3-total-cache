<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p><?php esc_html_e( 'Our Partner Hosts have supplied rich tutorials that can help you run faster on ', 'w3-total-cache' ) . 'W3 Total Cache:'; ?></p>
<ul id="partner-list">
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/imh.png' ) ); ?>" width="25"></span>
		<a href="<?php esc_url( 'https://www.inmotionhosting.com/support/edu/wordpress/plugins/w3-total-cache/' ); ?>" target="_blank">InMotion Hosting</a>
	</li>
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/a2.png' ) ); ?>" width="25"></span>
		<a href="<?php esc_url( 'https://www.a2hosting.com/kb/installable-applications/optimization-and-configuration/wordpress2/optimizing-wordpress-with-w3-total-cache-and-gtmetrix/' ); ?>" target="_blank">A2 Hosting</a>
	</li>
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/convesio.png' ) ); ?>" width="25"></span>
		<a href="<?php esc_url( 'https://convesio.com/features/the-ultimate-guide-to-setting-up-w3-total-cache-with-convesio/' ); ?>" target="_blank">Convesio</a>
	</li>
	<li>
		<span class="partner-logo"><img src="<?php echo esc_url( plugins_url( 'w3-total-cache/pub/img/dreamhost.png' ) ); ?>" width="25"></span>
		<a href="<?php esc_url( 'https://www.dreamhost.com/wordpress/' ); ?>" target="_blank">Dreamhost</a>
	</li>
</ul>
<p><?php esc_html_e( 'Shopping around for better hosting? Checkout our', 'w3-total-cache' ); ?> <a href="<?php esc_url( 'https://www.boldgrid.com/wordpress-hosting/' ); ?>" target="_blank"><?php esc_html_e( 'WordPress Hosting Recommendations', 'w3-total-cache' ); ?></a></p>
<hr>
<p id="partner-bottom"><a href="mailto: partners@boldgrid.com"><?php esc_html_e( 'Are you a host', 'w3-total-cache' ); ?>?</a></p>
