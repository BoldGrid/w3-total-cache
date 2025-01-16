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
	$cdn_engine = $c->get_string( 'cdn.engine' );
	$cdn_name   = '';
	switch ( true ) {
		case ( 'ftp' === $cdn_engine ):
			$cdn_name = 'Self-hosted / File Transfer Protocol Upload';
			break;

		case ( 's3' === $cdn_engine ):
			$cdn_name = 'Amazon Simple Storage Service (S3)';
			break;

		case ( 's3_compatible' === $cdn_engine ):
			$cdn_name = 'Amazon Simple Storage Service (S3) Compatible';
			break;

		case ( 'cf' === $cdn_engine ):
			$cdn_name = 'Amazon CloudFront over S3';
			break;

		case ( 'cf2' === $cdn_engine ):
			$cdn_name = 'Amazon CloudFront';
			break;

		case ( 'rscf' === $cdn_engine ):
			$cdn_name = 'Rackspace Cloud Files';
			break;

		case ( 'azure' === $cdn_engine ):
			$cdn_name = 'Microsoft Azure Storage';
			break;

		case ( 'azuremi' === $cdn_engine ):
			$cdn_name = 'Microsoft Azure Storage (Managed Identity)';
			break;

		case ( 'google_drive' === $cdn_engine ):
			$cdn_name = 'Google Drive';
			break;

		case ( 'mirror' === $cdn_engine ):
			$cdn_name = 'Generic Mirror';
			break;

		case ( 'cotendo' === $cdn_engine ):
			$cdn_name = 'Cotendo (Akamai)';
			break;

		case ( 'edgecast' === $cdn_engine ):
			$cdn_name = 'Verizon Digital Media Services (EdgeCast) / Media Temple ProCDN';
			break;

		case ( 'att' === $cdn_engine ):
			$cdn_name = 'AT&T';
			break;

		case ( 'akamai' === $cdn_engine ):
			$cdn_name = 'Akamai';
			break;

		case ( 'highwinds' === $cdn_engine ):
			$cdn_name = 'Highwinds';
			break;

		case ( 'limelight' === $cdn_engine ):
			$cdn_name = 'LimeLight';
			break;

		case ( 'rackspace_cdn' === $cdn_engine ):
			$cdn_name = 'RackSpace';
			break;

		case ( 'stackpath' === $cdn_engine ):
			$cdn_name = 'StackPath SecureCDN (Legacy)';
			break;

		case ( 'stackpath2' === $cdn_engine ):
			$cdn_name = 'StackPath';
			break;
	}

	if ( ! empty( $cdn_name ) ) {
		?>
		<p class="notice notice-error">
			<?php
			w3tc_e(
				'cdn.bunnycdn.widget.v2.no_cdn',
				\sprintf(
					// translators: 1 configured CDN name, 2 HTML acronym for Content Delivery Network (CDN).
					\__( 'W3 Total Cache has detected that you are using the %1$s %2$s, which is fully supported and compatible. For optimal performance and value, we recommend considering BunnyCDN as an alternative.', 'w3-total-cache' ),
					$cdn_name,
					'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
				)
			);
			?>
		</p>
		<?php
	} else {
		?>
		<p class="notice notice-error">
			<?php
			w3tc_e(
				'cdn.bunnycdn.widget.v2.no_cdn',
				\sprintf(
					// translators: 1 HTML acronym for Content Delivery Network (CDN).
					\__( 'W3 Total Cache has detected that you do not have a %1$s configured', 'w3-total-cache' ),
					'<acronym title="' . \__( 'Content Delivery Network', 'w3-total-cache' ) . '">' . \__( 'CDN', 'w3-total-cache' ) . '</acronym>'
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
	<p>
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
