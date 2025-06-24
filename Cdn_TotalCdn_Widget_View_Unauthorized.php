<?php
/**
 * File: Cdn_TotalCdn_Widget_View_Unauthorized.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>

<div id="<?php echo esc_attr( 'totalcdn' ); ?>-widget" class="w3tc_<?php echo esc_attr( 'totalcdn' ); ?>_signup">
	<?php
	$cdn_engine  = $config->get_string( 'cdn.engine' );
	$cdn_enabled = $config->get_boolean( 'cdn.enabled' );
	$cdn_name    = Cache::engine_name( $cdn_engine );

	$cdnfsd_engine  = $config->get_string( 'cdnfsd.engine' );
	$cdnfsd_enabled = $config->get_boolean( 'cdnfsd.enabled' );
	$cdnfsd_name    = Cache::engine_name( $cdnfsd_engine );

	// Check if W3TC provided CDN is selected but not fully configured.
	$is_w3tc_cdn_incomplete = (
		(
			$cdn_enabled &&
			'totalcdn' === $cdn_engine &&
			empty( $config->get_integer( 'cdn.' . 'totalcdn' . '.pull_zone_id' ) )
		) ||
		(
			$cdnfsd_enabled &&
			'totalcdn' === $cdnfsd_engine &&
			empty( $config->get_integer( 'cdnfsd.' . 'totalcdn' . '.pull_zone_id' ) )
		)
	);

	// Check if a non-W3TC provided CDN is configured.
	$is_other_cdn_configured = (
		(
			$cdn_enabled &&
			! empty( $cdn_engine ) &&
			'totalcdn' !== $cdn_engine
		) ||
		(
			$cdnfsd_enabled &&
			! empty( $cdnfsd_engine ) &&
			'totalcdn' !== $cdnfsd_engine
		)
	);

	if ( $is_w3tc_cdn_incomplete ) {
		// W3TC provided CDN selected but not fully configured.
		?>
		<p class="notice notice-error">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML a tag to CDN settings page, 2 closing HTML a tag, 3: CDN name.
					__( 'W3 Total Cache has detected that %3$s is selected but not fully configured. Please use the "Authorize" button on the %1$sCDN%2$s settings page to connect a pull zone.', 'w3-total-cache' ),
					'<a href="' . esc_url_raw( Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ) ) . '">',
					'</a>',
					esc_html( W3TC_CDN_NAME )
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
		// A CDN is configured but it is not W3TC provided CDN.
		?>
		<p class="notice notice-error">
			<?php
			switch ( true ) {
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
					// translators: 1 configured CDN/CDN FSD label, 2: CDN name.
					__( 'W3 Total Cache has detected that you are using the %1$s, which is fully supported and compatible. For optimal performance and value, we recommend considering our %2$s service as an alternative.', 'w3-total-cache' ),
					$cdn_label,
					esc_html( W3TC_CDN_NAME )
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
	} elseif ( ! $cdn_enabled && ! $cdnfsd_enabled && ! empty( $config->get_string( 'cdn.' . 'totalcdn' . '.account_api_key' ) ) ) {
		// W3TC provided CDN is purchased and available but no CDN enabled.
		?>
		<p class="notice notice-error">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML a tag to CDN settings page, 2 closing HTML a tag, 3: CDN name.
					__( 'W3 Total Cache has detected that %3$s has been purchased and is available but has yet to be enabled. Please %1$sEnable%2$s the CDN feature on the General Settings page and select %3$s for the CDN type.', 'w3-total-cache' ),
					'<a class="button-primary" href="' . \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ), 'w3tc' ) ) . '">',
					'</a>',
					esc_html( W3TC_CDN_NAME )
				),
				array(
					'a' => array(
						'class' => array(),
						'href'  => array(),
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
					// translators: 1 HTML acronym for Content Delivery Network (CDN), 2: CDN name.
					__( 'W3 Total Cache has detected that you do not have a %1$s configured. For optimal performance and value, we recommend considering our %2$s service.', 'w3-total-cache' ),
					'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">' . __( 'CDN', 'w3-total-cache' ) . '</acronym>',
					esc_html( W3TC_CDN_NAME )
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

	if (
		( ! $cdn_enabled && empty( $config->get_string( 'cdn.' . 'totalcdn' . '.account_api_key' ) ) ) ||
		in_array( $state->get_string( 'cdn.' . 'totalcdn' . '.status' ), array( 'canceled', 'inactive.expired' ), true )
	) {
		?>
		<p>
			<?php
			w3tc_e(
				'cdn.' . 'totalcdn' . '.widget.v2.header',
				\sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					\__( 'Enhance your website performance by adding our Total %1$s service to your site.', 'w3-total-cache' ),
					'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
				)
			);
			?>
		</p>

		<h4 class="w3tc_<?php echo esc_attr( 'totalcdn' ); ?>_signup_h4"><?php \esc_html_e( 'New customer? Sign up now to speed up your site!', 'w3-total-cache' ); ?></h4>

		<p>
			<?php
				w3tc_e(
					'cdn.' . 'totalcdn' . '.widget.v2.works_magically',
					sprintf(
						// translators: 1: CDN name.
						\__( '%1$s works magically with W3 Total Cache to speed up your site around the world for as little as $1 per month.', 'w3-total-cache' ),
						esc_html( W3TC_CDN_NAME )
					)
				);
			?>
		</p>

			<input type="button" class="button-primary btn button-buy-tcdn" data-renew-key="<?php echo esc_attr( $config->get_string( 'plugin.license_key' ) ); ?>" data-src="general_page_cdn_subscribe" value="<?php echo esc_attr__( 'Subscribe To', 'w3-total-cache' ) . ' ' . esc_attr( W3TC_CDN_NAME ); ?>">
		<?php
	}
	?>
	<h4 class="w3tc_<?php echo esc_attr( 'totalcdn' ); ?>_signup_h4"><?php esc_html_e( 'Current customers', 'w3-total-cache' ); ?></h4>

	<p>
		<?php
		w3tc_e(
			'cdn.' . 'totalcdn' . '.widget.v2.existing',
			\sprintf(
				// translators: 1 HTML acronym for Content Delivery Network (CDN), 2: CDN name.
				\__(
					'If you\'re an existing %2$s customer, enable %1$s and authorize. If you need help configuring your %1$s, we also offer Premium Services to assist you.',
					'w3-total-cache'
				),
				'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>',
				esc_html( W3TC_CDN_NAME )
			)
		);
		?>
	</p>

	<?php
	if ( ! $cdn_enabled && ! $cdnfsd_enabled ) {
		?>
		<a class="button-primary" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ), 'w3tc' ) ); ?>">
			<?php \esc_html_e( 'Enable', 'w3-total-cache' ); ?>
		</a>
		<?php
	} elseif ( $is_w3tc_cdn_incomplete ) {
		?>
		<a class="button-primary" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ), 'w3tc' ) ); ?>">
			<?php \esc_html_e( 'Authorize', 'w3-total-cache' ); ?>
		</a>
		<?php
	}
	?>

	<a class="button" href="<?php echo \esc_url( \wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), 'w3tc' ) ); ?>">
		<?php \esc_html_e( 'Premium Services', 'w3-total-cache' ); ?>
	</a>
</div>
