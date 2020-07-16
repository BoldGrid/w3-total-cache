<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

/*
 * Display the header for our dashboard.
 *
 * If we're on the pro version, we'll show the standard W3TC logo and a message stating the user is
 * on pro. As of 0.14.3, the free version will instead show a really, really nice banner. Really terrific.
 * Just fantasic. Other banners, not so good. Everyone agrees, believe me.
 */
if ( Util_Environment::is_w3tc_pro( Dispatcher::config() ) ) {
	include W3TC_INC_DIR . '/options/common/header.php';

	echo '<p>' .
		sprintf( __( 'The plugin is currently <span class="w3tc-%s">%s</span> in <strong>%s</strong> mode.', 'w3-total-cache' )
			, $enabled ? "enabled" : "disabled"
			, $enabled ? __( 'enabled', 'w3-total-cache' ) : __( 'disabled', 'w3-total-cache' )
			, Util_Environment::w3tc_edition( $this->_config ) ) .
	'</p>';
} else {
	// When header.php is not included (above), we need to do our head action and open the wrap.
	do_action( 'w3tc-dashboard-head' );
	echo '<div class="wrap" id="w3tc">';

	include W3TC_INC_DIR . '/options/parts/dashboard_banner.php';
}
?>

<form id="w3tc_dashboard" action="admin.php?page=<?php echo $this->_page; ?>" method="post">
    <p>
        Perform a
        <input type="button" class="button button-self-test {nonce: '<?php echo wp_create_nonce( 'w3tc' ); ?>'}" value="<?php _e( 'compatibility check', 'w3-total-cache' ) ?>" />,
        <?php echo Util_Ui::nonce_field( 'w3tc' ); ?>
        <input id="flush_all" class="button" type="submit" name="w3tc_flush_all" value="<?php _e( 'empty all caches', 'w3-total-cache' ) ?>"<?php if ( ! $enabled ): ?> disabled="disabled"<?php endif; ?> /> <?php _e( 'at once or', 'w3-total-cache' ) ?>
        <input class="button" type="submit" name="w3tc_flush_memcached" value="<?php _e( 'empty only the memcached cache(s)', 'w3-total-cache' ) ?>"<?php if ( ! $can_empty_memcache ): ?> disabled="disabled"<?php endif; ?> /> <?php _e( 'or', 'w3-total-cache' ) ?>
        <input class="button" type="submit" name="w3tc_flush_opcode" value="<?php _e( 'empty only the opcode cache', 'w3-total-cache' ) ?>"<?php if ( ! $can_empty_opcode ): ?> disabled="disabled"<?php endif; ?> /> <?php _e( 'or', 'w3-total-cache' ) ?>
        <input class="button" type="submit" name="w3tc_flush_file" value="<?php _e( 'empty only the disk cache(s)', 'w3-total-cache' ) ?>"<?php if ( ! $can_empty_file ): ?> disabled="disabled"<?php endif; ?> /> <?php _e( 'or', 'w3-total-cache' ) ?>
        <?php if ( $cdn_mirror_purge && $cdn_enabled ): ?>
        <input class="button" type="submit" name="w3tc_flush_cdn" value="<?php _e( 'purge CDN completely', 'w3-total-cache' ) ?>" /> <?php _e( 'or', 'w3-total-cache' ) ?>
        <?php endif; ?>
        <input type="submit" name="w3tc_flush_browser_cache" value="<?php _e( 'update Media Query String', 'w3-total-cache' ) ?>" <?php disabled( ! ( $browsercache_enabled && $browsercache_update_media_qs ) ) ?> class="button" />
        <?php
$string = __( 'or', 'w3-total-cache' );
echo implode( " $string ", apply_filters( 'w3tc_dashboard_actions', array() ) ) ?>.
    </p>
</form>

    <div id="w3tc-dashboard-widgets" class="clearfix widefat metabox-holder">
        <?php $screen = get_current_screen();
?>
        <div id="postbox-container-left">
            <div class="content">
            <div id="dashboard-text" style="display:inline-block;">
                <h1><?php _e( 'Dashboard', 'w3-total-cache' )?></h1>
                <p>Thanks for choosing W3TC as your Web Performance Optimization (<acronym title="Web Performance Optimization">WPO</acronym>) framework!
            </div>
            <div id="widgets-container">
            <?php do_meta_boxes( $screen->id, 'normal', '' ); ?>
            </div>
            </div>
        </div>
        <div id="postbox-container-right">
            <div id='postbox-container-3' class='postbox-container' style="width: 100%;">
                <?php do_meta_boxes( $screen->id, 'side', '' ); ?>
            </div>
        </div>
        <div style="clear:both"></div>

        <?php
wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
?>
    </div>

<?php include W3TC_INC_DIR . '/options/common/footer.php'; ?>
