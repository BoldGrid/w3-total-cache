<?php
/**
 * File: Cdn_TotalCdn_Widget_View_Authorized.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>

<div id="totalcdn-widget"
	class="totalcdn-widget-base w3tc_totalcdn_content">
	<div class="w3tc_totalcdn_wrapper">
		<div class="w3tc_totalcdn_tools">
			<p>
			<?php
			w3tc_e(
				'cdn.totalcdn.widget.v2.header',
				\sprintf(
					// translators: CDN Name.
					\__( 'Your website performance is enhanced with our %1$s service.', 'w3-total-cache' ),
					esc_html( W3TC_CDN_NAME )
				)
			);
			?>
			</p>
		</div>
		<div class="w3tc_totalcdn_tools">
			<ul class="w3tc_totalcdn_ul">
				<li><a class="button" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_flush_cdn' ), 'w3tc' ) ); ?>"><?php \esc_html_e( 'Purge Cache', 'w3-total-cache' ); ?></a></li>
			</ul>
			<p>
			<?php
			w3tc_e(
				'cdn.totalcdn.widget.v2.existing',
				\sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					\__(
						'If you need help configuring your %1$s, we also offer Premium Services to assist you.',
						'w3-total-cache'
					),
					'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
				)
			);
			?>
		</p>
		<a class="button" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), 'w3tc' ) ); ?>">
			<?php \esc_html_e( 'Premium Services', 'w3-total-cache' ); ?>
		</a>
		</div>
	</div>
</div>
