<?php
/**
 * File: latest_ajax.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<?php foreach ( $items as $w3tc_item ) : ?>
<h4>
	<a href="<?php echo esc_url( $w3tc_item['link'] ); ?>">
		<?php echo esc_html( wp_strip_all_tags( $w3tc_item['title'] ) ); ?>
	</a>
</h4>
<?php endforeach ?>

<p style="text-align: center;">
	<a href="<?php echo esc_url( W3TC_FEED_URL ); ?>" target="_blank">View Feed</a>
</p>
