<?php
/**
 * File: SiteHealth_Cdn.php
 *
 * Adds a custom Site Health test for checking if the CDN is enabled.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class SiteHealth_Cdn
 */
class SiteHealth_Cdn {
		/**
		 * Register hooks.
		 *
		 * @return void
		 * @since  x.x.x
		 */
	public function run() {
		if ( is_admin() ) {
			add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

		/**
		 * Enqueue assets on the Site Health screen so the purchase button works.
		 *
		 * @param string $hook The current admin page hook.
		 *
		 * @return void
		 * @since  x.x.x
		 */
	public function enqueue_scripts( $hook ) {
		if ( 'site-health.php' !== $hook ) {
			return;
		}

		// Styles and scripts are registered in Generic_Plugin_Admin.
		wp_enqueue_style( 'w3tc-lightbox' );
		wp_enqueue_script( 'w3tc-lightbox' );

		// Provide nonce to JS just like on plugin screens.
		if ( ! wp_script_is( 'w3tc-lightbox', 'data' ) ) {
			wp_localize_script( 'w3tc-lightbox', 'w3tc_nonce', array( wp_create_nonce( 'w3tc' ) ) );
		}
	}

		/**
		 * Register the CDN status test.
		 *
		 * @param array $tests Existing Site Health tests.
		 *
		 * @return array
		 * @since  x.x.x
		 */
	public function add_tests( $tests ) {
		$tests['direct']['w3tc_cdn'] = array(
			'label' => __( 'W3 Total Cache CDN', 'w3-total-cache' ),
			'test'  => array( $this, 'test_cdn_enabled' ),
		);
		return $tests;
	}

		/**
		 * Perform the CDN enabled test.
		 *
		 * @return array Test result.
		 * @since  x.x.x
		 */
	public function test_cdn_enabled() {
		$config  = Dispatcher::config();
		$enabled = $config->get_boolean( 'cdn.enabled' );

		$result = array(
			'label'       => '',
			'status'      => '',
			'badge'       => array(
				'label' => __( 'Performance', 'w3-total-cache' ),
				'color' => 'blue',
			),
			'description' => '',
			'actions'     => '',
			'test'        => 'w3tc_cdn',
		);

		if ( $enabled ) {
			$result['status']      = 'good';
			$result['label']       = __( 'CDN is enabled', 'w3-total-cache' );
			$result['description'] = __( 'Your site is configured to use a Content Delivery Network (CDN).', 'w3-total-cache' );
		} else {
			$result['status']      = 'recommended';
			$result['label']       = __( 'CDN is not enabled', 'w3-total-cache' );
			$result['description'] = __(
				'Your site is not using a Content Delivery Network (CDN). Using a CDN can improve your site\'s performance by caching static files closer to your visitors.',
				'w3-total-cache'
			);
			$result['actions']     = $this->get_actions( $config );
		}

		return $result;
	}

		/**
		 * Generates the action markup when CDN is disabled.
		 *
		 * @param Config $config Plugin configuration.
		 *
		 * @return string HTML actions.
		 * @since  x.x.x
		 */
	private function get_actions( $config ) {
		$api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );

		if ( ! empty( $api_key ) ) {
			$url = wp_nonce_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ), 'w3tc' );
			return '<p><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Enable', 'w3-total-cache' ) . '</a></p>';
		}

		$license_key = $config->get_string( 'plugin.license_key' );
		$button      = '<input type="button" class="button-primary btn button-buy-tcdn"';
		$button     .= ' data-renew-key="' . esc_attr( $license_key ) . '"';
		$button     .= ' data-src="site_health"';
		$button     .= sprintf(
			' value="%1$s %2$s" />',
			esc_attr__( 'Purchase', 'w3-total-cache' ),
			esc_attr( W3TC_CDN_NAME )
		);

		return '<p>' . $button . '</p>';
	}
}
