<?php
/**
 * Dispatcher stub for the standalone MAx Cache tests.
 *
 * Extension_MaxCache_Core asks Dispatcher for the shared PgCache_Environment; the standalone tests
 * bootstrap no container. Its own file because the tests live in the global namespace.
 *
 * @package W3TC\Tests
 *
 * @since X.X.X
 */

namespace W3TC;

/*
 * Declared only when a standalone test asked for it, by name. This file is reached two other
 * ways: the plugin directory is web-reachable once deployed, and phpunit.xml collects every .php
 * under tests/ - and there the real Dispatcher is what must answer. Guessing from the
 * surroundings is not good enough, because a crippled Dispatcher declared for a whole PHPUnit run
 * turns every Dispatcher::config() in the suite into a fatal.
 */
if ( ! \defined( 'W3TC_MAXCACHE_STANDALONE_TEST' ) ) {
	return;
}

// Without autoload: whether the real class can be reached must not decide this.
if ( ! \class_exists( __NAMESPACE__ . '\\Dispatcher', false ) ) {
	/**
	 * Minimal Dispatcher.
	 */
	/**
	 * Configuration answering with nothing, for lookups no case is speaking about.
	 */
	class Dispatcher_Config_Stub {
		/**
		 * Returns an empty list.
		 *
		 * @param string $key Option name.
		 *
		 * @return array
		 */
		public function get_array( $key ) {
			return array();
		}

		/**
		 * Returns false.
		 *
		 * @param string $key Option name.
		 *
		 * @return bool
		 */
		public function get_boolean( $key ) {
			return false;
		}

		/**
		 * Returns an empty string.
		 *
		 * @param string $key Option name.
		 *
		 * @return string
		 */
		public function get_string( $key ) {
			return '';
		}

		/**
		 * Returns zero.
		 *
		 * @param string $key Option name.
		 *
		 * @return int
		 */
		public function get_integer( $key ) {
			return 0;
		}

		/**
		 * Returns an empty string.
		 *
		 * @param string $key Option name.
		 *
		 * @return mixed
		 */
		public function get( $key ) {
			return '';
		}
	}

	class Dispatcher {
		/**
		 * Returns the configuration a case put in place.
		 *
		 * Filter callbacks take no config argument and fetch it here, so a case that exercises one
		 * has to say which configuration it is speaking about.
		 *
		 * @return object
		 */
		public static function config() {
			// Util_Environment::trusted_proxy_cidrs() reaches here on every is_https() call, including from
			// cases that never set a configuration up, and swallows the resulting error.
			return $GLOBALS['w3tc_maxcache_test_config'] ?? new Dispatcher_Config_Stub();
		}

		/**
		 * Returns a state store backed by an array, for the dismissable-note cases.
		 *
		 * @return object
		 */
		public static function config_state_master() {
			return new Dispatcher_State_Stub();
		}

		/**
		 * Returns a component instance.
		 *
		 * @param string $class_name Component name, without the namespace.
		 *
		 * @return object
		 */
		/**
		 * Doubles a case installed for one component name, keyed by that name.
		 *
		 * @var array<string,object>
		 */
		public static $components = array();

		public static function component( $class_name ) {
			if ( isset( self::$components[ $class_name ] ) ) {
				return self::$components[ $class_name ];
			}

			$full = __NAMESPACE__ . '\\' . $class_name;

			return new $full();
		}
	}
}

if ( ! \class_exists( __NAMESPACE__ . '\\Dispatcher_State_Stub', false ) ) {
	/**
	 * Minimal config-state store.
	 */
	class Dispatcher_State_Stub {
		/**
		 * Returns a stored flag.
		 *
		 * @param string $key State key.
		 *
		 * @return bool
		 */
		public function get_boolean( $key ) {
			return ! empty( $GLOBALS['w3tc_maxcache_test_state'][ $key ] );
		}

		/**
		 * Stores a flag.
		 *
		 * @param string $key   State key.
		 * @param mixed  $value Value.
		 *
		 * @return void
		 */
		public function set( $key, $value ) {
			$GLOBALS['w3tc_maxcache_test_state'][ $key ] = $value;
		}

		/**
		 * Saving is a no-op here.
		 *
		 * @return void
		 */
		public function save() {
		}
	}
}

if ( ! \class_exists( __NAMESPACE__ . '\\Util_Ui', false ) ) {
	/**
	 * Minimal Util_Ui: the note cases need an admin URL and a dismiss button, not the real markup.
	 */
	class Util_Ui {
		/**
		 * Returns an admin URL.
		 *
		 * @param string $path Path.
		 *
		 * @return string
		 */
		public static function admin_url( $path = '' ) {
			return 'https://example.org/wp-admin/' . $path;
		}

		/**
		 * Returns a dismiss button.
		 *
		 * @param array $parameters Button parameters.
		 *
		 * @return string
		 */
		public static function button_hide_note2( $parameters ) {
			return '<input type="button" value="Hide this message" />';
		}

		/**
		 * Returns a link rendered as a button.
		 *
		 * @param string $text     Button text.
		 * @param string $w3tc_url Target.
		 *
		 * @return string
		 */
		public static function button_link( $text, $w3tc_url ) {
			return '<input type="button" value="' . $text . '" data-href="' . $w3tc_url . '" />';
		}
	}
}

if ( ! \class_exists( __NAMESPACE__ . '\\ConfigKeysSchema', false ) ) {
	/**
	 * Minimal configuration-schema stand-in: only the shipped defaults are read.
	 */
	class ConfigKeysSchema {
		/**
		 * Returns the descriptor for a configuration key.
		 *
		 * @param string $w3tc_key Option name.
		 *
		 * @return array|null
		 */
		public static function descriptor( $w3tc_key ) {
			$defaults = array(
				'pgcache.accept.files' => array( 'wp-comments-popup.php', 'wp-links-opml.php', 'wp-locations.php' ),
			);

			return isset( $defaults[ $w3tc_key ] ) ? array( 'default' => $defaults[ $w3tc_key ] ) : null;
		}
	}
}

if ( ! \function_exists( __NAMESPACE__ . '\\test_pin_installed_flags' ) ) {
	/**
	 * Pins $nginx_installed/$apache_installed via reflection, shared by every standalone suite.
	 *
	 * @since X.X.X
	 *
	 * @param bool $nginx  Whether the NGINX build reads as installed.
	 * @param bool $apache Whether the Apache build reads as installed.
	 *
	 * @return void
	 */
	function test_pin_installed_flags( $nginx, $apache ) {
		foreach ( array( 'nginx_installed' => $nginx, 'apache_installed' => $apache ) as $name => $value ) {
			$property = new \ReflectionProperty( '\W3TC\Extension_MaxCache_Core', $name );
			$property->setAccessible( true );
			$property->setValue( null, $value );
		}
	}
}

if ( ! \function_exists( __NAMESPACE__ . '\\test_pin_mode' ) ) {
	/**
	 * Pins $mode via reflection, shared by every standalone suite.
	 *
	 * @since X.X.X
	 *
	 * @param string $mode One of `apache`, `nginx`, `none`.
	 *
	 * @return void
	 */
	function test_pin_mode( $mode ) {
		$property = new \ReflectionProperty( '\W3TC\Extension_MaxCache_Core', 'mode' );
		$property->setAccessible( true );
		$property->setValue( null, $mode );
	}
}

if ( ! \function_exists( __NAMESPACE__ . '\\test_reset_host' ) ) {
	/**
	 * Nulls $mode/$levels/$nginx_installed/$apache_installed, shared by every standalone suite.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	function test_reset_host() {
		foreach (
			array(
				'mode'             => null,
				'levels'           => array(),
				'nginx_installed'  => null,
				'apache_installed' => null,
			) as $name => $empty
		) {
			$property = new \ReflectionProperty( '\W3TC\Extension_MaxCache_Core', $name );
			$property->setAccessible( true );
			$property->setValue( null, $empty );
		}
	}
}

if ( ! \function_exists( __NAMESPACE__ . '\\test_pin_single_build_mode' ) ) {
	/**
	 * Pins $mode and the matching one-build-only install flags, shared by every standalone suite.
	 *
	 * @since X.X.X
	 *
	 * @param string $mode One of `apache`, `nginx`, `none`.
	 *
	 * @return void
	 */
	function test_pin_single_build_mode( $mode ) {
		test_pin_mode( $mode );
		test_pin_installed_flags( 'nginx' === $mode, 'apache' === $mode );
	}
}
