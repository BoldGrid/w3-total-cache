<?php
/**
 * File: Minify_ConfigLabels.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Minify_ContentMinifier
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Minify_ContentMinifier {
	/**
	 * Config
	 *
	 * @var Config
	 */
	public $_config = null;

	/**
	 * Minifiers array
	 *
	 * @var array
	 */
	public $_minifiers = array(
		'combinejs'   => array( '\W3TCL\Minify\Minify_CombineOnly', 'minify' ),
		'combinecss'  => array( '\W3TCL\Minify\Minify_CombineOnly', 'minify' ),
		'js'          => array( '\W3TCL\Minify\JSMin', 'minify' ),
		'yuijs'       => array( '\W3TCL\Minify\Minify_YUICompressor', 'minifyJs' ),
		'ccjs'        => array( '\W3TCL\Minify\Minify_ClosureCompiler', 'minify' ),
		'jsminplus'   => array( '\W3TCL\Minify\JSMinPlus', 'minify' ),
		'googleccjs'  => array( '\W3TCL\Minify\Minify_JS_ClosureCompiler', 'minify' ),
		'css'         => array( '\W3TCL\Minify\Minify_CSS', 'minify' ),
		'yuicss'      => array( '\W3TCL\Minify\Minify_YUICompressor', 'minifyCss' ),
		'cssmin'      => array( '\W3TCL\YuiCssMin\Minifier', 'minify_static' ),
		'csstidy'     => array( '\W3TCL\Minify\Minify_CSSTidy', 'minify' ),
		'html'        => array( '\W3TCL\Minify\Minify_HTML', 'minify' ),
		'htmlxml'     => array( '\W3TCL\Minify\Minify_HTML', 'minify' ),
		'htmltidy'    => array( '\W3TCL\Minify\Minify_HTMLTidy', 'minifyXhtml' ),
		'htmltidyxml' => array( '\W3TCL\Minify\Minify_HTMLTidy', 'minifyXml' ),
	);

	/**
	 * Constructor for initializing the Minify Content Minifier class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}


	/**
	 * Checks if a minifier engine is available.
	 *
	 * @param string $w3tc_engine The minifier engine to check.
	 *
	 * @return bool True if the engine exists, false otherwise.
	 */
	public function exists( $w3tc_engine ) {
		return isset( $this->_minifiers[ $w3tc_engine ] );
	}

	/**
	 * Checks if the given minifier engine is available with the required files.
	 *
	 * For Java-backed engines (yuijs, yuicss, ccjs) the configured Java
	 * executable is run through `Util_Java::validate()` as part of the
	 * availability check, not only during `init()`.  Callers
	 * (`Minify_Plugin`, `Minify_MinifiedFileRequestHandler`) gate engine
	 * selection on `available()`, so refusing the engine here is what
	 * actually stops a rejected `path.java` from being used by an
	 * `init()` whose return value the caller ignores.
	 *
	 * @param string $w3tc_engine The minifier engine to check.
	 *
	 * @return bool True if the engine is available, false otherwise.
	 */
	public function available( $w3tc_engine ) {
		switch ( $w3tc_engine ) {
			case 'yuijs':
				$path_java = $this->_config->get_string( 'minify.yuijs.path.java' );
				$path_jar  = $this->_config->get_string( 'minify.yuijs.path.jar' );

				return false !== Util_Java::validate_with_log( $path_java, 'yuijs' )
					&& file_exists( $path_jar );

			case 'yuicss':
				$path_java = $this->_config->get_string( 'minify.yuicss.path.java' );
				$path_jar  = $this->_config->get_string( 'minify.yuicss.path.jar' );

				return false !== Util_Java::validate_with_log( $path_java, 'yuicss' )
					&& file_exists( $path_jar );

			case 'ccjs':
				$path_java = $this->_config->get_string( 'minify.ccjs.path.java' );
				$path_jar  = $this->_config->get_string( 'minify.ccjs.path.jar' );

				return false !== Util_Java::validate_with_log( $path_java, 'ccjs' )
					&& file_exists( $path_jar );

			case 'htmltidy':
			case 'htmltidyxml':
				return class_exists( 'tidy' );
		}

		return $this->exists( $w3tc_engine );
	}

	/**
	 * Retrieves the specified minifier engine.
	 *
	 * @param string $w3tc_engine The minifier engine to retrieve.
	 *
	 * @return mixed|null The minifier engine or null if not found.
	 */
	public function get_minifier( $w3tc_engine ) {
		if ( isset( $this->_minifiers[ $w3tc_engine ] ) ) {
			return $this->_minifiers[ $w3tc_engine ];
		}

		return null;
	}

	/**
	 * Initializes the given minifier engine.
	 *
	 * Java-backed engines re-run `Util_Java::validate()` and refuse to
	 * assign the vendored static `$javaExecutable` when the configured
	 * path is rejected; in that case the method returns `false` so
	 * callers that want to fall back can do so. Note that `available()`
	 * already runs the same allowlist check (via `validate_with_log()`,
	 * which emits the minify-debug log entry once per request), so a
	 * properly-gated caller will not reach `init()` with a bad path in
	 * the first place — the return value here is defense-in-depth.
	 * Using plain `validate()` rather than `validate_with_log()` here
	 * avoids double-logging the same rejection from `available()` and
	 * `init()` on the rare path where both run.
	 *
	 * @param string $w3tc_engine The minifier engine to initialize.
	 *
	 * @return bool True on success, false if a Java-backed engine was
	 *              rejected by the allowlist.
	 */
	public function init( $w3tc_engine ) {
		switch ( $w3tc_engine ) {
			case 'yuijs':
				$java = Util_Java::validate( $this->_config->get_string( 'minify.yuijs.path.java' ) );
				if ( false === $java ) {
					return false;
				}
				\W3TCL\Minify\Minify_YUICompressor::$tempDir        = Util_File::create_tmp_dir();
				\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = $java;
				\W3TCL\Minify\Minify_YUICompressor::$jarFile        = $this->_config->get_string( 'minify.yuijs.path.jar' );
				return true;

			case 'yuicss':
				$java = Util_Java::validate( $this->_config->get_string( 'minify.yuicss.path.java' ) );
				if ( false === $java ) {
					return false;
				}
				\W3TCL\Minify\Minify_YUICompressor::$tempDir        = Util_File::create_tmp_dir();
				\W3TCL\Minify\Minify_YUICompressor::$javaExecutable = $java;
				\W3TCL\Minify\Minify_YUICompressor::$jarFile        = $this->_config->get_string( 'minify.yuicss.path.jar' );
				return true;

			case 'ccjs':
				$java = Util_Java::validate( $this->_config->get_string( 'minify.ccjs.path.java' ) );
				if ( false === $java ) {
					return false;
				}
				\W3TCL\Minify\Minify_ClosureCompiler::$tempDir        = Util_File::create_tmp_dir();
				\W3TCL\Minify\Minify_ClosureCompiler::$javaExecutable = $java;
				\W3TCL\Minify\Minify_ClosureCompiler::$jarFile        = $this->_config->get_string( 'minify.ccjs.path.jar' );
				return true;
		}

		return true;
	}

	/**
	 * Retrieves the options for a specific minifier engine.
	 *
	 * @param string $w3tc_engine The minifier engine to retrieve options for.
	 *
	 * @return array The options for the given engine.
	 */
	public function get_options( $w3tc_engine ) {
		$options = array();

		switch ( $w3tc_engine ) {
			case 'js':
				$options = array(
					'preserveComments' => ! $this->_config->get_boolean( 'minify.js.strip.comments' ),
					'stripCrlf'        => $this->_config->get_boolean( 'minify.js.strip.crlf' ),
				);
				break;

			case 'css':
				$options = array(
					'preserveComments' => ! $this->_config->get_boolean( 'minify.css.strip.comments' ),
					'stripCrlf'        => $this->_config->get_boolean( 'minify.css.strip.crlf' ),
				);

				$symlinks = $this->_config->get_array( 'minify.symlinks' );
				$docroot  = Util_Environment::document_root();

				foreach ( $symlinks as $link => $target ) {
					$link                         = str_replace( '//', realpath( $docroot ), $link );
					$link                         = strtr( $link, '/', DIRECTORY_SEPARATOR );
					$options['symlinks'][ $link ] = realpath( $target );
				}
				break;

			case 'yuijs':
				$options = Util_Java::sanitize_yui_options(
					array(
						'line-break'            => $this->_config->get_integer( 'minify.yuijs.options.line-break' ),
						'nomunge'               => $this->_config->get_boolean( 'minify.yuijs.options.nomunge' ),
						'preserve-semi'         => $this->_config->get_boolean( 'minify.yuijs.options.preserve-semi' ),
						'disable-optimizations' => $this->_config->get_boolean( 'minify.yuijs.options.disable-optimizations' ),
					)
				);
				break;

			case 'yuicss':
				$options = Util_Java::sanitize_yui_options(
					array(
						'line-break' => $this->_config->get_integer( 'minify.yuicss.options.line-break' ),
					)
				);
				break;

			case 'ccjs':
				$options = Util_Java::sanitize_ccjs_options(
					array(
						'compilation_level' => $this->_config->get_string( 'minify.ccjs.options.compilation_level' ),
						'formatting'        => $this->_config->get_string( 'minify.ccjs.options.formatting' ),
					)
				);
				break;

			case 'googleccjs':
				$options = Util_Java::sanitize_ccjs_options(
					array(
						'compilation_level' => $this->_config->get_string( 'minify.ccjs.options.compilation_level' ),
						'formatting'        => $this->_config->get_string( 'minify.ccjs.options.formatting' ),
					)
				);
				break;

			case 'csstidy':
				$options = array(
					'remove_bslash'              => $this->_config->get_boolean( 'minify.csstidy.options.remove_bslash' ),
					'compress_colors'            => $this->_config->get_boolean( 'minify.csstidy.options.compress_colors' ),
					'compress_font-weight'       => $this->_config->get_boolean( 'minify.csstidy.options.compress_font-weight' ),
					'lowercase_s'                => $this->_config->get_boolean( 'minify.csstidy.options.lowercase_s' ),
					'optimise_shorthands'        => $this->_config->get_integer( 'minify.csstidy.options.optimise_shorthands' ),
					'remove_last_;'              => $this->_config->get_boolean( 'minify.csstidy.options.remove_last_;' ),
					'space_before_important'     => ! $this->_config->get_boolean( 'minify.csstidy.options.remove_space_before_important' ),
					'case_properties'            => $this->_config->get_integer( 'minify.csstidy.options.case_properties' ),
					'sort_properties'            => $this->_config->get_boolean( 'minify.csstidy.options.sort_properties' ),
					'sort_selectors'             => $this->_config->get_boolean( 'minify.csstidy.options.sort_selectors' ),
					'merge_selectors'            => $this->_config->get_integer( 'minify.csstidy.options.merge_selectors' ),
					'discard_invalid_selectors'  => $this->_config->get_boolean( 'minify.csstidy.options.discard_invalid_selectors' ),
					'discard_invalid_properties' => $this->_config->get_boolean( 'minify.csstidy.options.discard_invalid_properties' ),
					'css_level'                  => $this->_config->get_string( 'minify.csstidy.options.css_level' ),
					'preserve_css'               => $this->_config->get_boolean( 'minify.csstidy.options.preserve_css' ),
					'timestamp'                  => $this->_config->get_boolean( 'minify.csstidy.options.timestamp' ),
					'template'                   => $this->_config->get_string( 'minify.csstidy.options.template' ),
				);
				break;

			case 'html':
			case 'htmlxml':
				$options = array(
					'xhtml'           => true,
					'stripCrlf'       => $this->_config->get_boolean( 'minify.html.strip.crlf' ),
					'ignoredComments' => $this->_config->get_array( 'minify.html.comments.ignore' ),
				);
				break;

			case 'htmltidy':
			case 'htmltidyxml':
				$options = array(
					'clean'         => $this->_config->get_boolean( 'minify.htmltidy.options.clean' ),
					'hide-comments' => $this->_config->get_boolean( 'minify.htmltidy.options.hide-comments' ),
					'wrap'          => $this->_config->get_integer( 'minify.htmltidy.options.wrap' ),
				);
				break;
		}

		if (
			$this->_config->get_boolean( 'browsercache.enabled' ) &&
			(
				$this->_config->get_boolean( 'browsercache.cssjs.replace' ) ||
				$this->_config->get_boolean( 'browsercache.html.replace' ) ||
				$this->_config->get_boolean( 'browsercache.other.replace' )
			)
		) {
			$w3_plugin_browsercache = Dispatcher::component( 'BrowserCache_Plugin' );
			$browsercache_core      = Dispatcher::component( 'BrowserCache_Core' );

			$options = array_merge(
				$options,
				array(
					'browserCacheId'         => $w3_plugin_browsercache->get_filename_uniqualizator(),
					'browserCacheExtensions' => $browsercache_core->get_replace_extensions( $this->_config ),
				)
			);
		}

		if ( $this->_config->get_boolean( 'cdn.enabled' ) ) {
			$common = Dispatcher::component( 'Cdn_Core' );
			$cdn    = $common->get_cdn();

			$options = array_merge(
				$options,
				array(
					'prependAbsolutePathCallback' => array( &$cdn, 'get_prepend_path' ),
				)
			);
		}

		return $options;
	}
}
