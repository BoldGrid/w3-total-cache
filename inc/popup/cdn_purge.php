<?php
/**
 * File: cdn_purge.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<?php require W3TC_INC_DIR . '/popup/common/header.php'; ?>

<p>
	<?php
	echo wp_kses(
		sprintf(
			// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
			__(
				'Remove objects from the %1$sCDN%2$s by specifying the relative path on individual lines below and clicking the "Purge" button when done. For example:',
				'w3-total-cache'
			),
			'<acronym title="' . esc_attr__( 'Content Delivery Network', 'w3-total-cache' ) . '">',
			'</acronym>'
		),
		array(
			'acronym' => array(
				'title' => array(),
			),
		)
	);
	?>
</p>
<p>
	<em><?php echo esc_url( $path ); ?>/images/headers/path.jpg</em>
</p>


<form action="admin.php?page=w3tc_cdn" method="post">
	<p><?php esc_html_e( 'Files to purge:', 'w3-total-cache' ); ?></p>
	<p>
		<textarea name="files" rows="10" cols="90"></textarea>
	</p>
	<p>
		<?php
		echo wp_kses(
			Util_Ui::nonce_field( Util_Nonce::admin_action( 'w3tc_cdn_purge_files' ) ),
			array(
				'input' => array(
					'type'  => array(),
					'name'  => array(),
					'value' => array(),
				),
			)
		);
		?>
		<input class="button-primary" type="submit" name="w3tc_cdn_purge_files" value="<?php esc_attr_e( 'Purge', 'w3-total-cache' ); ?>" />
	</p>
</form>

<div class="log">
	<?php foreach ( $results as $w3tc_result ) : ?>
		<div class="log-<?php echo W3TC_CDN_RESULT_OK === $w3tc_result['result'] ? 'success' : 'error'; ?>">
			<?php echo esc_html( $w3tc_result['remote_path'] ); ?>
			<strong><?php echo esc_html( $w3tc_result['error'] ); ?></strong>
		</div>
	<?php endforeach; ?>
</div>
