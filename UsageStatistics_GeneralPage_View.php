<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

Util_Ui::postbox_header( 'Statistics', '', 'stats' );

$c = Dispatcher::config();
$is_pro = Util_Environment::is_w3tc_pro( $c );

?>
<p>Cache usage statistics.</p>

<table class="form-table">
	<?php
Util_Ui::config_item_pro( array(
		'key' => 'stats.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'disabled' => ( $is_pro ? null : true ),
		'excerpt' => __( 'Enable statistics collection. Note that this consumes additional resources and is not recommended to be run continuously.',
			'w3-total-cache' ),
		'description' => array(
			__( 'Statistics provide transparency into the behavior of your caching performance. Without statistics, it’s challenging to identify opportunities for improvement or ensure operations are working as expected consistently. Metrics like cache sizes, object lifetimes, hit vs miss ratio, etc across every caching method configured in your settings.', 'w3-total-cache' ),

			__( 'Some statistics are available directly on your Performance Dashboard, however, the comprehensive suite of statistics are available on the Statistics screen. Web server logs created by Nginx or Apache can be analyzed if accessible.', 'w3-total-cache' ),

			__( 'Use the caching statistics to compare the performance of different configurations like caching methods, object lifetimes and so on. Contact support for any help optimizing performance metrics or troubleshooting.',
				'w3-total-cache' )
		)
	) );
Util_Ui::config_item( array(
		'key' => 'stats.slot_seconds',
		'label' => __( 'Slot time (seconds):', 'w3-total-cache' ),
		'control' => 'textbox',
		'textbox_type' => 'number',
		'description' =>
		'The duration of time in seconds to collect statistics per interval.'
	) );
Util_Ui::config_item( array(
		'key' => 'stats.slots_count',
		'label' => __( 'Slots collected:', 'w3-total-cache' ),
		'control' => 'textbox',
		'textbox_type' => 'number',
		'description' =>
		'The number of intervals that are represented in the graph.'
	) );

Util_Ui::config_item( array(
		'key' => 'stats.cpu.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Use the system reported averages of CPU resource usage.', 'w3-total-cache' ),
		'description' => __( 'Collect CPU usage', 'w3-total-cache' )
	) );
Util_Ui::config_item( array(
		'key' => 'stats.access_log.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Parse server access log', 'w3-total-cache' ),
		'disabled' => ( $is_pro ? null : true ),
		'description' => __( 'Enable collecting statistics from an Access Log.  This provides much more precise statistics.', 'w3-total-cache' )
	) );
Util_Ui::config_item( array(
		'key' => 'stats.access_log.webserver',
		'label' => __( 'Webserver:', 'w3-total-cache' ),
		'control' => 'selectbox',
		'selectbox_values' => array(
			'apache' => 'Apache',
			'nginx' => 'Nginx'
		),
		'description' => 'Webserver type generating access logs.'
	) );
Util_Ui::config_item( array(
		'key' => 'stats.access_log.filename',
		'label' => __( 'Access Log Filename:', 'w3-total-cache' ),
		'control' => 'textbox',
		'textbox_size' => 60,
		'description' => 'Where your access log is located.',
		'control_after' =>
			'<input type="button" class="button" id="ustats_access_log_test" value="Test" /><span id="ustats_access_log_test_result" style="padding-left: 20px"></span>'
	) );
Util_Ui::config_item( array(
		'key' => 'stats.access_log.format',
		'label' => __( 'Access Log Format:', 'w3-total-cache' ),
		'control' => 'textbox',
		'textbox_size' => 60,
		'description' =>
		'Format of your access log from webserver configuration.',
		'control_after' =>
			'<input type="button" class="button" id="ustats_access_log_format_reset" value="Reset to Default" />'
	) );
?>
</table>

<?php
Util_Ui::button_config_save( 'stats' );
?>
<?php Util_Ui::postbox_footer(); ?>

<script>
jQuery('#ustats_access_log_format_reset').click(function() {
	var webserver = jQuery('#stats__access_log__webserver').val();

	var v;
	if (webserver == 'nginx') {
		v = '$remote_addr - $remote_user [$time_local] "$request" $status $body_bytes_sent "$http_referer" "$http_user_agent"';
	} else {
		v = '%h %l %u %t \\"%r\\" %>s %O \\"%{Referer}i\\" \\"%{User-Agent}i\\"';
	}
	jQuery('#stats__access_log__format').val(v);
});

jQuery('#ustats_access_log_test').click(function() {
	var params = {
		action: 'w3tc_ajax',
		_wpnonce: w3tc_nonce,
		w3tc_action: 'ustats_access_log_test',
		filename: jQuery('#stats__access_log__filename').val()
	};

	jQuery.post(ajaxurl, params, function(data) {
		jQuery('#ustats_access_log_test_result').text(data);
	}).fail(function() {
		jQuery('#ustats_access_log_test_result').text('Check failed');
	});
});
</script>
