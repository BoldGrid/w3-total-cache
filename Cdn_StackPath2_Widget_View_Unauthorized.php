<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<div id="stackpath-widget" class="w3tcstackpath_signup">
	<?php if ( !$c->get_boolean( 'cdn.enabled' ) ): ?>
		<p class="notice notice-error">
			<?php w3tc_e( 'cdn.stackpath.widget.v2.no_cdn',
			'W3 Total Cache has detected that you do not have a <acronym title="Content Delivery Network">CDN</acronym> configured' ) ?>
		</p>
	<?php endif ?>

	<p>
		<?php w3tc_e( 'cdn.stackpath.widget.v2.header',
		"Enhance your website performance by adding StackPath's (<acronym title='Content Delivery Network'>CDN</acronym>) service to your site." ) ?>
	</p>
	<h4 class="w3tcstackpath_signup_h4"><?php _e( 'New customer? Sign up now to speed up your site!', 'w3-total-cache' )?></h4>

	<p>
		<?php w3tc_e( 'cdn.stackpath2.widget.v2.works_magically',
		'StackPath works magically with W3 Total Cache to speed up your site around the world for as little as $10 per month.' )?>
	</p>
	<a class="button-primary" href="<?php echo esc_url( W3TC_STACKPATH_SIGNUP_URL )?>" target="_blank"><?php _e( 'Sign Up Now ', 'w3-total-cache' )?></a>
	<p>
		<h4 class="w3tcstackpath_signup_h4"><?php _e( 'Current customers', 'w3-total-cache' )?></h4>
		<p>
			<?php w3tc_e( 'cdn.stackpath2.widget.v2.existing', "If you're an existing StackPath customer, enable <acronym title='Content Delivery Network'>CDN</acronym> and Authorize. If you need help configuring your <acronym title='Content Delivery Network'>CDN</acronym>, we also offer Premium Services to assist you." )?></p>
		<a class="button-primary" href="<?php echo wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ), 'w3tc' )?>" target="_blank"><?php _e( 'Authorize', 'w3-total-cache' )?></a>
		<a class="button" href="<?php echo wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_support' ), 'w3tc' )?>"><?php _e( 'Premium Services', 'w3-total-cache' )?></a>
</div>
