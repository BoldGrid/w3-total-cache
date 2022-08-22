<?php
/**
 * File: PageSpeed_Plugin_Widget.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed API
 */
class PageSpeed_Api {
	/**
	 * Config
	 *
	 * @var null
	 */
	private $config;

	/**
	 * W3TCG_Google_Client
	 *
	 * @var null|object
	 */
	public $client;

	/**
	 * PageSpeed API constructor
	 *
	 * @param string $access_token_json API access token JSON.
	 */
	public function __construct( $access_token_json = null ) {
		$this->config = Dispatcher::config();
		$this->client = new \W3TCG_Google_Client();
		$this->client->setApplicationName( 'W3TC PageSpeed Analyzer' );
		$this->client->setAuthConfig( W3TC_GOOGLE_CLIENT_JSON );
		$this->client->setRedirectUri( W3TC_PAGESPEED_RETURN_URL );
		$this->client->addScope( 'openid' );
		$this->client->setAccessType( 'offline' );
		$this->client->setApprovalPrompt( 'force' );
		$this->client->setDefer( true );

		if ( ! empty( $access_token_json ) ) {
			$this->client->setAccessToken( $access_token_json );
		}
	}

	/**
	 * Fully analyze URL via PageSpeed API
	 *
	 * @param string $url URL to analyze via PageSpeed API.
	 *
	 * @return array
	 */
	public function analyze( $url ) {
		$mobile  = $this->analyze_strategy( $url, 'mobile' );
		$desktop = $this->analyze_strategy( $url, 'desktop' );
		return array(
			'mobile'   => $mobile,
			'desktop'  => $desktop,
			'test_url' => Util_Environment::url_format(
				W3TC_PAGESPEED_API_URL,
				array( 'url' => $url )
			),
		);
	}

	/**
	 * Analyze URL via PageSpeed API using strategy
	 *
	 * @param string $url URL to analyze.
	 * @param string $strategy Strategy to use desktop/mobile.
	 *
	 * @return array
	 */
	public function analyze_strategy( $url, $strategy ) {
		$data = $this->_request(
			array(
				'url'      => $url,
				'category' => 'performance',
				'strategy' => $strategy,
			)
		);

		if ( ! empty( $this->v( $data, array( 'error', 'code' ) ) ) && $this->v( $data, array( 'error', 'code' ) ) !== 200 ) {
			return array(
				'error' => array(
					'code'    => $this->v( $data, array( 'error', 'code' ) ),
					'message' => $this->v( $data, array( 'error', 'message' ) ),
				),
			);
		}

		return array(
			'score'                    => $this->v( $data, array( 'lighthouseResult', 'categories', 'performance', 'score' ) ) * 100,
			'first-contentful-paint'   => array(
				'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'score' ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'displayValue' ) ),
			),
			'largest-contentful-paint' => array(
				'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'score' ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint', 'displayValue' ) ),
			),
			'interactive'              => array(
				'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'interactive', 'score' ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'interactive', 'displayValue' ) ),
			),
			'cumulative-layout-shift'  => array(
				'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'score' ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'cumulative-layout-shift', 'displayValue' ) ),
			),
			'total-blocking-time'      => array(
				'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'score' ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-blocking-time', 'displayValue' ) ),
			),
			'speed-index'              => array(
				'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'score' ) ),
				'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'speed-index', 'displayValue' ) ),
			),
			'screenshots'              => array(
				'final' => array(
					'title'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'title' ) ),
					'screenshot' => $this->v( $data, array( 'lighthouseResult', 'audits', 'final-screenshot', 'details', 'data' ) ),
				),
				'other' => array(
					'title'       => $this->v( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'title' ) ),
					'screenshots' => $this->v( $data, array( 'lighthouseResult', 'audits', 'screenshot-thumbnails', 'details', 'items' ) ),
				),
			),
			'opportunities'            => array(
				'render-blocking-resources' => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'render-blocking-resources', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 HTML a tag to W3TC minify JS section, 2 HTML a tag to W3TC minify CSS section.
							__(
								'W3TC can eliminate render blocking resources. Once Minified, you can deffer JS in the 
									%1$s. Render blocking CSS can be eliminated in %2$s using the "Eliminate Render 
									blocking CSS by moving it to HTTP body" (PRO FEATURE)',
								'w3-total-cache'
							),
							'<a href="' . esc_url( '/wp-admin/admin.php?page=w3tc_minify#js' ) . '">' . esc_html__( 'Performance>Minify>JS', 'w3-total-cache' ) . '</a>',
							'<a href="' . esc_url( '/wp-admin/admin.php?page=w3tc_minify#css' ) . '">' . esc_html__( 'Performance>Minify>CSS', 'w3-total-cache' ) . '</a>'
						),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
				),
				'unused-css-rules'          => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-css-rules', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 two HTML line break tags, 3 two HTML line break tags,
							// translators: 4 two HTML line break tags, 5 two HTML line break tags.
							__(
								'Some themes and plugins are loading CSS files or parts of the CSS files on all pages and 
									not only on the pages that should be loading on. For eaxmple if you are using some contact 
									form plugin, there is a chance that the CSS file of that plugin will load not only on the 
									/contact/ page, but on all other pages as well and this is why the unused CSS should be 
									removed.
									
									%1$sOpen your Chrome browser, go to “Developer Tools”, click on “More Tools” and 
									then “Coverage”.
									
									%2$sA Coverage will open up. We will see buttons for start capturing 
									coverage, to reload and start capturing coverage and to stop capturing coverage and show 
									results.
									
									%3$sIf you have a webpage you want to analyze its code coverage. Load the webpage 
									and click on the o button in the Coverage tab.
									
									%4$sAfter sometime, a table will show up in 
									the tab with the resources it analyzed, and how much code is used in the webpage. All the 
									files linked in the webpage (css, js) will be listed in the Coverage tab. Clicking on any 
									resource there will open that resource in the Sources panel with a breakdown of Total Bytes 
									and Unused Bytes.
									
									%5$sWith this breakdown, we can see how many unused bytes are in our CSS 
									files, so we can manually remove them',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
						),
						array(
							'br' => array(),
						)
					),
				),
				'unminified-css'            => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-css', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 opening HTML a tag to W3TC Minify admin page,
							// translators: 2 closing HTML a tag, 3 opening HTML acronym tag, 4 closing HTML acronym tag,
							// translators: 5 opening HTML acronym tag, 6 closing HTML acronym tag, 7 opening HTML acronym tag,
							// translators: 8 closing HTML acronym tag, 9 opening HTML a tag to FAQ page on api.w3-edge.com,
							// translators: 10 opening HTML acronym tag, 11 closing HTML acronym tag, 12 closing HTML acronym tag.
							__(
								'On the %1$sMinify%2$s tab all of the recommended settings are preset. Use the help button 
									to simplify discovery of your %3$sCSS%4$s and %5$sJS%6$s files and groups. Pay close 
									attention to the method and location of your %7$sJS%8$s group embeddings. See the 
									plugin\'s %9$s%10$sFAQ%11$s%12$s for more information on usage.',
								'w3-total-cache',
							),
							'<a href="admin.php?page=w3tc_minify#css" alt="' . __( 'Minify', 'w3-total-cache' ) . '" target="_blank">',
							'</a>',
							'<acronym title="' . __( 'Cascading Style Sheet', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<a href="https://api.w3-edge.com/v1/redirects/faq/usage" alt="' . __( 'Frequently Asked Questions', 'w3-total-cache' ) . '" target="_blank">',
							'<acronym title="' . __( 'Frequently Asked Questions', 'w3-total-cache' ) . '">',
							'</acronym>',
							'</a>'
						),
						array(
							'a'       => array(
								'href'   => array(),
								'target' => array(),
								'alt'    => array(),
							),
							'acronym' => array(
								'title' => array(),
							),
						)
					),
				),
				'unminified-javascript'     => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 opening HTML a tag to W3TC Minify admin page,
							// translators: 2 closing HTML a tag, 3 opening HTML acronym tag, 4 closing HTML acronym tag,
							// translators: 5 opening HTML acronym tag, 6 closing HTML acronym tag, 7 opening HTML acronym tag,
							// translators: 8 closing HTML acronym tag, 9 opening HTML a tag to FAQ page on api.w3-edge.com,
							// translators: 10 opening HTML acronym tag, 11 closing HTML acronym tag, 12 closing HTML acronym tag.
							__(
								'On the %1$sMinify%2$s tab all of the recommended settings are preset. Use the help 
									button to simplify discovery of your %3$sCSS%4$s and %5$sJS%6$s files and groups. Pay 
									close attention to the method and location of your %7$sJS%8$s group embeddings. See 
									the plugin\'s %9$s%10$sFAQ%11$s%12$s for more information on usage.',
								'w3-total-cache',
							),
							'<a href="admin.php?page=w3tc_minify#js" alt="' . __( 'Minify', 'w3-total-cache' ) . '" target="_blank">',
							'</a>',
							'<acronym title="' . __( 'Cascading Style Sheet', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<a href="https://api.w3-edge.com/v1/redirects/faq/usage" alt="' . __( 'Frequently Asked Questions', 'w3-total-cache' ) . '" target="_blank">',
							'<acronym title="' . __( 'Frequently Asked Questions', 'w3-total-cache' ) . '">',
							'</acronym>',
							'</a>'
						),
						array(
							'a'       => array(
								'href'   => array(),
								'target' => array(),
								'alt'    => array(),
							),
							'acronym' => array(
								'title' => array(),
							),
						)
					),
				),
				'unused-javascript'         => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'unused-javascript', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 two HTML line break tags, 3 two HTML line break tags,
							// translators: 4 two HTML line break tags, 5 two HTML line break tags.
							__(
								'Some themes and plugins are loading JS files or parts of the JS files on all pages and 
									not only on the pages that should be loading on. For eaxmple if you are using some contact 
									form plugin, there is a chance that the JS file of that plugin will load not only on the 
									/contact/ page, but on all other pages as well and this is why the unused JS should be 
									removed. %1$sOpen your Chrome browser, go to “Developer Tools”, click on “More Tools” and 
									then “Coverage”. %2$sA Coverage will open up. We will see buttons for start capturing 
									coverage, to reload and start capturing coverage and to stop capturing coverage and show 
									results. %3$sIf you have a webpage you want to analyze its code coverage. Load the webpage 
									and click on the o button in the Coverage tab. %4$sAfter sometime, a table will show up in 
									the tab with the resources it analyzed, and how much code is used in the webpage. All the 
									files linked in the webpage (css, js) will be listed in the Coverage tab. Clicking on any 
									resource there will open that resource in the Sources panel with a breakdown of Total Bytes 
									and Unused Bytes. %5$sWith this breakdown, we can see how many unused bytes are in our JS 
									files, so we can manually remove them',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
						),
						array(
							'br' => array(),
						)
					),
				),
				'uses-responsive-images'    => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-responsive-images', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 HTML a tag to helpx.adobe.com for photoshop,
							// translators: 2 two HTML line break tags, 3 HTML line break tag, 4 two HTML line break tags,
							// translators: 5 HTML line break tag,
							// translators: 6 HTML line break tag followed by HTML code tag containing sample code followed by 2 HTML line break tags.
							__(
								'It\'s important to prepare images before uloading them to the website. This should be 
									done before the Image is uploaded and can be done by using some image optimization tool 
									like %1$s %2$sUsing srcset: %3$sThe srcset HTML tag provides the browser with 
									variations of an image (including a fallback image) and instructs the browser to use 
									specific images depending on the situation. %4$sEssentially, you create various sizes 
									of your image, and then utilize the srcset tag to define when the images get served. 
									This is useful for responsive design when you have multiple images to deliver across 
									several devices and dimensions. %5$sFor example, let\'s say you want to send a 
									high-resolution image to only those users that have high-resolution screens, as 
									determined by the Device pixel ratio (DPR). The code would look like this: %6$sUse 
									image optimization plugin.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( 'https://helpx.adobe.com/photoshop-elements/using/optimizing-images-jpeg-format.html' ) . '" target="_blank">' . esc_html__( 'photoshop', 'w3-total-cache' ) . '</a>',
							'<br/><br/>',
							'<br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><code>' . esc_html( '<img srcset="large.jpg 2x, small.jpg 1x" src="small.jpg" alt="' . esc_attr__( 'A single image', 'w3-total-cache' ) . '">' ) . '</code><br/><br/>',
						),
						array(
							'a'    => array(
								'href'   => array(),
								'target' => array(),
							),
							'br'   => array(),
							'img'  => array(
								'srcset' => array(),
								'src'    => array(),
								'alt'    => array(),
							),
							'code' => array(),
						)
					),
				),
				'offscreen-images'          => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'offscreen-images', 'details', 'items' ) ),
					'instruction'  => __(
						'Enable lazy load for images.',
						'w3-total-cache'
					),
				),
				'uses-optimized-images'     => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-optimized-images', 'details', 'items' ) ),
					'instruction'  => __(
						'Use W3TC Image Service to convert images to WebP.',
						'w3-total-cache'
					),
				),
				'modern-image-formats'      => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'modern-image-formats', 'details', 'items' ) ),
					'instruction'  => __(
						'Use W3TC Image Service to convert images to WebP.',
						'w3-total-cache'
					),
				),
				'uses-text-compression'     => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-text-compression', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 HTML a tag to github.com for php-ext-brotli.
							__(
								'Use W3 Total Cache Browser Caching - Peformance>Browser Cache - Enable Gzip compression 
									or Brotli compression (Gzip compression is most common anf for Brotli compression you 
									need to install %1$s on your server )',
								'w3-total-cache'
							),
							'<a href="' . esc_url( 'https://github.com/kjdev/php-ext-brotli' ) . '" target="_blank">' . esc_html__( 'Brotli extension', 'w3-total-cache' ) . '</a>'
						),
						array(
							'a'  => array(
								'href'  => array(),
								'taget' => array(),
							),
						)
					),
				),
				'uses-rel-preconnect'       => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preconnect', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags followed by opening HTML ol tag followed by opening HTML li tag,
							// translators: 2 HTML line break tag followed by an opening HTML code tag containing "link" example followed by HTML line break tag,
							// translators: 3 closing HTML li tag followed by opening HTML li tag,
							// translators: 4 HTML line break tag followed by an optning HTML code tag containing "link" example followed by HTML line break tag.
							__(
								'Look at the list of third-party resources flagged by Google Page speed and add preconnect 
									or dns-prefetch to their link tags depending on whether the resource is critical or not 
									%1$sAdd preconnect for critical third-party domains. Out of the list of third-party 
									resources flagged by Google Page speed, identify the critical third-party resources and 
									add the following code to the link tag: %2$s where "https://third-party-example.com" is 
									the critical third-party domain your page intends to connect to. %3$sAdd dns-prefetch for 
									all other third-party domains. For all other third-party scripts, including non-critical 
									ones, add the following code to the link tag: %4$swhere "https://third-party-example.com" 
									is the domain of the respective third-party resource.',
								'w3-total-cache'
							),
							'<br/><br/><ol><li>',
							'<br/><code>' . esc_html( '<link rel="preconnect" href="' . esc_url( 'https://third-party-example.com', 'w3-total-cache' ) . '">' ) . '</code><br>',
							'</li><li>',
							'<br/><code>' . esc_html( '<link rel="dns-prefetch" href="' . esc_url( 'https://third-party-example.com', 'w3-total-cache' ) . '">' ) . '</code><br>'
						),
						array(
							'br'   => array(),
							'ol'   => array(),
							'li'   => array(),
							'code' => array(),
							'link' => array(
								'rel'  => array(),
								'href' => array(),
							),
						)
					),
				),
				'server-response-time'      => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'server-response-time', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => __(
						'W3 Total Cache Page Caching (fastest module)',
						'w3-total-cache'
					),
				),
				'redirects'                 => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'redirects', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 two HTML line break tags, 3 two HTML line break tags,
							// translators: 4 two HTML line break tags, 5 two HTML line break tags.
							__(
								'When dealing with server-side redirects, we recommend that they be executed via web server 
									configuration as they are often faster than application-level configuration. %1$sAvoid client-side 
									redirects, as much as possible, as they are slower, non-cacheable and may not be supported by 
									browsers by default. %2$sWherever possible, avoid landing page redirects; especially, the practice 
									of executing separate, individual redirects for reasons such as protocol change, adding www, 
									mobile-specific page, geo-location, and subdomain. %3$sAlways redirect to the preferred version of 
									the URL, especially, when redirects are dynamically generated. This helps eliminate unnecessary 
									redirects. %4$sSimilarly, remove temporary redirects if not needed anymore. %5$sRemember that 
									combining multiple redirects into a single redirect is the most effective way to improve web 
									performance.',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
						),
						array(
							'br' => array(),
						)
					),
				),
				'uses-rel-preload'          => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-rel-preload', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 HTML code tag containing sample link.
							__(
								'JS and CSS - Use HTTP2/Push for W3TC Minified files %1$sPreload fonts hosted on the server: %2$s',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<code>' . esc_html( '<link rel="preload" href="fontname" as="font" type="font/format" crossorigin>' ) . '</code>'
						),
						array(
							'br'   => array(),
							'code' => array(),
						)
					),
				),
				'efficient-animated-content' => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'efficient-animated-content', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
					'instruction'  => __(
						'Use W3TC Image Service to convert images to WebP.',
						'w3-total-cache'
					),
				),
				'duplicated-javascript'        => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'duplicated-javascript', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML br tags, 2 HTML a tag to github.com zillow webpack-stats-duplicates.
							__(
								'Incorporate good site building practices into your development workflow to ensure you avoid 
									duplication of JavaScript modules in the first place. %1$sTo fix this audit, use a tool like 
									%2$s to identify duplicate modules.',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<a href="' . esc_url( 'https://github.com/zillow/webpack-stats-duplicates' ) . '" target="_blank">' . esc_html__( 'webpack-stats-duplicates', 'w3-total-cache' ) . '</a>'
						),
						array(
							'br' => array(),
							'a'  => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
				),
				'legacy-javascript'            => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'legacy-javascript', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags,
							// translators: 2 HTML code tag containing example script tag,
							// translators: 3 HTML code tag containing sample script tag,
							// translators: 4 two HTML line break tags,
							// translators: 5 HTML code tag containing example script tag,
							// translators: 6 two HTML line break tags,
							// translators: 7 HTML code tag containing example script tag,
							// translators: 8 two HTML line break tags,
							// translators: 9 HTML a tag to philipwalton.com article on technique.
							__(
								'One way to deal with this issue is to load polyfills, only when needed, which can provide 
									feature-detection support at JavaScript runtime. However, it is often very difficult to implement 
									in practice. %1$sImplement modern feature-detection using %2$s and %3$s. %4$sEvery browser that 
									supports %5$s also supports most of the ES6 features. This lets you load regular 
									JavaScript files with ES6 features, knowing that the browser can handle it. %6$sFor browsers that 
									don\'t support %7$s you can use the translated ES5 code instead. In this manner, 
									you are always serving modern code to modern browsers. %8$sLearn more about implementing this 
									technique %9$s.',
								'w3-total-cache'
							),
							'<br/><br>',
							'<code>' . esc_html( '<script type="module">' ) . '</code>',
							'<code>' . esc_html( '<script nomodule>' ) . '</code>',
							'<br/><br/>',
							'<code>' . esc_html( '<script type="module">' ) . '</code>',
							'<br/><br/>',
							'<code>' . esc_html( '<script type="module">' ) . '</code>',
							'<br/><br/>',
							'<a href="' . esc_url( 'https://philipwalton.com/articles/deploying-es2015-code-in-production-today/' ) . '" target="_blank">here</a>'
						),
						array(
							'br'   => array(),
							'code' => array(),
							'a'    => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
				),
				'preload-lcp-image'            => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'preload-lcp-image', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
					'instruction'  => __(
						'Enable lazy load for images.',
						'w3-total-cache'
					),
				),
				'total-byte-weight'            => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'total-byte-weight', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 two HTML line break tags, 3 two HTML line break tags.
							__(
								'Deffer or async the JS (Select  Non blocking using Defer or  Non blocking using async Embed 
									method in W3 Total Cache Minify options before head and after body) %1$sCompress your HTML, 
									CSS, and JavaScript files and minify your CSS and JavaScript to ensure your text-based resources 
									are as small as they can be. W3 Total Cache Minify JS and CSS %2$sOptimize your image delivery 
									by sizing them properly and compressing them for smaller sizes. Use Webp conversion in W3TC 
									%3$sUse Browser Caching for static files and HTML  - 1 year for static files 1 hor for html',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>'
						),
						array(
							'br' => array(),
						)
					),
				),
				'dom-size'                     => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'dom-size', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 opening HTML ol tag followed by opening HTML li tag,
							// translators: 2 closing HTML li tag followed by opening HTML li tag, 3 closing HTML li tag followed by opening HTML li tag,
							// translators: 4 closing HTML li tag followed by opening HTML li tag, 5 closing HTML li tag followed by closing HTML ol tag.
							__(
								'Without completely redesigning your web page from scratch, typically you cannot resolve this 
									warning.  Understand that this warning is significant and if you get it for more than one or 
									two pages in your site, you should consider: %1$sReducing the amount of widgets / sections within 
									your web pages or page layouts %2$sUsing a simpler web page builder as many page builders add 
									a lot of code bloat %3$sUsing a different theme %4$sUsing a different slider%5$s',
								'w3-total-cache'
							),
							'<ol><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li></ol>'
						),
						array(
							'ol' => array(),
							'li' => array(),
						)
					),
				),
				'user-timings'                 => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'user-timings', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 HTML a tag to developer.mozilla.org User_Timing_API page,
							// translators: 2 HTML a tag to developer.chrome.com evaluate-performance devtool.
							__(
								'The %1$s gives you a way to measure your app\'s JavaScript performance. You do that 
									by inserting API calls in your JavaScript and then extracting detailed timing data that you 
									can use to optimize your code. You can access those data from JavaScript using the API or by 
									viewing them on your %2$s.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( 'https://developer.mozilla.org/docs/Web/API/User_Timing_API' ) . '" target="_blank">' . esc_html__( 'User Timing API', 'w3-total-cache' ) . '</a>',
							'<a href="' . esc_url( 'https://developer.chrome.com/docs/devtools/evaluate-performance/reference/' ) . '" target="_blank">' . esc_html__( 'User Timing API', 'w3-total-cache' ) . '</a>'
						),
						array(
							'a'  => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
				),
				'bootup-time'                  => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'bootup-time', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 opening HTML a tag to W3TC Minify admin page,
							// translators: 2 closing HTML a tag, 3 opening HTML acronym tag, 4 closing HTML acronym tag,
							// translators: 5 opening HTML acronym tag, 6 closing HTML acronym tag, 7 opening HTML acronym tag,
							// translators: 8 closing HTML acronym tag, 9 opening HTML a tag to FAQ page on api.w3-edge.com,
							// translators: 10 opening HTML acronym tag, 11 closing HTML acronym tag, 12 closing HTML acronym tag.
							__(
								'On the %1$sMinify%2$s tab all of the recommended settings are preset. Use the help button to 
									simplify discovery of your %3$sCSS%4$s and %5$sJS%6$s files and groups. Pay close attention to 
									the method and location of your %7$sJS%8$s group embeddings. See the plugin\'s %9$s%10$sFAQ%11$s%12$s 
									for more information on usage.',
								'w3-total-cache',
							),
							'<a href="admin.php?page=w3tc_minify#js" alt="' . __( 'Minify', 'w3-total-cache' ) . '" target="_blank">',
							'</a>',
							'<acronym title="' . __( 'Cascading Style Sheet', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . __( 'JavaScript', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<a href="https://api.w3-edge.com/v1/redirects/faq/usage" alt="' . __( 'Frequently Asked Questions', 'w3-total-cache' ) . '" target="_blank">',
							'<acronym title="' . __( 'Frequently Asked Questions', 'w3-total-cache' ) . '">',
							'</acronym>',
							'</a>'
						),
						array(
							'a'       => array(
								'href'   => array(),
								'target' => array(),
								'alt'    => array(),
							),
							'acronym' => array(
								'title' => array(),
							),
						)
					),
				),
				'mainthread-work-breakdown'    => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'mainthread-work-breakdown', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags,
							// translators: 2 two HTML line break tags followed by HTML a tag to web.dev for debouncing input handlers followed by two HTML line break tags
							// translators: 3 HTML line break tag, 4 two HTML line break tags.
							// translators: 5 two HTML line break tags, 6 two HTML line break tags, 7 two HTML line break tags.
							// translators: 8 two HTML line break tags, 9 two HTML line break tags,
							// translators: 10 two HTML line break tags followed by HTML a tag to developers.google.com for using only compositor properties followed by two HTML line break tags.
							// translators: 11 two HTML line break tags.
							__(
								'Optimizing third-party JavaScript %1$sReview your website\'s third-party code and remove the ones 
									that aren\'t adding any value to your website. %2$sDebouncing your input handlers %3$sAvoid using 
									long-running input handlers (which may block scrolling) and do not make style changes in input 
									handlers (which is likely to cause repainting of pixels). %4$sDebouncing your input handlers helps 
									solve both of the above problems. %5$sDelay 3rd-party JS %6$sReducing JavaScript execution time 
									%7$sReduce your JavaScript payload by implementing code splitting, minifying and compressing your 
									JavaScript code, removing unused code, and following the PRPL pattern. (Use W3TC Minify for JS and 
									compression.) Use HTTP2 Push if available on server Use CDN %8$sReducing CSS parsing time %9$sReduce 
									the time spent parsing CSS by minifying, or deferring non-critical CSS, or removing unused CSS. 
									(Use W3TC Minify for JS and compression.) Use HTTP2 Push if available on server Use CDN %10$sOnly 
									using compositor properties %11$sStick to using compositor properties to keep events away from the 
									main-thread. Compositor properties are run on a separate compositor thread, freeing the main-thread 
									for longer and improving your page load performance.',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/><a href="' . esc_url( 'https://web.dev/debounce-your-input-handlers/' ) . '" target="_blank">' . esc_html__( 'Debouncing your input handlers', 'w3-total-cache' ) . '</a>',
							'<br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/><a href="' . esc_url( 'https://developers.google.com/web/fundamentals/performance/rendering/stick-to-compositor-only-properties-and-manage-layer-count' ) . '" target="_blank">' . esc_html__( 'Only using compositor properties:', 'w3-total-cache' ) . '</a>',
							'<br/><br/>'
						),
						array(
							'br' => array(),
							'a'  => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
				),
				'third-party-summary'          => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-summary', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 opening HTML ol tag followed by closing HTML li tag, 2 opening HTML li tag followed by closing HTML li tag,
							// translators: 3 opening HTML li tag followed by closing HTML li tag, 4 opening HTML li tag followed by closing HTML li tag,
							// translators: 5 opening HTML li tag followed by closing HTML li tag, 6 opening HTML li tag followed by closing HTML li tag,
							// translators: 7 opening HTML li tag followed by closing HTML li tag, 8 opening HTML li tag followed by closing HTML li tag,
							// translators: 9 opening HTML li tag followed by closing HTML li tag, 10 opening HTML li tag followed by closing HTML li tag,
							// translators: 11 opening HTML li tag followed by closing HTML li tag, 12 opening HTML li tag followed by closing HTML li tag,
							// translators: 13 opening HTML li tag followed by closing HTML li tag, 14 opening HTML li tag followed by closing HTML li tag,
							// translators: 15 closing HTML li tag followed by closing HTML ol tag.
							__(
								'%1$sFind Slow Third-Party-Code
									%2$sLazy Load YouTube Videos
									%3$sHost Google Fonts Locally
									%4$sHost Google Analytics Locally
									%5$sHost Facebook Pixel Locally
									%6$sHost Gravatars Locally
									%7$sDelay Third-Party JavaScript
									%8$sDefer Parsing Of JavaScript
									%9$sPrefetch Or Preconnect Third-Party Scripts
									%10$sAvoid Google AdSense And Maps
									%11$sDon\'t Overtrack In Google Tag Manager
									%12$sReplace Embeds With Screenshots
									%13$sUse A Lightweight Social Sharing Plugin
									%14$sUse Cloudflare Workers%15$s',
								'w3-total-cache'
							),
							'<ol><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li></ol>',
						),
						array(
							'ol' => array(),
							'li' => array(),
						)
					),
				),
				'third-party-facades'          => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'third-party-facades', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => __(
						'Preload - Lazyload embeded videos ',
						'w3-total-cache'
					),
				),
				'lcp-lazy-loaded'              => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'lcp-lazy-loaded', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags.
							__(
								'Don\'t lazy load images that appear "above the fold" just use a standard <img> or <picture> 
									element. %1$sExclude the image from being lazy-loaded if the W3TC Lazy load is enabled in 
									Performance>User Experience>Exclude words',
								'w3-total-cache'
							),
							'<br/><br/>'
						),
						array(
							'br'      => array(),
						)
					),
				),
				'uses-passive-event-listeners' => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-passive-event-listeners', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags,
							// translators: 2 HTML line break tag followed by HTML code tag containing JS example followed by two HTML line break tags.
							__(
								'Add a passive flag to every event listener that Lighthouse identified %1$sIf you\'re only 
									supporting browsers that have passive event listener support, just add the flag. For example: 
									%2$sIf you\'re supporting older browsers that don\'t support passive event listeners, you\'ll 
									need to use feature detection or a polyfill. See the Feature Detection section of the WICG 
									Passive event listeners explainer document for more information.',
								'w3-total-cache'
							),
							'<br/><br>',
							'<br/><code>' . esc_html( 'document.addEventListener("touchstart", onTouchStart, {passive: true});' ) . '</code><br/><br/>'
						),
						array(
							'br'   => array(),
							'code' => array(),
						)
					),
				),
				'no-document-write'            => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'no-document-write', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 two HTML line break tags.
							__(
								'You can fix this audit by preferably eliminating document.write() altogether, wherever 
									possible %1$sAvoiding the use of document.write() should ideally be built into your development 
									workflow so that your production website is optimized for web performance from the beginning 
									%2$sUseing W3TC JS Minify and deffering or using async may help',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/>'
						),
						array(
							'br' => array(),
						)
					),
				),
				'non-composited-animations'    => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'non-composited-animations', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 HTML a tag to developers.google.com for using only compositor properties followed by two HTML line break tags.
							__(
								'%1$sStick to using compositor properties to keep events away from the main-thread. Compositor 
									properties are run on a separate compositor thread, freeing the main-thread for longer and 
									improving your page load performance.',
								'w3-total-cache'
							),
							'<a href="' . esc_url( 'https://developers.google.com/web/fundamentals/performance/rendering/stick-to-compositor-only-properties-and-manage-layer-count' ) . '" target="_blank">' . esc_html__( 'Only using compositor properties:', 'w3-total-cache' ) . '</a><br/><br/>'
						),
						array(
							'a' => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
				),
				'unsized-images'               => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'unsized-images', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags,
							// translators: 2 HTML line break tag followed by HTML code tag containing example image followed by HTML line break tag,
							// translators: 3 two HTML line break tags.
							__(
								'To fix this audit, simply specify, both, the width and height for your webpage\'s image and video 
									elements. This ensures that the correct spacing is used for images and videos. %1$sFor example: 
									%2$swhere width and height have been declared as 640 and 360 pixels respectively. %3$sNote that 
									modern browsers automatically calculate the aspect ratio for an image/video based on the declared 
									width and height attributes.',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><code>' . esc_html( '<img src="image.jpg" width="640" height="360" alt="image">' ) . '</code><br/>',
							'<br/><br/>'
						),
						array(
							'br'   => array(),
							'code' => array(),
						),
					),
				),
				'viewport'                     => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'viewport', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 HTML code tag containing example meta tag,
							// translators: 2 HTML line break tag followed by HTML code tag containing example meta tag followed by HTML line break tag,
							// translators: 3 HTML a tag to developer.mozilla.org documentation on Viewport_meta_tag.
							__(
								'Use the "viewport" %1$s tag to control the viewport\'s size and shape form mobile friendly 
									website %2$sMore details %3$s',
								'w3-total-cache'
							),
							'<code>' . esc_html( '<meta>' ) . '</code>',
							'<br/><code>' . esc_html( '<meta name="viewport" content="width=device-width, initial-scale=1">' ) . '</code><br/>',
							'<a href="' . esc_url( 'https://developer.mozilla.org/en-US/docs/Web/HTML/Viewport_meta_tag' ) . '" target="_blank">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
						),
						array(
							'br'   => array(),
							'code' => array(),
							'a'    => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
				),
			),
			'diagnostics'              => array(
				'font-display'                     => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'font-display', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags,
							// translators: 2 HTML line break tag followed by HTML code tag containing sample link tag followed by two HTML line break tags,
							// translators: 3 HTML line break tag.
							__(
								'It\'s advisable to host the fonts on the server instead of using Google CDN %1$sPreload fonts 
									with a plugin or manually %2$sUse font-display atribute: %3$sThe font-display attribute determines 
									how the font is displayed during your page load, based on whether it has been downloaded and is 
									ready for use. It takes the following values:',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><code>' . esc_html( '<link rel="preload" href="/webfontname" as="font" type="font/format" crossorigin>' ) . '</code><br/><br/>',
							'<br/>'
						),
						array(
							'br'   => array(),
							'code' => array(),
						)
					),
				),
				'first-contentful-paint-3g'        => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'first-contentful-paint-3g', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags, 2 two HTML line break tags, 3 two HTML line break tags,
							// translators: 4 two HTML line break tags, 5 two HTML line break tags, 6 two HTML line break tags.
							__(
								'Enable Page Cache using the fastest engine. %1$sWhat it represents: How much is visible at a 
									time during load. %2$sLighthouse Performance score weighting: 10%%%3$sWhat it measures: The Speed 
									Index is the average time at which visible parts of the page are displayed. %4$sHow it\'s measured: 
									Lighthouse\'s Speed Index measurement comes from a node module called Speedline. %5$sIn order for 
									content to be displayed to the user, the browser must first download, parse, and process all external 
									stylesheets it encounters before it can display or render any content to a user\'s screen. %6$sThe 
									fastest way to bypass the delay of external resources is to use in-line styles for above-the-fold content.',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>',
							'<br/><br/>'
						),
						array(
							'br' => array(),
						)
					),
				),
				'uses-long-cache-ttl'              => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'uses-long-cache-ttl', 'details', 'items' ) ),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags.
							__(
								'Use Browser Caching in W3 Total Cache and set the Expires header and cache control header for 
									static files and HTML%1$sUse default values for best results',
								'w3-total-cache'
							),
							'<br/><br/>'
						),
						array(
							'br' => array(),
						)
					),
				),
				'critical-request-chains'          => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'critical-request-chains', 'details', 'items' ) ),
					'type'         => array(
						'FCP',
						'LCP',
					),
					'instruction'  => __(
						'Eliminate Render Blocking CSS and apply asynchronous loading where applicable. Additionally, 
							image optimization by way of resizing, lazy loaidng, and webp conversion can impact this metric as well.',
						'w3-total-cache',
					),
				),
				'resource-summary'                 => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'resource-summary', 'details', 'items' ) ),
					'instruction'  => __(
						'TBD',
						'w3-total-cache'
					),
				),
				'largest-contentful-paint-element' => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'largest-contentful-paint-element', 'details', 'items' ) ),
					'type'         => array(
						'LCP',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags,
							// translators: 2 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 3 closing HTML li tag followed by opening HTML li tag, 4 closing HTML li tag followed by opening HTML li tag,
							// translators: 5 closing HTML li tag followed by opening HTML li tag, 6 closing HTML li tag followed by opening HTML li tag,
							// translators: 7 closing HTML li tag followed by closing HTML ul tag followed by two HTML line break tags,
							// translators: 8 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 9 closing HTML li tag followed by opening HTML li tag, 10 closing HTML li tag followed by opening HTML li tag,
							// translators: 11 closing HTML li tag followed by opening HTML li tag, 12 closing HTML li tag followed by opening HTML li tag,
							// translators: 13 closing HTML li tag followed by opening HTML li tag,
							// translators: 14 closing HTML li tag followed by closing HTML ul tag followed by two HTML line break tags,
							// translators: 15 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 16 closing HTML li tag followed by opening HTML li tag, 17 closing HTML li tag followed by opening HTML li tag,
							// translators: 18 closing HTML li tag followed by opening HTML li tag, 19 closing HTML li tag followed by opening HTML li tag,
							// translators: 20 closing HTML li tag followed by closing HTML ul tag followed by two HTML line break tags,
							// translators: 21 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 22 closing HTML li tag followed by opening HTML li tag, 23 closing HTML li tag followed by closing HTML ul tag.
							__(
								'How To Fix Poor LCP
									%1$sIf the cause is slow server response time:
									%2$sOptimize your server.
									%3$sRoute users to a nearby CDN. (W3TC CDN setup)
									%4$sCache assets. (W3TC Page Caching, Minify)
									%5$sServe HTML pages cache-first.  (W3TC Page Caching, )
									%6$sEstablish third-party connections early.

									%7$sIf the cause is render-blocking JavaScript and CSS:
									%9$sMinify CSS.
									%9$sDefer non-critical CSS.
									%10$sInline critical CSS.
									%11$sMinify and compress JavaScript files.
									%12$sDefer unused JavaScript.
									%13$sMinimize unused polyfills.
								
									%14$sIf the cause is resource load times:
									%15$sOptimize and compress images.
									%16$sPreload important resources.
									%17$sCompress text files.
									%18$sDeliver different assets based on the network connection (adaptive serving).
									%19$sCache assets using a service worker.
								
									%20$sIf the cause is client-side rendering:
									%21$sMinimize critical JavaScript.
									%22$sUse another rendering strategy.%23$s',
								'w3-total-cache'
							),
							'<br/><br/>',
							'<br/><ul><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li></ul><br/><br/>',
							'<br/><ul><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li></ul><br/><br/>',
							'<br/><ul><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li></ul><br/><br/>',
							'<br/><ul><li>',
							'</li><li>',
							'</li></ul>'
						),
						array(
							'br' => array(),
							'ul' => array(),
							'li' => array(),
						)
					),
				),
				'layout-shift-elements'            => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'layout-shift-elements', 'details', 'items' ) ),
					'type'         => array(
						'CLS',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 2 closing HTML li tag followed by opening HTML li tag, 3 closing HTML li tag followed by opening HTML li tag,
							// translators: 4 closing HTML li tag followed by opening HTML li tag, 5 closing HTML li tag followed by closing HTML ul tag.
							__(
								'Without completely redesigning your web page from scratch, typically you cannot resolve this 
									warning.  Understand that this warning is significant and if you get it for more than one or two 
									pages in your site, you should consider:
									%1$sReducing the amount of widgets / sections within your web pages or page layouts
									%2$sUsing a simpler web page builder as many page builders add a lot of code bloat
									%3$sUsing a different theme
									%4$sUsing a different slider%5$s',
								'w3-total-cache'
							),
							'<br/><br/><ul><li>',
							'</li><li>',
							'</li><li>',
							'</li><li>',
							'</li></ul>'
						),
						array(
							'br' => array(),
							'ul' => array(),
							'li' => array(),
						)
					),
				),
				'long-tasks'                       => array(
					'title'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'title' ) ),
					'description'  => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'description' ) ),
					'score'        => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'score' ) ),
					'displayValue' => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'displayValue' ) ),
					'details'      => $this->v( $data, array( 'lighthouseResult', 'audits', 'long-tasks', 'details', 'items' ) ),
					'type'         => array(
						'TBT',
					),
					'instruction'  => wp_kses(
						sprintf(
							// translators: 1 two HTML line break tags followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 2 closing HTML li tag followed by closing HTML ul tag followed by HTML line break tag,
							// translators: 3 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 4 closing HTML li tag followed by closing HTML ul tag followed by HTML line break tag,
							// translators: 5 two HTML line break tags,
							// translators: 6 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 7 closing HTML li tag followed by opening HTML li tag,
							// translators: 8 closing HTML li tag followed by closing HTML ul tag followed by HTML line break tag,
							// translators: 9 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 10 closing HTML li tag followed by closing HTML ul tag followed by HTML line break tag,
							// translators: 11 HTML line break tag followed by opening HTML ul tag followed by opening HTML li tag,
							// translators: 12 closing HTML li tag followed by closing HTML ul tag.
							__(
								'Optimizing third-party JavaScript
									%1$sReview your website\'s third-party code and remove the ones that aren\'t adding any value to your website.
								 
									%2$sDebouncing your input handlers
									%3$sAvoid using long-running input handlers (which may block scrolling) and do not make style changes in input handlers (which is likely to cause repainting of pixels).
								
									%4$sDebouncing your input handlers helps solve both of the above problems. 
								
									%5$sDelay 3rd-party JS 
									%6$sReducing JavaScript execution time
									%7$sReduce your JavaScript payload by implementing code splitting, minifying and compressing your JavaScript code, removing unused code, and following the PRPL pattern. (Use W3TC Minify for JS and compression.) Use HTTP2 Push if available on server Use CDN
								
									%8$sReducing CSS parsing time
									%9$sReduce the time spent parsing CSS by minifying, or deferring non-critical CSS, or removing unused CSS. (Use W3TC Minify for JS and compression.) Use HTTP2 Push if available on server Use CDN
								
									%10$sOnly using compositor properties
									%11$sStick to using compositor properties to keep events away from the main-thread. Compositor properties are run on a separate compositor thread, freeing the main-thread for longer and improving your page load performance.%12$s',
								'w3-total-cache'
							),
							'<br/><br/><ul><li>',
							'</li></ul><br/>',
							'<br/><ul><li>',
							'</li></ul><br/>',
							'<br/><br/>',
							'<br/><ul><li>',
							'</li><li>',
							'</li></ul><br/>',
							'<br/><ul><li>',
							'</li></ul><br/>',
							'<br/><ul><li>',
							'</li></ul>'
						),
						array(
							'br' => array(),
							'ul' => array(),
							'li' => array(),
						)
					),
				),
			),
		);
	}

	/**
	 * Get general score for URL
	 *
	 * @param string $url URL to analyze.
	 *
	 * @return string | null
	 */
	public function get_page_score( $url ) {
		$data = array();
		try {
			$data = $this->analyze( $url );
		} catch ( \Exception $e ) {
			return array(
				'error' => array(
					'code'    => 500,
					'message' => $e->getMessage(),
				),
			);
		}

		$page_score = '';
		if ( ! empty( $this->v( $data, array( 'desktop', 'score' ) ) ) ) {
			$page_score .= '<i class="material-icons" aria-hidden="true">computer</i> ' . $this->v( $data, array( 'desktop', 'score' ) );
		}
		if ( ! empty( $this->v( $data, array( 'mobile', 'score' ) ) ) ) {
			$page_score .= '<i class="material-icons" aria-hidden="true">smartphone</i> ' . $this->v( $data, array( 'mobile', 'score' ) );
		}

		return array( 'score' => $page_score );
	}

	/**
	 * Make API request
	 *
	 * @param string $query API request query.
	 *
	 * @return string | false
	 */
	public function _request( $query ) {
		if ( empty( $this->client->getAccessToken() ) ) {
			return array(
				'error' => array(
					'code'    => 403,
					'message' => __( 'Missing Google access token.', 'w3-total-cache' )
				),
			);
		} elseif ( ! empty( $this->client->getRefreshToken() ) && $this->client->isAccessTokenExpired() ) {
			$response = json_decode( $this->refresh_token() );
			if ( is_wp_error( $response ) ) {
				return array(
					'error' => array(
						'message' => $response->get_error_message()
					),
				);
			} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
				return array(
					'error' => array(
						'code'    => $response['response']['code'],
						'message' => $response['response']['message']
					),
				);
			}
		}

		$access_token = json_decode( $this->client->getAccessToken() );

		$request = Util_Environment::url_format(
			W3TC_PAGESPEED_API_URL,
			array_merge(
				$query,
				array(
					'quotaUser'    => Util_Http::generate_site_id(),
					'access_token' => $access_token->access_token,
				)
			)
		);

		// Attempt the request up to 4 times with an increasing delay between each attempt
		$attempts = 0;
		while ( ++$attempts ) {
			try {
				$response = wp_remote_get(
					$request,
					array(
						'timeout' => 60,
					)
				);
				break;
			} catch ( \Exception $e ) {
				if ( $attempts <= 4 ) {
					sleep( $attempts * 2 );
				} else {
					return array(
						'error' => array(
							'code'    => 500,
							'message' => $e->getMessage(),
						),
					);
				}
			}
		};

		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response['response']['code'],
						'message' => $response['response']['message'],
					),
				)
			);
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Recursively get value series of key decendents
	 *
	 * @param array $data PageSpeed data.
	 * @param array $elements Elements to get values of.
	 *
	 * @return object
	 */
	public function v( $data, $elements ) {
		if ( empty( $elements ) ) {
			return $data;
		}

		$key = array_shift( $elements );
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return $this->v( $data[ $key ], $elements );
	}

	/**
	 * Recursively get value series of key decendents
	 *
	 * @param array $data PageSpeed data.
	 * @param array $elements Elements to get values of.
	 *
	 * @return object
	 */
	public function handle_( $data, $elements ) {
		if ( empty( $elements ) ) {
			return $data;
		}

		$key = array_shift( $elements );
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return $this->v( $data[ $key ], $elements );
	}

	/**
	 * Checks if the Google access token is expired and attempts to refresh.
	 *
	 * @return string | void
	 */
	public function refresh_token_check() {
		if ( $this->client->isAccessTokenExpired() && ! empty( $this->config->get_string( 'widget.pagespeed.w3key' ) ) ) {
			$this->refresh_token();
		}
		return;
	}

	/**
	 * Refreshes the Google access token if a valid refresh token is defined.
	 *
	 * @return string | null
	 */
	public function refresh_token() {
		$initial_refresh_token = $this->client->getRefreshToken();
		if ( empty( $initial_refresh_token ) ) {
			$initial_refresh_token_json = $this->get_refresh_token( Util_Http::generate_site_id(), $this->config->get_string( 'widget.pagespeed.w3key' ) );
			$initial_refresh_token      = json_decode( $initial_refresh_token_json );
			if ( ! empty( $initial_refresh_token->error ) ) {
				return wp_json_encode(
					array(
						'error' => sprintf(
							// translators: 1 Refresh URL value, 2 Request response code, 3 Error message.
							__(
								'API request error<br/><br/>
									Refresh URL: %1$s<br/><br/>
									Response Code: %2$s<br/>
									Response Message: %3$s<br/>',
								'w3-total-cache'
							),
							W3TC_API_GPS_GET_TOKEN_URL . '/' . Util_Http::generate_site_id() . '/' . $this->config->get_string( 'widget.pagespeed.w3key' ),
							! empty( $initial_refresh_token->error->code ) ? $initial_refresh_token->error->code : 'N/A',
							! empty( $initial_refresh_token->error->message ) ? $initial_refresh_token->error->message : 'N/A'
						),
					)
				);
			}
		}

		try {
			$this->client->refreshToken( $initial_refresh_token->refresh_token );
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}
		
		$new_access_token = json_decode( $this->client->getAccessToken() );

		if ( ! empty( $new_access_token->refresh_token ) && $new_access_token->refresh_token !== $initial_refresh_token->refresh_token ) {
			$new_refresh_token = $new_access_token->refresh_token;
			unset( $new_access_token->refresh_token );

			$request = Util_Environment::url_format(
				W3TC_API_GPS_UPDATE_TOKEN_URL,
				array(
					'site_id'       => Util_Http::generate_site_id(),
					'w3key'         => $this->config->get_string( 'widget.pagespeed.w3key' ),
					'refresh_token' => $new_refresh_token,
				)
			);

			try {
				$response = wp_remote_get(
					$request,
					array(
						'timeout' => 60,
					)
				);
			} catch ( \Exception $e ) {
				return wp_json_encode(
					array(
						'error' => array(
							'code'    => 500,
							'message' => $e->getMessage(),
						),
					)
				);
			}

			if ( is_wp_error( $response ) ) {
				return wp_json_encode(
					array(
						'error' => array(
							'message' => $response->get_error_message(),
						),
					)
				);
			} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
				return wp_json_encode(
					array(
						'error' => array(
							'code'    => $response['response']['code'],
							'message' => $response['response']['message'],
						),
					)
				);
			}
		}

		$this->config->set( 'widget.pagespeed.access_token', wp_json_encode( $new_access_token ) );
		$this->config->save();

		return wp_json_encode( array( 'access_key' => $new_access_token ) );
	}

	/**
	 * Creates new Google access token from authorize request response.
	 *
	 * @param string gacode New Google access authentication code.
	 * @param string w3key  W3 API access key.
	 *
	 * @return string | null
	 */
	public function new_token( $gacode, $w3key ) {
		if ( empty( $gacode ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid Google access authentication code.', 'w3-total-cache' ),
					),
				)
			);
		} elseif ( empty( $w3key ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid W3 API key.', 'w3-total-cache' ),
					),
				)
			);
		}

		try {
			$this->client->authenticate( $gacode );
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}
		
		$access_token_json = $this->client->getAccessToken();
		
		if ( empty( $access_token_json ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid Google access token JSON setting after authentication.', 'w3-total-cache' ),
					),
				)
			);
		}

		$access_token = ( ! empty( $access_token_json ) ? json_decode( $access_token_json ) : '' );

		$request = Util_Environment::url_format(
			W3TC_API_GPS_UPDATE_TOKEN_URL,
			array(
				'site_id'       => Util_Http::generate_site_id(),
				'w3key'         => $w3key,
				'refresh_token' => $access_token->refresh_token,
			)
		);
		
		try {
			$response = wp_remote_get(
				$request,
				array(
					'timeout' => 60,
				)
			);
		} catch ( \Exception $e ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 500,
						'message' => $e->getMessage(),
					),
				)
			);
		}
		
		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response['response']['code'],
						'message' => $response['response']['message'],
					),
				)
			);
		}
		
		unset( $access_token->refresh_token );
		
		$this->config->set( 'widget.pagespeed.access_token', wp_json_encode( $access_token ) );
		$this->config->set( 'widget.pagespeed.w3key', $w3key );
		$this->config->save();
		
		return null;
	}

	/**
	 * fetches Google refresh token from W3 API server.
	 *
	 * @param string site_id W3 API access key.
	 * @param string w3key   W3 API access key.
	 *
	 * @return string | null
	 */
	public function get_refresh_token( $site_id, $w3key ) {
		if ( empty( $site_id ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid Site ID.', 'w3-total-cache' ),
					),
				)
			);
		} elseif ( empty( $w3key ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => 409,
						'message' => __( 'Missing/invalid W3 API key.', 'w3-total-cache' ),
					),
				)
			);
		}

		$request = W3TC_API_GPS_GET_TOKEN_URL . '/' . $site_id . '/' . $w3key;
		try {
			$response = wp_remote_get(
				$request,
				array(
					'timeout' => 60,
				)
			);
		} catch ( \Exception $e ) {
			return wp_json_encode(
			    array(
				    'error' => array(
				    	'code'    => 500,
				    	'message' => $e->getMessage(),
				    ),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return wp_json_encode(
				array(
					'error' => array(
						'message' => $response->get_error_message(),
					),
				)
			);
		} elseif ( isset( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
			return wp_json_encode(
				array(
					'error' => array(
						'code'    => $response['response']['code'],
						'message' => $response['response']['message'],
					),
				)
			);
		}
		
		return wp_remote_retrieve_body( $response );
	}
}
