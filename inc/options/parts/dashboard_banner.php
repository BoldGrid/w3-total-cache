<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();
?>

<div id="w3tc_dashboard_banner" class="metabox-holder">
	<div class="postbox">
		<div class="w3tc-postbox-ad">
			<?php
				echo wp_kses(
					sprintf(
						// Translators: 1 the opening anchor tag to the w3tc_support page, 2 its closing tag, 3 a line break.
						__( 'Did you know that we offer premium support services?%3$s Our experts will configure W3 Total Cache for you! %1$sClick here for info%2$s.', 'w3-total-cache' ),
						'<a href="' . admin_url( 'admin.php?page=w3tc_support' ) . '">',
						'</a>',
						'<br />'
					),
					array(
						'a'  => array(
							'href' => array()
						),
						'br' => array(),
					)
				);
			?>
		</div>
		<h3 class="hndle">
			<img style="height:32px;" src="<?php echo plugins_url( 'w3-total-cache/pub/img/W3TC_dashboard_logo_title.png' ); ?>" />
		</h3>
		<div class="inside">
			<p>
				<?php
					echo wp_kses(
						sprintf(
							// Translators: 1 an opening strong tag, 2 its closing tag.
							__( 'You\'re using the Community Edition of W3 Total Cache. Maximize your website\'s speed even more by upgrading to %1$sW3 Total Cache Pro%2$s to unlock advanced anaytics, fragment caching, full site delivery, extension support and other tools that will allow you to completely fine tune your website\'s performance.', 'w3-total-cache' ),
							'<strong>',
							'</strong>'
						),
						array( 'strong' => array() )
					);
				?>
			</p>
			<p>
				<input
					type="button"
					class="button w3tc-gopro-button button-buy-plugin"
					data-src="dashboard_banner" value="<?php esc_attr_e( 'Learn more about Pro', 'w3-total-cache' ) ?>" />
			</p>
		</div>
	</div>
</div>