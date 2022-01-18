<?php
/**
 * File: Update_PluginsRow_View.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

?>

<tr class="plugin-update-tr active" id="w3-total-cache-update" data-slug="w3-total-cache" data-plugin="w3-total-cache/w3-total-cache.php">
	<td colspan="4" class="plugin-update colspanchange">
		<div class="update-message notice inline notice-warning notice-alt">
			<p>
<?php
printf(
	// translators: 1: HTML anchor open tag, 2: HTML anchor close tag.
	esc_html__(
		'There is a new version of W3 Total Cache available.  This update is blocked for Pro licenses.  Please follow the %1$sinstructions%2$s to complete the update.',
		'w3-total-cache'
	),
	'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_update' ) ) . '">',
	'</a>'
);
?>
				</p>
		</div>
	</td>
</tr>
