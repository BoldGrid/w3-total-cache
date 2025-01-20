<?php
/**
 * File: Cdn_BunnyCdn_Widget_View_Unauthorized.php
 *
 * @since   2.6.0
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>

<div id="bunnycdn-widget" class="w3tc_bunnycdn_signup">
	<?php
	$cdn_engine     = $c->get_string( 'cdn.engine' );
	$cdn_enabled    = $c->get_boolean( 'cdn.enabled' );
	$cdn_name       = Cache::engine_name( $cdn_engine );

	$cdnfsd_engine  = $c->get_string( 'cdnfsd.engine' );
	$cdnfsd_enabled = $c->get_boolean( 'cdnfsd.enabled' );
	$cdnfsd_name    = Cache::engine_name( $cdnfsd_engine );

	// Check if BunnyCDN is selected but not fully configured.
	$is_bunny_cdn_incomplete = (
		(
			$cdn_enabled &&
			'bunnycdn' === $cdn_engine &&
			empty( $c->get_integer( 'cdn.bunnycdn.pull_zone_id' ) )
		) ||
		(
			$cdnfsd_enabled &&
			'bunnycdn' === $cdnfsd_engine &&
			empty( $c->get_integer( 'cdnfsd.bunnycdn.pull_zone_id' ) )
		)
	);

	// Check if a non-BunnyCDN is configured.
	$is_other_cdn_configured = (
		(
			$cdn_enabled &&
			! empty( $cdn_engine ) &&
			'bunnycdn' !== $cdn_engine
		) ||
		(
			$cdnfsd_enabled &&
			! empty( $cdnfsd_engine ) &&
			'bunnycdn' !== $cdnfsd_engine
		)
	);

	if ( $is_bunny_cdn_incomplete ) {
		// BunnyCDN selected but not fully configured.
		?>
		<p class="notice notice-error">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML a tag to CDN settings page, 2 closing HTML a tag.
					__( 'W3 Total Cache has detected that BunnyCDN is selected but not fully configured. Please use the "Authorize" button on the %1$sCDN%2$s settings page to connect a pull zone.', 'w3-total-cache' ),
					'<a href="' . esc_url_raw( Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ) ) . '">',
					'</a>'
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			?>
		</p>
		<?php
	} elseif ( $is_other_cdn_configured ) {
		// A CDN is configured but it is not BunnyCDN.
		?>
		<p class="notice notice-error">
			<?php
			switch (true) {
				case $cdn_enabled && ! empty( $cdn_engine ) && $cdnfsd_enabled && ! empty( $cdnfsd_engine ):
					$cdn_label =
						$cdn_name .
						' <acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>' .
						' ' . __( 'and', 'w3-total-cache' ) . ' ' .
						$cdnfsd_name .
						' <acronym title="' . __( 'Content Delivery Network Full Site Delivery', 'w3-total-cache' ) . '">' . __( 'CDN FSD', 'w3-total-cache' ) . '</acronym>';
					break;
			
				case $cdn_enabled && ! empty( $cdn_engine ):
					$cdn_label =
						$cdn_name .
						' <acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>';
					break;
			
				case $cdnfsd_enabled && ! empty( $cdnfsd_engine ):
					$cdn_label =
						$cdnfsd_name .
						' <acronym title="' . __( 'Content Delivery Network Full Site Delivery', 'w3-total-cache' ) . '">' . __( 'CDN FSD', 'w3-total-cache' ) . '</acronym>';
					break;
			
				default:
					$cdn_label =
						__( 'Unknown', 'w3-total-cache' ) .
						' <acronym title="' . __( 'Content Delivery Network / Content Delivery Network Full Site Delivery', 'w3-total-cache' ) . '">' . __( 'CDN / CDN FSD', 'w3-total-cache' ) . '</acronym>';
					break;
			}

			echo wp_kses(
				sprintf(
					// translators: 1 configured CDN/CDN FSD label.
					__( 'W3 Total Cache has detected that you are using the %1$s, which is fully supported and compatible. For optimal performance and value, we recommend considering BunnyCDN as an alternative.', 'w3-total-cache' ),
					$cdn_label
				),
				array(
					'acronym' => array(
						'title' => array(),
					),
				)
			);
			?>
		</p>
		<?php
	} else {
		// No CDN is configured.
		?>
		<p class="notice notice-error">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					__( 'W3 Total Cache has detected that you do not have a %1$s configured. For optimal performance and value, we recommend considering BunnyCDN.', 'w3-total-cache' ),
					'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>'
				),
				array(
					'acronym' => array(
						'title' => array(),
					),
				)
			);
			?>
		</p>
		<?php
	}

	?>
	<p>
		<?php
		w3tc_e(
			'cdn.bunnycdn.widget.v2.header',
			\sprintf(
				// translators: 1 HTML acronym for Content Delivery Network (CDN).
				\__( 'Enhance your website performance by adding Bunny.Net\'s (%1$s) service to your site.', 'w3-total-cache' ),
				'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
			)
		);
		?>
	</p>

	<h4 class="w3tc_bunnycdn_signup_h4"><?php \esc_html_e( 'New customer? Sign up now to speed up your site!', 'w3-total-cache' ); ?></h4>

	<p>
		<?php
		w3tc_e(
			'cdn.bunnycdn.widget.v2.works_magically',
			\__( 'Bunny CDN works magically with W3 Total Cache to speed up your site around the world for as little as $1 per month.', 'w3-total-cache' )
		);
		?>
	</p>

	<a class="button-primary" href="<?php echo esc_url( W3TC_BUNNYCDN_SIGNUP_URL ); ?>" target="_blank">
		<?php \esc_html_e( 'Sign Up Now ', 'w3-total-cache' ); ?>
	</a>

	<h4 class="w3tc_bunnycdn_signup_h4"><?php esc_html_e( 'Current customers', 'w3-total-cache' ); ?></h4>

	<p>
		<?php
		w3tc_e(
			'cdn.bunnycdn.widget.v2.existing',
			\sprintf(
				// translators: 1 HTML acronym for Content Delivery Network (CDN).
				\__(
					'If you\'re an existing Bunny CDN customer, enable %1$s and authorize. If you need help configuring your %1$s, we also offer Premium Services to assist you.',
					'w3-total-cache'
				),
				'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
			)
		);
		?>
	</p>

	<a class="button-primary" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ), 'w3tc' ) ); ?>">
		<?php \esc_html_e( 'Authorize', 'w3-total-cache' ); ?>
	</a>

	<a class="button" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), 'w3tc' ) ); ?>">
		<?php \esc_html_e( 'Premium Services', 'w3-total-cache' ); ?>
	</a>
</div>
