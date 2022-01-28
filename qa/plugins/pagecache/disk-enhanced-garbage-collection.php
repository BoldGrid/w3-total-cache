<?php
/**
 * File: disk-enhanced-garbage-collection.php
 *
 * Page cache: Disk enhanced garbage collection test.
 *
 * @package W3TC
 * @subpackage QA
 */

require __DIR__ . '/wp-load.php';

// Taking garbage collection value in seconds.
$cronjob   = wp_get_schedule( 'w3_pgcache_cleanup' );
$schedules = wp_get_schedules();
$seconds   = $schedules['w3_pgcache_cleanup']['interval'];

echo esc_html( $cronjob ) . ' ' . esc_html( $seconds );

do_action( 'w3_pgcache_cleanup' );
