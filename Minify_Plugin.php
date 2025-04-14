<?php
/**
 * File: Minify_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Minify_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Minify_Plugin {
	/**
	 * Minify reject reason
	 *
	 * @var string
	 */
	public $minify_reject_reason = '';

	/**
	 * Error
	 *
	 * @var string
	 */
	public $error = '';

	/**
	 * Array of replaced styles
	 *
	 * @var array
	 */
	public $replaced_styles = array();

	/**
	 * Array of replaced scripts
	 *
	 * @var array
	 */
	public $replaced_scripts = array();

	/**
	 * Array of printed scripts.
	 *
	 * @var array
	 */
	public $printed_scripts = array();

	/**
	 * Array of printed styles.
	 *
	 * @var array
	 */
	public $printed_styles = array();

	/**
	 * Helper object to use
	 *
	 * @var _W3_MinifyHelpers
	 */
	private $minify_helpers;

	/**
	 * Config.
	 *
	 * @var Config Configuration.
	 */
	private $_config = null;

	/**
	 * Constructor for the Minify_Plugin class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Initializes the plugin by registering filters and actions.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( 'w3tc_minifycache_purge_wpcron', array( $this, 'w3tc_minifycache_purge_wpcron' ) );

		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );

		add_filter( 'w3tc_footer_comment', array( $this, 'w3tc_footer_comment' ) );

		if ( 'file' === $this->_config->get_string( 'minify.engine' ) ) {
			add_action( 'w3_minify_cleanup', array( $this, 'cleanup' ) );
		}
		add_filter( 'w3tc_pagecache_set_header', array( $this, 'w3tc_pagecache_set_header' ), 20, 2 );

		// usage statistics handling.
		add_action( 'w3tc_usage_statistics_of_request', array( $this, 'w3tc_usage_statistics_of_request' ), 10, 1 );
		add_filter( 'w3tc_usage_statistics_metrics', array( $this, 'w3tc_usage_statistics_metrics' ) );

		// Start minify.
		if ( $this->can_minify() ) {
			Util_Bus::add_ob_callback( 'minify', array( $this, 'ob_callback' ) );
		}
	}

	/**
	 * Initializes the Minify Plugin during the `init` action.
	 *
	 * @return void
	 */
	public function init() {
		$url    = Util_Environment::filename_to_url( W3TC_CACHE_MINIFY_DIR );
		$parsed = wp_parse_url( $url );
		$prefix = '/' . trim( $parsed['path'], '/' ) . '/';

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( substr( $request_uri, 0, strlen( $prefix ) ) === $prefix ) {
			$w3_minify = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
			$filename  = Util_Environment::remove_query_all( substr( $request_uri, strlen( $prefix ) ) );
			$w3_minify->process( $filename );
			exit();
		}

		if ( ! empty( Util_Request::get_string( 'w3tc_minify' ) ) ) {
			$w3_minify = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
			$w3_minify->process( Util_Request::get_string( 'w3tc_minify' ) );
			exit();
		}
	}

	/**
	 * Cleans up the minify cache.
	 *
	 * @return void
	 */
	public function cleanup() {
		$a = Dispatcher::component( 'Minify_Plugin_Admin' );
		$a->cleanup();
	}

	/**
	 * Adds custom schedules for cron jobs related to minify cache cleanup.
	 *
	 * @param array $schedules The existing cron schedules.
	 *
	 * @return array The updated cron schedules.
	 */
	public function cron_schedules( $schedules ) {
		$c              = $this->_config;
		$minify_enabled = $c->get_boolean( 'minify.enabled' );
		$engine         = $c->get_string( 'minify.engine' );

		if ( $minify_enabled && ( 'file' === $engine || 'file_generic' === $engine ) ) {
			$interval                       = $c->get_integer( 'minify.file.gc' );
			$schedules['w3_minify_cleanup'] = array(
				'interval' => $interval,
				'display'  => sprintf(
					// translators: 1 interval in seconds.
					__( '[W3TC] Minify Cache file GC (every %d seconds)', 'w3-total-cache' ),
					$interval
				),
			);
		}

		return $schedules;
	}

	/**
	 * Purges the minify cache via WP-Cron.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public function w3tc_minifycache_purge_wpcron() {
		$flusher = Dispatcher::component( 'CacheFlush' );
		$flusher->minifycache_flush();
	}

	/**
	 * Handles output buffering for minification and optimization.
	 *
	 * @param string $buffer The output buffer content.
	 *
	 * @return string The processed buffer after minification.
	 *
	 * @throws \Exception If an error occurs during the minification process.
	 */
	public function ob_callback( $buffer ) {
		$enable = Util_Content::is_html( $buffer ) && $this->can_minify2( $buffer );
		$enable = apply_filters( 'w3tc_minify_enable', $enable );
		if ( ! $enable ) {
			return $buffer;
		}

		$this->minify_helpers = new _W3_MinifyHelpers( $this->_config );

		// Replace script and style tags.
		$js_enable   = $this->_config->get_boolean( 'minify.js.enable' );
		$css_enable  = $this->_config->get_boolean( 'minify.css.enable' );
		$html_enable = $this->_config->get_boolean( 'minify.html.enable' );

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$js_enable  = false;
			$css_enable = false;
		}

		$js_enable   = apply_filters( 'w3tc_minify_js_enable', $js_enable );
		$css_enable  = apply_filters( 'w3tc_minify_css_enable', $css_enable );
		$html_enable = apply_filters( 'w3tc_minify_html_enable', $html_enable );

		$head_prepend   = '';
		$body_prepend   = '';
		$body_append    = '';
		$embed_extsrcjs = false;
		$buffer         = apply_filters( 'w3tc_minify_before', $buffer );

		// If the minify cache folder is missing minify fails. This will generate the minify folder path if missing.
		$minify_environment = Dispatcher::component( 'Minify_Environment' );
		try {
			$minify_environment->fix_on_wpadmin_request( $this->_config, true );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Exception.
		}

		if ( $this->_config->get_boolean( 'minify.auto' ) ) {
			if ( $js_enable ) {
				$minifier               = new Minify_AutoJs( $this->_config, $buffer, $this->minify_helpers );
				$buffer                 = $minifier->execute();
				$this->replaced_scripts = $minifier->get_debug_minified_urls();
			}

			if ( $css_enable ) {
				$minifier = new Minify_AutoCss( $this->_config, $buffer, $this->minify_helpers );
				$buffer   = $minifier->execute();
			}

			$buffer = apply_filters( 'w3tc_minify_processed', $buffer );
		} else {
			if ( $css_enable ) {
				$style = $this->get_style_group( 'include' );

				if ( $style['body'] ) {
					if ( $this->_custom_location_does_not_exist( '/<!-- W3TC-include-css -->/', $buffer, $style['body'] ) ) {
						$head_prepend .= $style['body'];
					}

					$this->remove_styles_group( $buffer, 'include' );
				}

				if ( $this->_config->getf_boolean( 'minify.css.http2push' ) ) {
					$this->minify_helpers->http2_header_add( $style['url'], 'style' );
				}
			}

			if ( $js_enable ) {
				$embed_type = $this->_config->get_string( 'minify.js.header.embed_type' );
				$http2push  = $this->_config->getf_boolean( 'minify.js.http2push' );

				$script = $this->get_script_group( 'include', $embed_type );

				if ( $script['body'] ) {
					$embed_extsrcjs = 'extsrc' === $embed_type || 'asyncsrc' === $embed_type ? true : $embed_extsrcjs;

					if ( $this->_custom_location_does_not_exist( '/<!-- W3TC-include-js-head -->/', $buffer, $script['body'] ) ) {
						$head_prepend .= $script['body'];
					}

					$this->remove_scripts_group( $buffer, 'include' );
				}
				if ( $http2push ) {
					$this->minify_helpers->http2_header_add( $script['url'], 'script' );
				}

				$embed_type = $this->_config->get_string( 'minify.js.body.embed_type' );
				$script     = $this->get_script_group( 'include-body', $embed_type );

				if ( $script['body'] ) {
					$embed_extsrcjs = 'extsrc' === $embed_type || 'asyncsrc' === $embed_type ? true : $embed_extsrcjs;

					if ( $this->_custom_location_does_not_exist( '/<!-- W3TC-include-js-body-start -->/', $buffer, $script['body'] ) ) {
						$body_prepend .= $script['body'];
					}

					$this->remove_scripts_group( $buffer, 'include-body' );
				}
				if ( $http2push ) {
					$this->minify_helpers->http2_header_add( $script['url'], 'script' );
				}

				$embed_type = $this->_config->get_string( 'minify.js.footer.embed_type' );
				$script     = $this->get_script_group( 'include-footer', $embed_type );

				if ( $script['body'] ) {
					$embed_extsrcjs = 'extsrc' === $embed_type || 'asyncsrc' === $embed_type ? true : $embed_extsrcjs;

					if ( $this->_custom_location_does_not_exist( '/<!-- W3TC-include-js-body-end -->/', $buffer, $script['body'] ) ) {
						$body_append .= $script['body'];
					}

					$this->remove_scripts_group( $buffer, 'include-footer' );
				}
				if ( $http2push ) {
					$this->minify_helpers->http2_header_add( $script['url'], 'script' );
				}
			}
		}

		if ( '' !== $head_prepend ) {
			$buffer = preg_replace( '~<head(\s+[^>]*)*>~Ui', '\\0' . $head_prepend, $buffer, 1 );
		}

		if ( '' !== $body_prepend ) {
			$buffer = preg_replace( '~<body(\s+[^>]*)*>~Ui', '\\0' . $body_prepend, $buffer, 1 );
		}

		if ( '' !== $body_append ) {
			$buffer = preg_replace( '~<\\/body>~', $body_append . '\\0', $buffer, 1 );
		}

		if ( $embed_extsrcjs ) {
			$script = '
<script>
var extsrc=null;
(function(){function j(){if(b&&g){document.write=k;document.writeln=l;var f=document.createElement("span");f.innerHTML=b;g.appendChild(f);b=""}}function d(){j();for(var f=document.getElementsByTagName("script"),c=0;c<f.length;c++){var e=f[c],h=e.getAttribute("asyncsrc");if(h){e.setAttribute("asyncsrc","");var a=document.createElement("script");a.async=!0;a.src=h;document.getElementsByTagName("head")[0].appendChild(a)}if(h=e.getAttribute("extsrc")){e.setAttribute("extsrc","");g=document.createElement("span");e.parentNode.insertBefore(g,e);document.write=function(a){b+=a};document.writeln=function(a){b+=a;b+="\n"};a=document.createElement("script");a.async=!0;a.src=h;/msie/i.test(navigator.userAgent)&&!/opera/i.test(navigator.userAgent)?a.onreadystatechange=function(){("loaded"==this.readyState||"complete"==this.readyState)&&d()}:-1!=navigator.userAgent.indexOf("Firefox")||"onerror"in a?(a.onload=d,a.onerror=d):(a.onload=d,a.onreadystatechange=d);document.getElementsByTagName("head")[0].appendChild(a);return}}j();document.write=k;document.writeln=l;for(c=0;c<extsrc.complete.funcs.length;c++)extsrc.complete.funcs[c]()}function i(){arguments.callee.done||(arguments.callee.done=!0,d())}extsrc={complete:function(b){this.complete.funcs.push(b)}};extsrc.complete.funcs=[];var k=document.write,l=document.writeln,b="",g="";document.addEventListener&&document.addEventListener("DOMContentLoaded",i,!1);if(/WebKit/i.test(navigator.userAgent))var m=setInterval(function(){/loaded|complete/.test(document.readyState)&&(clearInterval(m),i())},10);window.onload=i})();
</script>
';

			$buffer = preg_replace( '~<head(\s+[^>]*)*>~Ui', '\\0' . $script, $buffer, 1 );
		}

		// Minify HTML/Feed.
		if ( $html_enable ) {
			try {
				$buffer = $this->minify_html( $buffer );
			} catch ( \Exception $exception ) {
				$this->error = $exception->getMessage();
			}
		}

		return $buffer;
	}

	/**
	 * Adds the minify flush item to the WordPress admin bar menu.
	 *
	 * @param array $menu_items Array of existing admin bar menu items.
	 *
	 * @return array Modified menu items with the minify cache option included.
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$menu_items['20210.minify'] = array(
			'id'     => 'w3tc_flush_minify',
			'parent' => 'w3tc_flush',
			'title'  => __( 'Minify Cache', 'w3-total-cache' ),
			'href'   => wp_nonce_url(
				admin_url(
					'admin.php?page=w3tc_dashboard&amp;w3tc_flush_minify'
				),
				'w3tc'
			),
		);

		return $menu_items;
	}

	/**
	 * Appends a footer comment regarding minification to the HTML strings.
	 *
	 * @param array $strings Array of footer comments to append.
	 *
	 * @return array Modified array of footer comments.
	 */
	public function w3tc_footer_comment( $strings ) {
		$strings[] = sprintf(
			// Translators: 1 engine name, 2 reject reason.
			__(
				'Minified using %1$s%2$s',
				'w3-total-cache'
			),
			Cache::engine_name( $this->_config->get_string( 'minify.engine' ) ),
			( '' !== $this->minify_reject_reason ? sprintf( ' (%s)', $this->minify_reject_reason ) : '' )
		);

		if ( $this->_config->get_boolean( 'minify.debug' ) ) {
			$strings[] = '';
			$strings[] = 'Minify debug info:';
			$strings[] = sprintf( '%s%s', str_pad( 'Theme: ', 20 ), $this->get_theme() );
			$strings[] = sprintf( '%s%s', str_pad( 'Template: ', 20 ), $this->get_template() );

			if ( $this->error ) {
				$strings[] = sprintf( '%s%s', str_pad( 'Errors: ', 20 ), $this->error );
			}

			if ( count( $this->replaced_styles ) ) {
				$strings[] = 'Replaced CSS files:';

				foreach ( $this->replaced_styles as $index => $file ) {
					$strings[] = sprintf( '%d. %s', $index + 1, Util_Content::escape_comment( $file ) );
				}
			}

			if ( count( $this->replaced_scripts ) ) {
				$strings[] = 'Replaced JavaScript files:';

				foreach ( $this->replaced_scripts as $index => $file ) {
					$strings[] = sprintf( "%d. %s\r\n", $index + 1, Util_Content::escape_comment( $file ) );
				}
			}

			$strings[] = '';
		}

		return $strings;
	}

	/**
	 * Checks if a custom minification location does not exist.
	 *
	 * @param string $pattern Regular expression pattern to search.
	 * @param string $source  Source string to check.
	 * @param string $script  Replacement script for the match.
	 *
	 * @return bool True if the location does not exist, false otherwise.
	 */
	public function _custom_location_does_not_exist( $pattern, &$source, $script ) {
		$count  = 0;
		$source = preg_replace( $pattern, $script, $source, 1, $count );
		return 0 === $count;
	}

	/**
	 * Removes specified CSS files from the provided content.
	 *
	 * @param string $content HTML content to search for CSS references.
	 * @param array  $files   List of CSS files to remove.
	 *
	 * @return void
	 */
	public function remove_styles( &$content, $files ) {
		$regexps         = array();
		$home_url_regexp = Util_Environment::home_url_regexp();

		$path = '';
		if ( Util_Environment::is_wpmu() && ! Util_Environment::is_wpmu_subdomain() ) {
			$path = ltrim( Util_Environment::home_url_uri(), '/' );
		}

		foreach ( $files as $file ) {
			if ( $path && strpos( $file, $path ) === 0 ) {
				$file = substr( $file, strlen( $path ) );
			}

			$this->replaced_styles[] = $file;

			if ( Util_Environment::is_url( $file ) && ! preg_match( '~' . $home_url_regexp . '~i', $file ) ) {
				// external CSS files.
				$regexps[] = Util_Environment::preg_quote( $file );
			} else {
				// local CSS files.
				$file = ltrim( $file, '/' );
				if (
					home_url() === site_url() &&
					ltrim( Util_Environment::site_url_uri(), '/' ) &&
					strpos( $file, ltrim( Util_Environment::site_url_uri(), '/' ) ) === 0
				) {
					$file = str_replace( ltrim( Util_Environment::site_url_uri(), '/' ), '', $file );
				}

				$file      = ltrim( preg_replace( '~' . $home_url_regexp . '~i', '', $file ), '/\\' );
				$regexps[] = '(' . $home_url_regexp . ')?/?' . Util_Environment::preg_quote( $file );
			}
		}

		foreach ( $regexps as $regexp ) {
			$content = preg_replace( '~<link\s+[^<>]*href=["\']?' . $regexp . '["\']?[^<>]*/?>(.*</link>)?~Uis', '', $content );
			$content = preg_replace( '~@import\s+(url\s*)?\(?["\']?\s*' . $regexp . '\s*["\']?\)?[^;]*;?~is', '', $content );
		}

		$content = preg_replace( '~<style[^<>]*>\s*</style>~', '', $content );
	}

	/**
	 * Removes specified JavaScript files from the provided content.
	 *
	 * @param string $content HTML content to search for script references.
	 * @param array  $files   List of JavaScript files to remove.
	 *
	 * @return void
	 */
	public function remove_scripts( &$content, $files ) {
		$regexps         = array();
		$home_url_regexp = Util_Environment::home_url_regexp();

		$path = '';
		if ( Util_Environment::is_wpmu() && ! Util_Environment::is_wpmu_subdomain() ) {
			$path = ltrim( Util_Environment::network_home_url_uri(), '/' );
		}

		foreach ( $files as $file ) {
			if ( $path && strpos( $file, $path ) === 0 ) {
				$file = substr( $file, strlen( $path ) );
			}

			$this->replaced_scripts[] = $file;

			if ( Util_Environment::is_url( $file ) && ! preg_match( '~' . $home_url_regexp . '~i', $file ) ) {
				// external JS files.
				$regexps[] = Util_Environment::preg_quote( $file );
			} else {
				// local JS files.
				$file = ltrim( $file, '/' );
				if (
					home_url() === site_url() &&
					ltrim( Util_Environment::site_url_uri(), '/' ) &&
					strpos( $file, ltrim( Util_Environment::site_url_uri(), '/' ) ) === 0
				) {
					$file = str_replace( ltrim( Util_Environment::site_url_uri(), '/' ), '', $file );
				}

				$file      = ltrim( preg_replace( '~' . $home_url_regexp . '~i', '', $file ), '/\\' );
				$regexps[] = '(' . $home_url_regexp . ')?/?' . Util_Environment::preg_quote( $file );
			}
		}

		foreach ( $regexps as $regexp ) {
			$content = preg_replace( '~<script\s+[^<>]*src=["\']?' . $regexp . '["\']?[^<>]*>\s*</script>~Uis', '', $content );
		}
	}

	/**
	 * Removes a group of CSS files for a specified location.
	 *
	 * @param string $content  HTML content to search for CSS references.
	 * @param string $location Location identifier for the CSS group.
	 *
	 * @return void
	 */
	public function remove_styles_group( &$content, $location ) {
		$theme    = $this->get_theme();
		$template = $this->get_template();

		$files  = array();
		$groups = $this->_config->get_array( 'minify.css.groups' );

		if ( isset( $groups[ $theme ]['default'][ $location ]['files'] ) ) {
			$files = (array) $groups[ $theme ]['default'][ $location ]['files'];
		}

		if ( 'default' !== $template && isset( $groups[ $theme ][ $template ][ $location ]['files'] ) ) {
			$files = array_merge( $files, (array) $groups[ $theme ][ $template ][ $location ]['files'] );
		}

		$this->remove_styles( $content, $files );
	}

	/**
	 * Removes a group of JavaScript files for a specified location.
	 *
	 * @param string $content  HTML content to search for script references.
	 * @param string $location Location identifier for the script group.
	 *
	 * @return void
	 */
	public function remove_scripts_group( &$content, $location ) {
		$theme    = $this->get_theme();
		$template = $this->get_template();
		$files    = array();
		$groups   = $this->_config->get_array( 'minify.js.groups' );

		if ( isset( $groups[ $theme ]['default'][ $location ]['files'] ) ) {
			$files = (array) $groups[ $theme ]['default'][ $location ]['files'];
		}

		if ( 'default' !== $template && isset( $groups[ $theme ][ $template ][ $location ]['files'] ) ) {
			$files = array_merge( $files, (array) $groups[ $theme ][ $template ][ $location ]['files'] );
		}

		$this->remove_scripts( $content, $files );
	}

	/**
	 * Minifies the provided HTML content.
	 *
	 * @param string $html HTML content to minify.
	 *
	 * @return string Minified HTML content.
	 */
	public function minify_html( $html ) {
		$w3_minifier = Dispatcher::component( 'Minify_ContentMinifier' );

		$ignored_comments = $this->_config->get_array( 'minify.html.comments.ignore' );

		if ( count( $ignored_comments ) ) {
			$ignored_comments_preserver = new \W3TCL\Minify\Minify_IgnoredCommentPreserver();
			$ignored_comments_preserver->setIgnoredComments( $ignored_comments );

			$html = $ignored_comments_preserver->search( $html );
		}

		if ( $this->_config->get_boolean( 'minify.html.inline.js' ) ) {
			$js_engine = $this->_config->get_string( 'minify.js.engine' );

			if ( ! $w3_minifier->exists( $js_engine ) || ! $w3_minifier->available( $js_engine ) ) {
				$js_engine = 'js';
			}

			$js_minifier = $w3_minifier->get_minifier( $js_engine );
			$js_options  = $w3_minifier->get_options( $js_engine );

			$w3_minifier->init( $js_engine );

			$html = \W3TCL\Minify\Minify_Inline_JavaScript::minify( $html, $js_minifier, $js_options );
		}

		if ( $this->_config->get_boolean( 'minify.html.inline.css' ) ) {
			$css_engine = $this->_config->get_string( 'minify.css.engine' );

			if ( ! $w3_minifier->exists( $css_engine ) || ! $w3_minifier->available( $css_engine ) ) {
				$css_engine = 'css';
			}

			$css_minifier = $w3_minifier->get_minifier( $css_engine );
			$css_options  = $w3_minifier->get_options( $css_engine );

			$w3_minifier->init( $css_engine );

			$html = \W3TCL\Minify\Minify_Inline_CSS::minify( $html, $css_minifier, $css_options );
		}

		$engine = $this->_config->get_string( 'minify.html.engine' );

		if ( ! $w3_minifier->exists( $engine ) || ! $w3_minifier->available( $engine ) ) {
			$engine = 'html';
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$engine .= 'xml';
		}

		$minifier = $w3_minifier->get_minifier( $engine );
		$options  = $w3_minifier->get_options( $engine );

		$w3_minifier->init( $engine );

		$html = call_user_func( $minifier, $html, $options );

		if ( isset( $ignored_comments_preserver ) ) {
			$html = $ignored_comments_preserver->replace( $html );
		}

		return $html;
	}

	/**
	 * Retrieves the current theme identifier.
	 *
	 * @return string Theme identifier.
	 */
	public function get_theme() {
		static $theme = null;

		if ( null === $theme ) {
			$theme = Util_Theme::get_theme_key( get_theme_root(), get_template(), get_stylesheet() );
		}

		return $theme;
	}

	/**
	 * Retrieves the current template identifier.
	 *
	 * phpcs:disable WordPress.CodeAnalysis.AssignmentInCondition.Found
	 * phpcs:disable Squiz.PHP.DisallowMultipleAssignments.Found
	 * phpcs:disable Generic.CodeAnalysis.AssignmentInCondition.Found
	 *
	 * @return string Template identifier.
	 */
	public function get_template() {
		static $template = null;

		if ( null === $template ) {
			$template_file = 'index.php';
			switch ( true ) {
				case ( is_404() && ( $template_file = get_404_template() ) ):
				case ( is_search() && ( $template_file = get_search_template() ) ):
				case ( is_tax() && ( $template_file = get_taxonomy_template() ) ):
				case ( is_front_page() && function_exists( 'get_front_page_template' ) && $template_file = get_front_page_template() ):
				case ( is_home() && ( $template_file = get_home_template() ) ):
				case ( is_attachment() && ( $template_file = get_attachment_template() ) ):
				case ( is_single() && ( $template_file = get_single_template() ) ):
				case ( is_page() && ( $template_file = get_page_template() ) ):
				case ( is_category() && ( $template_file = get_category_template() ) ):
				case ( is_tag() && ( $template_file = get_tag_template() ) ):
				case ( is_author() && ( $template_file = get_author_template() ) ):
				case ( is_date() && ( $template_file = get_date_template() ) ):
				case ( is_archive() && ( $template_file = get_archive_template() ) ):
				case ( is_paged() && ( $template_file = get_query_template( 'paged' ) ) ):
					break;

				default:
					if ( function_exists( 'get_index_template' ) ) {
						$template_file = get_index_template();
					} else {
						$template_file = 'index.php';
					}
					break;
			}

			$template = basename( $template_file, '.php' );
		}

		return $template;
	}

	/**
	 * Generates the HTML markup for including a stylesheet.
	 *
	 * phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	 *
	 * @param string $url        URL of the stylesheet.
	 * @param bool   $import     Whether to use @import syntax.
	 * @param bool   $use_style  Whether to wrap @import in <style> tags.
	 *
	 * @return string Generated HTML markup.
	 */
	public function get_style( $url, $import = false, $use_style = true ) {
		if ( $import && $use_style ) {
			return '<style media="all">@import url("' . $url . "\");</style>\r\n";
		} elseif ( $import && ! $use_style ) {
			return '@import url("' . $url . "\");\r\n";
		} else {
			return '<link rel="stylesheet" href="' . str_replace( '&', '&amp;', $url ) . "\" media=\"all\" />\r\n";
		}
	}

	/**
	 * Retrieves the details of a grouped CSS style by location.
	 *
	 * @param string $location Location identifier for the style group.
	 *
	 * @return array Associative array containing 'url' and 'body' keys.
	 */
	public function get_style_group( $location ) {
		$style    = false;
		$type     = 'css';
		$groups   = $this->_config->get_array( 'minify.css.groups' );
		$theme    = $this->get_theme();
		$template = $this->get_template();

		if ( 'default' !== $template && empty( $groups[ $theme ][ $template ][ $location ]['files'] ) ) {
			$template = 'default';
		}

		$return = array(
			'url'  => null,
			'body' => '',
		);

		if ( ! empty( $groups[ $theme ][ $template ][ $location ]['files'] ) ) {
			if ( $this->_config->get_boolean( 'minify.css.embed' ) ) {
				$minify          = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
				$minify_filename = $this->get_minify_manual_filename( $theme, $template, $location, $type );

				$m = $minify->process( $minify_filename, true );
				if ( isset( $m['content'] ) ) {
					$style = $m['content'];
				} else {
					$style = 'not set';
				}

				$return['body'] = "<style media=\"all\">$style</style>\r\n";
			} else {
				$return['url'] = $this->get_minify_manual_url( $theme, $template, $location, $type );

				if ( $return['url'] ) {
					$import = (
						isset( $groups[ $theme ][ $template ][ $location ]['import'] ) ?
						(bool) $groups[ $theme ][ $template ][ $location ]['import'] :
						false
					);

					$return['body'] = $this->get_style( $return['url'], $import );
				}
			}
		}

		return $return;
	}

	/**
	 * Retrieves the script group for the specified location and embed type.
	 *
	 * @param string $location   The location of the scripts (e.g., 'header', 'footer').
	 * @param string $embed_type The embed type, defaults to 'blocking'.
	 *
	 * @return array Associative array containing 'url' and 'body'.
	 */
	public function get_script_group( $location, $embed_type = 'blocking' ) {
		$script    = false;
		$file_type = 'js';
		$theme     = $this->get_theme();
		$template  = $this->get_template();
		$groups    = $this->_config->get_array( 'minify.js.groups' );

		if ( 'default' !== $template && empty( $groups[ $theme ][ $template ][ $location ]['files'] ) ) {
			$template = 'default';
		}

		$return = array(
			'url'  => null,
			'body' => '',
		);

		if ( ! empty( $groups[ $theme ][ $template ][ $location ]['files'] ) ) {
			$return['url'] = $this->get_minify_manual_url( $theme, $template, $location, $file_type );

			if ( $return['url'] ) {
				$return['body'] = $this->minify_helpers->generate_script_tag( $return['url'], $embed_type );
			}
		}

		return $return;
	}

	/**
	 * Generates a style tag or URL for custom styles.
	 *
	 * @param array $files         Array of file paths for the styles.
	 * @param bool  $embed_to_html Whether to embed styles directly in HTML.
	 *
	 * @return array Associative array containing 'url' and 'body'.
	 */
	public function get_style_custom( $files, $embed_to_html = false ) {
		return $this->minify_helpers->generate_css_style_tag( $files, $embed_to_html );
	}

	/**
	 * Retrieves the manual filename for minified resources.
	 *
	 * @param string $theme    The theme name.
	 * @param string $template The template name.
	 * @param string $location The location identifier.
	 * @param string $type     The resource type ('js' or 'css').
	 *
	 * @return string|false The generated filename or false if not available.
	 */
	public function get_minify_manual_filename( $theme, $template, $location, $type ) {
		$minify = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
		$id     = $minify->get_id_group( $theme, $template, $location, $type );
		if ( ! $id ) {
			return false;
		}

		return $theme . '.' . $template . '.' . $location . '.' . $id . '.' . $type;
	}

	/**
	 * Retrieves the manual URL for minified resources.
	 *
	 * @param string $theme    The theme name.
	 * @param string $template The template name.
	 * @param string $location The location identifier.
	 * @param string $type     The resource type ('js' or 'css').
	 *
	 * @return string The generated URL.
	 */
	public function get_minify_manual_url( $theme, $template, $location, $type ) {
		return Minify_Core::minified_url( $this->get_minify_manual_filename( $theme, $template, $location, $type ) );
	}

	/**
	 * Retrieves an array of all URLs for minified resources.
	 *
	 * @return array Array of URLs.
	 */
	public function get_urls() {
		$files = array();

		$js_groups  = $this->_config->get_array( 'minify.js.groups' );
		$css_groups = $this->_config->get_array( 'minify.css.groups' );

		foreach ( $js_groups as $js_theme => $js_templates ) {
			foreach ( $js_templates as $js_template => $js_locations ) {
				foreach ( (array) $js_locations as $js_location => $js_config ) {
					if ( ! empty( $js_config['files'] ) ) {
						$files[] = $this->get_minify_manual_url( $js_theme, $js_template, $js_location, 'js' );
					}
				}
			}
		}

		foreach ( $css_groups as $css_theme => $css_templates ) {
			foreach ( $css_templates as $css_template => $css_locations ) {
				foreach ( (array) $css_locations as $css_location => $css_config ) {
					if ( ! empty( $css_config['files'] ) ) {
						$files[] = $this->get_minify_manual_url( $css_theme, $css_template, $css_location, 'css' );
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Checks whether minification can be applied based on the current environment.
	 *
	 * @return bool True if minification can proceed, false otherwise.
	 */
	public function can_minify() {
		// Skip if doint AJAX.
		if ( defined( 'DOING_AJAX' ) ) {
			$this->minify_reject_reason = 'Doing AJAX';

			return false;
		}

		// Skip if doing cron.
		if ( defined( 'DOING_CRON' ) ) {
			$this->minify_reject_reason = 'Doing cron';

			return false;
		}

		// Skip if APP request.
		if ( defined( 'APP_REQUEST' ) ) {
			$this->minify_reject_reason = 'Application request';

			return false;
		}

		// Skip if XMLRPC request.
		if ( defined( 'XMLRPC_REQUEST' ) ) {
			$this->minify_reject_reason = 'XMLRPC request';

			return false;
		}

		// Skip if Admin.
		if ( defined( 'WP_ADMIN' ) ) {
			$this->minify_reject_reason = 'wp-admin';

			return false;
		}

		// Check for WPMU's and WP's 3.0 short init.
		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			$this->minify_reject_reason = 'Short init';

			return false;
		}

		// Check User agent.
		if ( ! $this->check_ua() ) {
			$this->minify_reject_reason = 'User agent is rejected';

			return false;
		}

		// Check request URI.
		if ( ! $this->check_request_uri() ) {
			$this->minify_reject_reason = 'Request URI is rejected';

			return false;
		}

		// Skip if user is logged in.
		if ( $this->_config->get_boolean( 'minify.reject.logged' ) && ! $this->check_logged_in() ) {
			$this->minify_reject_reason = 'User is logged in';

			return false;
		}

		return true;
	}

	/**
	 * Checks whether minification can be applied to the provided buffer.
	 *
	 * @param string $buffer The buffer to check.
	 *
	 * @return bool True if the buffer can be minified, false otherwise.
	 */
	public function can_minify2( $buffer ) {
		// Check for DONOTMINIFY constant.
		if ( defined( 'DONOTMINIFY' ) && DONOTMINIFY ) {
			$this->minify_reject_reason = 'DONOTMINIFY constant is defined';

			return false;
		}

		// Check feed minify.
		if ( $this->_config->get_boolean( 'minify.html.reject.feed' ) && function_exists( 'is_feed' ) && is_feed() ) {
			$this->minify_reject_reason = 'Feed is rejected';

			return false;
		}

		return true;
	}

	/**
	 * Validates the user agent for minification.
	 *
	 * @return bool True if the user agent is allowed, false otherwise.
	 */
	public function check_ua() {
		$uas = array_merge(
			$this->_config->get_array( 'minify.reject.ua' ),
			array(
				W3TC_POWERED_BY,
			)
		);

		foreach ( $uas as $ua ) {
			if ( ! empty( $ua ) ) {
				if ( stristr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', $ua ) !== false ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Checks whether the current user is logged in.
	 *
	 * @return bool True if the user is not logged in, false otherwise.
	 */
	public function check_logged_in() {
		foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
			if ( strpos( $cookie_name, 'wordpress_logged_in' ) === 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates the request URI for minification.
	 *
	 * @return bool True if the URI is valid, false otherwise.
	 */
	public function check_request_uri() {
		$auto_reject_uri = array(
			'wp-login',
			'wp-register',
		);

		foreach ( $auto_reject_uri as $uri ) {
			if ( strstr( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', $uri ) !== false ) {
				return false;
			}
		}

		$reject_uri = $this->_config->get_array( 'minify.reject.uri' );
		$reject_uri = array_map( array( '\W3TC\Util_Environment', 'parse_path' ), $reject_uri );

		foreach ( $reject_uri as $expr ) {
			$expr = trim( $expr );
			$expr = str_replace( '~', '\~', $expr );

			if ( '' !== $expr && preg_match( '~' . $expr . '~i', isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ) {
				return false;
			}
		}

		if ( Util_Request::get_string( 'wp_customize' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Collects usage statistics for the current request.
	 *
	 * @param mixed $storage The storage object or array for the statistics.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$o = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );
		$o->w3tc_usage_statistics_of_request( $storage );
	}

	/**
	 * Adds minification-related metrics to the usage statistics.
	 *
	 * @param array $metrics Existing metrics array.
	 *
	 * @return array Modified metrics array.
	 */
	public function w3tc_usage_statistics_metrics( $metrics ) {
		return array_merge(
			$metrics,
			array(
				'minify_requests_total',
				'minify_original_length_css',
				'minify_output_length_css',
				'minify_original_length_js',
				'minify_output_length_js',
			)
		);
	}

	/**
	 * Modifies or stores preload Link headers for page caching.
	 *
	 * @param array $header          The current header being processed.
	 * @param array $header_original The original header details.
	 *
	 * @return array The modified header.
	 */
	public function w3tc_pagecache_set_header( $header, $header_original ) {
		if ( 'Link' === $header_original['n'] && false !== strpos( $header_original['v'], 'rel=preload' ) ) {
			// store preload Link headers in cache.
			$new                = $header_original;
			$new['files_match'] = '\\.html[_a-z]*$';
			return $new;
		}

		return $header;
	}
}

/**
 * Class _W3_MinifyHelpers
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
 */
class _W3_MinifyHelpers {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Initializes the _W3_MinifyHelpers class.
	 *
	 * @param Config $config Configuration instance used for the class.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->config = $config;
		$this->debug  = $config->get_boolean( 'minify.debug' );
	}

	/**
	 * Retrieves the minified URL for a given set of files and type.
	 *
	 * @param array  $files Array of file paths to be minified.
	 * @param string $type  Type of files (e.g., 'css', 'js').
	 *
	 * @return string|null The minified URL or null if no URL is generated.
	 */
	public function get_minify_url_for_files( $files, $type ) {
		$minify_filename = Minify_Core::urls_for_minification_to_minify_filename( $files, $type );
		if ( is_null( $minify_filename ) ) {
			return null;
		}

		$url = Minify_Core::minified_url( $minify_filename );
		$url = Util_Environment::url_to_maybe_https( $url );

		$url = apply_filters( 'w3tc_minify_url_for_files', $url, $files, $type );

		return $url;
	}

	/**
	 * Retrieves the minified content for a given set of files and type.
	 *
	 * @param array  $files Array of file paths to be minified.
	 * @param string $type  Type of files (e.g., 'css', 'js').
	 *
	 * @return string|null The minified content wrapped in HTML or null if no content is available.
	 */
	public function get_minified_content_for_files( $files, $type ) {
		$minify_filename = Minify_Core::urls_for_minification_to_minify_filename( $files, $type );
		if ( is_null( $minify_filename ) ) {
			return null;
		}

		$minify = Dispatcher::component( 'Minify_MinifiedFileRequestHandler' );

		$m = $minify->process( $minify_filename, true );
		if ( ! isset( $m['content'] ) ) {
			return null;
		}

		if ( empty( $m['content'] ) ) {
			return null;
		}

		$style = $m['content'];

		return "<style media=\"all\">$style</style>\r\n";
	}

	/**
	 * Generates a script tag for a given URL and embed type.
	 *
	 * @param string $url        URL of the script.
	 * @param string $embed_type Type of embed (e.g., 'blocking', 'nb-js', 'nb-async').
	 *
	 * @return string The generated script tag.
	 */
	public function generate_script_tag( $url, $embed_type = 'blocking' ) {
		static $non_blocking_function = false;

		$rocket_loader_ignore = '';
		if ( $this->config->get_boolean( array( 'cloudflare', 'minify_js_rl_exclude' ) ) ) {
			$rocket_loader_ignore = 'data-cfasync="false"';
		}

		if ( 'blocking' === $embed_type ) {
			$script = '<script ' . $rocket_loader_ignore . ' src="' . str_replace( '&', '&amp;', $url ) . '"></script>';
		} else {
			$script = '';

			if ( 'nb-js' === $embed_type ) {
				if ( ! $non_blocking_function ) {
					$non_blocking_function = true;
					$script                = "<script>function w3tc_load_js(u){var d=document,p=d.getElementsByTagName('HEAD')[0],c=d.createElement('script');c.src=u;p.appendChild(c);}</script>";
				}

				$script .= "<script>w3tc_load_js('" . $url . "');</script>";

			} elseif ( 'nb-async' === $embed_type ) {
				$script = '<script ' . $rocket_loader_ignore . ' async src="' . str_replace( '&', '&amp;', $url ) . '"></script>';
			} elseif ( 'nb-defer' === $embed_type ) {
				$script = '<script ' . $rocket_loader_ignore . ' defer src="' . str_replace( '&', '&amp;', $url ) . '"></script>';
			} elseif ( 'extsrc' === $embed_type ) {
				$script = '<script ' . $rocket_loader_ignore . ' extsrc="' . str_replace( '&', '&amp;', $url ) . '"></script>';
			} elseif ( 'asyncsrc' === $embed_type ) {
				$script = '<script ' . $rocket_loader_ignore . ' asyncsrc="' . str_replace( '&', '&amp;', $url ) . '"></script>';
			} else {
				$script = '<script ' . $rocket_loader_ignore . ' src="' . str_replace( '&', '&amp;', $url ) . '"></script>';
			}
		}

		return $script . "\r\n";
	}

	/**
	 * Determines whether a given file or URL should be minified.
	 *
	 * @param string $url  URL of the file to check.
	 * @param string $file File path to check (optional).
	 *
	 * @return string Indicates the type of minification ('url', 'file', or empty string).
	 */
	public function is_file_for_minification( $url, $file ) {
		static $external;
		static $external_regexp;
		if ( ! isset( $external ) ) {
			$external        = $this->config->get_array( 'minify.cache.files' );
			$external_regexp = $this->config->get_boolean( 'minify.cache.files_regexp' );
		}

		foreach ( $external as $item ) {
			if ( empty( $item ) ) {
				continue;
			}

			if ( $external_regexp ) {
				$item = str_replace( '~', '\~', $item );
				if ( ! preg_match( '~' . $item . '~', $url ) ) {
					continue;
				}
			} elseif ( ! preg_match( '~^' . Util_Environment::get_url_regexp( $item ) . '~', $url ) ) {
				continue;
			}

			if ( $this->debug ) {
				Minify_Core::log( 'is_file_for_minification: whilelisted ' . $url . ' by ' . $item );
			}

			return 'url';
		}

		if ( is_null( $file ) ) {
			if ( $this->debug ) {
				Minify_Core::log( 'is_file_for_minification: external not whitelisted url ' . $url );
			}

			return '';
		}

		$file_normalized = Util_Environment::remove_query_all( $file );
		$ext             = strrchr( $file_normalized, '.' );

		if ( '.js' !== $ext && '.css' !== $ext ) {
			if ( $this->debug ) {
				Minify_Core::log( 'is_file_for_minification: unknown extension ' . $ext . ' for ' . $file );
			}

			return '';
		}

		$path = Util_Environment::docroot_to_full_filename( $file );

		if ( ! file_exists( $path ) ) {
			if ( $this->debug ) {
				Minify_Core::log( 'is_file_for_minification: file doesnt exists ' . $path );
			}

			return '';
		}

		if ( $this->debug ) {
			Minify_Core::log( 'is_file_for_minification: true for file ' . $file . ' path ' . $path );
		}

		return 'file';
	}

	/**
	 * Adds an HTTP/2 header for preloading a given URL.
	 *
	 * @param string $url  URL to be preloaded.
	 * @param string $type Resource type (e.g., 'script', 'style').
	 *
	 * @return void
	 */
	public function http2_header_add( $url, $type ) {
		if ( empty( $url ) ) {
			return;
		}

		// Cloudflare needs URI without host.
		$uri = Util_Environment::url_to_uri( $url );

		// priorities attached:
		// 3000 - cdn
		// 4000 - browsercache.
		$data = apply_filters(
			'w3tc_minify_http2_preload_url',
			array(
				'result_link'  => $uri,
				'original_url' => $url,
			)
		);

		header( 'Link: <' . $data['result_link'] . '>; rel=preload; as=' . $type, false );
	}

	/**
	 * Generates a CSS style tag or URL for embedding styles.
	 *
	 * @param array $files         Array of CSS file paths.
	 * @param bool  $embed_to_html Whether to embed the CSS content directly into the HTML.
	 *
	 * @return array Contains 'url' (string|null) and 'body' (string) keys.
	 */
	public function generate_css_style_tag( $files, $embed_to_html ) {
		$return = array(
			'url'  => null,
			'body' => '',
		);

		if ( count( $files ) ) {
			if ( $embed_to_html ) {
				$body = $this->get_minified_content_for_files( $files, 'css' );
				if ( ! is_null( $body ) ) {
					$return['body'] = $body;
				}
			}

			if ( empty( $return['body'] ) ) {
				$return['url'] = $this->get_minify_url_for_files( $files, 'css' );
				if ( ! is_null( $return['url'] ) ) {
					$return['body'] = '<link rel="stylesheet" href="' . // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
						str_replace( '&', '&amp;', $return['url'] ) . "\" media=\"all\" />\r\n";
				}
			}
		}

		return $return;
	}
}
