<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

Util_Ui::postbox_header( 'Statistics', '', 'stats' );

$c = Dispatcher::config();
$is_pro = Util_Environment::is_w3tc_pro( $c );

?>

<table class="<?php echo esc_attr( Util_Ui::table_class() ); ?>">
	<?php
Util_Ui::config_item_pro( array(
		'key' => 'stats.enabled',
		'label' => esc_html__( 'Cache usage statistics' ),
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'disabled' => ( $is_pro ? null : true ),
		'excerpt' => __( 'Enable statistics collection. Note that this consumes additional resources and is not recommended to be run continuously.',
			'w3-total-cache' ),
		'description' => array(
			__( 'Statistics provides near-complete transparency into the behavior of your caching performance, allowing you to identify opportunities to further improve your website speed and ensure operations are working as expected. Includes metrics like cache sizes, object lifetimes, hit vs miss ratio, etc across every caching method configured in your settings.', 'w3-total-cache' ),
			__( 'Some statistics are available directly on your Performance Dashboard, however, the comprehensive suite of statistics are available on the Statistics screen. Web server logs created by Nginx or Apache can be analyzed if accessible.', 'w3-total-cache' ),
			wp_kses(
				sprintf(
					// translators: 1 The opening anchor tag linking to our support page, 2 its closing tag.
					__( 'Use the caching statistics to compare the performance of different configurations like caching methods, object lifetimes and so on. Did you know that we offer premium support, customization and audit services? %1$sClick here for more information%2$s.', 'w3-total-cache' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_support' ) ) . '">',
					'</a>'
				),
				array( 'a' => array( 'href' => array() ) )
			),
		),
	) );
?>
</table>

<?php
Util_Ui::button_config_save( 'stats' );
?>
<?php Util_Ui::postbox_footer(); ?>
