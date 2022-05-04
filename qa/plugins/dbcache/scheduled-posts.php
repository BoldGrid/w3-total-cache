<?php
include(dirname(__FILE__) . '/wp-load.php');
define('DONOTCACHEPAGE', true);
$post_id = $_REQUEST['ID'];
$future_post = get_post($post_id);
$date = time() - 600;
wp_update_post(array(
	'ID' => $post_id,
	'post_date_gmt' => gmdate('Y-m-d H:i:s', $date),
	'post_date' => gmdate('Y-m-d H:i:s', $date)
));
check_and_publish_future_post( $post_id );
echo 'Future post #' . $post_id . ' published successfully ' . gmdate('Y-m-d H:i:s', $date);
