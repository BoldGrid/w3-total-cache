<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

$c = Dispatcher::config();
$is_pro = Util_Environment::is_w3tc_pro( $c );

$plugins = get_option( 'active_plugins' );
$is_wp_google_maps = ( in_array( 'wp-google-maps/wpGoogleMaps.php', $plugins ) );
$is_wp_google_map_plugin = ( in_array( 'wp-google-map-plugin/wp-google-map-plugin.php', $plugins ) );
$is_google_maps_easy = ( in_array( 'google-maps-easy/gmp.php', $plugins ) );

?>
<?php Util_Ui::postbox_header( __( 'Lazy Loading', 'w3-total-cache' ), '', 'application' ); ?>
<table class="form-table">
	<?php
	Util_Ui::config_item( array(
			'key' => 'lazyload.process_img',
			'control' => 'checkbox',
			'checkbox_label' => __( 'Process HTML image tags', 'w3-total-cache' ),
			'description' => __( 'Process <code>img</code> tags',
				'w3-total-cache' )
	) );

	Util_Ui::config_item( array(
			'key' => 'lazyload.process_background',
			'control' => 'checkbox',
			'checkbox_label' => __( 'Process background images', 'w3-total-cache' ),
			'description' => __( 'Process <code>background</code> styles',
				'w3-total-cache' )
	) );

	Util_Ui::config_item( array(
			'key' => 'lazyload.exclude',
			'label' => 'Exclude words:',
			'control' => 'textarea',
			'description' => __( 'Exclude tags containing words',
				'w3-total-cache' )
	) );

	Util_Ui::config_item( array(
			'key' => 'lazyload.embed_method',
			'label' => __( 'Script Embed method:', 'w3-total-cache' ),
			'control' => 'selectbox',
			'selectbox_values' => array(
				'async_head' => 'async',
				'sync_head' => 'sync (to head)',
				'inline_footer' => 'inline'
			),
			'description' => 'Use <code>inline</code> method only when your website has just a few pages'
		)
	);

	?>
	<tr>
		<th>Google Maps</th>
		<td>
			<?php Util_Ui::pro_wrap_maybe_start(); ?>
			<p class="description w3tc-gopro-excerpt" style="padding-bottom: 10px">Lazy load google map</p>
			<div>
				<?php
				Util_Ui::control2( Util_Ui::config_item_preprocess( array(
						'key' => 'lazyload.googlemaps.wp_google_map_plugin',
						'control' => 'checkbox',
						'disabled' => ( $is_pro ? !$is_wp_google_map_plugin : true ),
						'checkbox_label' => __( '<a href="https://wordpress.org/plugins/wp-google-map-plugin/" target="_blank">WP Google Map Plugin</a> plugin', 'w3-total-cache' ),
						'label_class' => 'w3tc_no_trtd'
				) ) );
				?>
			</div>
			<div>
				<?php
				Util_Ui::control2( Util_Ui::config_item_preprocess( array(
						'key' => 'lazyload.googlemaps.google_maps_easy',
						'control' => 'checkbox',
						'disabled' => ( $is_pro ? !$is_google_maps_easy : true ),
						'checkbox_label' => __( '<a href="https://wordpress.org/plugins/google-maps-easy/" target="_blank">Google Maps Easy</a> plugin', 'w3-total-cache' ),
						'label_class' => 'w3tc_no_trtd'
				) ) );
				?>
			</div>
			<div>
				<?php
				Util_Ui::control2( Util_Ui::config_item_preprocess( array(
						'key' => 'lazyload.googlemaps.wp_google_maps',
						'control' => 'checkbox',
						'disabled' => ( $is_pro ? !$is_wp_google_maps : true ),
						'checkbox_label' => __( '<a href="https://wordpress.org/plugins/wp-google-maps/" target="_blank">WP Google Maps</a> plugin', 'w3-total-cache' ),
						'label_class' => 'w3tc_no_trtd'
				) ) );
				?>
			</div>
			<?php Util_Ui::pro_wrap_maybe_end( 'lazyload_googlemaps' ); ?>
		</td>
	</tr>
</table>
<p class="submit">
	<?php Util_Ui::button_config_save( 'lazyload' ); ?>
</p>

<?php Util_Ui::postbox_footer(); ?>
