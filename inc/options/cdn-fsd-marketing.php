<?php
/**
 * File: cdn-fsd-marketing.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die;

Util_Ui::postbox_header(
	esc_html__( 'Upgrade Opportunities', 'w3-total-cache' ),
	'',
	'full-site-delivery-upsell'
);
?>
<div class="w3tc-fsd-marketing">
<?php
if ( ! $is_w3tc_pro ) {
	?>
	<div class="w3tc-gopro-manual-wrap">
		<?php Util_Ui::pro_wrap_maybe_start(); ?>
		<p>
			<?php
			esc_html_e(
				'Full-Site Delivery (FSD) is a W3 Total Cache Pro feature that serves every page and asset through a CDN provider for the fastest experience available.',
				'w3-total-cache'
			);
			?>
		</p>
		<p>
			<?php
			esc_html_e(
				'Upgrade today to unlock premium support and edge caching for HTML, media, CSS, and JavaScript via CDN.',
				'w3-total-cache'
			);
			?>
		</p>
		<?php Util_Ui::pro_wrap_maybe_end( 'cdn_fsd', true ); ?>
	</div>
	<?php
}

if ( ! $is_totalcdn_authorized ) {
	?>
	<div id="w3tc-tcdn-ad-fsd">
		<img class="w3tc-tcdn-icon" src="<?php echo esc_url( plugins_url( '/pub/img/w3total-cdn-teal.svg', W3TC_FILE ) ); ?>" alt="<?php echo esc_attr( W3TC_CDN_NAME ); ?> icon">
		<p>
			<?php
			printf(
				// translators: %s: CDN brand name.
				esc_html__(
					'Deliver your entire site through %s with Full-Site Delivery.',
					'w3-total-cache'
				),
				esc_html( W3TC_CDN_NAME )
			);
			?>
		</p>
		<input type="button" class="button-buy-tcdn" data-renew-key="<?php echo esc_attr( $license_key ); ?>" data-src="cdn_page_fsd_totalcdn" value="<?php esc_attr_e( 'Get Total CDN', 'w3-total-cache' ); ?>">
	</div>
	<?php
} elseif ( $is_w3tc_pro && $is_totalcdn_static ) {
	?>
	<div class="notice notice-info inline w3tc-fsd-notice">
		<p>
			<?php
			printf(
				// translators: 1 CDN brand name, 2 opening a tag to CDN section on general settings page, 3 closing a tag.
				esc_html__(
					'You\'re already using %1$s for static asset delivery. To enable full-site delivery - which caches HTML, media, and scripts on the same global edge for even faster performance - %2$sGo to your General Settings page%3$s and enable Full-Site Delivery (FSD).',
					'w3-total-cache'
				),
				esc_html( W3TC_CDN_NAME ),
				'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '">',
				'</a>'
			);
			?>
		</p>
	</div>
	<?php
} elseif ( $is_w3tc_pro && ! $is_totalcdn_fsd ) {
	?>
	<div class="notice notice-info inline w3tc-fsd-notice">
		<p>	
			<?php
			printf(
				// translators: 1 CDN brand name, 2 opening a tag to CDN section on general settings page, 3 closing a tag.
				esc_html__(
					'You\'re %1$s account is ready for Full-Site Delivery. To enable full-site delivery - which caches HTML, media, and scripts on the same global edge for even faster performance - %2$sGo to your General Settings page%3$s and enable Full-Site Delivery (FSD).',
					'w3-total-cache'
				),
				esc_html( W3TC_CDN_NAME ),
				'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '">',
				'</a>'
			);
			?>
		</p>
	</div>
	<?php
}
?>
</div>
<?php Util_Ui::postbox_footer(); ?>
