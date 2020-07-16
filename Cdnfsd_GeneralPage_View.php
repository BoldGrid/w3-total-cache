<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<p><?php w3tc_e( 'cdnfsd.general.header', 'Host the entire website with your compatible <acronym title="Content Delivery Network">CDN</acronym> provider to reduce page load time.' ) ?>
<?php if ( !$cdnfsd_enabled ): ?>
<?php printf( __( ' If you do not have a <acronym title="Content Delivery Network">CDN</acronym> provider try StackPath. <a href="%s" target="_blank">Sign up now to enjoy a special offer!</a>.', 'w3-total-cache' ), wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard&w3tc_cdn_maxcdn_signup' ), 'w3tc' ) ); ?>
<?php endif ?>
</p>

<table class="<?php echo esc_attr( Util_Ui::table_class() ); ?>">
	<?php
	Util_Ui::config_item_pro( array(
			'key' => 'cdnfsd.enabled',
			'label' => __( '<acronym title="Full Site Delivery">FSD</acronym> <acronym title="Content Delivery Network">CDN</acronym>:', 'w3-total-cache' ),
			'control' => 'checkbox',
			'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
			'disabled' => ( $is_pro ? null : true ),
			'excerpt' => __( 'Deliver visitors the lowest possible response and load times for all site content including HTML, media (e.g. images or fonts), CSS, and JavaScript.', 'w3-total-cache' ),
			'description' => array(
				__( 'Want even faster speeds? The full site delivery Content Delivery Network will speed up your website by over 60% to increase conversions, revenue and reach your website visitors globally. With a Full Site Content Delivery Network (CDN), your website and all its assets will be available instantly to your visitors all over the world at blazing fast speeds.', 'w3-total-cache' ),
				wp_kses(
					sprintf(
						// translators: 1 The opening anchor tag linking to our support page, 2 its closing tag.
						__( 'For even better performance, combine FSD with other powerful features like Browser Cache, Minify, Fragment caching, or Lazy Load! Did you know that we offer premium support, customization and audit services? %1$sClick here for more information%2$s.', 'w3-total-cache' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
						'</a>'
					),
					array( 'a' => array( 'href' => array() ) )
				),
			)
		) );

	Util_Ui::config_item( array(
			'key' => 'cdnfsd.engine',
			'label' => __( '<acronym title="Full Site Delivery">FSD</acronym> <acronym title="Content Delivery Network">CDN</acronym> Type:', 'w3-total-cache' ),
			'control' => 'selectbox',
			'selectbox_values' => $cdnfsd_engine_values,
			'value' => $cdnfsd_engine,
			'disabled' => ( $is_pro ? null : true ),
			'description' => __( 'Select the <acronym title="Content Delivery Network">CDN</acronym> type you wish to use.',
				'w3-total-cache' ) . $cdnfsd_engine_extra_description,
			'show_in_free' => false,
		) );
	?>
</table>
