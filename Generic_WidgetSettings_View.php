<?php
/**
 * File: Generic_WidgetSettings_View.php
 *
 * @since   2.7.0
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

$settings = array(
	'preview_mode'  => array(
		'label'   => esc_html__( 'Preview Mode', 'w3-total-cache' ),
		'enabled' => Util_Environment::is_preview_mode(),
	),
	'pgcache'       => array(
		'label'   => esc_html__( 'Page Cache', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'pgcache.enabled' ),
	),
	'minify'        => array(
		'label'   => esc_html__( 'Minify', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'minify.enabled' ),
	),
	'opcode'        => array(
		'label'   => esc_html__( 'Opcode Cache', 'w3-total-cache' ),
		'enabled' => Util_Installed::opcache() || Util_Installed::apc_opcache(),
	),
	'dbcache'       => array(
		'label'   => esc_html__( 'Database Cache', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'dbcache.enabled' ),
	),
	'objectcache'   => array(
		'label'   => esc_html__( 'Object Cache', 'w3-total-cache' ),
		'enabled' => $config->getf_boolean( 'objectcache.enabled' ),
	),
	'browsercache'  => array(
		'label'   => esc_html__( 'Browser Cache', 'w3-total-cache' ),
		'enabled' => $config->getf_boolean( 'browsercache.enabled' ),
	),
	'cdn'           => array(
		'label'   => esc_html__( 'CDN', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'cdn.enabled' ) || $config->get_boolean( 'cdnfsd.enabled' ),
	),
	'varnish'       => array(
		'label'   => esc_html__( 'Reverse Proxy', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'varnish.enabled' ),
	),
	'stats'         => array(
		'label'   => esc_html__( 'Statistics', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'stats.enabled' ),
	),
	'fragmentcache' => array(
		'label'   => esc_html__( 'Fragment Cache', 'w3-total-cache' ),
		'enabled' => $config->is_extension_active_frontend( 'fragmentcache' ) && Util_Environment::is_w3tc_pro( $config ),
	),
	'debug'         => array(
		'label'   => esc_html__( 'Debug', 'w3-total-cache' ),
		'enabled' => $config->get_boolean( 'pgcache.debug' )
			|| $config->get_boolean( 'pgcache.debug_purge' )
			|| $config->get_boolean( 'minify.debug' )
			|| $config->get_boolean( 'dbcache.debug' )
			|| $config->get_boolean( 'dbcache.debug_purge' )
			|| $config->get_boolean( 'objectcache.debug' )
			|| $config->get_boolean( 'objectcache.debug_purge' )
			|| $config->get_boolean( array( 'fragmentcache', 'debug' ) )
			|| $config->get_boolean( 'cdn.debug' )
			|| $config->get_boolean( 'cdnfsd.debug' )
			|| $config->get_boolean( 'varnish.debug' )
			|| $config->get_boolean( 'cluster.messagebus.debug' ),
	),
);
?>
<div class="general-settings-container">
	<?php
	$enabled  = esc_html__( 'enabled', 'w3-total-cache' );
	$disabled = esc_html__( 'disabled', 'w3-total-cache' );
	foreach ( $settings as $setting ) {
		?>
		<div class="general-setting">
			<span><b><?php echo $setting['label']; ?></b></span>
			<span class="general-setting-enabled<?php echo $setting['enabled'] ? ' setting-enabled' : ''; ?>">
				<?php echo $setting['enabled'] ? $enabled : $disabled; ?>
			</span>
		</div>
		<?php
	}
	?>
</div>
<p class="general-settings-description">
	<?php esc_html_e( 'Settings can be modified by visiting ', 'w3-total-cache' ); ?><a href="<?php echo esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general' ) ); ?>" alt="General Settings"><?php esc_html_e( 'General Settings', 'w3-total-cache' ); ?></a>
</p>
