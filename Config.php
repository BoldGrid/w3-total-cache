<?php
/**
 * File: Cli.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Config
 *
 * Provides configuration data using cache
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
 */
class Config {
	/**
	 * Blog ID of loaded config
	 *
	 * @var integer
	 */
	private $_blog_id;

	/**
	 * Is master flag
	 *
	 * @var bool
	 */
	private $_is_master;

	/**
	 * Is this preview config
	 *
	 * @var boolean
	 */
	private $_preview;

	/**
	 * First 20 digits of data MD5
	 *
	 * @var integer
	 */
	private $_md5;

	/**
	 * Data
	 *
	 * @var array
	 */
	private $_data;

	/**
	 * Compiled flag
	 *
	 * @var bool
	 */
	private $_compiled;

	/**
	 * Retrieves a configuration array from cache storage if enabled and present, otherwise retrieves from
	 * database/file via _util_array_from_storage private method
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return array|null The configuration array or null if not found.
	 */
	public static function util_array_from_storage( $blog_id, $preview ) {
		if ( ! defined( 'W3TC_CONFIG_CACHE_ENGINE' ) ) {
			return self::_util_array_from_storage( $blog_id, $preview );
		}

		// config cache enabled.
		$w3tc_config = ConfigCache::util_array_from_storage( $blog_id, $preview );
		if ( ! is_null( $w3tc_config ) ) {
			return $w3tc_config;
		}

		$w3tc_config = self::_util_array_from_storage( $blog_id, $preview );
		ConfigCache::save_item( $blog_id, $preview, $w3tc_config );

		return $w3tc_config;
	}

	/**
	 * Retrieves a configuration array from database/file storage.
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return array|null The configuration array or null if not found.
	 */
	private static function _util_array_from_storage( $blog_id, $preview ) {
		if ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) {
			return ConfigDbStorage::util_array_from_storage( $blog_id, $preview );
		}

		$filename = self::util_config_filename( $blog_id, $preview );
		if ( file_exists( $filename ) && is_readable( $filename ) ) {
			/**
			 * Including file directly instead of read+eval causes constant problems with APC, ZendCache, and
			 * WSOD in a case of broken config file.
			 */
			$content     = @file_get_contents( $filename );
			$w3tc_config = @json_decode( substr( $content, 14 ), true );

			if ( is_array( $w3tc_config ) ) {
				return $w3tc_config;
			}
		}

		return null;
	}

	/**
	 * Retrieves the filename for the configuration.
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return string The configuration file path.
	 */
	public static function util_config_filename( $blog_id, $preview ) {
		$postfix = ( $preview ? '-preview' : '' ) . '.php';

		if ( $blog_id <= 0 ) {
			$filename = W3TC_CONFIG_DIR . '/master' . $postfix;
		} else {
			$filename = W3TC_CONFIG_DIR . '/' . sprintf( '%06d', $blog_id ) . $postfix;
		}

		$d = w3tc_apply_filters(
			'config_filename',
			array(
				'blog_id'  => $blog_id,
				'preview'  => $preview,
				'filename' => $filename,
			)
		);

		return $d['filename'];
	}

	/**
	 * Retrieves the legacy configuration filename.
	 *
	 * @param int  $blog_id  The ID of the blog.
	 * @param bool $preview  Whether to load the preview configuration.
	 *
	 * @return string The legacy configuration file path.
	 */
	public static function util_config_filename_legacy_v2( $blog_id, $preview ) {
		$postfix = ( $preview ? '-preview' : '' ) . '.json';

		if ( $blog_id <= 0 ) {
			return W3TC_CONFIG_DIR . '/master' . $postfix;
		} else {
			return W3TC_CONFIG_DIR . '/' . sprintf( '%06d', $blog_id ) . $postfix;
		}
	}

	/**
	 * Constructor to initialize configuration for a given blog.
	 *
	 * @param int|null $blog_id The ID of the blog, or null to determine based on environment.
	 */
	public function __construct( $blog_id = null ) {
		if ( ! is_null( $blog_id ) ) {
			$this->_blog_id   = $blog_id;
			$this->_is_master = ( 0 === $this->_blog_id );
		} else {
			if ( Util_Environment::is_using_master_config() ) {
				$this->_blog_id = 0;
			} else {
				$this->_blog_id = Util_Environment::blog_id();
			}

			$this->_is_master = ( 0 === Util_Environment::blog_id() );
		}

		$this->_preview = Util_Environment::is_preview_mode();
		$this->load();
	}

	/**
	 * Retrieves a configuration value for a given key or returns cached/uncached default value if not found.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param mixed  $default_value The default value to return if the key is not found.
	 *
	 * @return mixed The configuration value, or the default if not found.
	 */
	public function get( $w3tc_key, $default_value = null ) {
		$v = $this->_get( $this->_data, $w3tc_key );
		if ( ! is_null( $v ) ) {
			return $v;
		}

		// take default value.
		if ( ! empty( $default_value ) || ! function_exists( 'apply_filters' ) ) {
			return $default_value;
		}

		// try cached default values.
		static $default_values = null;
		if ( is_null( $default_values ) ) {
			$default_values = apply_filters( 'w3tc_config_default_values', array() );
		}

		$v = $this->_get( $default_values, $w3tc_key );
		if ( ! is_null( $v ) ) {
			return $v;
		}

		// update default values.
		$default_values = apply_filters( 'w3tc_config_default_values', array() );

		$v = $this->_get( $default_values, $w3tc_key );
		if ( ! is_null( $v ) ) {
			return $v;
		}

		return $default_value;
	}

	/**
	 * Retrieves a configuration value for a given key.
	 *
	 * @param array  $w3tc_a   The array to search in.
	 * @param string $w3tc_key The key to look up in the array.
	 *
	 * @return mixed The configuration value, or null if not found.
	 */
	private function _get( &$w3tc_a, $w3tc_key ) {
		if ( is_array( $w3tc_key ) ) {
			$key0 = $w3tc_key[0];
			if ( isset( $w3tc_a[ $key0 ] ) ) {
				$key1 = $w3tc_key[1];
				if ( isset( $w3tc_a[ $key0 ][ $key1 ] ) ) {
					self::_maybe_lazy_decrypt( $w3tc_a[ $key0 ][ $key1 ] );
					return $w3tc_a[ $key0 ][ $key1 ];
				}
			}
		} elseif ( isset( $w3tc_a[ $w3tc_key ] ) ) {
			self::_maybe_lazy_decrypt( $w3tc_a[ $w3tc_key ] );
			return $w3tc_a[ $w3tc_key ];
		}

		return null;
	}

	/**
	 * Lazily decrypts an `enc:v1:...` envelope value in place on first read.
	 *
	 * Companion to the early-bootstrap skip in {@see self::decrypt_secrets()}:
	 * `advanced-cache.php` loads BEFORE `wp-includes/pluggable.php`, so any
	 * `Dispatcher::config()` call made during the page-cache drop-in runs
	 * `load()` while `wp_salt()` is still undefined. We skip the eager
	 * decrypt in that window so {@see Util_Crypto::derive_key()} doesn't
	 * take its `SECURE_AUTH_KEY|AUTH_KEY` fallback branch — which derives a
	 * different key than `wp_salt('secure_auth')` and would HMAC-fail every
	 * secret, collapsing each one to `''` for the rest of the request.
	 *
	 * By the time admin / settings / CDN code calls `$w3tc_config->get_string()`,
	 * `wp_salt()` has loaded, so we decrypt then and cache the plaintext
	 * back into `$_data` so subsequent reads are plain hash lookups.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $w3tc_value Value to inspect; mutated in place when an
	 *                     envelope is successfully decrypted (or collapsed
	 *                     to '' on HMAC tamper).
	 *
	 * @return void
	 */
	private static function _maybe_lazy_decrypt( &$w3tc_value ) {
		if ( ! is_string( $w3tc_value ) || 0 !== strncmp( $w3tc_value, 'enc:v1:', 7 ) ) {
			return;
		}
		if ( ! \function_exists( 'wp_salt' ) || ! class_exists( '\W3TC\Util_Crypto' ) ) {
			return;
		}
		$plain      = Util_Crypto::envelope_decrypt( $w3tc_value );
		$w3tc_value = ( false === $plain ) ? '' : $plain;
	}

	/**
	 * Retrieves a string configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param string $default_value The default string value to return if the key is not found.
	 * @param bool   $trim          Whether to trim the value.
	 *
	 * @return string The configuration value as a string.
	 */
	public function get_string( $w3tc_key, $default_value = '', $trim = true ) {
		$w3tc_value = (string) $this->get( $w3tc_key, $default_value );

		return $trim ? trim( $w3tc_value ) : $w3tc_value;
	}

	/**
	 * Retrieves an integer configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param int    $default_value The default integer value to return if the key is not found.
	 *
	 * @return int The configuration value as an integer.
	 */
	public function get_integer( $w3tc_key, $default_value = 0 ) {
		return (int) $this->get( $w3tc_key, $default_value );
	}

	/**
	 * Retrieves a boolean configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param bool   $default_value The default boolean value to return if the key is not found.
	 *
	 * @return bool The configuration value as a boolean.
	 */
	public function get_boolean( $w3tc_key, $default_value = false ) {
		return (bool) $this->get( $w3tc_key, $default_value );
	}

	/**
	 * Retrieves an array configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param array  $default_value The default array value to return if the key is not found.
	 *
	 * @return array The configuration value as an array.
	 */
	public function get_array( $w3tc_key, $default_value = array() ) {
		return (array) $this->get( $w3tc_key, $default_value );
	}

	/**
	 * Retrieves a filtered configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param mixed  $default_value The default value to return if the key is not found.
	 *
	 * @return mixed The configuration value, potentially filtered.
	 */
	public function getf( $w3tc_key, $default_value = null ) {
		$v = $this->get( $w3tc_key, $default_value );
		return apply_filters( 'w3tc_config_item_' . $w3tc_key, $v );
	}

	/**
	 * Retrieves a filtered string configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param string $default_value The default string value to return if the key is not found.
	 * @param bool   $trim          Whether to trim the value.
	 *
	 * @return string The filtered configuration value as a string.
	 */
	public function getf_string( $w3tc_key, $default_value = '', $trim = true ) {
		$w3tc_value = (string) $this->getf( $w3tc_key, $default_value );

		return $trim ? trim( $w3tc_value ) : $w3tc_value;
	}

	/**
	 * Retrieves a filtered integer configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param int    $default_value The default integer value to return if the key is not found.
	 *
	 * @return int The filtered configuration value as an integer.
	 */
	public function getf_integer( $w3tc_key, $default_value = 0 ) {
		return (int) $this->getf( $w3tc_key, $default_value );
	}

	/**
	 * Retrieves a filtered boolean configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param bool   $default_value The default boolean value to return if the key is not found.
	 *
	 * @return bool The filtered configuration value as a boolean.
	 */
	public function getf_boolean( $w3tc_key, $default_value = false ) {
		return (bool) $this->getf( $w3tc_key, $default_value );
	}

	/**
	 * Retrieves a filtered array configuration value for a given key.
	 *
	 * @param string $w3tc_key           The key to look up in the configuration.
	 * @param array  $default_value The default array value to return if the key is not found.
	 *
	 * @return array The filtered configuration value as an array.
	 */
	public function getf_array( $w3tc_key, $default_value = array() ) {
		return (array) $this->getf( $w3tc_key, $default_value );
	}

	/**
	 * Checks if a specific extension is active in the configuration.
	 *
	 * @param string $w3tc_extension The extension to check.
	 *
	 * @return bool True if the extension is active, false otherwise.
	 */
	public function is_extension_active( $w3tc_extension ) {
		$extensions = $this->get_array( 'extensions.active' );
		return isset( $extensions[ $w3tc_extension ] );
	}

	/**
	 * Checks if a specific extension is active on the frontend.
	 *
	 * @param string $w3tc_extension The extension to check.
	 *
	 * @return bool True if the extension is active on the frontend, false otherwise.
	 */
	public function is_extension_active_frontend( $w3tc_extension ) {
		$extensions = $this->get_array( 'extensions.active_frontend' );
		return isset( $extensions[ $w3tc_extension ] );
	}

	/**
	 * Sets the active frontend extension.
	 *
	 * @param string $w3tc_extension         The extension key to be set as active.
	 * @param bool   $is_active_frontend Whether the extension should be active on the frontend.
	 *
	 * @return void
	 */
	public function set_extension_active_frontend( $w3tc_extension, $is_active_frontend ) {
		$w3tc_a = $this->get_array( 'extensions.active_frontend' );
		if ( ! $is_active_frontend ) {
			unset( $w3tc_a[ $w3tc_extension ] );
		} else {
			$w3tc_a[ $w3tc_extension ] = '*';
		}

		$this->set( 'extensions.active_frontend', $w3tc_a );
	}

	/**
	 * Sets the active dropin extension.
	 *
	 * @param string $w3tc_extension        The extension key to be set as active dropin.
	 * @param bool   $is_active_dropin Whether the extension should be active as a dropin.
	 *
	 * @return void
	 */
	public function set_extension_active_dropin( $w3tc_extension, $is_active_dropin ) {
		$w3tc_a = $this->get_array( 'extensions.active_dropin' );
		if ( ! $is_active_dropin ) {
			unset( $w3tc_a[ $w3tc_extension ] );
		} else {
			$w3tc_a[ $w3tc_extension ] = '*';
		}

		$this->set( 'extensions.active_dropin', $w3tc_a );
	}

	/**
	 * Sets a key-value pair in the configuration data.
	 *
	 * @param string|array $w3tc_key   The key or array of keys to set.
	 * @param mixed        $w3tc_value The value to set.
	 *
	 * @return mixed The value that was set.
	 */
	public function set( $w3tc_key, $w3tc_value ) {
		/**
		 * Strip directive-terminating bytes from values bound for keys whose
		 * stored string (or stored array of strings) is later concatenated
		 * into a `.htaccess` / `nginx.conf` directive. See
		 * `Util_Rule::sanitize_directive_value()` for the strip set and
		 * rationale. Routed through the shared helper rather than
		 * duplicating the regex so a future strip-set extension lands in
		 * one place and stored/rendered values stay in lockstep.
		 *
		 * The same characters are stripped at the renderer boundary too;
		 * this is the upstream defence-in-depth half so the bad bytes
		 * never enter master.php in the first place.
		 */
		if ( ! is_array( $w3tc_key ) && '' !== $w3tc_value ) {
			$desc = self::directive_string_descriptor( $w3tc_key );
			if ( null !== $desc ) {
				if ( is_string( $w3tc_value ) ) {
					$w3tc_value = Util_Rule::sanitize_directive_value( $w3tc_value );
				} elseif ( is_array( $w3tc_value ) ) {
					/**
					 * Directive-bound array keys (`pgcache.reject.cookie`,
					 * `pgcache.reject.ua`, `mobile.rgroups`, etc.) each
					 * element of which is later concatenated into a rule
					 * alternation. Sanitise every scalar entry and drop
					 * any non-scalar (the helper would return '' anyway,
					 * and array_filter strips the empty result so a
					 * fully-stripped entry doesn't widen an `implode( '|', ... )`
					 * regex into a match-everything alternative).
					 */
					$w3tc_value = array_values(
						array_filter(
							array_map(
								function ( $v ) {
									return is_scalar( $v )
										? Util_Rule::sanitize_directive_value( (string) $v )
										: '';
								},
								$w3tc_value
							),
							function ( $v ) {
								return '' !== $v;
							}
						)
					);
				}
			}
		}

		$w3tc_value = self::enforce_enum( $w3tc_key, $w3tc_value, $this );

		if ( ! is_array( $w3tc_key ) ) {
			$this->_data[ $w3tc_key ] = $w3tc_value;
		} else {
			// set extension's key.
			$key0 = $w3tc_key[0];
			$key1 = $w3tc_key[1];

			if ( ! isset( $this->_data[ $key0 ] ) || ! is_array( $this->_data[ $key0 ] ) ) {
				$this->_data[ $key0 ] = array();
			}

			$this->_data[ $key0 ][ $key1 ] = $w3tc_value;
		}

		return $w3tc_value;
	}

	/**
	 * Removes a configuration key from the in-memory data set.
	 *
	 * Intended for one-time migrations that retire keys whose feature
	 * has been removed from the plugin (e.g. a discontinued CDN engine),
	 * so the orphaned key — and any stored secret under it — stops being
	 * written back to `master.php`. The key is dropped from
	 * `$this->_data`; call {@see save()} afterwards to persist the
	 * removal. Removing a key that is not present is a no-op.
	 *
	 * Only top-level string keys are supported; compound
	 * `array( 'extension', 'sub' )` keys are rejected.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_key Config key to remove.
	 *
	 * @return bool True if the key was present and removed; false otherwise.
	 */
	public function unset_key( $w3tc_key ) {
		if ( ! is_string( $w3tc_key ) || ! isset( $this->_data[ $w3tc_key ] ) ) {
			return false;
		}

		unset( $this->_data[ $w3tc_key ] );

		return true;
	}

	/**
	 * Returns the descriptor for a `directive_string`-flagged key, or
	 * null if the key isn't in the schema or doesn't carry the flag.
	 *
	 * Loads `ConfigKeys.php` once per request and caches the slimmed
	 * directive-string map (keyed by config-key name → true). Kept
	 * local to `Config` rather than promoted to a shared schema
	 * accessor because the `directive_string` flag is the only piece
	 * of `ConfigKeys.php` that `Config::set()` consults; promoting
	 * would widen this file's `ConfigKeys.php` surface without a
	 * second consumer to justify it.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_key Single-string config key.
	 *
	 * @return true|null  `true` when the key is flagged; `null` otherwise.
	 */
	private static function directive_string_descriptor( $w3tc_key ) {
		static $set = null;

		if ( null === $set ) {
			$set       = array();
			$w3tc_keys = array();
			include W3TC_DIR . '/ConfigKeys.php';
			if ( is_array( $w3tc_keys ) ) {
				foreach ( $w3tc_keys as $w3tc_name => $w3tc_descriptor ) {
					if (
						is_array( $w3tc_descriptor )
						&& isset( $w3tc_descriptor['flags'] )
						&& is_array( $w3tc_descriptor['flags'] )
						&& ! empty( $w3tc_descriptor['flags']['directive_string'] )
					) {
						$set[ $w3tc_name ] = true;
					}
				}
			}
		}

		return isset( $set[ $w3tc_key ] ) ? true : null;
	}

	/**
	 * Constrains scalar `$w3tc_value` to the enum declared in
	 * {@see ConfigKeys.php} for `$w3tc_key`, when present.
	 *
	 * Schema declares an enum like:
	 *
	 *     'cdn.engine' => array(
	 *         'type'    => 'string',
	 *         'default' => '',
	 *         'enum'    => array( 'ftp', 's3', ... ),
	 *     ),
	 *
	 * Behavior:
	 *
	 *  * If the key has no `enum` entry, return `$w3tc_value` unchanged.
	 *  * If `$w3tc_value` is in the enum, return it unchanged.
	 *  * Otherwise, retain the value already stored under that key
	 *    (or the schema `default`) and emit an audit-log entry. The
	 *    invalid value never reaches `$this->_data`, so downstream
	 *    callers — including the `header()` emitters that
	 *    interpolate `cdn.engine` — only ever see an allowlisted
	 *    slug.
	 *
	 * Top-level keys only (compound `array( 'extension', 'sub' )`
	 * keys skip enforcement); the schema doesn't currently declare
	 * enums on extension subkeys.
	 *
	 * @since 2.10.0
	 *
	 * @param string|array $w3tc_key    Config key (string for top-level, array for extension subkey).
	 * @param mixed        $w3tc_value  The candidate value.
	 * @param Config       $w3tc_config Config instance, used to look up the prior value as fallback.
	 *
	 * @return mixed The original value if allowed, otherwise the prior stored value (or schema default).
	 */
	private static function enforce_enum( $w3tc_key, $w3tc_value, Config $w3tc_config ) {
		if ( ! \is_string( $w3tc_key ) ) {
			return $w3tc_value;
		}

		$schema = self::config_keys_schema();
		if ( ! isset( $schema[ $w3tc_key ]['enum'] ) || ! \is_array( $schema[ $w3tc_key ]['enum'] ) ) {
			return $w3tc_value;
		}

		$enum = $schema[ $w3tc_key ]['enum'];

		/**
		 * Non-scalar values are an immediate reject path. The earlier
		 * shape coerced them to `''` and then ran the enum match — but
		 * `''` is itself a valid enum member for `cdn.engine`, so an
		 * array / object write (e.g. from a malformed import) would
		 * have been *accepted* by the enum check and overwritten the
		 * prior value. Route non-scalars straight to the fallback
		 * branch below so they never normalise to an allowed slug.
		 */
		if ( \is_scalar( $w3tc_value ) ) {
			$value_string = (string) $w3tc_value;

			if ( \in_array( $value_string, $enum, true ) ) {
				/**
				 * Return the normalised string form so the stored
				 * type matches the schema's declared `string` type
				 * even when a caller passes a non-string scalar
				 * (e.g. `true` → `'1'`, `42` → `'42'`) that happens
				 * to string-cast into an allowed enum member.
				 */
				return $value_string;
			}
		}

		/**
		 * Reject. Retain whatever was previously stored (or fall
		 * back to the schema default). Audit-log the rejection so
		 * operators can see attempted out-of-enum writes (a CRLF-
		 * bearing value, or a buggy filter widening the surface).
		 */
		$fallback = '';
		if ( isset( $w3tc_config->_data[ $w3tc_key ] ) ) {
			$fallback = $w3tc_config->_data[ $w3tc_key ];
		} elseif ( isset( $schema[ $w3tc_key ]['default'] ) ) {
			$fallback = $schema[ $w3tc_key ]['default'];
		}

		/**
		 * Allow the autoloader to load `Util_Debug` if it isn't
		 * already in memory (the earlier `class_exists(..., false)`
		 * would skip the audit-log in any request path where
		 * `Util_Debug` hadn't been touched yet — i.e. most enum-write
		 * flows — and silently drop the diagnostic operators rely on).
		 */
		if ( \class_exists( __NAMESPACE__ . '\\Util_Debug' ) ) {
			Util_Debug::log(
				'config',
				\sprintf(
					'Rejected out-of-enum write to %s; retained prior value. Allowed: %s',
					$w3tc_key,
					\implode( ',', $enum )
				)
			);
		}

		return $fallback;
	}

	/**
	 * Lazy-load the {@see ConfigKeys.php} schema once per request.
	 *
	 * `ConfigKeys.php` populates a local `$w3tc_keys` variable; we
	 * import the file inside an isolated scope and cache the
	 * result so per-write enum lookups don't re-include the file.
	 *
	 * @since 2.10.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function config_keys_schema() {
		static $schema = null;

		if ( null === $schema ) {
			$schema = array();

			$loader = static function () {
				$w3tc_keys = array();
				include W3TC_DIR . '/ConfigKeys.php';
				return $w3tc_keys;
			};

			$schema = (array) $loader();
		}

		return $schema;
	}

	/**
	 * Checks if the current configuration is a preview.
	 *
	 * @return bool True if preview mode is enabled, false otherwise.
	 */
	public function is_preview() {
		return $this->_preview;
	}

	/**
	 * Checks if the current configuration is the master configuration.
	 *
	 * @return bool True if the configuration is the master, false otherwise.
	 */
	public function is_master() {
		return $this->_is_master;
	}

	/**
	 * Checks if the configuration is compiled.
	 *
	 * @return bool True if the configuration is compiled, false otherwise.
	 */
	public function is_compiled() {
		return $this->_compiled;
	}

	/**
	 * Sets the default configuration values.
	 *
	 * @return void
	 */
	public function set_defaults() {
		$w3tc_c      = new ConfigCompiler( $this->_blog_id, $this->_preview );
		$this->_data = $w3tc_c->get_data();
	}

	/**
	 * Saves the current configuration.
	 *
	 * @return void
	 */
	public function save() {
		if ( function_exists( 'do_action' ) ) {
			do_action( 'w3tc_config_save', $this );
		}

		$w3tc_c = new ConfigCompiler( $this->_blog_id, $this->_preview );
		$w3tc_c->apply_data( $this->_data );
		$w3tc_c->save();
	}

	/**
	 * Checks if a configuration key is sealed (immutable).
	 *
	 * @param string $w3tc_key The configuration key to check.
	 *
	 * @return bool True if the key is sealed, false otherwise.
	 */
	public function is_sealed( $w3tc_key ) {
		if ( $this->is_master() ) {
			return false;
		}

		// better to use master config data here, but its faster and preciese enough for UI.
		return ConfigCompiler::child_key_sealed( $w3tc_key, $this->_data, $this->_data );
	}

	/**
	 * Exports the current configuration as a JSON string.
	 *
	 * @return string The configuration data as a JSON string.
	 */
	public function export() {
		$export_data = $this->_data;

		// Export files are often copied between environments; omit stored credential values.
		foreach ( \array_keys( self::secret_keys() ) as $w3tc_key ) {
			if ( isset( $export_data[ $w3tc_key ] ) && '' !== $export_data[ $w3tc_key ] ) {
				$export_data[ $w3tc_key ] = '';
			}
		}

		/**
		 * JSON_HEX_TAG / HEX_AMP / HEX_APOS / HEX_QUOT escape `<` `&` `'`
		 * `"` to their `\uXXXX` forms. The export endpoint serves this
		 * body to admins, and even with `Content-Type: application/json`
		 * some clients can render the response as HTML (history walks,
		 * view-source, intermediary proxies that ignore the content-
		 * type). Hex-escaping every HTML-significant character means
		 * the body cannot contain a literal `<script>` regardless of
		 * what an admin saved in config — closes that path without
		 * changing the JSON semantics (JSON parsers decode `<` back
		 * to `<` transparently).
		 *
		 * Secret-flagged keys are blanked in the export copy so
		 * credential material is not written into a downloadable file.
		 */
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		if ( defined( 'JSON_PRETTY_PRINT' ) ) {
			$flags |= JSON_PRETTY_PRINT;
		}

		return wp_json_encode( $export_data, $flags );
	}

	/**
	 * Imports a configuration from a file.
	 *
	 * Every incoming JSON key is validated against the `ConfigKeys.php`
	 * schema via {@see ConfigKeysSchema}.  Unknown keys are dropped
	 * silently and high-impact keys flagged `no_import => true`
	 * (`extensions.active*`, `*.path.java/jar`, `*.engine`) are refused
	 * here even though they are documented config keys — those are
	 * editable only through their dedicated UI page, where the page-
	 * specific validator runs.  Accepted values are type-coerced
	 * (`boolean` becomes a real `bool`, etc.) so an unexpected
	 * non-scalar value cannot land in a slot the schema declares
	 * as a scalar.
	 *
	 * Rejection counts are written to the `w3tc` debug channel via
	 * `Util_Debug::log` so operators can diagnose "my exported config
	 * didn't restore everything" without having to look at the JSON.
	 *
	 * @global $wp_filesystem
	 * @see get_filesystem_method()
	 *
	 * @param string $filename The path to the file to import.
	 *
	 * @return bool True if the import was successful, false otherwise.
	 */
	public function import( string $filename ): bool {
		if ( 'direct' !== \get_filesystem_method() ) {
			return false;
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		WP_Filesystem();

		if ( $wp_filesystem->exists( $filename ) && $wp_filesystem->is_readable( $filename ) ) {
			$content = $wp_filesystem->get_contents( $filename );
			if ( \substr( $content, 0, 14 ) === '<?php exit; ?>' ) {
				$content = \substr( $content, 14 );
			}

			$w3tc_data = @json_decode( $content, true );

			if ( \is_array( $w3tc_data ) ) {
				if ( ! isset( $w3tc_data['version'] ) || W3TC_VERSION !== $w3tc_data['version'] ) {
					$w3tc_c = new ConfigCompiler( $this->_blog_id, false );
					$w3tc_c->load( $w3tc_data );
					$w3tc_data = $w3tc_c->get_data();
				}

				$rejected_unknown = 0;
				$rejected_locked  = 0;
				$applied          = 0;

				foreach ( $w3tc_data as $w3tc_key => $w3tc_value ) {
					// `version` is metadata, not a settable key.
					if ( 'version' === $w3tc_key ) {
						continue;
					}

					if ( ! ConfigKeysSchema::is_known( $w3tc_key ) ) {
						++$rejected_unknown;
						continue;
					}

					if ( ! ConfigKeysSchema::can_import( $w3tc_key ) ) {
						++$rejected_locked;
						continue;
					}

					$w3tc_descriptor = ConfigKeysSchema::descriptor( $w3tc_key );
					$w3tc_value      = ConfigKeysSchema::coerce( $w3tc_value, $w3tc_descriptor );

					$this->set( $w3tc_key, $w3tc_value );
					++$applied;
				}

				if ( ( $rejected_unknown > 0 || $rejected_locked > 0 ) && \class_exists( '\W3TC\Util_Debug' ) ) {
					Util_Debug::log(
						'w3tc',
						\sprintf(
							'Config::import: applied %d, rejected %d unknown key%s and %d no-import key%s.',
							$applied,
							$rejected_unknown,
							1 === $rejected_unknown ? '' : 's',
							$rejected_locked,
							1 === $rejected_locked ? '' : 's'
						)
					);
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the MD5 hash of the current configuration data.
	 *
	 * @return string The MD5 hash of the configuration data.
	 */
	public function get_md5() {
		if ( is_null( $this->_md5 ) ) {
			$this->_md5 = substr( md5( serialize( $this->_data ) ), 20 );
		}

		return $this->_md5;
	}

	/**
	 * Loads the configuration data.
	 *
	 * @return void
	 */
	public function load() {
		$w3tc_data = self::util_array_from_storage( 0, $this->_preview );

		// config file assumed is not up to date, use slow version.
		if ( ! isset( $w3tc_data['version'] ) || W3TC_VERSION !== $w3tc_data['version'] ) {
			$this->load_full();
			return;
		}

		if ( ! $this->is_master() ) {
			$child_data = self::util_array_from_storage( $this->_blog_id, $this->_preview );

			if ( ! is_null( $child_data ) ) {
				if ( ! isset( $w3tc_data['version'] ) || W3TC_VERSION !== $w3tc_data['version'] ) {
					$this->load_full();
					return;
				}

				foreach ( $child_data as $w3tc_key => $w3tc_value ) {
					$w3tc_data[ $w3tc_key ] = $w3tc_value;
				}
			}
		}

		self::decrypt_secrets( $w3tc_data );

		$this->_data     = $w3tc_data;
		$this->_compiled = false;
	}

	/**
	 * Loads the full configuration data when necessary.
	 *
	 * @return void
	 */
	private function load_full() {
		$w3tc_c = new ConfigCompiler( $this->_blog_id, $this->_preview );
		$w3tc_c->load();
		$w3tc_data = $w3tc_c->get_data();
		self::decrypt_secrets( $w3tc_data );
		$this->_data     = $w3tc_data;
		$this->_compiled = true;
	}

	/**
	 * Returns the set of config keys declared as `secret` in
	 * ConfigKeys.php — credential-typed keys whose stored value is
	 * encrypted with {@see Util_Crypto::envelope_encrypt()}.
	 *
	 * The schema include is cached for the request, so this is cheap
	 * to call from both the load and save paths.
	 *
	 * @since 2.10.0
	 *
	 * @return array<string,true> Map of secret key name → true.
	 */
	public static function secret_keys() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$w3tc_keys = array();
		include W3TC_DIR . '/ConfigKeys.php';

		$cache = array();
		if ( is_array( $w3tc_keys ) ) {
			foreach ( $w3tc_keys as $w3tc_name => $w3tc_descriptor ) {
				if (
					is_array( $w3tc_descriptor )
					&& isset( $w3tc_descriptor['flags'] )
					&& is_array( $w3tc_descriptor['flags'] )
					&& ! empty( $w3tc_descriptor['flags']['secret'] )
				) {
					$cache[ $w3tc_name ] = true;
				}
			}
		}

		return $cache;
	}

	/**
	 * Decrypts every secret-flagged key in-place inside `$w3tc_data`.
	 *
	 * Legacy plaintext values pass through unchanged (the envelope
	 * helper is a no-op on non-`enc:v1:` strings), so an existing
	 * install upgrades transparently — values stay readable until the
	 * next `save()` re-wraps them.
	 *
	 * Tampered envelopes (bad HMAC, malformed base64) decrypt to
	 * `false`; we collapse that to an empty string so the downstream
	 * config consumer sees "credential needs re-entry" rather than the
	 * literal `false` (which `get_string()` would coerce to `""` anyway
	 * but via a non-obvious code path).
	 *
	 * @since 2.10.0
	 *
	 * @param array $w3tc_data Configuration data (modified in place).
	 *
	 * @return void
	 */
	public static function decrypt_secrets( &$w3tc_data ) {
		if ( ! is_array( $w3tc_data ) || ! class_exists( '\W3TC\Util_Crypto' ) ) {
			return;
		}

		/**
		 * `wp_salt()` is defined in `wp-includes/pluggable.php`, which loads
		 * AFTER `advanced-cache.php`. When `WP_CACHE` is defined, the W3TC
		 * page-cache drop-in calls `Dispatcher::config()` during early
		 * bootstrap — triggering `Config::__construct()` → `load()` → this
		 * method — well before `wp_salt()` exists. An eager decrypt here
		 * would force {@see Util_Crypto::derive_key()} down its
		 * `SECURE_AUTH_KEY|AUTH_KEY` fallback path, which produces a
		 * different key than `wp_salt('secure_auth')` uses at encrypt time.
		 * Every secret would then HMAC-fail and collapse to `''`, and the
		 * Dispatcher singleton would carry empty credentials for the rest
		 * of the request.
		 *
		 * Skip eager decrypt in that window; {@see self::_get()} lazily
		 * decrypts envelope values on first read instead, by which point
		 * `wp_salt()` is guaranteed to be loaded.
		 */
		if ( ! \function_exists( 'wp_salt' ) ) {
			return;
		}

		$secret_keys = self::secret_keys();
		if ( empty( $secret_keys ) ) {
			return;
		}

		foreach ( $secret_keys as $w3tc_key => $_ ) {
			if ( ! isset( $w3tc_data[ $w3tc_key ] ) || ! is_string( $w3tc_data[ $w3tc_key ] ) ) {
				continue;
			}
			$plain = Util_Crypto::envelope_decrypt( $w3tc_data[ $w3tc_key ] );
			if ( false === $plain ) {
				$w3tc_data[ $w3tc_key ] = '';
			} else {
				$w3tc_data[ $w3tc_key ] = $plain;
			}
		}
	}

	/**
	 * Encrypts every secret-flagged key in-place inside `$w3tc_data`.
	 *
	 * Called by {@see ConfigCompiler::save()} before writing
	 * `master.php`. Non-secret keys are untouched. Already-enveloped
	 * values are not double-wrapped (the envelope helper detects its
	 * own prefix and short-circuits).
	 *
	 * @since 2.10.0
	 *
	 * @param array $w3tc_data Configuration data (modified in place).
	 *
	 * @return void
	 */
	public static function encrypt_secrets( &$w3tc_data ) {
		if ( ! is_array( $w3tc_data ) || ! class_exists( '\W3TC\Util_Crypto' ) ) {
			return;
		}

		$secret_keys = self::secret_keys();
		if ( empty( $secret_keys ) ) {
			return;
		}

		foreach ( $secret_keys as $w3tc_key => $_ ) {
			if ( ! isset( $w3tc_data[ $w3tc_key ] ) || ! is_string( $w3tc_data[ $w3tc_key ] ) || '' === $w3tc_data[ $w3tc_key ] ) {
				continue;
			}
			$w3tc_data[ $w3tc_key ] = Util_Crypto::envelope_encrypt( $w3tc_data[ $w3tc_key ] );
		}
	}
}
