<?php
/**
 * File: self_test.php
 *
 * Self test tab.
 *
 * @since   0.9.5
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
defined( 'W3TC' ) || die();

?>
<h3><?php esc_html_e( 'Compatibility Check', 'w3-total-cache' ); ?></h3>

<fieldset>
	<legend><?php esc_html_e( 'Legend', 'w3-total-cache' ); ?></legend>

	<p>
		<?php
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML span with background, 2 closing HTML span tag, 3 HTML line break tag.
				__(
					'%1$sInstalled/Ok/Yes/True/On%2$s: Functionality will work properly.%3$s',
					'w3-total-cache'
				),
				'<span style="padding: 0 2px;padding: 0 5px;background-color: #33cc33">',
				'</span>',
				'<br />'
			),
			array(
				'span' => array(
					'style' => array(),
				),
				'br'   => array(),
			)
		);
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML span with background, 2 closing HTML span tag, 3 HTML line break tag.
				__(
					'%1$sNot detected/Not available/Off%2$s: May be installed, but cannot be automatically confirmed. Functionality may be limited.%3$s',
					'w3-total-cache'
				),
				'<span style="padding: 0 2px;padding: 0 5px;background-color: #FFFF00">',
				'</span>',
				'<br />'
			),
			array(
				'span' => array(
					'style' => array(),
				),
				'br'   => array(),
			)
		);
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML span with background, 2 closing HTML span tag, 3 HTML line break tag.
				__(
					'%1$sNot installed/Error/No/False%2$s: Plugin or some functions may not work.%3$s',
					'w3-total-cache'
				),
				'<span style="padding: 0 2px;padding: 0 5px;background-color: #FF0000; color: #FFFFFF;">',
				'</span>',
				'<br />'
			),
			array(
				'span' => array(
					'style' => array(),
				),
				'br'   => array(),
			)
		);
		?>
	</p>
</fieldset>

<div id="w3tc-self-test">
	<h4 style="margin-top: 0;"><?php esc_html_e( 'Server Modules &amp; Resources:', 'w3-total-cache' ); ?></h4>

	<ul>
		<li>
			<?php esc_html_e( 'Plugin Version:', 'w3-total-cache' ); ?> <code><?php echo esc_html( W3TC_VERSION ); ?></code>
		</li>

		<li>
			<?php esc_html_e( 'PHP Version:', 'w3-total-cache' ); ?>
			<code><?php echo PHP_VERSION; ?></code>;
		</li>

		<li>
			Web Server:
			<?php
			$w3tc_server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

			switch ( true ) {
				case stristr( $w3tc_server_software, 'apache' ) !== false:
					?>
					<code>Apache</code>
					<?php
					break;
				case stristr( $w3tc_server_software, 'LiteSpeed' ) !== false:
					?>
					<code>Lite Speed</code>
					<?php
					break;
				case stristr( $w3tc_server_software, 'nginx' ) !== false:
					?>
					<code>nginx</code>
					<?php
					break;
				case stristr( $w3tc_server_software, 'lighttpd' ) !== false:
					?>
					<code>lighttpd</code>
					<?php
					break;
				case stristr( $w3tc_server_software, 'iis' ) !== false:
					?>
					<code>Microsoft IIS</code>
					<?php
					break;
				default:
					?>
					<span style="padding: 0 2px;background-color: #FFFF00">Not detected</span>
					<?php
					break;
			}
			?>
		</li>

		<li>
			FTP functions:
			<?php if ( function_exists( 'ftp_connect' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33">Installed</span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00">Not detected</span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint">
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
							// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag.
							__(
								'(required for Self-hosted (%1$sFTP%2$s) %3$sCDN%4$s support)',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'File Transfer Protocol', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</span>
		</li>

		<li>
			<?php esc_html_e( 'Multibyte String support:', 'w3-total-cache' ); ?>
			<?php if ( function_exists( 'mb_substr' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint"><?php esc_html_e( '(required for Rackspace Cloud Files support)', 'w3-total-cache' ); ?></span>
		</li>

		<li>
			<?php esc_html_e( 'cURL extension:', 'w3-total-cache' ); ?>
			<?php if ( function_exists( 'curl_init' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint"><?php esc_html_e( '(required for Amazon S3, Amazon CloudFront, Rackspace CloudFiles support)', 'w3-total-cache' ); ?></span>
		</li>

		<li>
			zlib extension:
			<?php if ( function_exists( 'gzencode' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint"><?php esc_html_e( '(required for gzip compression support)', 'w3-total-cache' ); ?></span>
		</li>

		<li>
			brotli extension:
			<?php if ( function_exists( 'brotli_compress' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not detected', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint"><?php esc_html_e( '(required for brotli compression support)', 'w3-total-cache' ); ?></span>
		</li>

		<li>
			Opcode cache:
			<?php if ( Util_Installed::opcache() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (OPCache)', 'w3-total-cache' ); ?></span>
			<?php elseif ( Util_Installed::apc() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (APC)', 'w3-total-cache' ); ?></span>
			<?php elseif ( Util_Installed::eaccelerator() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (eAccelerator)', 'w3-total-cache' ); ?></span>
			<?php elseif ( Util_Installed::xcache() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (XCache)', 'w3-total-cache' ); ?></span>
			<?php elseif ( PHP_VERSION >= 6 ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'PHP6', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'Memcached extension:', 'w3-total-cache' ); ?>
			<?php if ( class_exists( '\Memcached' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not available', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'Memcache extension:', 'w3-total-cache' ); ?>
			<?php if ( class_exists( '\Memcache' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not available', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'Redis extension:', 'w3-total-cache' ); ?>
			<?php if ( Util_Installed::redis() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not available', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'HTML Tidy extension:', 'w3-total-cache' ); ?>
			<?php if ( Util_Installed::tidy() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint"><?php esc_html_e( '(required for HTML Tidy minifier support)', 'w3-total-cache' ); ?></span>
		</li>

		<li>
			<?php esc_html_e( 'Mime type detection:', 'w3-total-cache' ); ?>
			<?php if ( function_exists( 'finfo_open' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (Fileinfo)', 'w3-total-cache' ); ?></span>
			<?php elseif ( function_exists( 'mime_content_type' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (mime_content_type)', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint">
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1 opening HTML acornym tag, 2 closing HTML acronym tag.
							__(
								'(required for %1$sCDN%2$s support)',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</span>
		</li>

		<li>
			<?php esc_html_e( 'Hash function:', 'w3-total-cache' ); ?>
			<?php if ( function_exists( 'hash' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (hash)', 'w3-total-cache' ); ?></span>
			<?php elseif ( function_exists( 'mhash' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed (mhash)', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'Open basedir:', 'w3-total-cache' ); ?>
			<?php $w3tc_open_basedir = ini_get( 'open_basedir' ); if ( $w3tc_open_basedir ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'On:', 'w3-total-cache' ); ?> <?php echo esc_html( $w3tc_open_basedir ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Off', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'zlib output compression:', 'w3-total-cache' ); ?>
			<?php if ( Util_Environment::to_boolean( ini_get( 'zlib.output_compression' ) ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'On', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Off', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'set_time_limit:', 'w3-total-cache' ); ?>
			<?php if ( function_exists( 'set_time_limit' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Available', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not available', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			SSH2 extension:
			<?php if ( function_exists( 'ssh2_connect' ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not detected', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
				<span class="w3tc-self-test-hint">
					<?php
					echo wp_kses(
						sprintf(
							// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
							// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag,
							// translators: 5 opening HTML acronym tag, 6 closing HTML acronym tag.
							__(
								'(required for Self-hosted (%1$sFTP%2$s) %3$sCDN%4$s %5$sSFTP%6$s support)',
								'w3-total-cache'
							),
							'<acronym title="' . esc_attr__( 'File Transfer Protocol', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
							'</acronym>',
							'<acronym title="' . esc_attr__( 'Secure File Transfer Protocol', 'w3-total-cache' ) . '">',
							'</acronym>'
						),
						array(
							'acronym' => array(
								'title' => array(),
							),
						)
					);
					?>
				</span>
		</li>

		<?php
		if ( Util_Environment::is_apache() ) :

			$w3tc_modules = array(
				'mod_deflate',
				'mod_env',
				'mod_expires',
				'mod_filter',
				'mod_ext_filter',
				'mod_headers',
				'mod_mime',
				'mod_rewrite',
				'mod_setenvif',
			);

			if ( function_exists( 'apache_get_modules' ) ) {
				// apache_get_modules only works when PHP is installed as an Apache module.
				$w3tc_apache_modules = apache_get_modules();

			} elseif ( function_exists( 'exec' ) ) {
				// alternative modules capture for php CGI.
				exec( 'apache2 -t -D DUMP_MODULES', $w3tc_output, $status );

				if ( 0 !== $status ) {
					exec( 'httpd -t -D DUMP_MODULES', $w3tc_output, $status );
				}

				if ( 0 === $status && 0 < count( $w3tc_output ) ) {
					$w3tc_apache_modules = array();

					foreach ( $w3tc_output as $w3tc_line ) {
						if ( preg_match( '/^\s(\S+)\s\((\S+)\)$/', $w3tc_line, $matches ) === 1 ) {
							$w3tc_apache_modules[] = $matches[1];
						}
					}
				}

				// modules have slightly different names.
				$w3tc_modules = array(
					'deflate_module',
					'env_module',
					'expires_module',
					'filter_module',
					'ext_filter_module',
					'headers_module',
					'mime_module',
					'rewrite_module',
					'setenvif_module',
				);
			} else {
				$w3tc_apache_modules = false;
			}

			?>
			<h5><?php esc_html_e( 'Detection of the below modules may not be possible on all environments. As such "Not detected" means that the environment disallowed detection for the given module which may still be installed/enabled whereas "Not installed" means the given module was detected but is not installed/detected.', 'w3-total-cache' ); ?></h5>
			<?php foreach ( $w3tc_modules as $w3tc_module ) : ?>
				<li>
					<?php echo esc_html( $w3tc_module ); ?>:
					<?php if ( ! empty( $w3tc_apache_modules ) ) : ?>
						<?php if ( in_array( $w3tc_module, $w3tc_apache_modules, true ) ) : ?>
							<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Installed', 'w3-total-cache' ); ?></span>
						<?php else : ?>
							<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not installed', 'w3-total-cache' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Not detected', 'w3-total-cache' ); ?></span>
					<?php endif; ?>
						<span class="w3tc-self-test-hint"><?php esc_html_e( '(required for disk enhanced Page Cache and Browser Cache)', 'w3-total-cache' ); ?></span>
				</li>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>
	<?php
	$w3tc_additional_checks = apply_filters( 'w3tc_compatibility_test', __return_empty_array() );
	if ( $w3tc_additional_checks ) :
		?>
		<h4><?php esc_html_e( 'Additional Server Modules', 'w3-total-cache' ); ?></h4>
		<ul>
			<?php
			foreach ( $w3tc_additional_checks as $w3tc_check ) :
				echo '<li>' . wp_kses( $w3tc_check, Util_Ui::get_allowed_html_for_wp_kses_from_content( $w3tc_check ) ) . '</li>';
			endforeach;
			?>
		</ul>
		<?php
	endif;
	?>

	<h4><?php esc_html_e( 'WordPress Resources', 'w3-total-cache' ); ?></h4>

	<ul>
		<?php
		$w3tc_paths = array_unique(
			array(
				Util_Rule::get_pgcache_rules_core_path(),
				Util_Rule::get_browsercache_rules_cache_path(),
			)
		);
		?>
		<?php
		foreach ( $w3tc_paths as $w3tc_rules_path ) :
			if ( $w3tc_rules_path ) :
				?>
				<li>
					<?php echo esc_html( $w3tc_rules_path ); ?>:
					<?php if ( file_exists( $w3tc_rules_path ) ) : ?>
						<?php if ( Util_File::is_writable( $w3tc_rules_path ) ) : ?>
							<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'OK', 'w3-total-cache' ); ?></span>
						<?php else : ?>
							<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not write-able', 'w3-total-cache' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<?php if ( Util_File::is_writable_dir( dirname( $w3tc_rules_path ) ) ) : ?>
							<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Write-able', 'w3-total-cache' ); ?></span>
						<?php else : ?>
							<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not write-able', 'w3-total-cache' ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</li>
				<?php
			endif;
		endforeach;
		?>

		<li>
			<?php echo esc_html( Util_Environment::normalize_path( WP_CONTENT_DIR ) ); ?>:
			<?php if ( Util_File::is_writable_dir( WP_CONTENT_DIR ) ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'OK', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not write-able', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php $w3tc_uploads_dir = @wp_upload_dir(); ?>
			<?php echo esc_html( $w3tc_uploads_dir['path'] ); ?>:
			<?php if ( ! empty( $w3tc_uploads_dir['error'] ) ) : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Error:', 'w3-total-cache' ); ?> <?php echo esc_html( $w3tc_uploads_dir['error'] ); ?></span>
			<?php elseif ( ! Util_File::is_writable_dir( $w3tc_uploads_dir['path'] ) ) : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not write-able', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'OK', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'Fancy permalinks:', 'w3-total-cache' ); ?>
			<?php $w3tc_permalink_structure = get_option( 'permalink_structure' ); if ( $w3tc_permalink_structure ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php echo esc_html( $w3tc_permalink_structure ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Off', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'WP_CACHE define:', 'w3-total-cache' ); ?>
			<?php if ( defined( 'WP_CACHE' ) && WP_CACHE ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Defined (true)', 'w3-total-cache' ); ?></span>
			<?php elseif ( defined( 'WP_CACHE' ) && ! WP_CACHE ) : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Defined (false)', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Not defined', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'URL rewrite:', 'w3-total-cache' ); ?>
			<?php if ( Util_Rule::can_check_rules() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'Enabled', 'w3-total-cache' ); ?></span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FF0000; color: #FFFFFF;"><?php esc_html_e( 'Disabled', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>

		<li>
			<?php esc_html_e( 'Network mode:', 'w3-total-cache' ); ?>
			<?php if ( Util_Environment::is_wpmu() ) : ?>
				<span style="padding: 0 2px;background-color: #33cc33"><?php esc_html_e( 'On', 'w3-total-cache' ); ?> (<?php echo Util_Environment::is_wpmu_subdomain() ? 'subdomain' : 'subdir'; ?>)</span>
			<?php else : ?>
				<span style="padding: 0 2px;background-color: #FFFF00"><?php esc_html_e( 'Off', 'w3-total-cache' ); ?></span>
			<?php endif; ?>
		</li>
	</ul>
</div>

<div id="w3tc-self-test-bottom">
	<input class="button-primary" type="button" value="<?php esc_attr_e( 'Close', 'w3-total-cache' ); ?>" />
</div>
