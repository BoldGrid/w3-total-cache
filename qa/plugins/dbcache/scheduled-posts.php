<?php
/**
 * File: scheduled-posts.php
 *
 * Database cache: Scheduled posts.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Recommended, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

define( 'DONOTCACHEPAGE', true );

require __DIR__ . '/wp-load.php';

$post_id     = $_REQUEST['ID'];
$future_post = get_post( $post_id );
$date        = time() - 600;

wp_update_post(
	array(
		'ID'            => $post_id,
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $date ),
		'post_date'     => gmdate( 'Y-m-d H:i:s', $date )
	)
);

check_and_publish_future_post( $post_id );

echo 'Future post #' . esc_html( $post_id ) . ' published successfully ' . esc_html( gmdate( 'Y-m-d H:i:s', $date ) );
