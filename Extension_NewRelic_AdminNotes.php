<?php
/**
 * File: Extension_NewRelic_AdminNotes.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_NewRelic_AdminNotes
 */
class Extension_NewRelic_AdminNotes {
	/**
	 * Checks the current configuration and page load time to recommend optimizations based on New Relic data.
	 *
	 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	 *
	 * @param object $config Configuration object containing the plugin settings.
	 *
	 * @return array An array with a message recommending optimizations, or an empty array if no recommendations.
	 */
	public function notifications( $config ) {
		$config_state = Dispatcher::config_state();
		if ( ! $config_state->get_boolean( 'newrelic.hide_note_pageload_slow' ) ) {
			$pl = get_option( 'w3tc_nr_frontend_response_time' );

			if ( false !== $pl && $pl > 0.3 ) {
				$nr_recommends = array();
				if ( ! $config->get_boolean( 'pgcache.enabled' ) ) {
					$nr_recommends[] = __( 'Page Cache', 'w3-total-cache' );
				}

				if ( ! $config->get_boolean( 'minify.enabled' ) ) {
					$nr_recommends[] = __( 'Minify', 'w3-total-cache' );
				}

				if ( ! $config->get_boolean( 'cdn.enabled' ) ) {
					$nr_recommends[] = __( 'CDN', 'w3-total-cache' );
				}

				if ( ! $config->get_boolean( 'browsercache.enabled' ) ) {
					$nr_recommends[] = __( 'Browser Cache and use compression', 'w3-total-cache' );
				}

				if ( $nr_recommends ) {
					$message = sprintf(
						// Translators: 1 New Relic recommendations, 2 button link.
						__(
							'Application monitoring has detected that your page load time is higher than 300ms. It is recommended that you enable the following features: %1$s %2$s',
							'w3-total-cache'
						),
						implode( ', ', $nr_recommends ),
						Util_Ui::button_link(
							'Hide this message',
							Util_Ui::url(
								array(
									'w3tc_default_config_state' => 'y',
									'key'                       => 'newrelic.hide_note_pageload_slow',
									'value'                     => 'true',
								)
							)
						)
					);

					return array(
						'newrelic_recommends' => $message,
					);
				}
			}
		}

		return array();
	}
}
