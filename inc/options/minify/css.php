<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

$is_pro = Util_Environment::is_w3tc_pro( $this->_config );

?>
<?php $this->checkbox( 'minify.css.strip.comments', false, 'css_' ) ?> <?php Util_Ui::e_config_label( 'minify.css.strip.comments' ) ?></label><br />
<?php $this->checkbox( 'minify.css.strip.crlf', false, 'css_' ) ?> <?php Util_Ui::e_config_label( 'minify.css.strip.crlf' ) ?></label><br />

<?php
Util_Ui::config_item_pro( array(
		'key' => 'minify.css.embed',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Eliminate render-blocking <acronym title="Cascading Style Sheet">CSS</acronym> by moving it to <acronym title="Hypertext Markup Language">HTTP</acronym> body', 'w3-total-cache' ),
		'disabled' => ( $is_pro ? null : true ),
		'label_class' => 'w3tc_no_trtd',
		'excerpt' => __( 'Website visitors cannot navigate your website until a given page is ready - reduce the wait time with this feature.', 'w3-total-cache' ),
		'description' => array(
			__( 'Opportunities to improve user experience exist in nearly every aspect of your website. Once the components are delivered to the web browser there’s an opportunity to improve the performance of how a given page is painted in the browser. Enable, this feature to reduce wait times and ensure that users can interact with your website as quickly as possible.', 'w3-total-cache' ),

			__( 'Faster paint time is a key last step in lowering bounce rates even for repeat page views.', 'w3-total-cache' )
		)
	) );
?>

<br />
