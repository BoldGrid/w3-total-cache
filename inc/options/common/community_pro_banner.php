<?php
/**
 * File: community_pro_banner.php
 *
 * Community-only Pro upsell banner. Variables set by Util_Ui::print_community_pro_banner().
 *
 * @package W3TC
 *
 * @var array  $copy      Title, features, settings, and headings.
 * @var string $data_src  Lightbox campaign source.
 * @var string $nonce     Upgrade overlay nonce.
 * @var bool   $collapsed Whether the current user has collapsed the banner.
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_settings_heading = ! empty( $copy['settings_heading'] ) ?
	$copy['settings_heading'] :
	__( 'Pro settings on this page', 'w3-total-cache' );
$w3tc_collapsed        = ! empty( $collapsed );
$w3tc_collapse_label   = $w3tc_collapsed ?
	__( 'Expand', 'w3-total-cache' ) :
	__( 'Collapse', 'w3-total-cache' );
$w3tc_banner_class     = 'w3tc-gopro-manual-wrap';
if ( $w3tc_collapsed ) {
	$w3tc_banner_class .= ' is-collapsed';
}
?>
<div id="w3tc-community-pro-banner" class="<?php echo esc_attr( $w3tc_banner_class ); ?>" role="region" aria-label="<?php esc_attr_e( 'W3 Total Cache Pro', 'w3-total-cache' ); ?>">
	<div class="w3tc-gopro">
		<div class="w3tc-gopro-ribbon"><span><?php esc_html_e( '★ PRO', 'w3-total-cache' ); ?></span></div>
		<div class="w3tc-gopro-content">
			<p class="w3tc-community-pro-banner__title">
				<strong><?php echo esc_html( $copy['title'] ); ?></strong>
			</p>
			<?php if ( ! empty( $copy['features'] ) ) : ?>
				<div class="w3tc-community-pro-banner__section">
					<p class="w3tc-community-pro-banner__heading"><?php esc_html_e( 'Pro features', 'w3-total-cache' ); ?></p>
					<ul class="w3tc-community-pro-banner__list">
						<?php foreach ( $copy['features'] as $item ) : ?>
							<?php
							$label = isset( $item['label'] ) ? $item['label'] : '';
							$blurb = isset( $item['blurb'] ) ? $item['blurb'] : '';
							if ( '' === $label ) {
								continue;
							}
							?>
							<li>
								<span class="w3tc-community-pro-banner__tag"><?php echo esc_html( $label ); ?></span>
								<?php if ( '' !== $blurb ) : ?>
									<p class="w3tc-community-pro-banner__blurb"><?php echo esc_html( $blurb ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $copy['settings'] ) ) : ?>
				<div class="w3tc-community-pro-banner__section">
					<p class="w3tc-community-pro-banner__heading"><?php echo esc_html( $w3tc_settings_heading ); ?></p>
					<ul class="w3tc-community-pro-banner__list">
						<?php foreach ( $copy['settings'] as $setting ) : ?>
							<?php
							$label   = isset( $setting['label'] ) ? $setting['label'] : '';
							$feature = isset( $setting['feature'] ) ? $setting['feature'] : '';
							$blurb   = isset( $setting['blurb'] ) ? $setting['blurb'] : '';
							if ( '' === $label ) {
								continue;
							}
							?>
							<li>
								<div class="w3tc-community-pro-banner__item-head">
									<?php if ( '' !== $feature ) : ?>
										<span class="w3tc-community-pro-banner__tag"><?php echo esc_html( $feature ); ?></span>
										<?php if ( $label !== $feature ) : ?>
											<span class="w3tc-community-pro-banner__setting-label"><?php echo esc_html( $label ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="w3tc-community-pro-banner__tag"><?php echo esc_html( $label ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( '' !== $blurb ) : ?>
									<p class="w3tc-community-pro-banner__blurb"><?php echo esc_html( $blurb ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<p class="w3tc-community-pro-banner__cta">
				<input type="button"
					class="button w3tc-gopro-button button-buy-plugin {nonce: '<?php echo esc_attr( $nonce ); ?>'}"
					data-src="<?php echo esc_attr( $data_src ); ?>"
					value="<?php esc_attr_e( 'Upgrade', 'w3-total-cache' ); ?>" />
				<button type="button"
					class="button button-primary w3tc-community-pro-banner__toggle"
					aria-expanded="<?php echo $w3tc_collapsed ? 'false' : 'true'; ?>"
					data-label-collapse="<?php esc_attr_e( 'Collapse', 'w3-total-cache' ); ?>"
					data-label-expand="<?php esc_attr_e( 'Expand', 'w3-total-cache' ); ?>">
					<span class="w3tc-community-pro-banner__toggle-label"><?php echo esc_html( $w3tc_collapse_label ); ?></span>
				</button>
			</p>
		</div>
	</div>
</div>
