<?php
/**
 * File: FeatureShowcase_Plugin_Admin_View.php
 *
 * @since 2.1.0
 *
 * @package W3TC
 *
 * @uses array $w3tc_config W3TC configuration.
 * @uses array $w3tc_cards {
 *     Feature configuration.
 *
 *     @type string $title      Title.
 *     @type string $w3tc_icon       Dashicon icon class.
 *     @type string $text       Description.
 *     @type string $button     Button markup.
 *     @type string $link       Link URL address.
 *     @type bool   $w3tc_is_premium Is it a premium feature.
 * }
 *
 * @see Util_Environment::is_w3tc_pro()
 * @see Util_Ui::pro_wrap_maybe_end2()
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
$w3tc_is_pro = Util_Environment::is_w3tc_pro( $w3tc_config );

require W3TC_INC_DIR . '/options/common/header.php';
?>

<div class="w3tc-page-container">
<?php
foreach ( $cards_data as $w3tc_card_type => $w3tc_cards ) {
	$w3tc_class = 'new' === $w3tc_card_type ? 'w3tc-card-container-new' : 'w3tc-card-container';

	echo reset( $cards_data ) !== $w3tc_cards ? '<hr class="w3tc-card-container-divider"/>' : '';
	?>
	<div class="<?php echo $w3tc_class; ?>">
	<?php
	foreach ( $w3tc_cards as $w3tc_feature_id => $w3tc_card ) {
		$w3tc_card_classes  = 'w3tc-card';
		$w3tc_title_classes = 'w3tc-card-title';
		$w3tc_is_premium    = ! empty( $w3tc_card['is_premium'] );

		if ( $w3tc_is_premium ) {
			$w3tc_card_classes  .= ' w3tc-card-premium';
			$w3tc_title_classes .= ' w3tc-card-premium';
		}

		if ( $w3tc_is_premium && ! $w3tc_is_pro ) {
			$w3tc_card_classes .= ' w3tc-card-upgrade';
		}

		?>
		<div class="<?php echo $w3tc_card_classes; ?>" id="w3tc-feature-<?php echo esc_attr( $w3tc_feature_id ); ?>">
			<div class="<?php echo $w3tc_title_classes; ?>">
				<p><?php echo $w3tc_card['title']; ?></p>
				<?php
				if ( $w3tc_is_premium ) {
					echo '<p class="w3tc-card-pro">' . __( 'PRO FEATURE', 'w3-total-cache' ) . '</p>';
				}
				?>
			</div>
			<div class="w3tc-card-icon"><span class="dashicons <?php echo $w3tc_card['icon']; ?>"></span></div>
			<div class="w3tc-card-body"><p><?php echo $w3tc_card['text']; ?></p></div>
			<div class="w3tc-card-footer">
				<div class="w3tc-card-button">
					<?php
					if ( $w3tc_is_premium && ! $w3tc_is_pro ) {
						echo '<button class="button w3tc-gopro-button button-buy-plugin" data-src="feature_showcase">'
							. esc_html__( 'Unlock Feature', 'w3-total-cache' ) . '</button>';
					} elseif ( ! empty( $w3tc_card['button'] ) ) {
						echo $w3tc_card['button'];
					}
					?>
				</div><div class="w3tc-card-links"><?php echo $w3tc_card['link']; ?></div>
			</div>
			<?php
			if ( ! empty( $w3tc_card['is_new'] ) && ! empty( $w3tc_card['version'] ) ) {
				?>
				<div class="w3tc-card-ribbon-new">
					<span class="dashicons dashicons-awards"></span>
					<b><?php esc_html_e( 'New', 'w3-total-cache' ); ?></b>
					<span>
						<?php esc_html_e( 'in', 'w3-total-cache' ); ?> W3 Total Cache <?php echo $w3tc_card['version']; ?> !
					</span>
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}
	?>
	</div>
	<?php
}
?>
</div>
