<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);

//{id: 8, content: "<!-- wp:paragraph -->↵<p>bb</p>↵<!-- /wp:paragraph -->", status: "publish"}
?>
<div id="resultReady" style="display: none">ready</div>
<textarea id="result" style="width: 100%; height: 500px"></textarea>

<script>

async function run() {
	let response = await fetch(<?php echo json_encode($_REQUEST['url']) ?>, {
		method: 'post',
		headers: {
			'Content-Type': 'application/json; charset=UTF-8',
			'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ) ?>'
		},
		body: '<?php echo $_REQUEST['body'] ?>'
	})

	let text = await response.text();
	document.querySelector('#resultReady').style.display = 'block';
	document.querySelector('#result').value = text;
}



run();

</script>
