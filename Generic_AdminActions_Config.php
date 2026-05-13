<?php
/**
 * File: Generic_AdminActions_Config.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_AdminActions_Config
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Generic_AdminActions_Config {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Generic_AdminActions_Config constructor method.
	 *
	 * Initializes the configuration object.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Imports the configuration settings from an uploaded file.
	 *
	 * @return void
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 */
	public function w3tc_config_import() {
		$error = '';

		$config = new Config();

		if ( ! isset( $_FILES['config_file']['error'] ) || UPLOAD_ERR_NO_FILE === $_FILES['config_file']['error'] ) {
			$error = 'config_import_no_file';
		} elseif ( UPLOAD_ERR_OK !== $_FILES['config_file']['error'] ) {
			$error = 'config_import_upload';
		} else {
			$imported = $config->import(
				isset( $_FILES['config_file']['tmp_name'] ) ?
				wp_normalize_path( sanitize_text_field( $_FILES['config_file']['tmp_name'] ) ) : ''
			);

			if ( ! $imported ) {
				$error = 'config_import_import';
			}
		}

		if ( $error ) {
			Util_Admin::redirect( array( 'w3tc_error' => $error ), true );
			return;
		}

		Util_Admin::config_save( $this->_config, $config );
		Util_Admin::redirect( array( 'w3tc_note' => 'config_import' ), true );
	}

	/**
	 * Exports the current configuration settings to a file.
	 *
	 * Outputs the exported JSON and terminates script execution.
	 *
	 * @return void
	 */
	public function w3tc_config_export() {
		$filename = substr( get_home_url(), strpos( get_home_url(), '//' ) + 2 );
		@header(
			sprintf(
				// Translators: 1 filename.
				__(
					'Content-Disposition: attachment; filename=%1$s.json',
					'w3-total-cache'
				),
				$filename
			)
		);
		echo $this->_config->export(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		die();
	}

	/**
	 * Resets the configuration settings to their default values.
	 *
	 * @return void
	 */
	public function w3tc_config_reset() {
		$config = new Config();
		$config->set_defaults();
		Util_Admin::config_save( $this->_config, $config );

		$config_state = Dispatcher::config_state();
		$config_state->reset();
		$config_state->save();

		$config_state = Dispatcher::config_state_master();
		$config_state->reset();
		$config_state->save();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'config_reset',
			),
			true
		);
	}

	/**
	 * Enables the preview mode by copying production settings.
	 *
	 * @return void
	 */
	public function w3tc_config_preview_enable() {
		ConfigUtil::preview_production_copy( Util_Environment::blog_id(), -1 );
		Util_Environment::set_preview( true );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'preview_enable',
			)
		);
	}

	/**
	 * Disables the preview mode.
	 *
	 * @return void
	 */
	public function w3tc_config_preview_disable() {
		$blog_id = Util_Environment::blog_id();
		ConfigUtil::remove_item( $blog_id, true );
		Util_Environment::set_preview( false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'preview_disable',
			)
		);
	}

	/**
	 * Deploys preview settings to production.
	 *
	 * @return void
	 */
	public function w3tc_config_preview_deploy() {
		ConfigUtil::preview_production_copy( Util_Environment::blog_id(), 1 );
		Util_Environment::set_preview( false );

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'preview_deploy',
			)
		);
	}

	/**
	 * Saves the database cluster configuration file.
	 *
	 * The file is later `require`'d from
	 * {@see Enterprise_Dbcache_WpdbInjection_Cluster::initialize_cluster()}
	 * and from {@see Util_Environment::is_dbcluster()}, so its contents
	 * execute as PHP. The legacy code wrote the raw request body to
	 * `W3TC_FILE_DB_CLUSTER_CONFIG` with no validation, turning the
	 * handler into an arbitrary-PHP-write → RCE primitive for any user
	 * reaching it (rt9-219, rt9-222).
	 *
	 * The handler now enforces three gates:
	 *
	 * 1. **Capability** — must be `manage_options`. This is hardcoded,
	 *    not filterable, because the downstream effect is code execution.
	 * 2. **Content shape** — the submitted blob is run through
	 *    {@see self::validate_dbcluster_content()}, a tokenizer-based
	 *    allowlist that rejects any function calls, `include`, `require`,
	 *    `eval`, backticks, or static-method calls.
	 * 3. **Write** — only if both gates pass.
	 *
	 * @throws \Exception If the file write operation fails.
	 *
	 * @return void
	 */
	public function w3tc_config_dbcluster_config_save() {
		$params = array( 'page' => 'w3tc_general' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			wp_die( \esc_html__( 'You do not have permission to modify this configuration.', 'w3-total-cache' ) );
		}

		$content = Util_Request::get_string( 'newcontent' );

		$validation_error = self::validate_dbcluster_content( $content );
		if ( null !== $validation_error ) {
			Util_Admin::redirect_with_custom_messages(
				$params,
				array(
					'dbcluster_save_failed' => $validation_error,
				)
			);
			return;
		}

		if ( ! file_put_contents( W3TC_FILE_DB_CLUSTER_CONFIG, $content ) ) {
			try {
				Util_Activation::throw_on_write_error( W3TC_FILE_DB_CLUSTER_CONFIG );
			} catch ( \Exception $e ) {
				$error = $e->getMessage();
				Util_Admin::redirect_with_custom_messages(
					$params,
					array(
						'dbcluster_save_failed' => $error,
					)
				);
			}
		}

		Util_Admin::redirect_with_custom_messages(
			$params,
			null,
			array(
				'dbcluster_save' => __( 'Database Cluster configuration file has been successfully saved', 'w3-total-cache' ),
			)
		);
	}

	/**
	 * Validates the body of the DB-cluster config file before writing.
	 *
	 * The file is `require`'d, so its contents run as PHP. We can't
	 * accept arbitrary PHP — only the documented "set
	 * `$w3tc_dbcluster_config` to an array literal" shape (see
	 * `ini/dbcluster-config-sample.php`).
	 *
	 * Approach: tokenize with `token_get_all()` and walk the token
	 * stream against a strict allowlist. Anything that could produce a
	 * side effect (function call, include/require, eval, backtick exec,
	 * arrow operator, double-colon, magic constants, T_HALT_COMPILER,
	 * etc.) is rejected.
	 *
	 * @since X.X.X
	 *
	 * @param string $content Raw submitted PHP source.
	 *
	 * @return string|null Localised error string on rejection, or null on accept.
	 */
	public static function validate_dbcluster_content( $content ) {
		if ( ! \is_string( $content ) || '' === \trim( $content ) ) {
			return \__( 'Configuration file body is empty.', 'w3-total-cache' );
		}

		if ( 0 !== \strpos( \ltrim( $content ), '<?php' ) ) {
			return \__( 'Configuration file must start with `<?php`.', 'w3-total-cache' );
		}

		// Forbidden tokens: any of these in the token stream is a hard reject.
		$forbidden_tokens = array(
			T_EVAL,
			T_INCLUDE,
			T_INCLUDE_ONCE,
			T_REQUIRE,
			T_REQUIRE_ONCE,
			T_EXIT,
			T_HALT_COMPILER,
			T_NEW,
			T_DOUBLE_COLON,
			T_PAAMAYIM_NEKUDOTAYIM,
			T_OBJECT_OPERATOR,
			T_CLASS,
			T_FUNCTION,
			T_NAMESPACE,
			T_USE,
			T_TRY,
			T_THROW,
			T_GOTO,
			T_PRINT,
			T_ECHO,
			T_YIELD,
		);

		$tokens = @\token_get_all( $content );
		if ( false === $tokens || empty( $tokens ) ) {
			return \__( 'Configuration file is not valid PHP.', 'w3-total-cache' );
		}

		$has_assignment_to_target = false;

		for ( $i = 0, $n = \count( $tokens ); $i < $n; $i++ ) {
			$tok = $tokens[ $i ];

			// String tokens (single chars like `;`, `=`, `(`, etc.) — reject backticks.
			if ( \is_string( $tok ) ) {
				if ( '`' === $tok ) {
					return \__( 'Backtick execution is not allowed in the configuration file.', 'w3-total-cache' );
				}
				continue;
			}

			list( $tid, $tval ) = $tok;

			if ( \in_array( $tid, $forbidden_tokens, true ) ) {
				return \sprintf(
					/* translators: %s: token name. */
					\__( 'Disallowed PHP construct in configuration file: %s', 'w3-total-cache' ),
					\token_name( $tid )
				);
			}

			// A bareword followed by `(` is a function call.
			if ( T_STRING === $tid ) {
				// Look ahead past whitespace / comments to find the next significant token.
				for ( $j = $i + 1; $j < $n; $j++ ) {
					$next = $tokens[ $j ];
					if ( \is_array( $next ) && \in_array( $next[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
						continue;
					}
					if ( \is_string( $next ) && '(' === $next ) {
						// Allow only `array(` literal.
						if ( 'array' !== \strtolower( $tval ) ) {
							return \sprintf(
								/* translators: %s: function name. */
								\__( 'Function calls are not allowed in the configuration file: %s()', 'w3-total-cache' ),
								$tval
							);
						}
					}
					break;
				}
			}

			// Track whether the file ever assigns to $w3tc_dbcluster_config.
			if ( T_VARIABLE === $tid && '$w3tc_dbcluster_config' === $tval ) {
				$has_assignment_to_target = true;
			}
		}

		if ( ! $has_assignment_to_target ) {
			return \__( 'Configuration file must assign $w3tc_dbcluster_config.', 'w3-total-cache' );
		}

		return null;
	}

	/**
	 * Saves the "Support Us" configuration settings.
	 *
	 * Updates settings based on user actions like tweeting or signing up.
	 *
	 * @return void
	 */
	public function w3tc_config_save_support_us() {
		$tweeted      = Util_Request::get_boolean( 'tweeted' );
		$signmeup     = Util_Request::get_boolean( 'signmeup' );
		$accept_terms = Util_Request::get_boolean( 'accept_terms' );
		$this->_config->set( 'common.tweeted', $tweeted );

		$state_master = Dispatcher::config_state_master();
		if ( $accept_terms ) {
			$this->_config->set( 'common.track_usage', true );
			$state_master->set( 'license.community_terms', 'accept' );
		}
		$state_master->save();

		if ( $signmeup ) {
			if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
				$license = 'pro';
			} else {
				$license = 'community';
			}

			$email = filter_input( INPUT_POST, 'email', FILTER_SANITIZE_EMAIL );

			wp_remote_post(
				W3TC_MAILLINGLIST_SIGNUP_URL,
				array(
					'body' => array(
						'email'   => $email,
						'license' => $license,
					),
				)
			);
		}
		$this->_config->save();

		Util_Admin::redirect(
			array(
				'w3tc_note' => 'config_save',
			)
		);
	}

	/**
	 * Updates the upload path option in the WordPress settings.
	 *
	 * @return void
	 */
	public function w3tc_config_update_upload_path() {
		update_option( 'upload_path', '' );

		Util_Admin::redirect();
	}

	/**
	 * Disables an overloaded configuration setting by its HTTP key.
	 *
	 * Restricted to keys ending in `.configuration_overloaded` AND
	 * declared with `'type' => 'boolean'` in ConfigKeys.php. The legacy
	 * code accepted any `$http_key` from the URL and wrote it as `false`
	 * — bypassing the read_request() page allowlist and letting a CSRF /
	 * subscriber-via-privesc actor flip arbitrary boolean keys, including
	 * `*.engine` fallbacks and `common.support_us` (rt9-207).
	 *
	 * @param string $http_key The HTTP key of the setting to disable.
	 *
	 * @return void
	 */
	public function w3tc_config_overloaded_disable( $http_key ) {
		if ( ! self::is_overloaded_toggle_key( $http_key ) ) {
			Util_Admin::redirect( array() );
			return;
		}

		$key = Util_Ui::config_key_from_http_name( $http_key );

		$c = Dispatcher::config();
		$c->set( $key, false );
		$c->save();

		Util_Admin::redirect( array() );
	}

	/**
	 * Enables an overloaded configuration setting by its HTTP key.
	 *
	 * See {@see self::w3tc_config_overloaded_disable()} for the gate
	 * applied to the `$http_key` argument.
	 *
	 * @param string $http_key The HTTP key of the setting to enable.
	 *
	 * @return void
	 */
	public function w3tc_config_overloaded_enable( $http_key ) {
		if ( ! self::is_overloaded_toggle_key( $http_key ) ) {
			Util_Admin::redirect( array() );
			return;
		}

		$key = Util_Ui::config_key_from_http_name( $http_key );

		$c = Dispatcher::config();
		$c->set( $key, true );
		$c->save();

		Util_Admin::redirect( array() );
	}

	/**
	 * Returns true if the given HTTP key resolves to a `*_configuration_overloaded`
	 * boolean config entry that the overloaded-toggle handlers are
	 * allowed to flip.
	 *
	 * @since X.X.X
	 *
	 * @param string $http_key Raw HTTP key from the URL.
	 *
	 * @return bool
	 */
	private static function is_overloaded_toggle_key( $http_key ) {
		if ( ! \is_string( $http_key ) || '' === $http_key ) {
			return false;
		}

		$key = Util_Ui::config_key_from_http_name( $http_key );

		// Compound (extension) keys are not toggleable here.
		if ( \is_array( $key ) || ! \is_string( $key ) ) {
			return false;
		}

		// Tightly scoped suffix — `dbcache.configuration_overloaded`,
		// `pgcache.configuration_overloaded`, etc. — and nothing else.
		$suffix = '.configuration_overloaded';
		if ( \strlen( $key ) <= \strlen( $suffix )
			|| $suffix !== \substr( $key, -\strlen( $suffix ) )
		) {
			return false;
		}

		$descriptor = ConfigKeysSchema::descriptor( $key );
		if ( null === $descriptor || ! isset( $descriptor['type'] ) || 'boolean' !== $descriptor['type'] ) {
			return false;
		}

		return true;
	}
}
