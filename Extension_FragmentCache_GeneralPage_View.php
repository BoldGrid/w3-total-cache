<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

Util_Ui::postbox_header_tabs(
	esc_html__( 'Fragment Cache', 'w3-total-cache' ),
	esc_html__(
		'Fragment caching is a powerful feature that helps improve the speed and performance of your
			website. It allows you to cache specific sections or fragments of your web pages instead
			of caching the entire page. By selectively caching these fragments, such as sidebar widgets
			or dynamic content, you can reduce the processing time required to generate the page,
			resulting in faster load times and improved overall site performance.',
		'w3-total-cache'
	),
	'',
	'fragmentcache',
	Util_Environment::is_w3tc_pro( $config ) ? Util_UI::admin_url( 'admin.php?page=w3tc_fragmentcache' ) : ''
);

?>

<table class="form-table">
	<div class="fragmentcache_disk_notice notice notice-warning">
		<p><b><?php esc_html_e( 'Warning: Disk-Based Fragment Caching Selected', 'w3-total-cache' ); ?></b></p>
		<p>
			<li style="margin-left:15px;"><?php esc_html_e( 'Using disk as the cache engine for fragment caching is not recommended due to its potential for slow performance depending on storage device types and server configuration.', 'w3-total-cache' ); ?></li>
			<li style="margin-left:15px;"><?php esc_html_e( 'This setting can potentially create a large number of files.  Please be aware of any inode or disk space limits you may have on your hosting account.', 'w3-total-cache' ); ?></li>
		</p>
		<p>
			<?php esc_html_e( 'For optimal performance, consider using a memory-based caching solution like Redis or Memcached.', 'w3-total-cache' ); ?>
			<a target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/comparing-disk-redis-memcached-caching/' ); ?>"
				title="<?php esc_attr_e( 'Comparing Disk, Redis, and Memcached: Understanding Caching Solutions', 'w3-total-cache' ); ?>">
				<?php esc_html_e( 'Learn more', 'w3-total-cache' ); ?> <span class="dashicons dashicons-external"></span></a>
		</p>
	</div>
	<?php
	$fragmentcache_config = array(
		'key'         => array( 'fragmentcache', 'engine' ),
		'label'       => __( 'Fragment Cache Method:', 'w3-total-cache' ),
		'empty_value' => true,
		'pro'         => true,
	);

	if ( ! Util_Environment::is_w3tc_pro( $config ) ) {
		$fragmentcache_config['disabled'] = true;
	}

	Util_Ui::config_item_engine( $fragmentcache_config );
	?>
</table>

<?php Util_Ui::postbox_footer(); ?>
