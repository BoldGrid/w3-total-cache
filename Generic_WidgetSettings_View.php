<?php
/**
 * File: Generic_WidgetSettings_View.php
 *
 * @since   2.7.0
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$w3tc_config = Dispatcher::config();

$w3tc_settings = array(
	'preview_mode'  => array(
		'label'   => esc_html__( 'Preview Mode', 'w3-total-cache' ),
		'enabled' => Util_Environment::is_preview_mode(),
	),
	'pgcache'       => array(
		'label'   => esc_html__( 'Page Cache', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'pgcache.enabled' ),
	),
	'minify'        => array(
		'label'   => esc_html__( 'Minify', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'minify.enabled' ),
	),
	'opcode'        => array(
		'label'   => esc_html__( 'Opcode Cache', 'w3-total-cache' ),
		'enabled' => Util_Installed::opcache() || Util_Installed::apc_opcache(),
	),
	'dbcache'       => array(
		'label'   => esc_html__( 'Database Cache', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'dbcache.enabled' ),
	),
	'objectcache'   => array(
		'label'   => esc_html__( 'Object Cache', 'w3-total-cache' ),
		'enabled' => $w3tc_config->getf_boolean( 'objectcache.enabled' ),
	),
	'browsercache'  => array(
		'label'   => esc_html__( 'Browser Cache', 'w3-total-cache' ),
		'enabled' => $w3tc_config->getf_boolean( 'browsercache.enabled' ),
	),
	'cdn'           => array(
		'label'   => esc_html__( 'CDN', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'cdn.enabled' ) || $w3tc_config->get_boolean( 'cdnfsd.enabled' ),
	),
	'varnish'       => array(
		'label'   => esc_html__( 'Reverse Proxy', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'varnish.enabled' ),
	),
	'stats'         => array(
		'label'   => esc_html__( 'Statistics', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'stats.enabled' ),
	),
	'fragmentcache' => array(
		'label'   => esc_html__( 'Fragment Cache', 'w3-total-cache' ),
		'enabled' => $w3tc_config->is_extension_active_frontend( 'fragmentcache' ),
	),
	'debug'         => array(
		'label'   => esc_html__( 'Debug', 'w3-total-cache' ),
		'enabled' => $w3tc_config->get_boolean( 'pgcache.debug' )
			|| $w3tc_config->get_boolean( 'pgcache.debug_purge' )
			|| $w3tc_config->get_boolean( 'minify.debug' )
			|| $w3tc_config->get_boolean( 'dbcache.debug' )
			|| $w3tc_config->get_boolean( 'dbcache.debug_purge' )
			|| $w3tc_config->get_boolean( 'objectcache.debug' )
			|| $w3tc_config->get_boolean( 'objectcache.debug_purge' )
			|| $w3tc_config->get_boolean( array( 'fragmentcache', 'debug' ) )
			|| $w3tc_config->get_boolean( 'cdn.debug' )
			|| $w3tc_config->get_boolean( 'cdnfsd.debug' )
			|| $w3tc_config->get_boolean( 'varnish.debug' )
			|| $w3tc_config->get_boolean( 'cluster.messagebus.debug' ),
	),
);
?>
<div class="general-settings-container">
	<?php
	$w3tc_enabled  = __( 'enabled', 'w3-total-cache' );
	$w3tc_disabled = __( 'disabled', 'w3-total-cache' );
	foreach ( $w3tc_settings as $w3tc_setting ) {
		?>
		<div class="general-setting">
			<span><b><?php echo esc_html( $w3tc_setting['label'] ); ?></b></span>
			<span class="general-setting-enabled<?php echo $w3tc_setting['enabled'] ? ' setting-enabled' : ''; ?>">
				<?php echo esc_html( $w3tc_setting['enabled'] ? $w3tc_enabled : $w3tc_disabled ); ?>
			</span>
		</div>
		<?php
	}
	?>
</div>
<p class="general-settings-description">
	<?php esc_html_e( 'Settings can be modified by visiting ', 'w3-total-cache' ); ?><a href="<?php echo esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general' ) ); ?>" alt="General Settings"><?php esc_html_e( 'General Settings', 'w3-total-cache' ); ?></a>
</p>
