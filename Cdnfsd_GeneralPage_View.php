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
<table class="form-table">
	<?php
	Util_Ui::config_item_pro( array(
			'key' => 'cdnfsd.enabled',
			'label' => __( '<acronym title="Full Site Delivery">FSD</acronym> <acronym title="Content Delivery Network">CDN</acronym>:', 'w3-total-cache' ),
			'control' => 'checkbox',
			'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
			'disabled' => ( $is_pro ? null : true ),
			'excerpt' => __( 'Deliver visitors the lowest possible response and load times for all site content including HTML, media (e.g. images or fonts), CSS, and JavaScript.', 'w3-total-cache' ),
			'description' => array(
				__( 'Without Full Site Delivery, the HTML of your website is not delivered with the lowest latency possible. A small change to DNS settings means that every component of your website is delivered to visitors using a worldwide network of servers. The net result is more resources for content creation or for authenticated users to post comments or browser personalized experiences like e-commerce and membership sites etc.', 'w3-total-cache' ),
				__( 'For even better performance, combine FSD with other powerful features like Browser Cache, Minify, Fragment caching, or Lazy Load!', 'w3-total-cache' ),
				__( 'Contact support for any help maximizing your performance scores or troubleshooting.', 'w3-total-cache' )
			)
		) )
	?>

	<?php
	Util_Ui::config_item( array(
			'key' => 'cdnfsd.engine',
			'label' => __( '<acronym title="Full Site Delivery">FSD</acronym> <acronym title="Content Delivery Network">CDN</acronym> Type:', 'w3-total-cache' ),
			'control' => 'selectbox',
			'selectbox_values' => $cdnfsd_engine_values,
			'value' => $cdnfsd_engine,
			'disabled' => ( $is_pro ? null : true ),
			'description' => __( 'Select the <acronym title="Content Delivery Network">CDN</acronym> type you wish to use.',
				'w3-total-cache' ) . $cdnfsd_engine_extra_description
		) )
	?>
</table>
