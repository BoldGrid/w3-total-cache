<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

$config = Dispatcher::config();
$state = Dispatcher::config_state();
$page = Util_Admin::get_current_page();
$licensing_visible = ( ( !Util_Environment::is_wpmu() || is_network_admin() ) &&
	!ini_get( 'w3tc.license_key' ) &&
	$state->get_string( 'license.status' ) != 'host_valid' );
?>

<?php do_action( 'w3tc-dashboard-head' ) ?>
<div class="wrap" id="w3tc">
	<h2 class="logo"><?php _e( 'W3 Total Cache <span>by W3 EDGE <sup>&reg;</sup></span>', 'w3-total-cache' ); ?></h2>
<?php if ( !Util_Environment::is_w3tc_pro( $config ) ): ?>
	<?php include W3TC_INC_OPTIONS_DIR . '/edd/buy.php' ?>
<?php endif ?>
	<?php
	switch ( $page ) {
	case 'w3tc_general':
		if ( !empty( Util_Request::get_string( 'view' ) ) ) {
			break;
		}
		$anchors = array(
			array( 'id' => 'general', 'text' => __( 'General', 'w3-total-cache' ) ),
			array( 'id' => 'page_cache', 'text' => __( 'Page Cache', 'w3-total-cache' ) ),
			array( 'id' => 'minify', 'text' => 'Minify' ),
			array( 'id' => 'system_opcache', 'text' => __( 'Opcode Cache', 'w3-total-cache' ) ),
			array( 'id' => 'database_cache', 'text' => __( 'Database Cache', 'w3-total-cache' ) ),
			array( 'id' => 'object_cache', 'text' => __( 'Object Cache', 'w3-total-cache' ) ) );
		if ( Util_Environment::is_w3tc_pro( $config ) )
			$anchors[] = array( 'id' => 'fragmentcache', 'text' => __( 'Fragment Cache', 'w3-total-cache' ) );

		$anchors = array_merge( $anchors, array(
				array( 'id' => 'browser_cache', 'text' => __( 'Browser Cache', 'w3-total-cache' ) ),
				array( 'id' => 'cdn', 'text' => __( '<abbr title="Content Delivery Network">CDN</abbr>', 'w3-total-cache' ) ),
				array( 'id' => 'reverse_proxy', 'text' => __( 'Reverse Proxy', 'w3-total-cache' ) ) ) );
		if ( Util_Environment::is_w3tc_pro() )
			$anchors[] = array( 'id' => 'amazon_sns', 'text' => __( 'Message Bus', 'w3-total-cache' ) );
		$anchors[] = array( 'id' => 'monitoring', 'text' => __( 'Monitoring', 'w3-total-cache' ) );
		if ( $licensing_visible )
			array( 'id' => 'licensing', 'text' => __( 'Licensing', 'w3-total-cache' ) );
		$link_attrs = array_merge( $anchors, $custom_areas, array(
				array( 'id' => 'miscellaneous', 'text' => __( 'Miscellaneous', 'w3-total-cache' ) ),
				array( 'id' => 'debug', 'text' => __( 'Debug', 'w3-total-cache' ) ),
				array( 'id' => 'settings', 'text' => __( 'Import / Export Settings', 'w3-total-cache' ) )
			) );

		$links = array();
		foreach ( $link_attrs as $link ) {
			$links[] = "<a href=\"#{$link['id']}\">{$link['text']}</a>";
		}

		$links[] = '<a href="#" class="button-self-test">Compatibility Test</a>';

?>
				<p id="w3tc-options-menu">
					<?php echo implode( ' | ', $links ); ?>
				</p>
	<?php
		break;
?>
	<?php
	case 'w3tc_pgcache':
?>
				<p id="w3tc-options-menu">
					Jump to:
					<a href="#toplevel_page_w3tc_general"><?php _e( 'Main Menu', 'w3-total-cache' ); ?></a> |
					<a href="#general"><?php _e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#mirrors"><?php _e( 'Mirrors', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php _e( 'Advanced', 'w3-total-cache' ); ?></a> |
					<a href="#cache_preload"><?php _e( 'Cache Preload', 'w3-total-cache' ); ?></a> |
					<a href="#purge_policy"><?php _e( 'Purge Policy', 'w3-total-cache' ); ?></a> |
					<a href="#notes"><?php _e( 'Note(s)', 'w3-total-cache' ); ?></a>
				</p>
	<?php
		break;
?>
	<?php
	case 'w3tc_minify':
?>
				<p id="w3tc-options-menu">
					<?php _e( 'Jump to: ', 'w3-total-cache' ); ?>
					<a href="#toplevel_page_w3tc_general"><?php _e( 'Main Menu', 'w3-total-cache' ); ?></a> |
					<a href="#general"><?php _e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#html_xml"><?php _e( '<acronym title="Hypertext Markup Language">HTML</acronym> &amp; <acronym title="eXtensible Markup Language">XML</acronym>', 'w3-total-cache' ); ?></a> |
					<a href="#js"><?php _e( '<acronym title="JavaScript">JS</acronym>', 'w3-total-cache' ); ?></a> |
					<a href="#css"><?php _e( '<acronym title="Cascading Style Sheet">CSS</acronym>', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php _e( 'Advanced', 'w3-total-cache' ); ?></a> |
					<a href="#notes"><?php _e( 'Note(s)', 'w3-total-cache' ); ?></a>
				</p>
	<?php
		break;
?>
	<?php
	case 'w3tc_dbcache':
?>
				<p id="w3tc-options-menu">
					<?php _e( 'Jump to: ', 'w3-total-cache' ); ?>
					<a href="#toplevel_page_w3tc_general"><?php _e( 'Main Menu', 'w3-total-cache' ); ?></a> |
					<a href="#general"><?php _e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php _e( 'Advanced', 'w3-total-cache' ); ?></a>
				</p>
	<?php
		break;
?>
	<?php
	case 'w3tc_objectcache':
?>
				<p id="w3tc-options-menu">
					<?php _e( 'Jump to: ', 'w3-total-cache' ); ?>
					<a href="#toplevel_page_w3tc_general"><?php _e( 'Main Menu', 'w3-total-cache' ); ?></a> |
					<a href="#advanced"><?php _e( 'Advanced', 'w3-total-cache' ); ?></a>
				</p>
	<?php
		break;
?>
	<?php
	case 'w3tc_browsercache':
?>
				<p id="w3tc-options-menu">
					<?php _e( 'Jump to: ', 'w3-total-cache' ); ?>
					<a href="#toplevel_page_w3tc_general"><?php _e( 'Main Menu', 'w3-total-cache' ); ?></a> |
					<a href="#general"><?php _e( 'General', 'w3-total-cache' ); ?></a> |
					<a href="#css_js"><?php _e( '<acronym title="Cascading Style Sheet">CSS</acronym> &amp; <acronym title="JavaScript">JS</acronym>', 'w3-total-cache' ); ?></a> |
					<a href="#html_xml"><?php _e( '<acronym title="Hypertext Markup Language">HTML</acronym> &amp; <acronym title="eXtensible Markup Language">XML</acronym>', 'w3-total-cache' ); ?></a> |
					<a href="#media"><?php _e( 'Media', 'w3-total-cache' ); ?></a> |
					<a href="#security"><?php _e( 'Security Headers', 'w3-total-cache' ); ?></a>
				</p>
	<?php
		break;
?>
	<?php
	case 'w3tc_cachegroups':
?>
				<p id="w3tc-options-menu">
					<?php _e( 'Jump to: ', 'w3-total-cache' ); ?>
					<a href="#toplevel_page_w3tc_general"><?php _e( 'Main Menu', 'w3-total-cache' ); ?></a> |
					<a href="#manage-uag"><?php _e( 'Manage User Agent Groups', 'w3-total-cache' ); ?></a> |
					<a href="#manage-rg"><?php _e( 'Manage Referrer Groups', 'w3-total-cache' ); ?></a> |
					<a href="#manage-cg"><?php _e( 'Manage Cookie Groups', 'w3-total-cache' ); ?></a>
				</p>
	<?php
		break;

	case 'w3tc_install':
?>
			<p id="w3tc-options-menu">
				Jump to:
				<a href="#initial"><?php _e( 'Initial Installation', 'w3-total-cache' ); ?></a> |
				<?php if ( count( $rewrite_rules_descriptors ) ): ?>
					<a href="#rules"><?php _e( 'Rewrite Rules', 'w3-total-cache' ); ?></a> |
				<?php endif ?>
				<?php if ( count( $other_areas ) ): ?>
					<a href="#other"><?php _e( 'Other', 'w3-total-cache' ); ?></a> |
				<?php endif ?>
				<a href="#additional"><?php _e( 'Services', 'w3-total-cache' ); ?></a> |
				<a href="#modules"><?php _e( '<acronym title="Hypertext Preprocessor">PHP</acronym> Modules', 'w3-total-cache' ); ?></a>
			</p>
<?php
	break;
	}
?>
