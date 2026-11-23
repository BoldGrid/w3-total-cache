<?php
/**
 * File: Support_Page_View_PageContent.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
require W3TC_INC_DIR . '/options/common/header.php'; ?>

<div id="w3tc-support-form-shell">
	<p>
		<?php esc_html_e( 'Premium support requests are submitted through a third-party form. The form loads only after you agree and choose to open it.', 'w3-total-cache' ); ?>
	</p>
	<p>
		<label for="w3tc-support-consent">
			<input type="checkbox" id="w3tc-support-consent" />
			<?php esc_html_e( 'I understand that a third-party form will load to send this request.', 'w3-total-cache' ); ?>
		</label>
	</p>
	<p>
		<button type="button" id="w3tc-support-load" class="button button-primary" disabled>
			<?php esc_html_e( 'Open support form', 'w3-total-cache' ); ?>
		</button>
	</p>
	<p id="w3tc-support-fallback" hidden>
		<?php esc_html_e( 'The support form could not be loaded. Please try again later.', 'w3-total-cache' ); ?>
	</p>
	<div id="w3tc-support-form-mount"></div>
</div>
