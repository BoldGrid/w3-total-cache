<?php
/**
 * File: Extension_AlwaysCached_Page_View.php
 *
 * Render the AlwaysCached settings page.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();

if ( ! $config->get_boolean( 'pgcache.enabled' ) ) {
	echo wp_kses(
		sprintf(
			// Translators: 1 opening HTML div tag followed by opening HTML p tag, 2 opening HTML a tag to general settings page,
			// Translators: 3 closing HTML a tag, 4 closing HTML p tag followed by closing HTML div tag.
			__( '%1$sPage Cache is required for the Always Cached extension. Please enable it %2$shere.%3$s%4$s', 'w3-total-cache' ),
			'<div class="notice notice-error inline"><p>',
			'<a href="' . esc_url( Util_UI::admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) . '">',
			'</a>',
			'</p></div>'
		),
		array(
			'div' => array(
				'class' => array(),
			),
			'p'   => array(),
			'a'   => array(
				'href' => array(),
			),
		)
	);
}

?>
<p>
	<?php esc_html_e( 'Page Cache is currently ', 'w3-total-cache' ); ?>
	<?php
	if ( $config->get_boolean( 'pgcache.enabled' ) ) {
		echo '<span class="w3tc-enabled">' . esc_html__( 'enabled.', 'w3-total-cache' ) . '</span>';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html__( 'disabled.', 'w3-total-cache' ) . '</span>';
	}
	?>
<p>
<p>
	<?php esc_html_e( 'AlwaysCached extension is currently ', 'w3-total-cache' ); ?>
	<?php
	if ( Extension_AlwaysCached_Plugin::is_enabled() ) {
		echo '<span class="w3tc-enabled">' . esc_html__( 'enabled.', 'w3-total-cache' ) . '</span>';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html__( 'disabled.', 'w3-total-cache' ) . '</span>';
	}
	?>
<p>
<p>
	<?php esc_html_e( 'The Always Cached extension prevents page/post updates from clearing corresponding cache entries and instead adds them to a queue that can be manually cleared or scheduled to clear via cron.', 'w3-total-cache' ); ?>
</p>
<form action="admin.php?page=w3tc_extensions&amp;extension=alwayscached&amp;action=view" method="post">
	<?php
	Util_Ui::print_control_bar( 'extension_alwayscached_form_control' );

	echo wp_kses(
		Util_Ui::nonce_field( 'w3tc' ),
		array(
			'input' => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
			),
		)
	);

	require __DIR__ . '/Extension_AlwaysCached_Page_View_BoxQueue.php';
	require __DIR__ . '/Extension_AlwaysCached_Page_View_Exclusions.php';
	require __DIR__ . '/Extension_AlwaysCached_Page_View_BoxCron.php';
	require __DIR__ . '/Extension_AlwaysCached_Page_View_BoxFlushAll.php';

	?>
</form>
