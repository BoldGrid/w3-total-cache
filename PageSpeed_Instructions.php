<?php
/**
 * File: PageSpeed_Instructions.php
 *
 * Defines W3TC's recomendations for each PageSpeed metric.
 *
 * @since 2.3.0 Update to utilize OAuth2.0 and overhaul of feature.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * PageSpeed Instructions Config.
 *
 * @since 2.3.0
 */
class PageSpeed_Instructions {

	/**
	 * Get PageSpeed Instructions Config.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	public static function get_pagespeed_instructions() {
		$allowed_tags = Util_PageSpeed::get_allowed_tags();
		return array(
			'insights'    => array(
				'cache-insight'                   => array(
					'instructions' =>
						'<p>' . sprintf(
							// translators: 1 opening HTML a tag to Browswer Cache settings, 2 closing HTML a tag, 3 W3TC plugin name.
							esc_html__(
								'Use %1$sBrowser Caching%2$s in %3$s and set the Expires header and cache control header for static files and HTML.',
								'w3-total-cache'
							),
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#browser_cache' ) ) . '" alt="' . esc_attr__( 'Browser Cache', 'w3-total-cache' ) . '">',
							'</a>',
							'W3 Total Cache'
						) . '</p>
						<p>' . esc_html__( 'Use default values for best results', 'w3-total-cache' ) . '</p>',
				),
				'cls-culprits-insight'            => array(
					'instructions' =>
						'<p>' . esc_html__( 'Audit the elements identified in the CLS report and reserve their space with explicit width, height, or CSS aspect-ratio rules so the browser never guesses.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Avoid inserting new content above existing nodes and keep animations on transform or opacity properties so layout is not recalculated mid-frame.', 'w3-total-cache' ) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 opening HTML a tag to W3TC User Experience page, 2 closing HTML a tag.
								esc_html__(
									'Enable %1$sLazy Load%2$s for images and iframes, then use the Exclude Words or placeholder options so above-the-fold slots stay stable while offscreen media is deferred.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience' ) ) . '" alt="' . esc_attr__( 'Lazy Load', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' . esc_html__( 'Combine those structural fixes with predictable font loading (see the Font Display insight) to keep layout shifts under the target.', 'w3-total-cache' ) . '</p>',
				),
				'document-latency-insight'        => array(
					'instructions' =>
						'<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to Page Cache setting, 3 closing HTML a tag.
							esc_html__(
								'Serve the main HTML as quickly as possible by enabling %1$s %2$sPage Caching%3$s (fastest module).',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) . '" alt="' . esc_attr__( 'Page Cache', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to Browser Cache setting, 3 closing HTML a tag.
							esc_html__(
								'Enable gzip or Brotli compression in %1$s %2$sBrowser Cache%3$s so the initial document downloads faster.',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_browsercache' ) ) . '" alt="' . esc_attr__( 'Browser Cache', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p>' . esc_html__( 'Where possible, collapse redirect chains so visitors reach the cached HTML in a single round trip.', 'w3-total-cache' ) . '</p>',
				),
				'dom-size-insight'                => array(
					'instructions' =>
						'<p>' . esc_html__( 'Without completely redesigning your web page from scratch, typically you cannot resolve this warning. Understand that this warning is significant and if you get it for more than one or two pages in your site, you should consider:', 'w3-total-cache' ) . '</p>
						<ol>
							<li>' . esc_html__( 'Reducing the amount of widgets / sections within your web pages or page layouts', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Using a simpler web page builder as many page builders add a lot of code bloat', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Using a different theme', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Using a different slider', 'w3-total-cache' ) . '</li>
						</ol>',
				),
				'duplicated-javascript-insight'   => array(
					'instructions' =>
						'<p>' . esc_html__( 'Incorporate good site building practices into your development workflow to ensure you avoid duplication of JavaScript modules in the first place.', 'w3-total-cache' ) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 HTML a tag to Zillow Webpack-Stats-Duplicates.
								esc_html__(
									'To fix this audit, use a tool like %1$s to identify duplicate modules',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( 'https://github.com/zillow/webpack-stats-duplicates' ) . '">' . esc_html__( 'webpack-stats-duplicates', 'w3-total-cache' ) . '</a>'
							),
							$allowed_tags
						) . '</p>',
				),
				'font-display-insight'            => array(
					'instructions' =>
						'<p>' . esc_html__( 'It\'s advisable to host the fonts on the server instead of using Google CDN', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Preload fonts with a plugin or manually:', 'w3-total-cache' ) . '</p>
						<br/>
						<code>' . esc_html( '<link rel="preload" target="_blank" href="/webfontname" as="font" type="font/format" crossorigin>' ) . '</code>
						<br/>
						<p>' . esc_html__( 'Use font-display attribute: The font-display attribute determines how the font is displayed during your page load, based on whether it has been downloaded and is ready for use.', 'w3-total-cache' ) . '</p>',
				),
				'forced-reflow-insight'           => array(
					'instructions' =>
						'<p>' . esc_html__( 'Forced reflows happen when scripts read layout data immediately after mutating the DOM. Batch reads and writes, move animations to transform or opacity, and keep heavy logic out of scroll or resize handlers.', 'w3-total-cache' ) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 opening HTML a tag to Minify JS admin page, 2 closing HTML a tag, 3 opening HTML a tag to Defer JS settings, 4 closing HTML a tag.
								esc_html__(
									'Reduce render-blocking work with %1$sMinify%2$s and schedule non-critical scripts through the %3$sDefer JavaScript%4$s option (PRO) so they execute after the initial paint.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify JS', 'w3-total-cache' ) . '">',
								'</a>',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience#application' ) ) . '" alt="' . esc_attr__( 'Defer Scripts', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' . esc_html__( 'Split any remaining long tasks with requestAnimationFrame, requestIdleCallback, or Web Workers so user input stays responsive.', 'w3-total-cache' ) . '</p>',
				),
				'image-delivery-insight'          => array(
					'instructions' =>
						'<p>' . esc_html__( 'Deliver responsive images that match their rendered dimensions, use srcset or picture markup, and compress files before uploading so PageSpeed does not flag oversized assets.', 'w3-total-cache' ) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 W3TC plugin name, 2 opening HTML a tag to Image Service extension, 3 closing HTML a tag.
								esc_html__(
									'Use %1$s %2$sWebP Converter%3$s to automatically create next-gen versions of Media Library uploads and serve them when supported.',
									'w3-total-cache'
								),
								'W3 Total Cache',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ) ) . '" alt="' . esc_attr__( 'W3TC Extensions', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 opening HTML a tag to W3TC User Experience page, 2 closing HTML a tag.
								esc_html__(
									'Enable %1$sLazy Load%2$s to defer offscreen media and configure Exclude Words or placeholders so hero images keep their layout space.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience' ) ) . '" alt="' . esc_attr__( 'Lazy Load', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 opening HTML a tag to Browser Cache settings, 2 closing HTML a tag.
								esc_html__(
									'Pair those optimizations with %1$sBrowser Cache%2$s (and your CDN) so optimized assets stay cached close to visitors.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_browsercache' ) ) . '" alt="' . esc_attr__( 'Browser Cache', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>',
				),
				'inp-breakdown-insight'           => array(
					'instructions' =>
						'<p>' . esc_html__( 'INP highlights the slowest user interactions on the page, so reduce the main-thread work performed by the scripts listed in this breakdown.', 'w3-total-cache' ) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 opening HTML a tag to Page Cache setting, 2 closing HTML a tag, 3 opening HTML a tag to Minify setting, 4 closing HTML a tag.
								esc_html__(
									'Serve prerendered markup via %1$sPage Cache%2$s and trim script payloads with %3$sMinify%4$s so less JavaScript runs before each interaction.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) . '" alt="' . esc_attr__( 'Page Cache', 'w3-total-cache' ) . '">',
								'</a>',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 opening HTML a tag to W3TC User Experience page, 2 closing HTML a tag.
								esc_html__(
									'Delay analytics, marketing tags, or other non-critical handlers with %1$sDefer JavaScript and Lazy Load%2$s so taps and key presses stay under the 200&nbsp;ms target.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience' ) ) . '" alt="' . esc_attr__( 'User Experience', 'w3-total-cache' ) . '">',
								'</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' . esc_html__( 'Break any remaining long tasks into smaller chunks (requestIdleCallback, Web Workers, or scheduled timeouts) so the main thread is free when users interact.', 'w3-total-cache' ) . '</p>',
				),
				'lcp-breakdown-insight'           => array(
					'instructions' =>
						'<p>' . esc_html__( 'How To Fix Poor LCP', 'w3-total-cache' ) . '</p>
						<br/>
						<p>' . esc_html__( 'If the cause is slow server response time:', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Optimize your server.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Route users to a nearby CDN.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Cache assets.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Serve HTML pages cache-first.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Establish third-party connections early.', 'w3-total-cache' ) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'If the cause is render-blocking JavaScript and CSS:', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Minify CSS.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Defer non-critical CSS.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Inline critical CSS.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Minify and compress JavaScript files.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Defer unused JavaScript.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Minimize unused polyfills.', 'w3-total-cache' ) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'If the cause is resource load times:', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Optimize and compress images.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Preload important resources.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Disable "lazy loading" for assets immediately visible on page load.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Compress text files.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Deliver different assets based on the network connection (adaptive serving).', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Cache assets using a service worker.', 'w3-total-cache' ) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'If the cause is client-side rendering:', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Minimize critical JavaScript.', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Use another rendering strategy.', 'w3-total-cache' ) . '</li>
						</ul>
						<br/>
						<p>W3 Total Cache ' . esc_html__( 'Features that will help performance of the above:', 'w3-total-cache' ) . '</p>
						<ul>
							<li><a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#minify' ) ) . '" alt="' . esc_attr__( 'Minify', 'w3-total-cache' ) . '">' . esc_html__( 'Minify', 'w3-total-cache' ) . '</a></li>
							<li><a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) . '" alt="' . esc_attr__( 'Page Cache', 'w3-total-cache' ) . '">' . esc_html__( 'Page Cache', 'w3-total-cache' ) . '</a></li>
							<li><a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#browser_cache' ) ) . '" alt="' . esc_attr__( 'Browser Cache', 'w3-total-cache' ) . '">' . esc_html__( 'Browser Cache', 'w3-total-cache' ) . '</a></li>
							<li><a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '" alt="' . esc_attr__( 'CDN', 'w3-total-cache' ) . '">' . esc_html__( 'CDN', 'w3-total-cache' ) . '</a></li>
							<li><a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#userexperience' ) ) . '" alt="' . esc_attr__( 'Preload Requests', 'w3-total-cache' ) . '">' . esc_html__( 'Preload Requests', 'w3-total-cache' ) . '</a></li>
						</ul>',
				),
				'lcp-discovery-insight'           => array(
					'instructions' =>
						'<p>' . esc_html__( 'Don\'t lazy load images that appear "above the fold" just use a standard ', 'w3-total-cache' ) . esc_html( '<img>' ) . esc_html__( ' or ', 'w3-total-cache' ) . esc_html( '<picture>' ) . esc_html__( '	element.', 'w3-total-cache' ) . '</p>
						<p>' . sprintf(
							// translators: 1 W3TC plugin name.
							esc_html__(
								'Exclude the image from being lazy-loaded if the %1$s Lazy load is enabled in Performance &raquo; User Experience &raquo; Exclude words.',
								'w3-total-cache'
							),
							'W3 Total Cache'
						) . '</p>',
				),
				'legacy-javascript-insight'       => array(
					'instructions' =>
						'<p>' . esc_html__( 'One way to deal with this issue is to load polyfills, only when needed, which can provide feature-detection support at JavaScript runtime. However, it is often very difficult to implement in practice.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Implement modern feature-detection using ', 'w3-total-cache' ) . '<code>' . esc_html( '<script type="module">' ) . '</code>' . esc_html__( ' and ', 'w3-total-cache' ) . '<code>' . esc_html( '<script nomodule>' ) . '</code>.</p>
						<p>' . esc_html__( 'Every browser that supports ', 'w3-total-cache' ) . '<code>' . esc_html( '<script type="module">' ) . '</code>' . esc_html__( ' also supports most of the ES6 features. This lets you load regular JavaScript files with ES6 features, knowing that the browser can handle it.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'For browsers that don\'t support ', 'w3-total-cache' ) . '<code>' . esc_html( '<script type="module">' ) . '</code>' . esc_html__( ' you can use the translated ES5 code instead. In this manner, you are always serving modern code to modern browsers.', 'w3-total-cache' ) . '</p>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 HTML a tag to philipwalton.com for deplying-es2015-code-in-production-today.
								esc_html__(
									'Learn more about implementing this technique %1$s.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( 'https://philipwalton.com/articles/deploying-es2015-code-in-production-today/' ) . '">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
							),
							$allowed_tags
						) . '</p>',
				),
				'network-dependency-tree-insight' => array(
					'instructions' => '<p>' . esc_html__( 'Eliminate Render Blocking CSS and apply asynchronous loading where applicable. Additionally, image optimization by way of resizing, lazy loading, and WebP conversion can impact this metric as well.', 'w3-total-cache' ) . '</p>',
				),
				'render-blocking-insight'         => array(
					'instructions' =>
						'<p>' . wp_kses(
							sprintf(
								// translators: 1 W3TC plugin name, 2 HTML a tag to W3TC Minify JS admin page
								// translators: 3 HTML a tag to W3TC general settings user experience section
								// translators: 4 HTML a tag to W3TC user expereince advanced settings page
								// translators: 5 HTML a tag to W3TC Minify CSS admin page, 6 HTML line break tag.
								esc_html__(
									'%1$s can eliminate render blocking resources.%6$sOnce Minified, you can defer JS in the
										%2$s.%6$sThe Defer Scripts (PRO FEATURE) can also be used with or without Minify to defer
										the loading of JS files containing the "src" attribute. Scripts matched using this
										feature will be excluded from the Minify process. To enable this feature navigate
										to %3$s and check the "Defer JavaScript" checkbox. Once enabled the settings can be found
										at %4$s.%6$sRender blocking CSS can be eliminated in %5$s using the "Eliminate Render
										blocking CSS by moving it to HTTP body" (PRO FEATURE).',
									'w3-total-cache'
								),
								'W3 Total Cache',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify JS', 'w3-total-cache' ) . '">' . esc_html__( 'Performance &raquo; Minify &raquo; JS', 'w3-total-cache' ) . '</a> ',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#userexperience' ) ) . '" alt="' . esc_attr__( 'Defer Scripts', 'w3-total-cache' ) . '">' . esc_html__( 'Performance &raquo; General Settings &raquo; User Experience', 'w3-total-cache' ) . '</a>',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience#application' ) ) . '" alt="' . esc_attr__( 'Defer Scripts Settings', 'w3-total-cache' ) . '">' . esc_html__( 'Performance &raquo; User Experience', 'w3-total-cache' ) . '</a>',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#css' ) ) . '" alt="' . esc_attr__( 'Minify CSS', 'w3-total-cache' ) . '">' . esc_html__( 'Performance &raquo; Minify &raquo; CSS', 'w3-total-cache' ) . '</a>',
								'<br/><br/>'
							),
							$allowed_tags
						) . '</p>',
				),
				'third-parties-insight'           => array(
					'instructions' =>
						'<ol>
							<li>' . esc_html__( 'Find Slow Third-Party-Code', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Lazy Load YouTube Videos', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Host Google Fonts Locally', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Host Google Analytics Locally', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Host Facebook Pixel Locally', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Host Gravatars Locally', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Delay Third-Party JavaScript', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Defer Parsing Of JavaScript', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Prefetch Or Preconnect Third-Party Scripts', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Avoid Google AdSense And Maps', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Don\'t Overtrack In Google Tag Manager', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Replace Embeds With Screenshots', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Use A Lightweight Social Sharing Plugin', 'w3-total-cache' ) . '</li>
							<li>' . esc_html__( 'Use Cloudflare Workers', 'w3-total-cache' ) . '</li>
						</ol>',
				),
				'viewport-insight'                => array(
					'instructions' =>
						'<p>' . esc_html__( 'Use the "viewport" <meta> tag to control the viewport\'s size and shape form mobile friendly website:', 'w3-total-cache' ) . '</p>
						<code>' . esc_html( '<meta name="viewport" content="width=device-width, initial-scale=1">' ) . '</code>
						<p>' .
						wp_kses(
							sprintf(
								// translators: 1 HTML a tag to developer.mozilla.org for documentation on viewport_meta_tag.
								esc_html__(
									'More details %1$s.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( 'https://developer.mozilla.org/en-US/docs/Web/HTML/Viewport_meta_tag' ) . '">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
							),
							$allowed_tags
						) . '</p>',
				),
			),
			'diagnostics' => array(
				'bootup-time'               => array(
					'instructions' =>
						'<p>' . wp_kses(
							sprintf(
								// translators: 1 HTML a tag to W3TC Minify JS admin page, 2 HTML acronym for CSS, 3 HTML acronym for JS, 4 HTML a tag to W3 API FAQ page containing HTML acronym tag for FAQ.
								esc_html__(
									'On the %1$s tab all of the recommended settings are preset. Use the help button to simplify discovery of your %2$s and %3$s files and groups. Pay close attention to the method and location of your %3$s group embeddings. See the plugin\'s %4$s for more information on usage.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify', 'w3-total-cache' ) . '">' . esc_html__( 'Minify', 'w3-total-cache' ) . '</a>',
								'<acronym title="' . esc_attr__( 'Cascading Style Sheet', 'w3-total-cache' ) . '">' . esc_html__( 'CSS', 'w3-total-cache' ) . '</acronym>',
								'<acronym title="' . esc_attr__( 'JavaScript', 'w3-total-cache' ) . '">' . esc_html__( 'JS', 'w3-total-cache' ) . '</acronym>',
								'<a target="_blank" href="https://api.w3-edge.com/v1/redirects/faq/usage" alt="' . esc_attr__( 'Frequently Asked Questions', 'w3-total-cache' ) . '"><acronym title="' . esc_attr__( 'Frequently Asked Questions', 'w3-total-cache' ) . '">' . esc_html__( 'FAQ', 'w3-total-cache' ) . '</acronym></a>'
							),
							$allowed_tags
						) . '</p>',
				),
				'long-tasks'                => array(
					'instructions' =>
						'<p>' . esc_html__( 'Optimizing third-party JavaScript', 'w3-total-cache' ) . '</p>
						<br/>
						<ul>
							<li>' . esc_html__( 'Review your website\'s third-party code and remove the ones that aren\'t adding any value to your website.', 'w3-total-cache' ) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'Debouncing your input handlers', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Avoid using long-running input handlers (which may block scrolling) and do not make style changes in input handlers (which is likely to cause repainting of pixels).', 'w3-total-cache' ) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'Debouncing your input handlers helps solve both of the above problems.', 'w3-total-cache' ) . '</p>
						<br/>
						<p>' . esc_html__( 'Delay 3rd-party JS', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Reducing JavaScript execution time', 'w3-total-cache' ) . '</li>
							<li>' . sprintf(
								// translators: 1 W3TC plugin name, 2 opening HTML a tag to CDN setting, 3 closing HTML a tag,
								// translators: 4 opening HTML a tag to CDN setting, 5 closing HTML a tag.
								esc_html__(
									'Reduce your JavaScript payload by implementing code splitting, minifying and compressing your JavaScript code, removing unused code, and following the PRPL pattern. (Use %1$s %2$sMinify for JS%3$s and compression.) Use %4$sCDN%5$s and HTTP2 Push if available on server.',
									'w3-total-cache'
								),
								'W3 Total Cache',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify JS', 'w3-total-cache' ) . '">',
								'</a>',
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '" alt="' . esc_attr__( 'CDN', 'w3-total-cache' ) . '">',
								'</a>'
							) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'Only using compositor properties', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Stick to using compositor properties to keep events away from the main-thread. Compositor properties are run on a separate compositor thread, freeing the main-thread for longer and improving your page load performance.', 'w3-total-cache' ) . '</li>
						</ul>
						</ul>
						<br/>
						<p>' . esc_html__( 'Reducing CSS parsing time', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to Minify CSS settings, 3 closing HTML a tag,
							// translators: 4 opening HTML a tag to CDN setting, 5 closing HTML a tag.
							esc_html__(
								'Reduce the time spent parsing CSS by minifying, or deferring non-critical CSS, or removing unused CSS. (Use %1$s %2$sMinify for CSS%3$s and compression.) Use %4$sCDN%5$s and HTTP2 Push if available on server.',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#css' ) ) . '" alt="' . esc_attr__( 'Minify CSS', 'w3-total-cache' ) . '">',
							'</a>',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '" alt="' . esc_attr__( 'CDN', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</li>
						</ul>
						<br/>
						<p>' . esc_html__( 'Only using compositor properties', 'w3-total-cache' ) . '</p>
						<ul>
							<li>' . esc_html__( 'Stick to using compositor properties to keep events away from the main-thread. Compositor properties are run on a separate compositor thread, freeing the main-thread for longer and improving your page load performance.', 'w3-total-cache' ) . '</li>
						</ul>',
				),
				'mainthread-work-breakdown' => array(
					'instructions' =>
						'<p>' . esc_html__( 'Optimizing third-party JavaScript', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Review your website\'s third-party code and remove the ones	that aren\'t adding any value to your website.', 'w3-total-cache' ) . '</p>
						<p><a target="_blank" href="' . esc_url( 'https://web.dev/debounce-your-input-handlers/' ) . '">' . esc_html__( 'Debouncing your input handlers', 'w3-total-cache' ) . '</a></p>
						<p>' . esc_html__( 'Avoid using long-running input handlers (which may block scrolling) and do not make style changes in input handlers (which is likely to cause repainting of pixels).', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Debouncing your input handlers helps solve both of the above problems.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Delay 3rd-party JS', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Reducing JavaScript execution time', 'w3-total-cache' ) . '</p>
						<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to Minify JS settings, 3 closing HTML a tag,
							// translators: 4 opening HTML a tag to CDN setting, 5 closing HTML a tag.
							esc_html__(
								'Reduce your JavaScript payload by implementing code splitting, minifying and compressing your JavaScript code, removing unused code, and following the PRPL pattern. (Use %1$s Minify for %2$sJS%3$s and compression.) Use %4$sCDN%5$s and HTTP2 Push if available on server.',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify JS', 'w3-total-cache' ) . '">',
							'</a>',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '" alt="' . esc_attr__( 'CDN', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p>' . esc_html__( 'Reducing CSS parsing time', 'w3-total-cache' ) . '</p>
						<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to Minify CSS settings, 3 closing HTML a tag,
							// translators: 4 opening HTML a tag to CDN setting, 5 closing HTML a tag.
							esc_html__(
								'Reduce the time spent parsing CSS by minifying, or deferring non-critical CSS, or removing unused CSS. (Use %1$s Minify for %2$sCSS%3$s and compression.) Use %4$sCDN%5$s and HTTP2 Push if available on server.',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#css' ) ) . '" alt="' . esc_attr__( 'Minify CSS', 'w3-total-cache' ) . '">',
							'</a>',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '" alt="' . esc_attr__( 'CDN', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p><a target="_blank" href="' . esc_url( 'https://developers.google.com/web/fundamentals/performance/rendering/stick-to-compositor-only-properties-and-manage-layer-count' ) . '">' . esc_html__( 'Only using compositor properties', 'w3-total-cache' ) . '</a></p>
						<p>' . esc_html__( 'Stick to using compositor properties to keep events away from the main-thread. Compositor properties are run on a separate compositor thread, freeing the main-thread for longer and improving your page load performance.', 'w3-total-cache' ) . '</p>',
				),
				'non-composited-animations' => array(
					'instructions' =>
						'<p><a target="_blank" href="' . esc_url( 'https://developers.google.com/web/fundamentals/performance/rendering/stick-to-compositor-only-properties-and-manage-layer-count' ) . '">' . esc_html__( 'Only using compositor properties:', 'w3-total-cache' ) . '</a></p>
						<p>' . esc_html__( 'Stick to using compositor properties to keep events away from the main-thread. Compositor properties are run on a separate compositor thread, freeing the main-thread for longer and improving your page load performance.', 'w3-total-cache' ) . '</p>',
				),
				'total-byte-weight'         => array(
					'instructions' =>
						'<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to Minify setting, 3 closing HTML a tag.
							esc_html__(
								'Defer or async the JS (Select  Non blocking using Defer or  Non blocking using async Embed method in %1$s %2$sMinify%3$s options before head and after body)',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#minify' ) ) . '" alt="' . esc_attr__( 'Minify', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to CSS Minify settings, 3 closing HTML a tag,
							// translators: 4 opening html a tagl to JS Minify settings, 5 closing HTML a tag.
							esc_html__(
								'Compress your HTML, CSS, and JavaScript files and Minify your CSS and JavaScript to ensure your text-based resources are as small as they can be. Use the %1$s Minify %2$sJS%3$s and %4$sCSS%5$s features to accomplish this.',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#css' ) ) . '" alt="' . esc_attr__( 'Minify CSS', 'w3-total-cache' ) . '">',
							'</a>',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify JS', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1 W3TC plugin name, 2 opening HTML a tag to W3TC extensions, 3 closing HTML a tag.
							esc_html__(
								'Optimize your image delivery by sizing them properly and compressing them for smaller sizes. Use WebP conversion via the %1$s %2$sWebP Converter%3$s extension.',
								'w3-total-cache'
							),
							'W3 Total Cache',
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ) ) . '" alt="' . esc_attr__( 'W3TC Extensions', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1 opening HTML a tag to Browser Caching setting, 2 closing HTML a tag.
							esc_html__(
								'Use %1$sBrowser Caching%2$s for static files and HTML  - 1 year for static files 1 hour for html',
								'w3-total-cache'
							),
							'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#browser_cache' ) ) . '" alt="' . esc_attr__( 'Browser Cache', 'w3-total-cache' ) . '">',
							'</a>'
						) . '</p>',
				),
				'unminified-css'            => array(
					'instructions' =>
						'<p>' . wp_kses(
							sprintf(
								// translators: 1 HTML a tag to W3TC Minify CSS admin page, 2 HTML acronym for CSS, 3 HTML acronym for JS, 4 HTML a tag to W3 API FAQ page containing HTML acronym tag for FAQ.
								esc_html__(
									'On the %1$s tab all of the recommended settings are preset. Use the help button to simplify discovery of your %2$s and %3$s files and groups. Pay close attention to the method and location of your %3$s group embeddings. See the plugin\'s %4$s for more information on usage.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#css' ) ) . '" alt="' . esc_attr__( 'Minify', 'w3-total-cache' ) . '">' . esc_html__( 'Minify', 'w3-total-cache' ) . '</a>',
								'<acronym title="' . esc_attr__( 'Cascading Style Sheet', 'w3-total-cache' ) . '">' . esc_html__( 'CSS', 'w3-total-cache' ) . '</acronym>',
								'<acronym title="' . esc_attr__( 'JavaScript', 'w3-total-cache' ) . '">' . esc_html__( 'JS', 'w3-total-cache' ) . '</acronym>',
								'<a target="_blank" href="https://api.w3-edge.com/v1/redirects/faq/usage" alt="' . esc_attr__( 'Frequently Asked Questions', 'w3-total-cache' ) . '"><acronym title="' . esc_attr__( 'Frequently Asked Questions', 'w3-total-cache' ) . '">' . esc_html__( 'FAQ', 'w3-total-cache' ) . '</acronym></a>'
							),
							$allowed_tags
						) . '</p>',
				),
				'unminified-javascript'     => array(
					'instructions' =>
						'<p>' . wp_kses(
							sprintf(
								// translators: 1 HTML a tag to W3TC Minify CSS admin page, 2 HTML acronym for CSS, 3 HTML acronym for JS, 4 HTML a tag to W3 API FAQ page containing HTML acronym tag for FAQ.
								esc_html__(
									'On the %1$s tab all of the recommended settings are preset. Use the help button to simplify discovery of your %2$s and %3$s files and groups. Pay close attention to the method and location of your %3$s group embeddings. See the plugin\'s %4$s for more information on usage.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#js' ) ) . '" alt="' . esc_attr__( 'Minify', 'w3-total-cache' ) . '">' . esc_html__( 'Minify', 'w3-total-cache' ) . '</a>',
								'<acronym title="' . esc_attr__( 'Cascading Style Sheet', 'w3-total-cache' ) . '">' . esc_html__( 'CSS', 'w3-total-cache' ) . '</acronym>',
								'<acronym title="' . esc_attr__( 'JavaScript', 'w3-total-cache' ) . '">' . esc_html__( 'JS', 'w3-total-cache' ) . '</acronym>',
								'<a target="_blank" href="https://api.w3-edge.com/v1/redirects/faq/usage" alt="' . esc_attr__( 'Frequently Asked Questions', 'w3-total-cache' ) . '"><acronym title="' . esc_attr__( 'Frequently Asked Questions', 'w3-total-cache' ) . '">' . esc_html__( 'FAQ', 'w3-total-cache' ) . '</acronym></a>'
							),
							$allowed_tags
						) . '</p>',
				),
				'unsized-images'            => array(
					'instructions' =>
						'<p>' . esc_html__( 'To fix this audit, simply specify, both, the width and height for your webpage\'s image and video elements. This ensures that the correct spacing is used for images and videos.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'For example:', 'w3-total-cache' ) . '</p>
						<code>' . esc_html( '<img src="image.jpg" width="640" height="360" alt="image">' ) . '</code>
						<p>' . esc_html__( 'Where width and height have been declared as 640 and 360 pixels respectively.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Note that modern browsers automatically calculate the aspect ratio for an image/video based on the declared width and height attributes.', 'w3-total-cache' ) . '</p>',
				),
				'unused-css-rules'          => array(
					'instructions' =>
						'<p>' . esc_html__( 'Some themes and plugins are loading CSS files or parts of the CSS files on all pages and not only on the pages that should be loading on. For example if you are using some contact form plugin, there is a chance that the CSS file of that plugin will load not only on the /contact/ page, but on all other pages as well and this is why the unused CSS should be removed.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Open your Chrome browser, go to “Developer Tools”, click on “More Tools” and then “Coverage”.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Coverage will open up. We will see buttons for start capturing coverage, to reload and start capturing coverage and to stop capturing coverage and show results.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'If you have a webpage you want to analyze its code coverage. Load the webpage and click on the o button in the Coverage tab.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'After sometime, a table will show up in the tab with the resources it analyzed, and how much code is used in the webpage. All the files linked in the webpage (css, js) will be listed in the Coverage tab. Clicking on any resource there will open that resource in the Sources panel with a breakdown of Total Bytes and Unused Bytes.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'With this breakdown, we can see how many unused bytes are in our CSS files, so we can manually remove them.', 'w3-total-cache' ) . '</p>',
				),
				'unused-javascript'         => array(
					'instructions' =>
						'<p>' . esc_html__( 'Some themes and plugins are loading JS files or parts of the JS files on all pages and not only on the pages that should be loading on. For example if you are using some contact form plugin, there is a chance that the JS file of that plugin will load not only on the /contact/ page, but on all other pages as well and this is why the unused JS should be removed.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Open your Chrome browser, go to “Developer Tools”, click on “More Tools” and then “Coverage”.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'Coverage will open up. We will see buttons for start capturing coverage, to reload and start capturing coverage and to stop capturing coverage and show results.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'If you have a webpage you want to analyze its code coverage. Load the webpage and click on the o button in the Coverage tab.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'After sometime, a table will show up in the tab with the resources it analyzed, and how much code is used in the webpage. All the files linked in the webpage (css, js) will be listed in the Coverage tab. Clicking on any resource there will open that resource in the Sources panel with a breakdown of Total Bytes and Unused Bytes.', 'w3-total-cache' ) . '</p>
						<p>' . esc_html__( 'With this breakdown, we can see how many unused bytes are in our JS files, so we can manually remove them.', 'w3-total-cache' ) . '</p>',
				),
				'user-timings'              => array(
					'instructions' =>
						'<p>' . wp_kses(
							sprintf(
								// translators: HTML a tag to developer.mozilla.org for User_Timing_API.
								esc_html__(
									'The %1$s gives you a way to measure your app\'s JavaScript performance.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( 'https://developer.mozilla.org/docs/Web/API/User_Timing_API' ) . '">' . esc_html__( 'User Timing API', 'w3-total-cache' ) . '</a>'
							),
							$allowed_tags
						) . '</p>
						<p>' . esc_html__( 'You do that by inserting API calls in your JavaScript and then extracting detailed timing data that you can use to optimize your code.', 'w3-total-cache' ) . '</p>
						<p>' . wp_kses(
							sprintf(
								// translators: 1 HTML a tag to developer.chrome.com for devtools/evaluate-performance/reference.
								esc_html__(
									'You can access those data from JavaScript using the API or by viewing them on your %1$s.',
									'w3-total-cache'
								),
								'<a target="_blank" href="' . esc_url( 'https://developer.chrome.com/docs/devtools/evaluate-performance/reference/' ) . '">' . esc_html__( 'Chrome DevTools Timeline Recordings', 'w3-total-cache' ) . '</a>'
							),
							$allowed_tags
						) . '</p>',
				),
			),
		);
	}
}
