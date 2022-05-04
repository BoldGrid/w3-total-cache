<?php

include(dirname(__FILE__) . '/wp-load.php');

$cronjob = wp_get_schedule('w3_pgcache_cleanup');
/** taking garbage collection value in seconds */
$schedules = wp_get_schedules();
$seconds = $schedules['w3_pgcache_cleanup']['interval'];
echo $cronjob . " " . $seconds;
/** clear pagecache */
do_action('w3_pgcache_cleanup');
