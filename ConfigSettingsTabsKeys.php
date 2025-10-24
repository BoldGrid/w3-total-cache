<?php
/**
 * File: ConfigSettingsTabsKeys.php
 *
 * @package W3TC
 */

namespace W3TC;

return array(
	'page_cache'     => array(
		'tabs' => array(
			'premium_support' => '<h3>' . esc_html__( 'Did you know that W3 Total Cache has premium services available, and can set up page cache for you?', 'w3-total-cache' ) . '</h3>
				<h4>' . esc_html__( 'Our more popular package is the Plugin Configuration package. Not only will we configure page caching for you, we will configure the object cache, database cache, and most other items too.', 'w3-total-cache' ) . '</h4>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Tailored W3 Total Cache setup, customized for your theme, plugins, and server.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Expert optimization based on WordPress-specific performance needs (WPO).', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Avoid harmful configurations like improper minification or caching.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Receive a detailed performance report with improvements and recommendations.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Proven to boost your site speed—ideal for unique traffic and site needs.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Start your optimization journey with W3 Total Cache as the foundation.', 'w3-total-cache' ) . '</p>

				<div class="cta-button">
					<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ) . '"> ' . esc_html__( 'Click here to purchase this premium service', 'w3-total-cache' ) . ' </a>
				</div>',
			'help'            => '<h3>' . esc_html__( 'Documentation', 'w3-total-cache' ) . '</h3>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/page-caching/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'How to set up Page Cache', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><span class="dashicons dashicons-video-alt3"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.youtube.com/watch?v=vdp0OrJ8hAg' ) . '" target="_blank">' . esc_html__( 'What is Page Cache', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<h3>' . esc_html__( 'Popular questions from the forums', 'w3-total-cache' ) . '</h3>
				<div class="help-forum-topics" data-loaded="0" data-tab-id="tab_page_cache">
				<p style="width:fit-content"><span class="spinner is-active" style="margin: 0 0 0 5px"></span> Loading Forum Topics... </p>
				</div>
				<p><a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/topic-tag/page-cache/' ) . '" target="_blank">' . esc_html__( 'View all questions in Page Cache forum', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><a class="button button-secondary" href="' . esc_url( 'https://www.boldgrid.com/support/ask-a-question/' ) . '" target="_blank">' . esc_html__( 'Ask a question', 'w3-total-cache' ) . '</a> ' . esc_html__( 'in the forums. It may show up here!', 'w3-total-cache' ) . '</p>',
		),
	),
	'minify'         => array(
		'tabs' => array(
			'premium_support' => '<h3>' . esc_html__( 'Did you know that W3 Total Cache has premium services available, and can set up minify options for you?', 'w3-total-cache' ) . '</h3>
				<h4>' . esc_html__( 'Our more popular package is the Plugin Configuration package. Not only will we configure minify options for you, we will configure the object cache, database cache, and most other items too.', 'w3-total-cache' ) . '</h4>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Tailored W3 Total Cache setup, customized for your theme, plugins, and server.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Expert optimization based on WordPress-specific performance needs (WPO).', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Avoid harmful configurations like improper minification or caching.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Receive a detailed performance report with improvements and recommendations.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Proven to boost your site speed—ideal for unique traffic and site needs.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Start your optimization journey with W3 Total Cache as the foundation.', 'w3-total-cache' ) . '</p>

				<div class="cta-button">
					<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ) . '"> ' . esc_html__( 'Click here to purchase this premium service', 'w3-total-cache' ) . ' </a>
				</div>',
			'help'            => '<h3>' . esc_html__( 'Documentation', 'w3-total-cache' ) . '</h3>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/minify-cache/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'Minify Cache Settings Guide', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<h3>' . esc_html__( 'Popular questions from the forums', 'w3-total-cache' ) . '</h3>
				<div class="help-forum-topics" data-loaded="0" data-tab-id="tab_minify_cache">
				<p style="width:fit-content"><span class="spinner is-active" style="margin: 0 0 0 5px"></span> Loading Forum Topics... </p>
				</div>
				<p><a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/topic-tag/minify/' ) . '" target="_blank">' . esc_html__( 'View all questions in Minify Cache forum', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><a class="button button-secondary" href="' . esc_url( 'https://www.boldgrid.com/support/ask-a-question/' ) . '" target="_blank">' . esc_html__( 'Ask a question', 'w3-total-cache' ) . '</a> ' . esc_html__( 'in the forums. It may show up here!', 'w3-total-cache' ) . '</p>',
		),
	),
	'database_cache' => array(
		'tabs' => array(
			'premium_support' => '<h3>' . esc_html__( 'Did you know that W3 Total Cache has premium services available, and can set up database cache for you?', 'w3-total-cache' ) . '</h3>
				<h4>' . esc_html__( 'Our more popular package is the Plugin Configuration package. Not only will we configure database caching for you, we will configure the object cache, page cache, and most other items too.', 'w3-total-cache' ) . '</h4>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Tailored W3 Total Cache setup, customized for your theme, plugins, and server.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Expert optimization based on WordPress-specific performance needs (WPO).', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Avoid harmful configurations like improper minification or caching.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Receive a detailed performance report with improvements and recommendations.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Proven to boost your site speed—ideal for unique traffic and site needs.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Start your optimization journey with W3 Total Cache as the foundation.', 'w3-total-cache' ) . '</p>

				<div class="cta-button">
					<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ) . '"> ' . esc_html__( 'Click here to purchase this premium service', 'w3-total-cache' ) . ' </a>
				</div>',
			'help'            => '<h3>' . esc_html__( 'Documentation', 'w3-total-cache' ) . '</h3>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/database-caching/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'How to set up Database Cache', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><span class="dashicons dashicons-video-alt3"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.youtube.com/watch?v=OWJckWamEvA' ) . '" target="_blank">' . esc_html__( 'What is Database Cache', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<h3>' . esc_html__( 'Popular questions from the forums', 'w3-total-cache' ) . '</h3>
				<div class="help-forum-topics" data-loaded="0" data-tab-id="tab_database_cache">
				<p style="width:fit-content"><span class="spinner is-active" style="margin: 0 0 0 5px"></span> Loading Forum Topics... </p>
				</div>
				<p><a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/topic-tag/database-cache/' ) . '" target="_blank">' . esc_html__( 'View all questions in Database Cache forum', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><a class="button button-secondary" href="' . esc_url( 'https://www.boldgrid.com/support/ask-a-question/' ) . '" target="_blank">' . esc_html__( 'Ask a question', 'w3-total-cache' ) . '</a> ' . esc_html__( 'in the forums. It may show up here!', 'w3-total-cache' ) . '</p>',
		),
	),
	'object_cache'   => array(
		'tabs' => array(
			'premium_support' => '<h3>' . esc_html__( 'Did you know that W3 Total Cache has premium services available, and can set up object cache for you?', 'w3-total-cache' ) . '</h3>
				<h4>' . esc_html__( 'Our more popular package is the Plugin Configuration package. Not only will we configure object caching for you, we will configure the page cache, database cache, and most other items too.', 'w3-total-cache' ) . '</h4>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Tailored W3 Total Cache setup, customized for your theme, plugins, and server.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Expert optimization based on WordPress-specific performance needs (WPO).', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Avoid harmful configurations like improper minification or caching.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Receive a detailed performance report with improvements and recommendations.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Proven to boost your site speed—ideal for unique traffic and site needs.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Start your optimization journey with W3 Total Cache as the foundation.', 'w3-total-cache' ) . '</p>

				<div class="cta-button">
					<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ) . '"> ' . esc_html__( 'Click here to purchase this premium service', 'w3-total-cache' ) . ' </a>
				</div>',
			'help'            => '<h3>' . esc_html__( 'Documentation', 'w3-total-cache' ) . '</h3>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/object-cache-settings-guide/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'Object Cache Settings Guide', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<h3>' . esc_html__( 'Popular questions from the forums', 'w3-total-cache' ) . '</h3>
				<div class="help-forum-topics" data-loaded="0" data-tab-id="tab_object_cache">
				<p style="width:fit-content"><span class="spinner is-active" style="margin: 0 0 0 5px"></span> Loading Forum Topics... </p>
				</div>
				<p><a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/topic-tag/object-cache/' ) . '" target="_blank">' . esc_html__( 'View all questions in Object Cache forum', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><a class="button button-secondary" href="' . esc_url( 'https://www.boldgrid.com/support/ask-a-question/' ) . '" target="_blank">' . esc_html__( 'Ask a question', 'w3-total-cache' ) . '</a> ' . esc_html__( 'in the forums. It may show up here!', 'w3-total-cache' ) . '</p>',
		),
	),
	'browser_cache'  => array(
		'tabs' => array(
			'premium_support' => '<h3>' . esc_html__( 'Did you know that W3 Total Cache has premium services available, and can set up browser cache for you?', 'w3-total-cache' ) . '</h3>
				<h4>' . esc_html__( 'Our more popular package is the Plugin Configuration package. Not only will we configure browser caching for you, we will configure the object cache, database cache, and most other items too.', 'w3-total-cache' ) . '</h4>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Tailored W3 Total Cache setup, customized for your theme, plugins, and server.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Expert optimization based on WordPress-specific performance needs (WPO).', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Avoid harmful configurations like improper minification or caching.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Receive a detailed performance report with improvements and recommendations.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Proven to boost your site speed—ideal for unique traffic and site needs.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Start your optimization journey with W3 Total Cache as the foundation.', 'w3-total-cache' ) . '</p>

				<div class="cta-button">
					<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ) . '"> ' . esc_html__( 'Click here to purchase this premium service', 'w3-total-cache' ) . ' </a>
				</div>',
			'help'            => '<h3>' . esc_html__( 'Documentation', 'w3-total-cache' ) . '</h3>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-browser-caching-in-w3-total-cache/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'How to configure Browser Cache', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<h3>' . esc_html__( 'Popular questions from the forums', 'w3-total-cache' ) . '</h3>
				<div class="help-forum-topics" data-loaded="0" data-tab-id="tab_browser_cache">
				<p style="width:fit-content"><span class="spinner is-active" style="margin: 0 0 0 5px"></span> Loading Forum Topics... </p>
				</div>
				<p><a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/topic-tag/browser-cache/' ) . '" target="_blank">' . esc_html__( 'View all questions in Browser Cache forum', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><a class="button button-secondary" href="' . esc_url( 'https://www.boldgrid.com/support/ask-a-question/' ) . '" target="_blank">' . esc_html__( 'Ask a question', 'w3-total-cache' ) . '</a> ' . esc_html__( 'in the forums. It may show up here!', 'w3-total-cache' ) . '</p>',
		),
	),
	'cdn'            => array(
		'tabs' => array(
			'premium_support' => '<h3>' . esc_html__( 'Did you know that W3 Total Cache has premium services available, and can help you with your Full Site Delivery via CDN?', 'w3-total-cache' ) . '</h3>
				<h4>' . esc_html__( 'Optimize Your WordPress Site with the Full Site Delivery (FSD) CDN service', 'w3-total-cache' ) . '</h4>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'FSD CDN accelerates website load times by caching and delivering entire web pages.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Acts as a proxy server to retrieve and serve content faster, reducing server response time.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Optimized for full page delivery, not just static files, enhancing overall site performance.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Setup includes integration with W3 Total Cache FSD CDN Pro for seamless performance.', 'w3-total-cache' ) . '</p>
				<p><span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Compatible with top CDN providers like Bunny CDN, Transparent CDN, Amazon, and Cloudflare.', 'w3-total-cache' ) . '</p>
				<div class="cta-button">
					<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_support' ) ) . '"> ' . esc_html__( 'Click here to purchase this premium service', 'w3-total-cache' ) . ' </a>
				</div>',
			'help'            => '<h3>' . esc_html__( 'Documentation', 'w3-total-cache' ) . '</h3>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/how-to-configure-cloudflare-in-wordpress-with-w3-total-cache/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'How to configure Cloudflare with W3 Total Cache', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/bunny-cdn-setup/#configure-w3-total-cache?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'Full Site Delivery with Bunny CDN', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<p><span class="dashicons dashicons-text-page"></span>
				<a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/full-site-delivery-fds/?utm_source=w3tc&utm_medium=documentation&utm_campaign=helptab' ) . '" target="_blank">' . esc_html__( 'Enhancing WordPress Performance with Full Site Delivery (FSD)', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a></p>
				<h3>' . esc_html__( 'Popular questions from the forums', 'w3-total-cache' ) . '</h3>
				<div class="help-forum-topics" data-loaded="0" data-tab-id="tab_cdn">
				<p style="width:fit-content"><span class="spinner is-active" style="margin: 0 0 0 5px"></span> Loading Forum Topics... </p>
				</div>
				<p><a class="w3tc-control-after" href="' . esc_url( 'https://www.boldgrid.com/support/topic-tag/cdn/' ) . '" target="_blank">' . esc_html__( 'View all questions in the CDN forum', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a><p>
				<p><a class="button button-secondary" href="' . esc_url( 'https://www.boldgrid.com/support/ask-a-question/' ) . '" target="_blank">' . esc_html__( 'Ask a question', 'w3-total-cache' ) . '</a> ' . esc_html__( 'in the forums. It may show up here!', 'w3-total-cache' ) . '</p>',
		),
	),
);
