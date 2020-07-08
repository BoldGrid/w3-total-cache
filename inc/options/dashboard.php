<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>

<div id="w3tc_dashboard_banner" class="metabox-holder">
	<div class="postbox">
		<div class="w3tc-postbox-ad">
			<?php
				echo wp_kses(
					sprintf(
						// Translators: 1 the opening anchor tag to the w3tc_support page, 2 its closing tag, 3 a line break.
						__( 'Did you know that we offer premium support services?%3$s Our experts will configure W3 Total Cache for you! %1$sClick here for info%2$s.', 'w3-total-cache' ),
						'<a href="' . admin_url( 'admin.php?page=w3tc_support' ) . '">',
						'</a>',
						'<br />'
					),
					array(
						'a'  => array(
							'href' => array()
						),
						'br' => array(),
					)
				);
			?>
		</div>
		<h3 class="hndle">
			<img style="height:32px;" src="<?php echo plugins_url( 'w3-total-cache/pub/img/W3TC_dashboard_logo_title.png' ); ?>" />
		</h3>
		<div class="inside">
			<p>
				<?php
					echo wp_kses(
						sprintf(
							// Translators: 1 an opening strong tag, 2 its closing tag.
							__( 'You\'re using the Community Edition of W3 Total Cache. Maximize your website\'s speed even more by upgrading to %1$sW3 Total Cache Pro%2$s to unlock advanced anaytics, fragment caching, full site delivery, extension support and other tools that will allow you to completely fine tune your website\'s performance.', 'w3-total-cache' ),
							'<strong>',
							'</strong>'
						),
						array( 'strong' => array() )
					);
				?>
			</p>
			<p>
				<input
					type="button"
					class="button w3tc-gopro-button button-buy-plugin"
					data-src="dashboard_banner" value="<?php esc_attr_e( 'Learn more about Pro', 'w3-total-cache' ) ?>" />
			</p>
		</div>
	</div>
</div>

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
