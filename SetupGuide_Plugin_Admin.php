<?php
/**
 * File: SetupGuide_Plugin_Admin.php
 *
 * @since X.X.X
 *
 * @package    W3TC
 */

namespace W3TC;

/**
 * Class: SetupGuide_Plugin_Admin
 *
 * @since X.X.X
 */
class SetupGuide_Plugin_Admin {
	/**
	 * Current page.
	 *
	 * @since  X.X.X
	 * @access protected
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_setup_guide';

	/**
	 * Wizard template.
	 *
	 * @var \W3TC\Wizard\Template
	 */
	private static $template;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		require_once W3TC_INC_DIR . '/wizard/template.php';

		if ( is_null( self::$template ) ) {
			self::$template = new Wizard\Template( $this->get_config() );
		}
	}

	/**
	 * Run.
	 *
	 * Needed by the Root_Loader.
	 *
	 * @since X.X.X
	 */
	public function run() {
	}

	/**
	 * Display the setup guide.
	 *
	 * @since X.X.X
	 *
	 * @see \W3TC\Wizard\Template::render()
	 */
	public function load() {
		self::$template->render();
	}

	/**
	 * Admin-Ajax: Set option to skip the setup guide.
	 *
	 * @since X.X.X
	 */
	public function skip() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			update_site_option( 'w3tc_setupguide_completed', time() );
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Security violation', 403 );
		}
	}

	/**
	 * Admin-Ajax: Test URL addreses for Time To First Byte (TTFB).
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Util_Http::ttfb()
	 */
	public function test_ttfb() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$nocache =  ! empty( $_POST['nocache'] );
			$results = array();
			$urls    = array( site_url() );

			foreach ( $urls as $index => $url ) {
				$results[ $index ] = array(
					'url'        => $url,
					'prime_time' => false,
				);

				// If "nocache" was not requested, then prime URLs if Page Cache is enabled.
				if ( ! $nocache ) {
					$results[ $index ]['prime_time'] = Util_Http::ttfb( $url );
				}

				$results[ $index ]['ttfb'] = Util_Http::ttfb( $url, $nocache );
			}

			wp_send_json_success( $results );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Configure the page cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 */
	public function config_pagecache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$enable                 = ! empty( $_POST['pagecache'] );
			$config                 = new Config();
			$pgcache_enabled        = $config->get_boolean( 'pgcache.enabled' );

			if ( $pgcache_enabled !== $enable ) {
				$config->set( 'pgcache.enabled', $enable );
				$config->set( 'pgcache.engine', 'file_generic' );
				$config->save();
			}

			wp_send_json_success(
				array(
					'enable'                 => $enable,
					'pgcache_enabled'        => $config->get_boolean( 'pgcache.enabled' ),
					'pgcache_previous'       => $pgcache_enabled,
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Get configuration.
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @return array
	 */
	private function get_config() {
		$config          = new Config();
		$pgcache_enabled = $config->get_boolean( 'pgcache.enabled' );

		return array(
			'title'        => esc_html__( 'Setup Guide', 'w3-total-cache' ),
			'scripts'      => array(
				array(
					'handle'    => 'setup-guide',
					'src'       => esc_url( plugin_dir_url( __FILE__ ) . 'pub/js/setup-guide.js' ),
					'deps'      => array( 'jquery' ),
					'version'   => W3TC_VERSION,
					'in_footer' => false,
					'localize'  => array(
						'object_name' => 'W3TC_SetupGuide',
						'data'      => array(
							'test_complete_msg' => __(
								'Testing complete.  Advancing to the next section...',
								'w3-total-cache'
							),
							'test_error_msg' => __(
								'Could not perform this test.  Please reload the page to try again or click skip button to abort the setup guide.',
								'w3-total-cache'
							),
							'config_error_msg' => __(
								'Could not update configuration.  Please reload the page to try again or click skip button to abort the setup guide.',
								'w3-total-cache'
							),
						),
					),
				),
			),
			'actions'      => array(
				array(
					'tag'           => 'wp_ajax_w3tc_wizard_skip',
					'function'      => array(
						$this,
						'skip',
					),
				),
				array(
					'tag'           => 'wp_ajax_w3tc_test_ttfb',
					'function'      => array(
						$this,
						'test_ttfb',
					),
				),
				array(
					'tag'           => 'wp_ajax_w3tc_config_pagecache',
					'function'      => array(
						$this,
						'config_pagecache',
					),
				),
			),
			'steps'        => array(
				array(
					'text'   => __( 'Page Cache', 'w3-total-cache' ),
				),
				array(
					'text'   => __( 'Browser Cache', 'w3-total-cache' ),
				),
				array(
					'text'   => __( 'More Caching Options', 'w3-total-cache' ),
				),
			),
			'slides' => array(
				array( // 1.
					'headline'  => __( 'Welcome to the W3 Total Cache Setup Guide!', 'w3-total-cache' ),
					'markup'    => '<p>' .
						esc_html__(
							'You have selected the Performance Suite that professionals have consistently ranked #1 for options and speed improvements.',
							'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong>
						' . esc_html__(
							'provides many options to help you website preform faster.  While the ideal settings vary for every website, there are a few settings we recommend that you enable now.',
							'w3-total-cache'
						) . '</p>',
				),
				array( // 2.
					'headline'  => __( 'Time to First Byte', 'w3-total-cache' ),
					'markup'    => '<p>' . sprintf(
						// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
						esc_html__(
							'When users visit your website, their browser must connect to your server, wait for your server to respond with the web page, and then display it.  The time it takes between your browser requesting the web page and the receiving of the very first byte of that web page is referred to as %1$sTime to First Byte%2$s.',
							'w3-total-cache'
						),
						'<em>',
						'</em>'
					) . '</p>
					<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
					esc_html__( 'can help you speed up', 'w3-total-cache' ) .
					' <em>' . esc_html__( 'Time to First Byte.', 'w3-total-cache' ) . '</em> ' .
					esc_html__( 'Before we do, let\'s get a baseline and take a measurement.', 'w3-total-cache' ) .
					'</p><p><span class="spinner inline"></span>' . esc_html__( 'Measuring', 'w3-total-cache' ) .
					'<em>' . esc_html__( 'Time to First Byte', 'w3-total-cache' ) . '</em>&hellip;</p>',
				),
				array( // 3.
					'headline'  => __( 'Time to First Byte', 'w3-total-cache' ),
					'markup'    => '<p>
						<table id="w3tc-ttfb-table">
							<thead>
								<tr>
									<th>'. esc_html__( 'URL', 'w3-total-cache' ) . '</th>
									<th>'. esc_html__( 'Before', 'w3-total-cache' ) . ' | '.
									esc_html__( 'After', 'w3-total-cache' ) . ' | '.
									esc_html__( 'Change', 'w3-total-cache' ) . '</th>
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>
					</p>' .
					( $pgcache_enabled ? '<div class="notice notice-info inline"><p>' . esc_html__( 'Page Cache is already enabled.' ) . '</p></div>' : '' ),
				),
				array( // 4.
					'headline'  => __( 'Time to First Byte', 'w3-total-cache' ),
					'markup'    => '<p>' . sprintf(
						// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
						esc_html__(
							'To improve %1$sTime to First Byte%2$s, we recommend enabling %1$sPage Cache%2$s.',
							'w3-total-cache',
						),
						'<em>',
						'</em>'
					) . '</p>
					<p><input type="checkbox" name="enable_pagecache" value="1" checked> ' .
						esc_html__( 'Page Cache', 'w3-total-cache' ) . '</p>
					<p>' . sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
							esc_html__(
								'Click %1$sTest Page Cache%2$s to enable Page Cache and test again.',
								'w3-total-cache'
							),
							'<em>',
							'</em>'
						) . '</p>
					<p>
						<input id="w3tc-test-pagecache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Page Cache', 'w3-total-cache' ) . '">
					</p>
					<p id="w3tc-testing-pagecache" class="hidden"><span class="spinner inline"></span>' .
						esc_html__( 'Measuring', 'w3-total-cache' ) .
						'<em>' . esc_html__( 'Time to First Byte', 'w3-total-cache' ) . '</em>&hellip;
					</p>',
				),
				array( // 5.
					'headline'  => __( 'Time to First Byte', 'w3-total-cache' ),
					'markup'    => '<p>
						<table id="w3tc-ttfb-table2">
						<thead>
						<tr>
							<th>'. esc_html__( 'URL', 'w3-total-cache' ) . '</th>
							<th>'. esc_html__( 'Before', 'w3-total-cache' ) . ' | '.
							esc_html__( 'After', 'w3-total-cache' ) . ' | '.
							esc_html__( 'Change', 'w3-total-cache' ) . '</th>
						</tr>
						</thead>
						<tbody>
						</tbody>
						</table>
						</p>',
				),
				array( // 6.
					'headline'  => __( 'Browser Cache', 'w3-total-cache' ),
					'markup'    => '<p>' . esc_html__(
						'To render your website, browsers must download many different types of assets, including javascript files, CSS stylesheets, images, and more.  For most assets, once a browser has downloaded them, they shouldn\'t have to download them again.',
						'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
						esc_html__(
							'can help ensure browsers are properly caching your assets.  Before making any changes, let\'s first review your current browser cache settings.',
							'w3-total-cache'
						) . '</p>
						<p><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Browser Cache', 'w3-total-cache' ) . '</em>&hellip;</p>',
				),
				array( // 7.
					'headline'  => __( 'Browser Cache', 'w3-total-cache' ),
					'markup'    => '<p>' . sprintf(
						// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
						esc_html__(
							'The %1$sCache-Control%2$s header tells your browser how it should cache specific files.  The %1$smax-age%2$s setting tells your browser how long, in seconds, it should use its cached version of a file before requesting an updated one.',
							'w3-total-cache'
						),
						'<em>',
						'</em>'
						) . '</p>
						<p>
						<table id="w3tc-browsercache-table">
						<thead>
						<tr>
							<th>File</th>
							<th>' . esc_html__( 'Cache-Control header', 'w3-total-cache' ) .
							'<br /><span>' . esc_html__( 'Before', 'w3-total-cache' ) . '</span><span>' .
							esc_html__( 'After', 'w3-total-cache' ) . '</span></th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<td>https://example.com/file.css</td>
							<td><span>' . esc_html__( 'Missing', 'w3-total-cache' ) . '</span><span></span></td>
						</tr>
						<tr>
							<td>https://example.com/file.js</td>
							<td><span>' . esc_html__( 'Missing', 'w3-total-cache' ) . '</span><span></span></td>
						</tr>
						<tr>
							<td>https://example.com/file.png</td>
							<td><span>' . esc_html__( 'Missing', 'w3-total-cache' ) . '</span><span></span></td>
						</tr>
						</tbody>
						</table>
						</p>',
				),
				array( // 8.
					'headline'  => __( 'Browser Cache', 'w3-total-cache' ),
					'markup'    => '<p>' . sprintf(
						// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
						esc_html__(
							'To improve %1$sBrowser Cache%2$s, we recommend enabling %1$sBrowser Cache%2$s setting.',
							'w3-total-cache',
						),
						'<em>',
						'</em>'
						) . '</p>
						<p><input type="checkbox" name="enable_browsercache" value="1" checked> ' .
						esc_html__( 'Browser Cache', 'w3-total-cache' ) . '</p>
						<p>' . sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
							esc_html__(
								'Click %1$sNext%2$s to enable this setting and test again.',
								'w3-total-cache'
							),
							'<em>',
							'</em>'
						) . '</p>
						<p><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
						'<em>' . esc_html__( 'Browser Cache', 'w3-total-cache' ) . '</em>&hellip;</p>',
				),
				array( // 9.
					'headline'  => __( 'Browser Cache', 'w3-total-cache' ),
					'markup'    => '<p>
						<table id="w3tc-browsercache-table">
						<thead>
						<tr>
							<th>' . esc_html__( 'File', 'w3-total-cache' ) . '</th>
							<th>' . esc_html__( 'Cache-Control header', 'w3-total-cache' ) . '<br /><span>' .
							esc_html__( 'Before', 'w3-total-cache' ) . '</span><span>' .
							esc_html__( 'After', 'w3-total-cache' ) . '</span></th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<td>https://example.com/file.css</td>
							<td><span>' . esc_html__( 'Missing', 'w3-total-cache' ) . '</span><span>max-age=3156000</span></td>
						</tr>
						<tr>
							<td>https://example.com/file.js</td>
							<td><span>' . esc_html__( 'Missing', 'w3-total-cache' ) . '</span><span>max-age=3156000</span></td>
						</tr>
						<tr>
							<td>https://example.com/file.png</td>
							<td><span>' . esc_html__( 'Missing', 'w3-total-cache' ) . '</span><span>max-age=3156000</span></td>
						</tr>
						</tbody>
						</table>
						</p>',
				),
				array( // 10.
					'headline'  => __( 'Setup Complete!', 'w3-total-cache' ),
					'markup'    => '<p>' . sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag.
							esc_html__(
								'%1$sTime to First Byte%2$s has improved between 1 and 3 seconds!',
								'w3-total-cache',
							),
							'<strong>',
							'</strong>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag.
							esc_html__(
								'%1$sBrowser Cache%2$s headers are now being set for your JavaScript, CSS, and images!',
								'w3-total-cache',
							),
							'<strong>',
							'</strong>'
						) . '</p>
						<h3>' . esc_html__( 'What\'s Next?', 'w3-total-cache' ) . '</h3>
						<p>' . sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag, 3: HTML break tag, 4: Anchor/link open tag, 5: Anchor/link close tag.
							esc_html__(
								'Your website\'s performance can still be improved by configuring %1$sminify%2$s settings, setting up a %1$sCDN%2$s, and more!%3$sPlease visit your %4$sW3TC Dashboard%5$s to learn more about these features.',
								'w3-total-cache',
							),
							'<strong>',
							'</strong>',
							'<br />',
							'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_dashboard' ) ) . '">',
							'</a>'
						) . '</p>',
				),
			),
		);
	}
}
