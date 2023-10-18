<?php
/**
 * File: Cdn_BunnyCdn_Widget_View_Authorized.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>

<div id="bunnycdn-widget" class="bunnycdn-widget-base w3tc_bunnycdn_content">
	<div class="w3tc_bunnycdn_wrapper">
		<div class="w3tc_bunnycdn_tools">
			<p>
			<?php
			w3tc_e(
				'cdn.bunnycdn.widget.v2.header',
				\sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					\__( 'Your website performance is enhanced with Bunny.Net\'s (%1$s) service.', 'w3-total-cache' ),
					'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
				)
			);
			?>
			</p>
		</div>
		<div class="w3tc_bunnycdn_tools">
			<ul class="w3tc_bunnycdn_ul">
				<li><a class="button" href="<?php echo \esc_url( \wp_nonce_url( \network_admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_cdn' ), 'w3tc' ) ); ?>"><?php \esc_html_e( 'Purge Cache', 'w3-total-cache' ); ?></a></li>
			</ul>
		</div>
	</div>
</div>
