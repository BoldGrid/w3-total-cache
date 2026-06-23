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
	 * @throws \Exception When the durable `Util_Admin::config_save()`
	 *                    write fails; the upstream admin-action catch
	 *                    handles the message-display side.
	 *
	 * @return void
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 */
	public function w3tc_config_import() {
		$error = '';

		$w3tc_config = new Config();

		if ( ! isset( $_FILES['config_file']['error'] ) || UPLOAD_ERR_NO_FILE === $_FILES['config_file']['error'] ) {
			$error = 'config_import_no_file';
		} elseif ( UPLOAD_ERR_OK !== $_FILES['config_file']['error'] ) {
			$error = 'config_import_upload';
		} else {
			$imported = $w3tc_config->import(
				isset( $_FILES['config_file']['tmp_name'] ) ?
				wp_normalize_path( sanitize_text_field( $_FILES['config_file']['tmp_name'] ) ) : ''
			);

			if ( ! $imported ) {
				$error = 'config_import_import';
			}
		}

		if ( $error ) {
			Util_Debug::audit_log(
				'config_import_failed',
				array( 'error' => $error )
			);
			Util_Admin::redirect( array( 'w3tc_error' => $error ), true );
			return;
		}

		/**
		 * Wrap the durable write so a `Util_Admin::config_save()` throw
		 * (filesystem-permission failure, etc.) is recorded as a
		 * `config_import_failed` event instead of being silently
		 * swallowed by the upstream catch — without this, the audit
		 * trail would show successful upload-and-parse but no completion
		 * event for an import that did not in fact land on disk.
		 */
		try {
			Util_Admin::config_save( $this->_config, $w3tc_config );
		} catch ( \Exception $ex ) {
			Util_Debug::audit_log(
				'config_import_failed',
				array(
					'error'   => 'config_save_exception',
					'message' => $ex->getMessage(),
				)
			);
			throw $ex;
		}
		Util_Debug::audit_log( 'config_imported', array() );
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
		Util_Debug::audit_log( 'config_exported', array() );
		$filename = substr( get_home_url(), strpos( get_home_url(), '//' ) + 2 );

		/**
		 * Force `application/json` and `X-Content-Type-Options: nosniff`
		 * so a browser walking back through history / preview /
		 * view-source renders the response as JSON, not HTML
		 * (closes that path).
		 */
		@header( 'Content-Type: application/json; charset=utf-8' );
		@header( 'X-Content-Type-Options: nosniff' );
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
		/**
		 * Audit only after every save in the reset sequence has
		 * completed. Emitting `config_reset` before the saves would
		 * produce a successful-looking audit event for a reset that a
		 * later `config_save()` throw (filesystem error, etc.) left
		 * half-applied.
		 */
		$w3tc_config = new Config();
		$w3tc_config->set_defaults();
		Util_Admin::config_save( $this->_config, $w3tc_config );

		$w3tc_config_state = Dispatcher::config_state();
		$w3tc_config_state->reset();
		$w3tc_config_state->save();

		$w3tc_config_state = Dispatcher::config_state_master();
		$w3tc_config_state->reset();
		$w3tc_config_state->save();

		Util_Debug::audit_log( 'config_reset', array() );

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
	 * `W3TC_FILE_DB_CLUSTER_CONFIG` with no validation, letting any
	 * user reaching the handler write arbitrary PHP to a file that is
	 * later require'd as code.
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

		$content_bytes = \strlen( $content );
		$write_ok      = (bool) file_put_contents( W3TC_FILE_DB_CLUSTER_CONFIG, $content );

		if ( ! $write_ok ) {
			try {
				Util_Activation::throw_on_write_error( W3TC_FILE_DB_CLUSTER_CONFIG );
			} catch ( \Exception $e ) {
				$error = $e->getMessage();
				Util_Debug::audit_log(
					'dbcluster_config_save_failed',
					array( 'message' => $error )
				);
				Util_Admin::redirect_with_custom_messages(
					$params,
					array(
						'dbcluster_save_failed' => $error,
					)
				);
			}
		}

		Util_Debug::audit_log(
			'dbcluster_config_save',
			array(
				'path'  => W3TC_FILE_DB_CLUSTER_CONFIG,
				'bytes' => $content_bytes,
			)
		);

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
	 * arrow operator, double-colon, T_HALT_COMPILER, etc.) is rejected.
	 *
	 * Two layers of "is this a function call" gating run in parallel:
	 *
	 *  1. **Bareword + `(`** rejects `phpinfo()`, `system()`, etc.
	 *  2. **Value-as-callable + `(`** rejects every PHP 5.4+ shape that
	 *     invokes a string/array/expression as a function:
	 *     `("printf")(…)`, `$var(…)`, `$arr[0](…)`, `(expr)(…)`. The
	 *     rule is "any `(` whose preceding significant token is `)`,
	 *     `]`, `}`, T_VARIABLE, or T_CONSTANT_ENCAPSED_STRING is a
	 *     call site." That single rule covers all known value-as-
	 *     callable shapes; layer 1 stays in place so the error
	 *     messages distinguish "bareword call" from "callable-shape
	 *     call" for operators editing the file by hand.
	 *
	 * @since 2.10.0
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

		/*
		 * Forbidden tokens: any of these in the token stream is a hard
		 * reject. T_DOUBLE_COLON is the canonical name for the `::`
		 * operator since PHP 5.4; the legacy alias T_PAAMAYIM_NEKUDOTAYIM
		 * is intentionally NOT referenced here -- listing both would
		 * duplicate the entry and the alias is not guaranteed to exist
		 * across every supported PHP build.
		 *
		 * T_CLOSE_TAG, T_INLINE_HTML, and T_OPEN_TAG_WITH_ECHO reject
		 * every shape of HTML output: an input that closes the PHP
		 * block and follows with literal text would otherwise validate
		 * and print that text to the response whenever the file is
		 * require'd, and the short-echo open-tag directly echoes its
		 * argument. Both are observable side effects; the dbcluster
		 * config file must do nothing but set a variable.
		 *
		 * T_FN (PHP 7.4+), T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES,
		 * and T_LIST close the remaining call-site shapes that don't
		 * tokenize as "value-as-callable + `(`":
		 *
		 *   - T_FN: `fn () => …` arrow functions; an IIFE on top
		 *     (`(fn () => phpinfo())()`) executes the body.
		 *   - T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES: complex
		 *     interpolation (`"{$var($arg)}"` / `"${var($arg)}"`)
		 *     evaluates the expression inside the braces, which can
		 *     be a call.
		 *   - T_LIST: `list(…) = …` destructuring is unnecessary for
		 *     the documented shape and avoids surprising token
		 *     sequences.
		 *
		 * `defined()` check on T_FN is defensive only — the plugin's
		 * supported PHP floor (7.4+) always exposes the constant; the
		 * placeholder `-1` ensures the array still builds on hosts
		 * that somehow strip it, and `-1` will never match a real
		 * token id.
		 */
		$forbidden_tokens = array(
			T_EVAL,
			T_INCLUDE,
			T_INCLUDE_ONCE,
			T_REQUIRE,
			T_REQUIRE_ONCE,
			T_HALT_COMPILER,
			T_NEW,
			T_DOUBLE_COLON,
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
			T_CLOSE_TAG,
			T_INLINE_HTML,
			T_OPEN_TAG_WITH_ECHO,
			\defined( 'T_FN' ) ? \constant( 'T_FN' ) : -1,
			T_CURLY_OPEN,
			T_DOLLAR_OPEN_CURLY_BRACES,
			T_LIST,
		);

		$tokens = @\token_get_all( $content );
		if ( false === $tokens || empty( $tokens ) ) {
			return \__( 'Configuration file is not valid PHP.', 'w3-total-cache' );
		}

		$has_assignment_to_target = false;

		for ( $w3tc_i = 0, $n = \count( $tokens ); $w3tc_i < $n; $w3tc_i++ ) {
			$tok = $tokens[ $w3tc_i ];

			// String tokens (single chars like `;`, `=`, `(`, etc.).
			if ( \is_string( $tok ) ) {
				if ( '`' === $tok ) {
					return \__( 'Backtick execution is not allowed in the configuration file.', 'w3-total-cache' );
				}

				/*
				 * Reject value-as-callable invocations. Walk back past
				 * whitespace / comments to find the preceding significant
				 * token. If it is one of:
				 *   `)`, `]`, `}` (string chars)
				 *   T_VARIABLE
				 *   T_CONSTANT_ENCAPSED_STRING
				 * then this `(` opens a call on a non-bareword value —
				 * the shape that token-walk denylists miss because they
				 * only gate `T_STRING + (`.
				 */
				if ( '(' === $tok ) {
					$prev = self::previous_significant_token( $tokens, $w3tc_i );
					if ( null !== $prev ) {
						if ( \is_string( $prev ) && \in_array( $prev, array( ')', ']', '}' ), true ) ) {
							return \__( 'Calls on values (string, variable, expression, subscript) are not allowed in the configuration file.', 'w3-total-cache' );
						}
						if ( \is_array( $prev )
							&& \in_array( $prev[0], array( T_VARIABLE, T_CONSTANT_ENCAPSED_STRING ), true )
						) {
							return \__( 'Calls on values (string, variable, expression, subscript) are not allowed in the configuration file.', 'w3-total-cache' );
						}
					}
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

			/*
			 * `exit` / `die` (T_EXIT) halt execution. Allow only the
			 * WordPress direct-access guard: `defined( … ) || exit`.
			 */
			if ( T_EXIT === $tid ) {
				$prev                 = self::previous_significant_token( $tokens, $w3tc_i );
				$exit_guard_operators = array( T_BOOLEAN_OR, T_BOOLEAN_AND, T_LOGICAL_OR, T_LOGICAL_AND );
				$is_exit_guard        = ( \is_string( $prev ) && \in_array( $prev, array( '||', '&&' ), true ) )
					|| ( \is_array( $prev ) && \in_array( $prev[0], $exit_guard_operators, true ) );
				if ( ! $is_exit_guard ) {
					return \sprintf(
						/* translators: %s: token name. */
						\__( 'Disallowed PHP construct in configuration file: %s', 'w3-total-cache' ),
						\token_name( $tid )
					);
				}
				continue;
			}

			// A bareword followed by `(` is a function call.
			if ( T_STRING === $tid ) {
				// Look ahead past whitespace / comments to find the next significant token.
				for ( $j = $w3tc_i + 1; $j < $n; $j++ ) {
					$next = $tokens[ $j ];
					if ( \is_array( $next ) && \in_array( $next[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
						continue;
					}
					if ( \is_string( $next ) && '(' === $next ) {
						// Allow `array(` literals and the ABSPATH guard.
						if ( ! \in_array( \strtolower( $tval ), array( 'array', 'defined' ), true ) ) {
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

			/*
			 * Track whether the file ACTUALLY assigns to $w3tc_dbcluster_config.
			 * A bare `$w3tc_dbcluster_config;` reference (no `=` after it) does
			 * not initialise the variable; the dbcluster code would then
			 * behave as if the file were empty. Walk forward past whitespace /
			 * comments and confirm the next significant token is the `=`
			 * assignment operator (or `[`, which would be a subscript-assignment).
			 */
			if ( T_VARIABLE === $tid && '$w3tc_dbcluster_config' === $tval ) {
				for ( $j = $w3tc_i + 1; $j < $n; $j++ ) {
					$next = $tokens[ $j ];
					if ( \is_array( $next ) && \in_array( $next[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
						continue;
					}
					if ( \is_string( $next ) && ( '=' === $next || '[' === $next ) ) {
						$has_assignment_to_target = true;
					}
					break;
				}
			}
		}

		if ( ! $has_assignment_to_target ) {
			return \__( 'Configuration file must assign $w3tc_dbcluster_config.', 'w3-total-cache' );
		}

		return null;
	}

	/**
	 * Walks back from `$pos` through the token stream and returns the
	 * first non-whitespace / non-comment token, or null if none.
	 *
	 * Used by `validate_dbcluster_content()` to identify the token that
	 * precedes a `(` so it can distinguish "array literal" from
	 * "function call" from "value-as-callable invocation."
	 *
	 * @since 2.10.0
	 *
	 * @param array $tokens Full token array from `token_get_all()`.
	 * @param int   $pos    Current position; the search starts at `$pos - 1`.
	 *
	 * @return array|string|null Token (in token_get_all() shape), or null
	 *                            if no significant token exists before `$pos`.
	 */
	private static function previous_significant_token( $tokens, $pos ) {
		for ( $j = $pos - 1; $j >= 0; $j-- ) {
			$prev = $tokens[ $j ];
			if ( \is_array( $prev ) && \in_array( $prev[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			return $prev;
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
				$w3tc_license = 'pro';
			} else {
				$w3tc_license = 'community';
			}

			/**
			 * RT9-218: Bind the mailing-list signup to the currently
			 * authenticated admin's verified WordPress account email,
			 * rather than trusting whatever address was POSTed. The
			 * original handler took `email` straight from the request
			 * body, which let any admin (or anyone driving the shared
			 * `w3tc` nonce) submit arbitrary third-party addresses to
			 * `api.w3-edge.com/v1/signup-newsletter` as an outbound
			 * abuse primitive. wp_get_current_user() is safe here
			 * because the surrounding admin-page dispatcher already
			 * gates this action behind an authenticated session.
			 */
			$current = \wp_get_current_user();
			$email   = ( $current instanceof \WP_User && ! empty( $current->user_email ) )
				? $current->user_email
				: '';

			if ( \is_string( $email ) && '' !== $email
				&& false !== \filter_var( $email, FILTER_VALIDATE_EMAIL )
			) {
				wp_remote_post(
					W3TC_MAILLINGLIST_SIGNUP_URL,
					array(
						'body' => array(
							'email'   => $email,
							'license' => $w3tc_license,
						),
					)
				);
			}
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
	 * — bypassing the read_request() page allowlist and letting an
	 * unprivileged caller flip arbitrary boolean keys, including
	 * `*.engine` fallbacks and `common.support_us`.
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

		$w3tc_key = Util_Ui::config_key_from_http_name( $http_key );

		$w3tc_c = Dispatcher::config();
		$w3tc_c->set( $w3tc_key, false );
		$w3tc_c->save();

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

		$w3tc_key = Util_Ui::config_key_from_http_name( $http_key );

		$w3tc_c = Dispatcher::config();
		$w3tc_c->set( $w3tc_key, true );
		$w3tc_c->save();

		Util_Admin::redirect( array() );
	}

	/**
	 * Returns true if the given HTTP key resolves to a `*_configuration_overloaded`
	 * boolean config entry that the overloaded-toggle handlers are
	 * allowed to flip.
	 *
	 * @since 2.10.0
	 *
	 * @param string $http_key Raw HTTP key from the URL.
	 *
	 * @return bool
	 */
	private static function is_overloaded_toggle_key( $http_key ) {
		if ( ! \is_string( $http_key ) || '' === $http_key ) {
			return false;
		}

		$w3tc_key = Util_Ui::config_key_from_http_name( $http_key );

		// Compound (extension) keys are not toggleable here.
		if ( \is_array( $w3tc_key ) || ! \is_string( $w3tc_key ) ) {
			return false;
		}

		/**
		 * Tightly scoped suffix — `dbcache.configuration_overloaded`,
		 * `pgcache.configuration_overloaded`, etc. — and nothing else.
		 */
		$suffix = '.configuration_overloaded';
		if ( \strlen( $w3tc_key ) <= \strlen( $suffix )
			|| \substr( $w3tc_key, -\strlen( $suffix ) ) !== $suffix
		) {
			return false;
		}

		$w3tc_descriptor = ConfigKeysSchema::descriptor( $w3tc_key );
		if ( null === $w3tc_descriptor || ! isset( $w3tc_descriptor['type'] ) || 'boolean' !== $w3tc_descriptor['type'] ) {
			return false;
		}

		return true;
	}
}
