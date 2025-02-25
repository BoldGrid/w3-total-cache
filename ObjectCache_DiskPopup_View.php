<?php
/**
 * File: ObjectCache_DiskPopup_View.php
 *
 * @since 2.8.6
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div class="w3tc-overlay-logo"></div>
<header>
</header>
<div class="w3tchelp_content">
	<h3><b><?php esc_html_e( 'Warning: Disk-Based Object Caching Selected', 'w3-total-cache' ); ?></b></h3>
	<p>
		<?php esc_html_e( 'Using disk as the object cache engine comes with risks:', 'w3-total-cache' ); ?>
	</p>
	<p>
		<li style="margin-left:15px;"><?php esc_html_e( 'Using disk as the cache engine for object caching is not recommended due to its potential for slow performance depending on storage device types and server configuration.', 'w3-total-cache' ); ?></li>
		<li style="margin-left:15px;"><?php esc_html_e( 'This setting can potentially create a large number of files.  Please be aware of any inode or disk space limits you may have on your hosting account.', 'w3-total-cache' ); ?></li>
	</p>
	<p>
		<?php esc_html_e( 'For optimal performance, consider using a memory-based caching solution like Redis or Memcached.', 'w3-total-cache' ); ?>
		<a target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/comparing-disk-redis-memcached-caching/' ); ?>"
			title="<?php esc_attr_e( 'Comparing Disk, Redis, and Memcached: Understanding Caching Solutions', 'w3-total-cache' ); ?>">
			<?php esc_html_e( 'Learn more', 'w3-total-cache' ); ?> <span class="dashicons dashicons-external"></span></a>
	</p>
	<div>
		<input type="submit" class="btn w3tc-size image btn-primary outset save palette-turquoise"
			value="<?php esc_attr_e( 'I Understand the Risks', 'w3-total-cache' ); ?>">
		<input type="button" class="btn w3tc-size image btn-secondary outset palette-light-grey"
			value="<?php esc_attr_e( 'Cancel', 'w3-total-cache' ); ?>">
	</div>
</div>
