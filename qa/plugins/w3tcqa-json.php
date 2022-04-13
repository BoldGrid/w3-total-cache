<?php
/**
 * File: w3tcqa-json.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput.OutputNotEscaped
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

?>

<div id="resultReady" style="display: none">ready</div>
<textarea id="result" style="width: 100%; height: 500px"></textarea>

<script>
	async function run() {
		let response = await fetch(<?php echo wp_json_encode( $_REQUEST['url'] ); ?>, {
			method: 'post',
			headers: {
				'Content-Type': 'application/json; charset=UTF-8',
				'X-WP-Nonce': '<?php echo sanitize_key( wp_create_nonce( 'wp_rest' ) ); ?>'
			},
			body: '<?php echo $_REQUEST['body']; ?>'
		})

		let text = await response.text();
		document.querySelector('#resultReady').style.display = 'block';
		document.querySelector('#result').value = text;
	}

	run();
</script>
