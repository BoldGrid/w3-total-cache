<?php
namespace W3TC;

class ProAd_Plugin_Admin {
	function run() {
		$c = Dispatcher::config();
		if ( Util_Environment::is_w3tc_pro( $c ) ) {
			return;
		}

		add_action( 'w3tc_settings_general_boxarea_cdn_footer',
			array( $this, 'w3tc_settings_general_boxarea_cdn_footer' ) );

		add_action( 'w3tc_settings_general_boxarea_debug',
			array( $this, 'w3tc_settings_general_boxarea_debug' ) );

		$widget = new ProAd_UsageStatistics_Widget();
		$widget->init();

		add_action( 'w3tc_settings_general_boxarea_stats',
			array( $this, 'w3tc_settings_general_boxarea_stats' ) );
	}



	public function w3tc_settings_general_boxarea_stats() {
		include  __DIR__ . '/ProAd_UsageStatistics_GeneralPage_View.php';
	}



	public function w3tc_settings_general_boxarea_cdn_footer() {
		$config = Dispatcher::config();

		include  __DIR__ . '/ProAd_Cdnfsd_GeneralPage_View.php';
	}



	public function w3tc_settings_general_boxarea_debug() {
		include  __DIR__ . '/ProAd_PurgeLog.php';
	}



	protected function checkbox_debug_pro( $option_id, $label, $label_pro ) {
		$config = Dispatcher::config();

		if ( is_array( $option_id ) ) {
			$section = $option_id[0];
			$section_enabled = $config->is_extension_active_frontend( $section );
		} else {
			$section = substr( $option_id, 0, strrpos( $option_id, '.' ) );
			$section_enabled = $config->get_boolean( $section . '.enabled' );
		}

		$disabled = true;
		$name = Util_Ui::config_key_to_http_name( $option_id );

		echo '<label>';
		echo '<input class="enabled" type="checkbox" id="' . $name .
			'" name="' . $name . '" value="1" disabled="disabled" />';
		echo esc_html( $label );

		echo '</label>';
	}
}
