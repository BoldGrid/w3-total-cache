<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$is_pro = Util_Environment::is_w3tc_pro( $this->_config );

?>

<?php $this->checkbox( 'minify.css.strip.comments', false, 'css_' ); ?> <?php Util_Ui::e_config_label( 'minify.css.strip.comments' ); ?></label><br />

<?php $this->checkbox( 'minify.css.strip.crlf', false, 'css_' ); ?> <?php Util_Ui::e_config_label( 'minify.css.strip.crlf' ); ?></label><br />

<?php
Util_Ui::config_item_pro(
	array(
		'key'               => 'minify.css.embed',
		'control'           => 'checkbox',
		'checkbox_label'    => __( 'Eliminate render-blocking <acronym title="Cascading Style Sheet">CSS</acronym> by moving it to <acronym title="Hypertext Transfer Protocol">HTTP</acronym> body', 'w3-total-cache' ),
		'disabled'          => ( $is_pro ? null : true ),
		'label_class'       => 'w3tc_no_trtd',
		'excerpt'           => __( 'Website visitors cannot navigate your website until a given page is ready - reduce the wait time with this feature.', 'w3-total-cache' ),
		'description'       => array(
			__( 'Faster paint time is a key last step in lowering bounce rates even for repeat page views. Enable this feature to significantly enhance your website’s user experience by reducing wait times and ensuring that users can interact with your website as quickly as possible.', 'w3-total-cache' ),
			wp_kses(
				sprintf(
					// translators: 1 The opening anchor tag linking to our support page, 2 its closing tag.
					__( 'Need help? Take a look at our %1$spremium support, customization and audit services%2$s.', 'w3-total-cache' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
					'</a>'
				),
				array( 'a' => array( 'href' => array() ) )
			),
		),
		'show_learn_more'   => false,
		'score'             => '+17.5',
		'score_label'       => __( 'Points', 'w3-total-cache' ),
		'score_description' => wp_kses(
			sprintf(
				// translators: 1  opening HTML a tag, 2 closing HTML a tag, 3 two HTML br tags followed by a HTML input button to purchase pro license.
				__(
					'In a recent test, Eliminating render blocking CSS improved our Google PageSpeed score by over 17 points on mobile devices! %1$sReview the testing results%2$s to see how.%3$s and improve your PageSpeed Scores today!',
					'w3-total-cache'
				),
				'<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/eliminate-render-blocking-css/?utm_source=w3tc&utm_medium=eliminate-render-blocking-css&utm_campaign=proof' ) . '">',
				'</a>',
				'<br /><br /><input type="button" class="button-primary btn button-buy-plugin" data-src="test_score_upgrade" value="' . esc_attr__( 'Upgrade to', 'w3-total-cache' ) . ' W3 Total Cache Pro">'
			),
			array(
				'a'     => array(
					'href'   => array(),
					'target' => array(),
				),
				'br'    => array(),
				'input' => array(
					'type'     => array(),
					'class'    => array(),
					'data-src' => array(),
					'value'    => array(),
				),
			)
		),
	)
);
?>

<br />
