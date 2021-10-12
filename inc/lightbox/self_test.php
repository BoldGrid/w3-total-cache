<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<h3><?php _e( 'Compatibility Check', 'w3-total-cache' ); ?></h3>

<fieldset>
    <legend><?php _e( 'Legend', 'w3-total-cache' ); ?></legend>

    <p>
        <?php _e( '<span style="background-color: #33cc33">Installed/Ok/Yes/True/On</span>: Functionality will work properly.', 'w3-total-cache' ); ?><br />
        <?php _e( '<span style="background-color: #FFFF00">Not detected/Not available/Off</span>: May be installed, but cannot be automatically confirmed. Functionality may be limited.', 'w3-total-cache' ); ?><br />
        <?php _e( '<span style="background-color: #FF0000">Not installed/Error/No/False</span>: Plugin or some functions may not work.', 'w3-total-cache' ); ?><br />
    </p>
</fieldset>

<div id="w3tc-self-test">
    <h4 style="margin-top: 0;"><?php _e( 'Server Modules &amp; Resources:', 'w3-total-cache' ); ?></h4>

    <ul>
        <li>
            <?php _e( 'Plugin Version:', 'w3-total-cache' ); ?> <code><?php echo W3TC_VERSION; ?></code>
        </li>

        <li>
            <?php _e( 'PHP Version:', 'w3-total-cache' ); ?>
            <code><?php echo PHP_VERSION; ?></code>;
        </li>

        <li>
            Web Server:
            <?php if ( stristr( $_SERVER['SERVER_SOFTWARE'], 'apache' ) !== false ): ?>
            <code>Apache</code>
            <?php elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'LiteSpeed' ) !== false ): ?>
            <code>Lite Speed</code>
            <?php elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ): ?>
            <code>nginx</code>
            <?php elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'lighttpd' ) !== false ): ?>
            <code>lighttpd</code>
            <?php elseif ( stristr( $_SERVER['SERVER_SOFTWARE'], 'iis' ) !== false ): ?>
            <code>Microsoft IIS</code>
            <?php else: ?>
            <span style="background-color: #FFFF00">Not detected</span>
            <?php endif; ?>
        </li>

        <li>
            FTP functions:
            <?php if ( function_exists( 'ftp_connect' ) ): ?>
            <span style="background-color: #33cc33">Installed</span>
            <?php else: ?>
            <span style="background-color: #FFFF00">Not detected</span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for Self-hosted (<acronym title="File Transfer Protocol">FTP</acronym>) <acronym title="Content Delivery Network">CDN</acronym> support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            <?php _e( 'Multibyte String support:', 'w3-total-cache' ); ?>
            <?php if ( function_exists( 'mb_substr' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for Rackspace Cloud Files support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            <?php _e( 'cURL extension:', 'w3-total-cache' ); ?>
            <?php if ( function_exists( 'curl_init' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for Amazon S3, Amazon CloudFront, Rackspace CloudFiles support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            zlib extension:
            <?php if ( function_exists( 'gzencode' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for gzip compression support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            brotli extension:
            <?php if ( function_exists( 'brotli_compress' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Not detected', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for brotli compression support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            Opcode cache:
            <?php if ( Util_Installed::opcache() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (OPCache)', 'w3-total-cache' ); ?></span>
            <?php elseif ( Util_Installed::apc() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (APC)', 'w3-total-cache' ); ?></span>
            <?php elseif ( Util_Installed::eaccelerator() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (eAccelerator)', 'w3-total-cache' ); ?></span>
            <?php elseif ( Util_Installed::xcache() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (XCache)', 'w3-total-cache' ); ?></span>
            <?php elseif ( PHP_VERSION >= 6 ): ?>
            <span style="background-color: #33cc33"><?php _e( 'PHP6', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'Memcached extension:', 'w3-total-cache' ); ?>
            <?php if ( class_exists( '\Memcached' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Not available', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'Memcache extension:', 'w3-total-cache' ); ?>
            <?php if ( class_exists( '\Memcache' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Not available', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'Redis extension:', 'w3-total-cache' ); ?>
            <?php if ( Util_Installed::redis() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Not available', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'HTML Tidy extension:', 'w3-total-cache' ); ?>
            <?php if ( Util_Installed::tidy() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for HTML Tidy minifier support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            <?php _e( 'Mime type detection:', 'w3-total-cache' ); ?>
            <?php if ( function_exists( 'finfo_open' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (Fileinfo)', 'w3-total-cache' ); ?></span>
            <?php elseif ( function_exists( 'mime_content_type' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (mime_content_type)', 'w3-total-cache' ); ?></span>
            <?php else:  ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for <acronym title="Content Delivery Network">CDN</acronym> support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            <?php _e( 'Hash function:', 'w3-total-cache' ); ?>
            <?php if ( function_exists( 'hash' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (hash)', 'w3-total-cache' ); ?></span>
            <?php elseif ( function_exists( 'mhash' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Installed (mhash)', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for NetDNA / MaxCDN <acronym title="Content Delivery Network">CDN</acronym> purge support)', 'w3-total-cache' ); ?></span>
        </li>

        <li>
            <?php _e( 'Open basedir:', 'w3-total-cache' ); ?>
            <?php $open_basedir = ini_get( 'open_basedir' ); if ( $open_basedir ): ?>
            <span style="background-color: #33cc33"><?php _e( 'On:', 'w3-total-cache' ); ?> <?php echo htmlspecialchars( $open_basedir ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Off', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'zlib output compression:', 'w3-total-cache' ); ?>
            <?php if ( Util_Environment::to_boolean( ini_get( 'zlib.output_compression' ) ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'On', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Off', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'set_time_limit:', 'w3-total-cache' ); ?>
            <?php if ( function_exists( 'set_time_limit' ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Available', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Not available', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            SSH2 extension:
            <?php if ( function_exists( 'ssh2_connect' ) ): ?>
                <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
            <?php else: ?>
                <span style="background-color: #FFFF00"><?php _e( 'Not detected', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
            <span class="w3tc-self-test-hint"><?php _e( '(required for Self-hosted (<acronym title="File Transfer Protocol">FTP</acronym>) <acronym title="Content Delivery Network">CDN</acronym> <acronym title="Secure File Transfer Protocol">SFTP</acronym> support)', 'w3-total-cache' ); ?></span>
        </li>

        <?php
if ( Util_Environment::is_apache() ):

    $modules = array(
        'mod_deflate',
        'mod_env',
        'mod_expires',
        'mod_filter',
        'mod_ext_filter',
        'mod_headers',
        'mod_mime',
        'mod_rewrite',
        'mod_setenvif'
    );

    if ( function_exists( 'apache_get_modules' ) ) {
        // apache_get_modules only works when PHP is installed as an Apache module
        $apache_modules = apache_get_modules();

    } elseif ( function_exists( 'exec' )) {
        // alternative modules capture for php CGI
        exec( 'apache2 -t -D DUMP_MODULES', $output, $status);

        if ( $status !== 0 ) {
            exec( 'httpd -t -D DUMP_MODULES', $output, $status);
        }

        if ( $status === 0 && count($output > 0) ) {
            $apache_modules = [];

            foreach ($output as $line) {
                if ( preg_match('/^\s(\S+)\s\((\S+)\)$/', $line, $matches) === 1) {
                    $apache_modules[] = $matches[1];
                }
            }
        }

        // modules have slightly different names
        $modules = array(
            'deflate_module',
            'env_module',
            'expires_module',
            'filter_module',
            'ext_filter_module',
            'headers_module',
            'mime_module',
            'rewrite_module',
            'setenvif_module'
        );
    } else {
        $apache_modules = false;
    }

?>
            <h5><?php _e( 'Detection of the below modules may not be possible on all environments. As such "Not detected" means that the environment disallowed detection for the given module which may still be installed/enabled whereas "Not installed" means the given module was detected but is not installed/detected.', 'w3-total-cache' )?></h5>
            <?php foreach ( $modules as $module ): ?>
                <li>
                    <?php echo $module; ?>:
                    <?php if ( ! empty( $apache_modules ) ): ?>
                        <?php if ( in_array( $module, $apache_modules ) ): ?>
                        <span style="background-color: #33cc33"><?php _e( 'Installed', 'w3-total-cache' ); ?></span>
                        <?php else: ?>
                        <span style="background-color: #FF0000"><?php _e( 'Not installed', 'w3-total-cache' ); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                    <span style="background-color: #FFFF00"><?php _e( 'Not detected', 'w3-total-cache' ); ?></span>
                    <?php endif; ?>
                    <span class="w3tc-self-test-hint"><?php _e( '(required for disk enhanced Page Cache and Browser Cache)', 'w3-total-cache' ); ?></span>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
    <?php $additional_checks = apply_filters( 'w3tc_compatibility_test', __return_empty_array() );
if ( $additional_checks ):?>
    <h4><?php _e( 'Additional Server Modules', 'w3-total-cache' )?></h4>
    <ul>
    <?php
	foreach ( $additional_checks as $check )
		echo '<li>', $check, '</li>';
?>
    </ul>
    <?php
	endif;
?>

    <h4><?php _e( 'WordPress Resources', 'w3-total-cache' ); ?></h4>

    <ul>
        <?php
$paths = array_unique( array(
		Util_Rule::get_pgcache_rules_core_path(),
		Util_Rule::get_browsercache_rules_cache_path(),
		Util_Rule::get_browsercache_rules_no404wp_path()
	) );
?>
        <?php foreach ( $paths as $path ): if ( $path ): ?>
        <li>
            <?php echo htmlspecialchars( $path ); ?>:
            <?php if ( file_exists( $path ) ): ?>
                <?php if ( Util_File::is_writable( $path ) ): ?>
                <span style="background-color: #33cc33"><?php _e( 'OK', 'w3-total-cache' ); ?></span>
                <?php else: ?>
                <span style="background-color: #FF0000"><?php _e( 'Not write-able', 'w3-total-cache' ); ?></span>
                <?php endif; ?>
            <?php else: ?>
                <?php if ( Util_File::is_writable_dir( dirname( $path ) ) ): ?>
                <span style="background-color: #33cc33"><?php _e( 'Write-able', 'w3-total-cache' ); ?></span>
                <?php else: ?>
                <span style="background-color: #FF0000"><?php _e( 'Not write-able', 'w3-total-cache' ); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </li>
        <?php endif; endforeach; ?>

        <li>
            <?php echo Util_Environment::normalize_path( WP_CONTENT_DIR ); ?>:
            <?php if ( Util_File::is_writable_dir( WP_CONTENT_DIR ) ): ?>
            <span style="background-color: #33cc33"><?php _e( 'OK', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not write-able', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php $uploads_dir = @wp_upload_dir(); ?>
            <?php echo htmlspecialchars( $uploads_dir['path'] ); ?>:
            <?php if ( !empty( $uploads_dir['error'] ) ): ?>
            <span style="background-color: #FF0000"><?php _e( 'Error:', 'w3-total-cache' ); ?> <?php echo htmlspecialchars( $uploads_dir['error'] ); ?></span>
            <?php elseif ( !Util_File::is_writable_dir( $uploads_dir['path'] ) ): ?>
            <span style="background-color: #FF0000"><?php _e( 'Not write-able', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #33cc33"><?php _e( 'OK', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'Fancy permalinks:', 'w3-total-cache' ); ?>
            <?php $permalink_structure = get_option( 'permalink_structure' ); if ( $permalink_structure ): ?>
            <span style="background-color: #33cc33"><?php echo htmlspecialchars( $permalink_structure ); ?></span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Off', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'WP_CACHE define:', 'w3-total-cache' ); ?>
            <?php if ( defined( 'WP_CACHE' ) && WP_CACHE == 'true' ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Defined (true)', 'w3-total-cache' ); ?></span>
            <?php elseif ( defined( 'WP_CACHE' ) && WP_CACHE == 'false' ): ?>
            <span style="background-color: #FF0000"><?php _e( 'Defined (false)', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Not defined', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'URL rewrite:', 'w3-total-cache' ); ?>
            <?php if ( Util_Rule::can_check_rules() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'Enabled', 'w3-total-cache' ); ?></span>
            <?php else: ?>
            <span style="background-color: #FF0000"><?php _e( 'Disabled', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>

        <li>
            <?php _e( 'Network mode:', 'w3-total-cache' ); ?>
            <?php if ( Util_Environment::is_wpmu() ): ?>
            <span style="background-color: #33cc33"><?php _e( 'On', 'w3-total-cache' ); ?> (<?php echo Util_Environment::is_wpmu_subdomain() ? 'subdomain' : 'subdir'; ?>)</span>
            <?php else: ?>
            <span style="background-color: #FFFF00"><?php _e( 'Off', 'w3-total-cache' ); ?></span>
            <?php endif; ?>
        </li>
    </ul>
</div>

<div id="w3tc-self-test-bottom">
    <input class="button-primary" type="button" value="<?php _e( 'Close', 'w3-total-cache' ); ?>" />
</div>
