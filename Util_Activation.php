<?php
/**
 * File: Util_Activation.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Util_Activation
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Util_Activation {
	/**
	 * Deactivate plugin after activation error
	 *
	 * @return void
	 */
	private static function _cleanup() {
		$active_plugins         = (array) get_option( 'active_plugins' );
		$active_plugins_network = (array) get_site_option( 'active_sitewide_plugins' );

		// workaround for WPMU deactivation bug.
		remove_action( 'deactivate_' . W3TC_FILE, 'deactivate_sitewide_plugin' );

		do_action( 'deactivate_plugin', W3TC_FILE );

		$key = array_search( W3TC_FILE, $active_plugins, true );

		if ( false !== $key ) {
			array_splice( $active_plugins, $key, 1 );
		}

		unset( $active_plugins_network[ W3TC_FILE ] );

		do_action( 'deactivate_' . W3TC_FILE );
		do_action( 'deactivated_plugin', W3TC_FILE );

		update_option( 'active_plugins', $active_plugins );
		update_site_option( 'active_sitewide_plugins', $active_plugins_network );
	}

	/**
	 * W3 activate error
	 *
	 * @param string $error Error.
	 *
	 * @return void
	 */
	private static function _error( $error ) {
		self::_cleanup();

		include W3TC_INC_DIR . '/error.php';
		exit();
	}

	/**
	 * Print activation error with repeat button based on exception
	 *
	 * @param unknown $e Error.
	 *
	 * @return void
	 */
	public static function error_on_exception( $e ) {
		$reactivate_url    = wp_nonce_url( 'plugins.php?action=activate&plugin=' . W3TC_FILE, 'activate-plugin_' . W3TC_FILE );
		$reactivate_button = sprintf(
			'%1$sre-activate plugin',
			'<input type="button" value="'
		) . sprintf(
			'" onclick="top.location.href = \'%s\'" />',
			addslashes( $reactivate_url )
		);

		self::_error(
			sprintf(
				// Translators: 1 error message, 2 reactivate button.
				__(
					'%1$s<br />then %2$s.',
					'w3-total-cache'
				),
				$e->getMessage(),
				$reactivate_button
			)
		);
	}

	/**
	 * W3 writable error
	 *
	 * @param string   $path       Path.
	 * @param string[] $chmod_dirs Directories that should be chmod 777 inorder to write.
	 *
	 * @throws \Exception Exception.
	 *
	 * @return void
	 */
	public static function throw_on_write_error( $path, $chmod_dirs = array() ) {
		$chmods = '';
		if ( $chmod_dirs ) {
			$chmods = '<ul>';
			foreach ( $chmod_dirs as $dir ) {
				$chmods .= sprintf(
					// Translators: 1 opening HTML li tag followed by opening HTML strong tag, 2 directory name
					// Translators: 3 closing HTML strong tag followed by closing HTML li tag.
					__(
						'%1$schmod 777 %2$s%3$s',
						'w3-total-cache'
					),
					'<li><strong style="color: #f00;">',
					$dir,
					'</strong></li>'
				);
			}
		} else {
			$chmods = sprintf(
				'<strong style="color: #f00;">chmod 777 %s</strong>',
				file_exists( $path ) ? $path : dirname( $path )
			);
		}
		if ( Util_File::check_open_basedir( $path ) ) {
			$error = sprintf(
				// Translators: 1 opening HTML strong tag, 2 file path, 3 closing HTML strong tag
				// Translators: 4 HTML line break, 5 chmod values.
				__(
					'%1$s%2$s%3$s could not be created, please run following command:%4$s%5$s',
					'w3-total-cache'
				),
				'<strong>',
				$path,
				'</strong>',
				'<br />',
				$chmods
			);
		} else {
			$error = sprintf(
				// Translators: 1 opening HTML strong tag, 2 file path, 3 closing HTML strong tag
				// Translators: 4 HTML line break followed by opening HTML strong tag, 5 open basedir value.
				__(
					'%1$s%2$s%3$s could not be created, %1$sopen_basedir%3$s restriction in effect, please check your php.ini settings:%4$sopen_basedir = "%5$s"%3$s',
					'w3-total-cache'
				),
				'<strong>',
				$path,
				'</strong>',
				'<br /><strong style="color: #f00;">',
				ini_get( 'open_basedir' )
			);
		}

		throw new \Exception( $error );
	}

	/**
	 * Creates maintenance mode file
	 *
	 * @param unknown $time Time.
	 *
	 * @return void
	 */
	public static function enable_maintenance_mode( $time = null ) {
		if ( is_null( $time ) ) {
			$time = 'time()';
		}

		Util_WpFile::write_to_file( Util_Environment::site_path() . '/.maintenance', "<?php \$upgrading = $time; ?>" );
	}

	/**
	 * Deletes maintenance mode file
	 *
	 * @return void
	 */
	public static function disable_maintenance_mode() {
		Util_WpFile::delete_file( Util_Environment::site_path() . '/.maintenance' );
	}

	/**
	 * Used to display Util_Environment_Exceptions in UI
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions.
	 *
	 * @return array(before_errors = [], required_changes =>, later_errors => [])
	 */
	public static function parse_environment_exceptions( $exs ) {
		$exceptions = $exs->exceptions();

		$commands                      = '';
		$required_changes              = '';
		$before_errors                 = array();
		$later_errors                  = array();
		$operation_error_already_shown = false;

		foreach ( $exceptions as $ex ) {
			if ( $ex instanceof Util_WpFile_FilesystemOperationException ) {
				if ( ! $operation_error_already_shown ) {
					$m = $ex->getMessage();
					if ( strlen( $m ) > 0 ) {
						$before_errors[] = $m;
						// if multiple operations failed when they tried to fix environment - show only first
						// otherwise can duplication information about absense of permissions.
						$operation_error_already_shown = true;
					}
					if ( $ex instanceof Util_WpFile_FilesystemWriteException ) {
						$required_changes .= sprintf(
							// Translators: 1 opening HTML strong tag, 2 file path, 3 closing HTML strong tag
							// Translators: 4 opening HTML textarea tag, 5 textarea content, 6 closing HTML textarea tag followed by line break.
							__(
								'Create the %1$s%2$s%3$s file and paste the following text into it: %4$s%5$s%6$s',
								'w3-total-cache'
							),
							'<strong>',
							$ex->filename(),
							'</strong>',
							'<textarea>',
							esc_textarea( $ex->file_contents() ),
							'</textarea> <br />'
						);
					} elseif ( $ex instanceof Util_WpFile_FilesystemModifyException ) {
						$modification_content = $ex->file_contents();
						if ( strlen( $modification_content ) > 0 ) {
							$modification_content = '<textarea style="height: 100px; width: 100%;">' .
								esc_textarea( $modification_content ) . '</textarea>';
						}
						$required_changes .= $ex->modification_description() . $modification_content . '<br />';
					} elseif ( $ex instanceof Util_WpFile_FilesystemCopyException ) {
						$commands .= 'cp ' . $ex->source_filename() . ' ' .
							$ex->destination_filename() . '<br />';
					} elseif ( $ex instanceof Util_WpFile_FilesystemMkdirException ) {
						$commands .= 'mkdir ' . $ex->folder() . '<br />';
						$commands .= 'chmod 777 ' . $ex->folder() . '<br />';
					} elseif ( $ex instanceof Util_WpFile_FilesystemRmException ) {
						$commands .= 'rm ' . $ex->filename() . '<br />';
					} elseif ( $ex instanceof Util_WpFile_FilesystemRmdirException ) {
						$commands .= 'rm -rf ' . $ex->folder() . '<br />';
					} elseif ( $ex instanceof Util_WpFile_FilesystemChmodException ) {
						$commands .= 'chmod 777 ' . $ex->filename() . '<br />';
					}
				}
			} elseif ( $ex instanceof Util_Environment_Exception ) {
				$t = $ex->technical_message();
				if ( strlen( $t ) > 0 ) {
					$t = '<br /><a class="w3tc_read_technical_info" href="#">' . __( 'Technical info', 'w3-total-cache' ) .
						'</a><div class="w3tc_technical_info" style="display: none">' . $t . '</div>';
				}
				$later_errors[] = $ex->getMessage() . $t;
			} else {
				// unknown command.
				$later_errors[] = $ex->getMessage();
			}
		}

		if ( strlen( $commands ) > 0 ) {
			$required_changes .= __( 'Execute next commands in a shell:', 'w3-total-cache' ) . '<br><strong>' . $commands . '</strong>';
		}

		return array(
			'before_errors'    => $before_errors,
			'required_changes' => $required_changes,
			'later_errors'     => $later_errors,
		);
	}

	/**
	 * Deactivate plugin
	 *
	 * @return string[] error messages
	 */
	public static function deactivate_plugin() {
		$errors = array();
		try {
			$environment = Dispatcher::component( 'Root_Environment' );
			$environment->fix_after_deactivation();
			deactivate_plugins( plugin_basename( W3TC_FILE ) );
		} catch ( SelfTestExceptions $exs ) {
			$r = self::parse_environment_exceptions( $exs );

			foreach ( $r['before_errors'] as $e ) {
				$errors[] = $e;
			}

			if ( strlen( $r['required_changes'] ) > 0 ) {
				$changes_style = 'border: 1px solid black; background: white; margin: 10px 30px 10px 30px; padding: 10px; display: none';
				$ftp_style     = 'border: 1px solid black; background: white; margin: 10px 30px 10px 30px; padding: 10px; display: none';
				$ftp_form      = str_replace( 'class="wrap"', '', $exs->credentials_form() );
				$ftp_form      = str_replace( '<fieldset>', '', $ftp_form );
				$ftp_form      = str_replace( '</fieldset>', '', $ftp_form );
				$ftp_form      = str_replace( 'id="upgrade" class="button"', 'id="upgrade" class="button w3tc-button-save"', $ftp_form );

				$error = sprintf(
					'%s
					<table>
						<tr>
							<td>%s</td>
							<td>%s</td>
						</tr>
						<tr>
							<td>%s</td>
							<td>%s</td>
						</tr>
					</table>',
					__( '<strong>W3 Total Cache Error:</strong> Files and directories could not be automatically deleted.', 'w3-total-cache' ),
					__( 'Please execute commands manually', 'w3-total-cache' ),
					__( 'or use FTP form to allow <strong>W3 Total Cache</strong> make it automatically.', 'w3-total-cache' ),
					Util_Ui::button(
						__( 'View required changes', 'w3-total-cache' ),
						'',
						'w3tc-show-required-changes'
					),
					Util_Ui::button(
						__( 'Update via FTP', 'w3-total-cache' ),
						'',
						'w3tc-show-ftp-form'
					)
				) . '<div class="w3tc-required-changes" style="' . $changes_style . '">' . $r['required_changes'] . '</div>' .
					'<div class="w3tc-ftp-form" style="' . $ftp_style . '">' . $ftp_form . '</div>';

				$errors[] = $error;
			}

			return $errors;
		}
	}
}
