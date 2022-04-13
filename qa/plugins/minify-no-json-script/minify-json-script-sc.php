<?php

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_script( 'jquery' );
} );


add_action( 'wp_head', function() {
	?>

	<script type="text/javascript">
		/* <![CDATA[ */
		var js4 = "#js4";
		console.log(   "hello"  + "   world"  );
		/* ]]> */
	</script>
	<script>
		console.log(   "hello2"  + " world2"  );
	</script>
	<script type="application/json">
		{ "a": ["b", "c"]  }
	</script>

	<?php
} );
