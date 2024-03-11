<?php
/**
 * File: FeatureShowcase_Plugin_Admin_View.php
 *
 * @since 2.1.0
 *
 * @package W3TC
 *
 * @uses array $config W3TC configuration.
 * @uses array $cards {
 *     Feature configuration.
 *
 *     @type string $title      Title.
 *     @type string $icon       Dashicon icon class.
 *     @type string $text       Description.
 *     @type string $button     Button markup.
 *     @type string $link       Link URL address.
 *     @type bool   $is_premium Is it a premium feature.
 * }
 *
 * @see Util_Environment::is_w3tc_pro()
 * @see Util_Ui::pro_wrap_maybe_end2()
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
 */

namespace W3TC;

$is_pro = Util_Environment::is_w3tc_pro( $config );

require W3TC_INC_DIR . '/options/common/header.php';
?>

<div class="w3tc-page-container">
<?php
foreach ( $cards_data as $card_type => $cards ) {
	$class = 'new' === $card_type ? 'w3tc-card-container-new' : 'w3tc-card-container';

	echo reset( $cards_data ) !== $cards ? '<hr class="w3tc-card-container-divider"/>' : '';
	?>
	<div class="<?php echo $class; ?>">
	<?php
	foreach ( $cards as $feature_id => $card ) {
		$card_classes  = 'w3tc-card';
		$title_classes = 'w3tc-card-title';
		$is_premium    = ! empty( $card['is_premium'] );

		if ( $is_premium ) {
			$card_classes  .= ' w3tc-card-premium';
			$title_classes .= ' w3tc-card-premium';
		}

		if ( $is_premium && ! $is_pro ) {
			$card_classes .= ' w3tc-card-upgrade';
		}

		?>
		<div class="<?php echo $card_classes; ?>" id="w3tc-feature-<?php echo esc_attr( $feature_id ); ?>">
			<div class="<?php echo $title_classes; ?>">
				<p><?php echo $card['title']; ?></p>
				<?php
				if ( $is_premium ) {
					echo '<p class="w3tc-card-pro">' . __( 'PRO FEATURE', 'w3-total-cache' ) . '</p>';
				}
				?>
			</div>
			<div class="w3tc-card-icon"><span class="dashicons <?php echo $card['icon']; ?>"></span></div>
			<div class="w3tc-card-body"><p><?php echo $card['text']; ?></p></div>
			<div class="w3tc-card-footer">
				<div class="w3tc-card-button">
					<?php
					if ( $is_premium && ! $is_pro ) {
						echo '<button class="button w3tc-gopro-button button-buy-plugin" data-src="feature_showcase">'
							. esc_html__( 'Unlock Feature', 'w3-total-cache' ) . '</button>';
					} elseif ( ! empty( $card['button'] ) ) {
						echo $card['button'];
					}
					?>
				</div><div class="w3tc-card-links"><?php echo $card['link']; ?></div>
			</div>
			<?php
			if ( ! empty( $card['is_new'] ) && ! empty( $card['version'] ) ) {
				?>
				<div class="w3tc-card-ribbon-new">
					<span class="dashicons dashicons-awards"></span>
					<b><?php esc_html_e( 'New', 'w3-total-cache' ); ?></b>
					<span>
						<?php esc_html_e( 'in', 'w3-total-cache' ); ?> W3 Total Cache <?php echo $card['version']; ?> !
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
