<?php
/**
 * File: Dispatcher.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Dispatcher
 *
 * Interplugin communication
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Dispatcher {
	/**
	 * Instances
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Retrieves a component instance by class name.
	 *
	 * @param string $class_name The class name of the component to retrieve.
	 *
	 * @return object The requested component instance.
	 */
	public static function component( $class_name ) {
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			$full_class                     = '\\W3TC\\' . $class_name;
			self::$instances[ $class_name ] = new $full_class();
		}

		$v = self::$instances[ $class_name ]; // Don't return reference.
		return $v;
	}

	/**
	 * Retrieves the configuration component.
	 *
	 * @return object The configuration component instance.
	 */
	public static function config() {
		return self::component( 'Config' );
	}

	/**
	 * Resets the configuration component.
	 *
	 * @return void
	 */
	public static function reset_config() {
		unset( self::$instances['Config'] );
	}

	/**
	 * Retrieves the master configuration instance.
	 *
	 * @return object The master configuration instance.
	 */
	public static function config_master() {
		static $config_master = null;

		if ( is_null( $config_master ) ) {
			$config_master = new Config( 0 );
		}

		return $config_master;
	}

	/**
	 * Retrieves the state-specific configuration instance.
	 *
	 * @return object The state-specific configuration instance.
	 */
	public static function config_state() {
		if ( Util_Environment::blog_id() <= 0 ) {
			return self::config_state_master();
		}

		static $config_state = null;

		if ( is_null( $config_state ) ) {
			$config_state = new ConfigState( false );
		}

		return $config_state;
	}

	/**
	 * Retrieves the master state configuration instance.
	 *
	 * @return object The master state configuration instance.
	 */
	public static function config_state_master() {
		static $config_state = null;

		if ( is_null( $config_state ) ) {
			$config_state = new ConfigState( true );
		}

		return $config_state;
	}

	/**
	 * Retrieves the configuration state note instance.
	 *
	 * @return object The configuration state note instance.
	 */
	public static function config_state_note() {
		static $o = null;

		if ( is_null( $o ) ) {
			$o = new ConfigStateNote( self::config_state_master(), self::config_state() );
		}

		return $o;
	}

	/**
	 * Checks if a given URL has been uploaded to the CDN.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL is uploaded to the CDN, false otherwise.
	 */
	public static function is_url_cdn_uploaded( $url ) {
		$minify_enabled = self::config()->get_boolean( 'minify.enabled' );
		if ( $minify_enabled ) {
			$minify = self::component( 'Minify_MinifiedFileRequestHandler' );
			$data   = $minify->get_url_custom_data( $url );
			if ( is_array( $data ) && isset( $data['cdn.status'] ) && 'uploaded' === $data['cdn.status'] ) {
				return true;
			}
		}
		// supported only for minify-based urls, futher is not needed now.
		return false;
	}

	/**
	 * Creates a file for CDN processing.
	 *
	 * @param string $filename The name of the file to create.
	 *
	 * @return void
	 */
	public static function create_file_for_cdn( $filename ) {
		$minify_enabled = self::config()->get_boolean( 'minify.enabled' );
		if ( $minify_enabled ) {
			$minify_document_root = Util_Environment::cache_blog_dir( 'minify' ) . '/';

			if ( ! substr( $filename, 0, strlen( $minify_document_root ) ) === $minify_document_root ) {
				// unexpected file name.
				return;
			}

			$short_filename = substr( $filename, strlen( $minify_document_root ) );
			$minify         = self::component( 'Minify_MinifiedFileRequestHandler' );

			$data = $minify->process( $short_filename, true );

			if ( ! file_exists( $filename ) && isset( $data['content'] ) ) {
				if ( ! file_exists( dirname( $filename ) ) ) {
					Util_File::mkdir_from_safe( dirname( $filename ), W3TC_CACHE_DIR );
				}
			}
			@file_put_contents( $filename, $data['content'] );
		}
	}

	/**
	 * Marks a file as uploaded to the CDN.
	 *
	 * @param string $file_name The name of the file.
	 *
	 * @return void
	 */
	public static function on_cdn_file_upload( $file_name ) {
		$minify_enabled = self::config()->get_boolean( 'minify.enabled' );
		if ( $minify_enabled ) {
			$minify_document_root = Util_Environment::cache_blog_dir( 'minify' ) . '/';

			if ( ! substr( $file_name, 0, strlen( $minify_document_root ) ) === $minify_document_root ) {
				// unexpected file name.
				return;
			}

			$short_file_name = substr( $file_name, strlen( $minify_document_root ) );
			$minify          = self::component( 'Minify_MinifiedFileRequestHandler' );
			$minify->set_file_custom_data( $short_file_name, array( 'cdn.status' => 'uploaded' ) );
		}
	}

	/**
	 * Generates Nginx rules for a browser cache section.
	 *
	 * @todo change to filters, like litespeed does
	 *
	 * @param object $config               The configuration object.
	 * @param string $section              The specific section for which to generate rules.
	 * @param bool   $extra_add_headers_set Whether additional headers are included.
	 *
	 * @return array The generated Nginx rules.
	 */
	public static function nginx_rules_for_browsercache_section( $config, $section, $extra_add_headers_set = false ) {
		$rules = array(
			'other'      => array(),
			'add_header' => array(),
		);
		if ( $config->get_boolean( 'browsercache.enabled' ) ) {
			$o     = new BrowserCache_Environment_Nginx( $config );
			$rules = $o->section_rules( $section, $extra_add_headers_set );
		}

		if ( ! empty( $rules['add_header'] ) && $config->get_boolean( 'cdn.enabled' ) ) {
			$o    = new Cdn_Environment_Nginx( $config );
			$rule = $o->generate_canonical();

			if ( ! empty( $rule ) ) {
				$rules['add_header'][] = $rule;
			}
		}

		return array_merge( $rules['other'], $rules['add_header'] );
	}

	/**
	 * Retrieves the minify filename requested.
	 *
	 * @param object $config The configuration object.
	 * @param string $file   The file name.
	 *
	 * @return string The processed file name.
	 */
	public static function requested_minify_filename( $config, $file ) {
		// browsercache may alter filestructure, allow it to remove its uniqualizator.
		if ( $config->get_boolean( 'browsercache.enabled' ) &&
			$config->get_boolean( 'browsercache.rewrite' ) ) {
			if ( preg_match( '~(.+)\.([0-9a-z]+)(\.[^.]+)$~', $file, $m ) ) {
				$file = $m[1] . $m[3];
			}
		}
		return $file;
	}

	/**
	 * Retrieves the cache instance for usage statistics.
	 *
	 * @return object The cache instance for usage statistics.
	 */
	public static function get_usage_statistics_cache() {
		static $cache = null;
		if ( is_null( $cache ) ) {
			$c             = self::config();
			$engine_config = null;
			if ( $c->getf_boolean( 'objectcache.enabled' ) ) {
				$provider = self::component( 'ObjectCache_WpObjectCache_Regular' );
			} elseif ( $c->get_boolean( 'dbcache.enabled' ) ) {
				$provider = self::component( 'DbCache_Core' );
			} elseif ( $c->get_boolean( 'pgcache.enabled' ) ) {
				$provider = self::component( 'PgCache_ContentGrabber' );
			} elseif ( $c->get_boolean( 'minify.enabled' ) ) {
				$provider = self::component( 'Minify_Core' );
			} else {
				$engine_config = array( 'engine' => 'file' );
			}

			if ( is_null( $engine_config ) ) {
				$engine_config = $provider->get_usage_statistics_cache_config();
			}

			$engine_config['module']  = 'stats';
			$engine_config['blog_id'] = 0;   // count wpmu-wide stats.

			if ( 'file' === $engine_config['engine'] ) {
				$engine_config['cache_dir'] = Util_Environment::cache_dir( 'stats' );
			}

			$cache = Cache::instance( $engine_config['engine'], $engine_config );
		}

		return $cache;
	}

	/**
	 * Applies usage statistics metrics before initialization and exits.
	 *
	 * @param callable $metrics_function The metrics function to apply.
	 *
	 * @return void
	 */
	public static function usage_statistics_apply_before_init_and_exit( $metrics_function ) {
		$c = self::config();
		if ( ! $c->get_boolean( 'stats.enabled' ) ) {
			exit();
		}

		$core = self::component( 'UsageStatistics_Core' );
		$core->apply_metrics_before_init_and_exit( $metrics_function );
	}
}
