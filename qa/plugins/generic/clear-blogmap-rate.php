<?php
/**
 * File: clear-blogmap-rate.php
 *
 * QA fixture: deletes the per-IP rate-limit transients written by
 * {@see \W3TC\Util_WpmuBlogmap::register_new_item()} so a multisite
 * spec running from a fixed CI source IP can register more than 5
 * brand-new blog URLs inside a single 60-second window without
 * tripping the rt9-180 sub-C rate limit. Without this clear, a matrix
 * that sweeps multiple subsite scenarios back-to-back will short-
 * circuit the 6th+ `register_new_item()` call and skip the
 * `Root_Environment::fix_on_event( 'first_frontend' )` fan-out —
 * causing the per-blog rewrite-rule install + blogmap.json mutation
 * assertions to race-fail.
 *
 * Scope is intentionally narrow: only the
 * `_transient_w3tc_blogmap_register_rate_*` rows (and their
 * companion `_transient_timeout_*` rows) are removed. No other
 * transient or option is touched.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.DB.DirectDatabaseQuery
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

global $wpdb;

$wpdb->query(
	"DELETE FROM `{$wpdb->options}`
	WHERE option_name LIKE '\\_transient\\_w3tc\\_blogmap\\_register\\_rate\\_%'
	   OR option_name LIKE '\\_transient\\_timeout\\_w3tc\\_blogmap\\_register\\_rate\\_%'"
);

if ( is_multisite() ) {
	$site_table = $wpdb->base_prefix . 'sitemeta';
	$wpdb->query(
		"DELETE FROM `{$site_table}`
		WHERE meta_key LIKE '\\_site\\_transient\\_w3tc\\_blogmap\\_register\\_rate\\_%'
		   OR meta_key LIKE '\\_site\\_transient\\_timeout\\_w3tc\\_blogmap\\_register\\_rate\\_%'"
	);
}

echo 'ok';
