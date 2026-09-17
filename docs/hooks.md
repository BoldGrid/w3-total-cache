# W3 Total Cache hooks

W3 Total Cache exposes the actions and filters below for integrations and customizations. Names containing `{...}` are dynamic patterns; substitute the value described by the placeholder at runtime.

This reference is generated from production PHP sources. Do not edit its tables manually. Run `php bin/generate-hooks-docs.php` to regenerate them, or `php bin/generate-hooks-docs.php --check` to verify they are current.

## Usage

Register callbacks with the function in the Register with column. Most hooks use WordPress's `add_action()` or `add_filter()`. Hooks dispatched before WordPress is available use W3TC's `w3tc_add_action()` for both actions and filters. For WordPress hooks, the Accepted arguments column is the integer to pass as the fourth argument to `add_action()` or `add_filter()`; multiple integers mean the hook is dispatched with different arities. For early hooks registered with `w3tc_add_action()`, the value is informational because that function does not take an accepted-arguments setting. For filters, the first parameter is the value your callback must return.

```php
add_filter(
	'w3tc_can_cache',
	static function ( $can_cache, $content_grabber, $buffer ) {
		return $can_cache;
	},
	10,
	3
);
```

## Actions

| Hook | Register with | Accepted arguments | Parameters | Defined in |
| --- | --- | ---: | --- | --- |
| `w3tc-dashboard-footer` | `add_action()` | 0 | `None` | [inc/options/common/footer.php:19](../inc/options/common/footer.php#L19) |
| `w3tc-dashboard-head` | `add_action()` | 0 | `None` | [inc/options/common/header.php:14](../inc/options/common/header.php#L14)<br>[inc/wizard/template.php:114](../inc/wizard/template.php#L114) |
| `w3tc_ajax` | `add_action()` | 0 | `None` | [Generic_Plugin_Admin.php:374](../Generic_Plugin_Admin.php#L374) |
| `w3tc_ajax_{action}` | `add_action()` | 0 | `None` | [Generic_Plugin_Admin.php:375](../Generic_Plugin_Admin.php#L375) |
| `w3tc_audit_log` | `add_action()` | 2 | `$event, $context` | [Util_Debug.php:149](../Util_Debug.php#L149) |
| `w3tc_cdn_purge_all` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:204](../CacheFlush_Locally.php#L204) |
| `w3tc_cdn_purge_all_after` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:209](../CacheFlush_Locally.php#L209) |
| `w3tc_cdn_purge_files` | `add_action()` | 1 | `$purgefiles` | [CacheFlush_Locally.php:226](../CacheFlush_Locally.php#L226) |
| `w3tc_cdn_purge_files_after` | `add_action()` | 1 | `$purgefiles` | [CacheFlush_Locally.php:230](../CacheFlush_Locally.php#L230) |
| `w3tc_config_save` | `add_action()` | 1 | `$this` | [Config.php:841](../Config.php#L841) |
| `w3tc_config_ui_save` | `add_action()` | 2 | `$w3tc_config, $this->_config` | [Generic_AdminActions_Default.php:701](../Generic_AdminActions_Default.php#L701) |
| `w3tc_config_ui_save-{page}` | `add_action()` | 2 | `$w3tc_config, $this->_config` | [Generic_AdminActions_Default.php:702](../Generic_AdminActions_Default.php#L702) |
| `w3tc_dashboard_top_nav_bar` | `add_action()` | 0 | `None` | [inc/options/common/top_nav_bar.php:116](../inc/options/common/top_nav_bar.php#L116) |
| `w3tc_db_delete` | `add_action()` | 3 | `$table, $where, $where_format` | [DbCache_WpdbNew.php:297](../DbCache_WpdbNew.php#L297) |
| `w3tc_db_insert` | `add_action()` | 3 | `$table, $w3tc_data, $format` | [DbCache_WpdbNew.php:205](../DbCache_WpdbNew.php#L205) |
| `w3tc_db_replace` | `add_action()` | 3 | `$table, $w3tc_data, $format` | [DbCache_WpdbNew.php:267](../DbCache_WpdbNew.php#L267) |
| `w3tc_db_update` | `add_action()` | 5 | `$table, $w3tc_data, $where, $format, $where_format` | [DbCache_WpdbNew.php:283](../DbCache_WpdbNew.php#L283) |
| `w3tc_deactivate_extension_{extension}` | `add_action()` | 0 | `None` | [Extensions_Util.php:204](../Extensions_Util.php#L204) |
| `w3tc_environment_fix_after_deactivation` | `add_action()` | 0 | `None` | [Root_Environment.php:120](../Root_Environment.php#L120) |
| `w3tc_environment_fix_on_event` | `add_action()` | 2 | `$w3tc_config, $event` | [Root_Environment.php:88](../Root_Environment.php#L88) |
| `w3tc_environment_fix_on_wpadmin_request` | `add_action()` | 2 | `$w3tc_config, $force_all_checks` | [Root_Environment.php:51](../Root_Environment.php#L51) |
| `w3tc_extension_after_row` | `add_action()` | 1 | `$w3tc_extension` | [inc/options/extensions/list.php:244](../inc/options/extensions/list.php#L244) |
| `w3tc_extension_after_row-{extension}` | `add_action()` | 0 | `None` | [inc/options/extensions/list.php:245](../inc/options/extensions/list.php#L245) |
| `w3tc_extension_before_row-{extension}` | `add_action()` | 0 | `None` | [inc/options/extensions/list.php:102](../inc/options/extensions/list.php#L102) |
| `w3tc_extension_load` | `add_action()` | 0 | `None` | [Root_Loader.php:215](../Root_Loader.php#L215) |
| `w3tc_extension_load_admin` | `add_action()` | 0 | `None` | [Root_Loader.php:217](../Root_Loader.php#L217) |
| `w3tc_extension_page_{extension}` | `add_action()` | 0 | `None` | [inc/options/extensions/settings.php:23](../inc/options/extensions/settings.php#L23) |
| `w3tc_extension_requirements-{extension}` | `add_action()` | 0 | `None` | [inc/options/extensions/list.php:199](../inc/options/extensions/list.php#L199) |
| `w3tc_flush_after_browsercache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:185](../CacheFlush_Locally.php#L185) |
| `w3tc_flush_after_fragmentcache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:103](../CacheFlush_Locally.php#L103) |
| `w3tc_flush_after_fragmentcache_group` | `add_action()` | 1 | `$w3tc_group` | [CacheFlush_Locally.php:119](../CacheFlush_Locally.php#L119) |
| `w3tc_flush_after_minify` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:146](../CacheFlush_Locally.php#L146) |
| `w3tc_flush_after_objectcache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:78](../CacheFlush_Locally.php#L78) |
| `w3tc_flush_all` | `add_action()` | 1 | `$extras` | [CacheFlush_Locally.php:318](../CacheFlush_Locally.php#L318) |
| `w3tc_flush_browsercache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:183](../CacheFlush_Locally.php#L183) |
| `w3tc_flush_dbcache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:46](../CacheFlush_Locally.php#L46) |
| `w3tc_flush_fragmentcache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:102](../CacheFlush_Locally.php#L102) |
| `w3tc_flush_fragmentcache_group` | `add_action()` | 1 | `$w3tc_group` | [CacheFlush_Locally.php:118](../CacheFlush_Locally.php#L118) |
| `w3tc_flush_group` | `add_action()` | 2 | `$w3tc_group, $extras` | [CacheFlush_Locally.php:335](../CacheFlush_Locally.php#L335) |
| `w3tc_flush_minify` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:143](../CacheFlush_Locally.php#L143) |
| `w3tc_flush_objectcache` | `add_action()` | 0 | `None` | [CacheFlush_Locally.php:74](../CacheFlush_Locally.php#L74) |
| `w3tc_flush_post` | `add_action()` | 3 | `$post_id, $force, $extras` | [CacheFlush_Locally.php:261](../CacheFlush_Locally.php#L261) |
| `w3tc_flush_posts` | `add_action()` | 1 | `$extras` | [CacheFlush_Locally.php:277](../CacheFlush_Locally.php#L277) |
| `w3tc_flush_url` | `add_action()` | 2 | `$w3tc_url, $extras` | [CacheFlush_Locally.php:352](../CacheFlush_Locally.php#L352) |
| `w3tc_hide_button-{note}` | `add_action()` | 0 | `None` | [Generic_AdminActions_Default.php:155](../Generic_AdminActions_Default.php#L155) |
| `w3tc_hide_button_custom-{note}` | `add_action()` | 0 | `None` | [Generic_AdminActions_Default.php:296](../Generic_AdminActions_Default.php#L296) |
| `w3tc_messagebus_message_processed` | `add_action()` | 0 | `None` | [Enterprise_SnsServer.php:182](../Enterprise_SnsServer.php#L182) |
| `w3tc_messagebus_message_received` | `add_action()` | 0 | `None` | [Enterprise_SnsServer.php:170](../Enterprise_SnsServer.php#L170) |
| `w3tc_message_action_{action}` | `add_action()` | 0 | `None` | [Generic_Plugin_Admin.php:632](../Generic_Plugin_Admin.php#L632)<br>[Generic_Plugin_Admin.php:639](../Generic_Plugin_Admin.php#L639) |
| `w3tc_network_dashboard_setup` | `add_action()` | 0 | `None` | [Util_Widget.php:46](../Util_Widget.php#L46) |
| `w3tc_pagecache_before_set` | `add_action()` | 1 | `array( 'request_url_fragments' => $this->_request_url_fragments, 'page_key_extension' => $this->_page_key_extension, )` | [PgCache_ContentGrabber.php:2966](../PgCache_ContentGrabber.php#L2966) |
| `w3tc_purge_urls_box` | `add_action()` | 0 | `None` | [inc/options/cdn.php:500](../inc/options/cdn.php#L500) |
| `w3tc_redirect` | `add_action()` | 0 | `None` | [Util_Environment.php:1334](../Util_Environment.php#L1334)<br>[Util_Environment.php:1356](../Util_Environment.php#L1356) |
| `w3tc_register_fragment_groups` | `add_action()` | 0 | `None` | [Extension_FragmentCache_Plugin.php:191](../Extension_FragmentCache_Plugin.php#L191) |
| `w3tc_saved_options` | `add_action()` | 1 | `$new_config` | [Util_Admin.php:607](../Util_Admin.php#L607) |
| `w3tc_settings_box_cdnfsd` | `add_action()` | 0 | `None` | [inc/options/cdn.php:495](../inc/options/cdn.php#L495) |
| `w3tc_settings_cdn_boxarea_configuration` | `add_action()` | 0 | `None` | [inc/options/cdn.php:486](../inc/options/cdn.php#L486) |
| `w3tc_settings_general_boxarea_cdn` | `add_action()` | 0 | `None` | [inc/options/general.php:611](../inc/options/general.php#L611) |
| `w3tc_settings_general_boxarea_cdn_footer` | `add_action()` | 0 | `None` | [Cdn_GeneralPage_View.php:151](../Cdn_GeneralPage_View.php#L151) |
| `w3tc_settings_general_boxarea_system_opcache` | `add_action()` | 0 | `None` | [inc/options/general.php:345](../inc/options/general.php#L345) |
| `w3tc_settings_general_boxarea_{area_id}` | `add_action()` | 0 | `None` | [inc/options/general.php:831](../inc/options/general.php#L831) |
| `w3tc_settings_page-{page}` | `add_action()` | 0 | `None` | [Root_AdminMenu.php:313](../Root_AdminMenu.php#L313) |
| `w3tc_usage_statistics_of_request` | `add_action()` | 1 | `$this->storage` | [UsageStatistics_Core.php:101](../UsageStatistics_Core.php#L101) |
| `w3tc_userexperience_page` | `add_action()` | 0 | `None` | [UserExperience_Page_View.php:30](../UserExperience_Page_View.php#L30) |
| `w3tc_widget_setup` | `add_action()` | 0 | `None` | [Util_Widget.php:49](../Util_Widget.php#L49) |
| `wp_loaded` | `w3tc_add_action()` | 0 | `None` | [Root_Loader.php:214](../Root_Loader.php#L214) |

## Filters

| Hook | Register with | Accepted arguments | Parameters | Defined in |
| --- | --- | ---: | --- | --- |
| `config_filename` | `w3tc_add_action()` | 1 | `array( 'blog_id' => $blog_id, 'preview' => $preview, 'filename' => $filename, )` | [Config.php:149](../Config.php#L149) |
| `pagecache_extract_accept_qs` | `w3tc_add_action()` | 1 | `$ignore_qs` | [PgCache_ContentGrabber.php:2831](../PgCache_ContentGrabber.php#L2831) |
| `pagecache_key_extension` | `w3tc_add_action()` | 3 | `$w3tc_extension, $this->_request_url_fragments['host'], $this->_request_uri` | [PgCache_ContentGrabber.php:1380](../PgCache_ContentGrabber.php#L1380) |
| `pagecache_normalize_url_fragments` | `w3tc_add_action()` | 1 | `$fragments` | [PgCache_ContentGrabber.php:2816](../PgCache_ContentGrabber.php#L2816) |
| `pagecache_page_key` | `w3tc_add_action()` | 1 | `array( 'key' => array( $key_urlpart, $key_extension, $key_postfix, $key_compression, ), 'page_key_extension' => $page_key_extension, 'url_fragments' => $this->_request_url_fragments, )` | [PgCache_ContentGrabber.php:1740](../PgCache_ContentGrabber.php#L1740) |
| `w3tc_admin_actions` | `add_filter()` | 1 | `$handlers` | [Root_AdminActions.php:133](../Root_AdminActions.php#L133) |
| `w3tc_admin_bar_menu` | `add_filter()` | 1 | `$menu_items` | [Generic_Plugin.php:719](../Generic_Plugin.php#L719) |
| `w3tc_admin_menu` | `add_filter()` | 2 | `$pages, $this->_config` | [Root_AdminMenu.php:146](../Root_AdminMenu.php#L146) |
| `w3tc_ajax_base_capability_` | `add_filter()` | 1 | `'manage_options'` | [Generic_Plugin_Admin.php:358](../Generic_Plugin_Admin.php#L358) |
| `w3tc_ajax_capability_{action}` | `add_filter()` | 1 | `$base_capability` | [Generic_Plugin_Admin.php:359](../Generic_Plugin_Admin.php#L359) |
| `w3tc_alwayscached_worker_timeslot` | `add_filter()` | 1 | `$timeslot_seconds` | [Extension_AlwaysCached_Worker.php:32](../Extension_AlwaysCached_Worker.php#L32) |
| `w3tc_browsercache_rules_apache` | `add_filter()` | 1 | `$rules` | [BrowserCache_Environment.php:757](../BrowserCache_Environment.php#L757) |
| `w3tc_browsercache_rules_apache_brotli` | `add_filter()` | 1 | `$rules_brotli` | [BrowserCache_Environment.php:387](../BrowserCache_Environment.php#L387) |
| `w3tc_browsercache_rules_apache_cssjs` | `add_filter()` | 1 | `$this->_rules_cache_generate_apache_for_type( $w3tc_config, $mime_types2['cssjs'], 'cssjs' )` | [BrowserCache_Environment.php:463](../BrowserCache_Environment.php#L463) |
| `w3tc_browsercache_rules_apache_deflate` | `add_filter()` | 1 | `$rules_deflate` | [BrowserCache_Environment.php:448](../BrowserCache_Environment.php#L448) |
| `w3tc_browsercache_rules_apache_headers` | `add_filter()` | 1 | `$rules_headers` | [BrowserCache_Environment.php:740](../BrowserCache_Environment.php#L740) |
| `w3tc_browsercache_rules_apache_html` | `add_filter()` | 1 | `$this->_rules_cache_generate_apache_for_type( $w3tc_config, $mime_types2['html'], 'html' )` | [BrowserCache_Environment.php:473](../BrowserCache_Environment.php#L473) |
| `w3tc_browsercache_rules_apache_mime` | `add_filter()` | 1 | `$rules_mime` | [BrowserCache_Environment.php:335](../BrowserCache_Environment.php#L335) |
| `w3tc_browsercache_rules_apache_other` | `add_filter()` | 1 | `$this->_rules_cache_generate_apache_for_type( $w3tc_config, $mime_types2['other'], 'other' )` | [BrowserCache_Environment.php:483](../BrowserCache_Environment.php#L483) |
| `w3tc_browsercache_rules_apache_rewrite` | `add_filter()` | 1 | `$g->rules_rewrite()` | [BrowserCache_Environment.php:755](../BrowserCache_Environment.php#L755) |
| `w3tc_browsercache_rules_section` | `add_filter()` | 3 | `$section_rules, $this->w3tc_c, $section` | [BrowserCache_Environment_LiteSpeed.php:140](../BrowserCache_Environment_LiteSpeed.php#L140) |
| `w3tc_browsercache_rules_section_extensions` | `add_filter()` | 3 | `3: $mime_types, $this->w3tc_c, $section`<br>`3: $mime_types, $w3tc_config, $section` | [BrowserCache_Environment.php:778](../BrowserCache_Environment.php#L778)<br>[BrowserCache_Environment_LiteSpeed.php:121](../BrowserCache_Environment_LiteSpeed.php#L121)<br>[BrowserCache_Environment_Nginx.php:431](../BrowserCache_Environment_Nginx.php#L431) |
| `w3tc_build_cdn_file_array` | `add_filter()` | 1 | `$w3tc_file` | [Cdn_Core.php:817](../Cdn_Core.php#L817) |
| `w3tc_cached_mobile_groups` | `add_filter()` | 1 | `$cached_mobile_groups` | [CacheGroups_Plugin_Admin.php:125](../CacheGroups_Plugin_Admin.php#L125) |
| `w3tc_cache_allowed_classes` | `add_filter()` | 2 | `$defaults, $context` | [Cache_Base.php:467](../Cache_Base.php#L467) |
| `w3tc_can_cache` | `add_filter()` | 3 | `$original_can_cache, $this, $buffer` | [PgCache_ContentGrabber.php:549](../PgCache_ContentGrabber.php#L549) |
| `w3tc_can_print_comment` | `add_filter()` | 1 | `self::is_html_xml( $buffer ) && ! defined( 'DOING_AJAX' )` | [Util_Content.php:70](../Util_Content.php#L70) |
| `w3tc_capability_admin_bar` | `add_filter()` | 1 | `'manage_options'` | [Generic_Plugin.php:576](../Generic_Plugin.php#L576)<br>[Util_Capability.php:82](../Util_Capability.php#L82)<br>[Util_Capability.php:119](../Util_Capability.php#L119) |
| `w3tc_capability_admin_bar_{item_id}` | `add_filter()` | 1 | `$default_cap` | [Generic_Plugin.php:756](../Generic_Plugin.php#L756) |
| `w3tc_capability_admin_notices` | `add_filter()` | 1 | `'manage_options'` | [Licensing_Plugin_Admin.php:367](../Licensing_Plugin_Admin.php#L367) |
| `w3tc_capability_config_save` | `add_filter()` | 1 | `'manage_options'` | [Generic_AdminActions_Default.php:455](../Generic_AdminActions_Default.php#L455) |
| `w3tc_capability_extensions_activate_{extension}` | `add_filter()` | 1 | `'manage_options'` | [Extensions_AdminActions.php:32](../Extensions_AdminActions.php#L32) |
| `w3tc_capability_favorite_action_flush_all` | `add_filter()` | 1 | `$capability` | [Util_Capability.php:98](../Util_Capability.php#L98) |
| `w3tc_capability_flush_all` | `add_filter()` | 1 | `$capability` | [Util_Capability.php:91](../Util_Capability.php#L91) |
| `w3tc_capability_flush_post` | `add_filter()` | 1 | `$capability` | [Util_Capability.php:128](../Util_Capability.php#L128) |
| `w3tc_capability_menu` | `add_filter()` | 1 | `$base_capability` | [Generic_Plugin_Admin.php:824](../Generic_Plugin_Admin.php#L824) |
| `w3tc_capability_menu_w3tc_dashboard` | `add_filter()` | 1 | `$base_capability` | [Root_AdminMenu.php:171](../Root_AdminMenu.php#L171) |
| `w3tc_capability_menu_{slug}` | `add_filter()` | 1 | `$base_capability` | [Root_AdminMenu.php:190](../Root_AdminMenu.php#L190) |
| `w3tc_capability_row_action_w3tc_flush_post` | `add_filter()` | 1 | `$capability` | [Util_Capability.php:135](../Util_Capability.php#L135) |
| `w3tc_cdn_add_attachment` | `add_filter()` | 1 | `$post_files` | [Cdn_Core_Admin.php:305](../Cdn_Core_Admin.php#L305) |
| `w3tc_cdn_cf_flush_all_uris` | `add_filter()` | 1 | `array( '/*' )` | [Cdnfsd_CloudFront_Engine.php:97](../Cdnfsd_CloudFront_Engine.php#L97) |
| `w3tc_cdn_config_headers` | `add_filter()` | 1 | `array()` | [Cdn_Core.php:548](../Cdn_Core.php#L548) |
| `w3tc_cdn_delete_attachment` | `add_filter()` | 1 | `$files` | [Cdn_Plugin.php:377](../Cdn_Plugin.php#L377) |
| `w3tc_cdn_rules_section` | `add_filter()` | 2 | `$section_rules, $this->w3tc_c` | [Cdn_Environment_LiteSpeed.php:68](../Cdn_Environment_LiteSpeed.php#L68) |
| `w3tc_cdn_update_attachment` | `add_filter()` | 1 | `$files` | [Cdn_Plugin.php:351](../Cdn_Plugin.php#L351) |
| `w3tc_cdn_update_attachment_metadata` | `add_filter()` | 1 | `$files` | [Cdn_Plugin.php:399](../Cdn_Plugin.php#L399) |
| `w3tc_cdn_url` | `add_filter()` | 3 | `$new_url, $w3tc_url, $is_engine_mirror` | [Cdn_Core.php:765](../Cdn_Core.php#L765) |
| `w3tc_cloudflare_proxy_cidrs` | `add_filter()` | 1 | `$cidrs` | [Util_Environment.php:2257](../Util_Environment.php#L2257) |
| `w3tc_compatibility_test` | `add_filter()` | 1 | `__return_empty_array()` | [inc/lightbox/self_test.php:438](../inc/lightbox/self_test.php#L438) |
| `w3tc_config_default_values` | `add_filter()` | 1 | `array()` | [Config.php:224](../Config.php#L224)<br>[Config.php:233](../Config.php#L233) |
| `w3tc_config_item_{key}` | `add_filter()` | 1 | `$v` | [Config.php:400](../Config.php#L400) |
| `w3tc_config_key_descriptor` | `add_filter()` | 2 | `$w3tc_descriptor, $w3tc_key` | [Generic_AdminActions_Default.php:950](../Generic_AdminActions_Default.php#L950) |
| `w3tc_config_labels` | `add_filter()` | 1 | `array()` | [Util_Ui.php:210](../Util_Ui.php#L210) |
| `w3tc_dashboard_actions` | `add_filter()` | 1 | `array()` | [Util_Ui.php:482](../Util_Ui.php#L482) |
| `w3tc_dashboard_widgets` | `add_filter()` | 1 | `array()` | [Util_Widget.php:50](../Util_Widget.php#L50) |
| `w3tc_dbcache_cache_set` | `add_filter()` | 1 | `$filter_data` | [DbCache_WpdbInjection_QueryCaching.php:246](../DbCache_WpdbInjection_QueryCaching.php#L246) |
| `w3tc_dbcache_can_cache_sql` | `add_filter()` | 2 | `( $caching ? '' : $reject_reason ), $query` | [DbCache_WpdbInjection_QueryCaching.php:182](../DbCache_WpdbInjection_QueryCaching.php#L182) |
| `w3tc_dbcache_get_flush_groups` | `add_filter()` | 3 | `$groups_to_flush, $w3tc_group, $extras` | [DbCache_WpdbInjection_QueryCaching.php:817](../DbCache_WpdbInjection_QueryCaching.php#L817) |
| `w3tc_dbcache_get_sql_group` | `add_filter()` | 3 | `$w3tc_group, $sql, $tables` | [DbCache_WpdbInjection_QueryCaching.php:752](../DbCache_WpdbInjection_QueryCaching.php#L752) |
| `w3tc_dbcluster_after_query` | `add_filter()` | 1 | `$query` | [Enterprise_Dbcache_WpdbInjection_Cluster.php:729](../Enterprise_Dbcache_WpdbInjection_Cluster.php#L729)<br>[lib/Db/mssql.php:1140](../lib/Db/mssql.php#L1140) |
| `w3tc_dbcluster_query` | `add_filter()` | 1 | `$query` | [Enterprise_Dbcache_WpdbInjection_Cluster.php:690](../Enterprise_Dbcache_WpdbInjection_Cluster.php#L690)<br>[lib/Db/mssql.php:1062](../lib/Db/mssql.php#L1062) |
| `w3tc_deferscripts_can_process` | `add_filter()` | 1 | `$can_process` | [UserExperience_DeferScripts_Extension.php:93](../UserExperience_DeferScripts_Extension.php#L93) |
| `w3tc_deferscripts_embed_script` | `add_filter()` | 1 | `$buffer` | [UserExperience_DeferScripts_Extension.php:119](../UserExperience_DeferScripts_Extension.php#L119) |
| `w3tc_deferscripts_is_embed_script` | `add_filter()` | 1 | `true` | [UserExperience_DeferScripts_Extension.php:121](../UserExperience_DeferScripts_Extension.php#L121) |
| `w3tc_deferscripts_mutator_before` | `add_filter()` | 1 | `array( 'buffer' => $buffer, 'modified' => $this->modified, )` | [UserExperience_DeferScripts_Mutator.php:64](../UserExperience_DeferScripts_Mutator.php#L64) |
| `w3tc_dynamic_callbacks` | `add_filter()` | 1 | `array()` | [PgCache_ContentGrabber.php:2299](../PgCache_ContentGrabber.php#L2299) |
| `w3tc_environment_get_other_instructions` | `add_filter()` | 1 | `$other_areas` | [Generic_Page_Install.php:34](../Generic_Page_Install.php#L34) |
| `w3tc_environment_get_required_rules` | `add_filter()` | 2 | `$required_rules, $w3tc_config` | [Root_Environment.php:149](../Root_Environment.php#L149) |
| `w3tc_errors` | `add_filter()` | 1 | `$errors` | [Generic_Plugin_Admin.php:1588](../Generic_Plugin_Admin.php#L1588) |
| `w3tc_extensions` | `add_filter()` | 2 | `array(), $w3tc_config` | [Extensions_Util.php:23](../Extensions_Util.php#L23) |
| `w3tc_extensions_hooks` | `add_filter()` | 1 | `$w3tc_hooks` | [Extensions_Plugin_Admin.php:164](../Extensions_Plugin_Admin.php#L164) |
| `w3tc_extension_plugin_links_{extension}` | `add_filter()` | 1 | `$w3tc_extra_links` | [inc/options/extensions/list.php:127](../inc/options/extensions/list.php#L127) |
| `w3tc_extension_requirements-{extension}` | `add_filter()` | 1 | `$w3tc_meta['requirements']` | [inc/options/extensions/list.php:194](../inc/options/extensions/list.php#L194) |
| `w3tc_filename_to_url` | `add_filter()` | 1 | `$w3tc_url` | [Util_Environment.php:171](../Util_Environment.php#L171) |
| `w3tc_flushable_post` | `add_filter()` | 3 | `$flushable, $post, $w3tc_module` | [Util_Environment.php:1604](../Util_Environment.php#L1604) |
| `w3tc_flushable_posts` | `add_filter()` | 2 | `false, $extras` | [CacheFlush.php:196](../CacheFlush.php#L196) |
| `w3tc_flush_execute_delayed_operations` | `add_filter()` | 1 | `$actions_made` | [CacheFlush_Locally.php:396](../CacheFlush_Locally.php#L396) |
| `w3tc_footer_comment` | `add_filter()` | 1 | `$strings` | [Generic_Plugin.php:1026](../Generic_Plugin.php#L1026) |
| `w3tc_generic_boldgrid_show` | `add_filter()` | 1 | `self::should_show_widget()` | [Generic_WidgetBoldGrid.php:20](../Generic_WidgetBoldGrid.php#L20) |
| `w3tc_imageservice_submit_capability` | `add_filter()` | 2 | `'manage_options', (int) $post_id` | [Extension_ImageService_Plugin_Admin.php:1770](../Extension_ImageService_Plugin_Admin.php#L1770) |
| `w3tc_is_cacheable_content_type` | `add_filter()` | 1 | `array( '', // redirects, they have only Location header set. 'application/json', 'text/html', 'text/xml', 'text/xsl', 'application/xhtml+xml', 'application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/xml', )` | [PgCache_ContentGrabber.php:2772](../PgCache_ContentGrabber.php#L2772) |
| `w3tc_known_note_ids` | `add_filter()` | 1 | `$allowed` | [ConfigKeysSchema.php:445](../ConfigKeysSchema.php#L445) |
| `w3tc_lazyload_can_process` | `add_filter()` | 1 | `$can_process` | [UserExperience_LazyLoad_Plugin.php:99](../UserExperience_LazyLoad_Plugin.php#L99) |
| `w3tc_lazyload_embed_script` | `add_filter()` | 1 | `$buffer` | [UserExperience_LazyLoad_Plugin.php:120](../UserExperience_LazyLoad_Plugin.php#L120) |
| `w3tc_lazyload_excludes` | `add_filter()` | 1 | `$this->w3tc_config->get_array( 'lazyload.exclude' )` | [UserExperience_LazyLoad_Mutator.php:63](../UserExperience_LazyLoad_Mutator.php#L63) |
| `w3tc_lazyload_is_embed_script` | `add_filter()` | 1 | `true` | [UserExperience_LazyLoad_Plugin.php:122](../UserExperience_LazyLoad_Plugin.php#L122) |
| `w3tc_lazyload_mutator_before` | `add_filter()` | 1 | `array( 'buffer' => $buffer, 'modified' => $this->modified, )` | [UserExperience_LazyLoad_Mutator.php:65](../UserExperience_LazyLoad_Mutator.php#L65) |
| `w3tc_lazyload_on_initialized_javascript` | `add_filter()` | 1 | `''` | [UserExperience_LazyLoad_Plugin.php:207](../UserExperience_LazyLoad_Plugin.php#L207) |
| `w3tc_minify_before` | `add_filter()` | 1 | `$buffer` | [Minify_Plugin.php:222](../Minify_Plugin.php#L222) |
| `w3tc_minify_css_content` | `add_filter()` | 3 | `$content, null, null` | [lib/Minify/Minify.php:619](../lib/Minify/Minify.php#L619) |
| `w3tc_minify_css_do_excluded_tag_style_minification` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoCss.php:267](../Minify_AutoCss.php#L267) |
| `w3tc_minify_css_do_flush_collected` | `add_filter()` | 3 | `true, $last_style_tag, $this` | [Minify_AutoCss.php:316](../Minify_AutoCss.php#L316) |
| `w3tc_minify_css_do_local_style_minification` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoCss.php:204](../Minify_AutoCss.php#L204) |
| `w3tc_minify_css_do_tag_minification` | `add_filter()` | 3 | `$do_tag_minification, $style_tag, $w3tc_file` | [Minify_AutoCss.php:245](../Minify_AutoCss.php#L245) |
| `w3tc_minify_css_enable` | `add_filter()` | 1 | `$css_enable` | [Minify_Plugin.php:215](../Minify_Plugin.php#L215) |
| `w3tc_minify_css_step` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoCss.php:332](../Minify_AutoCss.php#L332) |
| `w3tc_minify_css_step_style_to_embed` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoCss.php:343](../Minify_AutoCss.php#L343) |
| `w3tc_minify_css_style_tags` | `add_filter()` | 1 | `$style_tags` | [Minify_AutoCss.php:119](../Minify_AutoCss.php#L119) |
| `w3tc_minify_enable` | `add_filter()` | 1 | `$enable` | [Minify_Plugin.php:197](../Minify_Plugin.php#L197) |
| `w3tc_minify_file_handler_minify_options` | `add_filter()` | 1 | `$serve_options` | [Minify_MinifiedFileRequestHandler.php:411](../Minify_MinifiedFileRequestHandler.php#L411) |
| `w3tc_minify_html_enable` | `add_filter()` | 1 | `$html_enable` | [Minify_Plugin.php:216](../Minify_Plugin.php#L216) |
| `w3tc_minify_html_script_minifier` | `add_filter()` | 3 | `3: $minifier, $type, $openTag . $content . $closeTag`<br>`3: $minifier, $type, $script_tag` | [lib/Minify/Minify/HTML.php:317](../lib/Minify/Minify/HTML.php#L317)<br>[lib/Minify/Minify/Inline.php:52](../lib/Minify/Minify/Inline.php#L52) |
| `w3tc_minify_http2_preload_url` | `add_filter()` | 1 | `array( 'result_link' => $uri, 'original_url' => $w3tc_url, )` | [Minify_Plugin.php:1459](../Minify_Plugin.php#L1459) |
| `w3tc_minify_js_content` | `add_filter()` | 3 | `$content, null, null` | [lib/Minify/Minify.php:622](../lib/Minify/Minify.php#L622) |
| `w3tc_minify_js_do_excluded_tag_script_minification` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoJs.php:281](../Minify_AutoJs.php#L281) |
| `w3tc_minify_js_do_flush_collected` | `add_filter()` | 4 | `true, $last_script_tag, $this, $sync_type` | [Minify_AutoJs.php:364](../Minify_AutoJs.php#L364) |
| `w3tc_minify_js_do_local_script_minification` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoJs.php:220](../Minify_AutoJs.php#L220) |
| `w3tc_minify_js_do_tag_minification` | `add_filter()` | 3 | `$do_tag_minification, $script_tag, $w3tc_file` | [Minify_AutoJs.php:259](../Minify_AutoJs.php#L259) |
| `w3tc_minify_js_enable` | `add_filter()` | 1 | `$js_enable` | [Minify_Plugin.php:214](../Minify_Plugin.php#L214) |
| `w3tc_minify_js_script_tags` | `add_filter()` | 1 | `$script_tags` | [Minify_AutoJs.php:123](../Minify_AutoJs.php#L123) |
| `w3tc_minify_js_step` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoJs.php:392](../Minify_AutoJs.php#L392) |
| `w3tc_minify_js_step_script_to_embed` | `add_filter()` | 1 | `$w3tc_data` | [Minify_AutoJs.php:418](../Minify_AutoJs.php#L418) |
| `w3tc_minify_processed` | `add_filter()` | 1 | `$buffer` | [Minify_Plugin.php:244](../Minify_Plugin.php#L244) |
| `w3tc_minify_urls_for_minification_to_minify_filename` | `add_filter()` | 3 | `$minify_filename, $files, $type` | [Minify_Core.php:43](../Minify_Core.php#L43) |
| `w3tc_minify_url_for_files` | `add_filter()` | 3 | `$w3tc_url, $files, $type` | [Minify_Plugin.php:1203](../Minify_Plugin.php#L1203) |
| `w3tc_mobile_groups` | `add_filter()` | 1 | `$mobile_groups` | [CacheGroups_Plugin_Admin.php:149](../CacheGroups_Plugin_Admin.php#L149) |
| `w3tc_module_is_running-{module}` | `add_filter()` | 1 | `$this->is_enabled( $w3tc_module )` | [ModuleStatus.php:94](../ModuleStatus.php#L94) |
| `w3tc_network_dashboard_widgets` | `add_filter()` | 1 | `array()` | [Util_Widget.php:47](../Util_Widget.php#L47) |
| `w3tc_newrelic_should_disable_auto_rum` | `add_filter()` | 1 | `null` | [Extension_NewRelic_Plugin.php:171](../Extension_NewRelic_Plugin.php#L171) |
| `w3tc_notes` | `add_filter()` | 1 | `$w3tc_notes` | [Generic_Plugin_Admin.php:1589](../Generic_Plugin_Admin.php#L1589) |
| `w3tc_objectcache_addin_required` | `add_filter()` | 1 | `$objectcache_enabled` | [ObjectCache_Environment.php:35](../ObjectCache_Environment.php#L35) |
| `w3tc_pagecache_flush_all_groups` | `add_filter()` | 1 | `$groups_to_flush` | [PgCache_Flush.php:367](../PgCache_Flush.php#L367) |
| `w3tc_pagecache_flush_url` | `add_filter()` | 1 | `$w3tc_data` | [PgCache_Flush.php:450](../PgCache_Flush.php#L450) |
| `w3tc_pagecache_flush_url_keys` | `add_filter()` | 1 | `$page_keys` | [PgCache_Flush.php:473](../PgCache_Flush.php#L473) |
| `w3tc_pagecache_rules_apache_accept_qs` | `add_filter()` | 1 | `$w3tc_config->get_array( 'pgcache.accept.qs' )` | [PgCache_Environment.php:768](../PgCache_Environment.php#L768) |
| `w3tc_pagecache_rules_apache_accept_qs_rules` | `add_filter()` | 2 | `$query_rules, $query` | [PgCache_Environment.php:794](../PgCache_Environment.php#L794) |
| `w3tc_pagecache_rules_apache_rewrite_cond` | `add_filter()` | 1 | `$use_cache_rules` | [PgCache_Environment.php:1003](../PgCache_Environment.php#L1003) |
| `w3tc_pagecache_rules_apache_uri_prefix` | `add_filter()` | 1 | `$uri_prefix` | [PgCache_Environment.php:1011](../PgCache_Environment.php#L1011) |
| `w3tc_pagecache_rules_nginx_accept_qs` | `add_filter()` | 1 | `$w3tc_config->get_array( 'pgcache.accept.qs' )` | [PgCache_Environment.php:1139](../PgCache_Environment.php#L1139) |
| `w3tc_pagecache_rules_nginx_accept_qs_rules` | `add_filter()` | 2 | `$query_rules, $query` | [PgCache_Environment.php:1164](../PgCache_Environment.php#L1164) |
| `w3tc_pagecache_rules_nginx_rewrite_cond` | `add_filter()` | 1 | `''` | [PgCache_Environment.php:1290](../PgCache_Environment.php#L1290) |
| `w3tc_pagecache_rules_nginx_uri_prefix` | `add_filter()` | 1 | `$uri_prefix` | [PgCache_Environment.php:1484](../PgCache_Environment.php#L1484) |
| `w3tc_pagecache_set` | `add_filter()` | 3 | `$_data, $this->_page_key, $this->_page_group` | [PgCache_ContentGrabber.php:3009](../PgCache_ContentGrabber.php#L3009) |
| `w3tc_pagecache_set_header` | `add_filter()` | 3 | `$h, $h, 'file_generic'` | [Cache_File_Generic.php:260](../Cache_File_Generic.php#L260) |
| `w3tc_pageurls_wp_json_base` | `add_filter()` | 1 | `$wp_json_base` | [Util_PageUrls.php:899](../Util_PageUrls.php#L899) |
| `w3tc_page_extract_group` | `add_filter()` | 3 | `$page_key_extension['group'], $this->_request_url_fragments['host'] . $this->_request_uri, $page_key_extension` | [PgCache_ContentGrabber.php:424](../PgCache_ContentGrabber.php#L424) |
| `w3tc_page_extract_key` | `add_filter()` | 8 | `$this->_page_key, $page_key_extension['useragent'], $page_key_extension['referrer'], $page_key_extension['encryption'], $page_key_extension['compression'], $page_key_extension['content_type'], $this->_request_url_fragments['host'] . $this->_request_uri, $page_key_extension` | [PgCache_ContentGrabber.php:439](../PgCache_ContentGrabber.php#L439) |
| `w3tc_page_mapping` | `add_filter()` | 1 | `$map` | [Util_PageUrls.php:1100](../Util_PageUrls.php#L1100) |
| `w3tc_pgcache_cookiegroups` | `add_filter()` | 1 | `$cookiegroups` | [CacheGroups_Plugin_Admin.php:269](../CacheGroups_Plugin_Admin.php#L269) |
| `w3tc_pgcache_flush_post_queued_urls` | `add_filter()` | 1 | `$full_urls` | [PgCache_Flush.php:315](../PgCache_Flush.php#L315) |
| `w3tc_pgcache_postfix_nginx` | `add_filter()` | 1 | `$key_postfix` | [PgCache_Environment.php:1437](../PgCache_Environment.php#L1437) |
| `w3tc_pgcache_rules_apache_last` | `add_filter()` | 5 | `$rules, $use_cache_rules, $document_root, $uri_prefix, $env_W3TC_ENC` | [PgCache_Environment.php:1035](../PgCache_Environment.php#L1035) |
| `w3tc_pgcache_rules_required` | `add_filter()` | 2 | `$required, $w3tc_c` | [PgCache_Environment.php:265](../PgCache_Environment.php#L265) |
| `w3tc_preflush_all` | `add_filter()` | 2 | `true, $extras` | [CacheFlush_Locally.php:316](../CacheFlush_Locally.php#L316) |
| `w3tc_preflush_cdn_all` | `add_filter()` | 2 | `true, $extras` | [CacheFlush_Locally.php:200](../CacheFlush_Locally.php#L200) |
| `w3tc_preflush_group` | `add_filter()` | 3 | `true, $w3tc_group, $extras` | [CacheFlush_Locally.php:333](../CacheFlush_Locally.php#L333) |
| `w3tc_preflush_post` | `add_filter()` | 2 | `true, $extras` | [CacheFlush_Locally.php:259](../CacheFlush_Locally.php#L259) |
| `w3tc_preflush_posts` | `add_filter()` | 2 | `true, $extras` | [CacheFlush_Locally.php:275](../CacheFlush_Locally.php#L275) |
| `w3tc_preflush_url` | `add_filter()` | 2 | `true, $extras` | [CacheFlush_Locally.php:350](../CacheFlush_Locally.php#L350) |
| `w3tc_processed_content` | `add_filter()` | 1 | `$buffer` | [Generic_Plugin.php:1056](../Generic_Plugin.php#L1056) |
| `w3tc_process_content` | `add_filter()` | 1 | `$buffer` | [Generic_Plugin.php:1001](../Generic_Plugin.php#L1001) |
| `w3tc_process_wp_die` | `add_filter()` | 2 | `false, $buffer` | [Generic_Plugin.php:992](../Generic_Plugin.php#L992) |
| `w3tc_referrer_groups` | `add_filter()` | 1 | `$referrer_groups` | [CacheGroups_Plugin_Admin.php:215](../CacheGroups_Plugin_Admin.php#L215) |
| `w3tc_remove_cssjs_can_process` | `add_filter()` | 1 | `$can_process` | [UserExperience_Remove_CssJs_Extension.php:90](../UserExperience_Remove_CssJs_Extension.php#L90) |
| `w3tc_remove_cssjs_mutator_before` | `add_filter()` | 1 | `array( 'buffer' => $buffer, )` | [UserExperience_Remove_CssJs_Mutator.php:71](../UserExperience_Remove_CssJs_Mutator.php#L71) |
| `w3tc_repeating_headers` | `add_filter()` | 1 | `$repeating_headers` | [PgCache_ContentGrabber.php:1624](../PgCache_ContentGrabber.php#L1624) |
| `w3tc_save_options` | `add_filter()` | 2 | `$w3tc_data, $this->_page` | [Generic_AdminActions_Default.php:698](../Generic_AdminActions_Default.php#L698) |
| `w3tc_settings_general_anchors` | `add_filter()` | 1 | `array()` | [Generic_Page_General.php:92](../Generic_Page_General.php#L92)<br>[Util_Ui.php:553](../Util_Ui.php#L553) |
| `w3tc_swarmify_active` | `add_filter()` | 1 | `null` | [Extension_Swarmify_Plugin.php:134](../Extension_Swarmify_Plugin.php#L134) |
| `w3tc_trusted_proxies` | `add_filter()` | 1 | `$cidrs` | [Util_Environment.php:2217](../Util_Environment.php#L2217) |
| `w3tc_ui_config_item_{action}` | `add_filter()` | 1 | `$w3tc_a` | [Util_Ui.php:1832](../Util_Ui.php#L1832) |
| `w3tc_ui_settings_item` | `add_filter()` | 1 | `$w3tc_a` | [Util_Ui.php:1457](../Util_Ui.php#L1457) |
| `w3tc_uri_cdn_uri` | `add_filter()` | 1 | `ltrim( $remote_uri, '/' )` | [Cdn_Core.php:740](../Cdn_Core.php#L740) |
| `w3tc_url_to_docroot_filename` | `add_filter()` | 1 | `$w3tc_data` | [Util_Environment.php:1054](../Util_Environment.php#L1054) |
| `w3tc_usage_statistics_history_set` | `add_filter()` | 1 | `$history` | [UsageStatistics_StorageWriter.php:332](../UsageStatistics_StorageWriter.php#L332) |
| `w3tc_usage_statistics_metrics` | `add_filter()` | 1 | `$metrics` | [UsageStatistics_StorageWriter.php:268](../UsageStatistics_StorageWriter.php#L268) |
| `w3tc_usage_statistics_metric_values` | `add_filter()` | 1 | `$metric_values` | [UsageStatistics_StorageWriter.php:280](../UsageStatistics_StorageWriter.php#L280) |
| `w3tc_usage_statistics_sources` | `add_filter()` | 1 | `1: $sources`<br>`1: $summary` | [UsageStatistics_Sources.php:102](../UsageStatistics_Sources.php#L102)<br>[UsageStatistics_StorageReader.php:54](../UsageStatistics_StorageReader.php#L54) |
| `w3tc_usage_statistics_summary_from_history` | `add_filter()` | 2 | `$summary, $history` | [UsageStatistics_StorageReader.php:73](../UsageStatistics_StorageReader.php#L73) |
| `w3tc_ustats_access_log_format_regexp` | `add_filter()` | 1 | `$line_regexp` | [UsageStatistics_Source_AccessLog.php:169](../UsageStatistics_Source_AccessLog.php#L169) |
| `w3tc_ustats_access_log_line_elements` | `add_filter()` | 2 | `$e, $w3tc_line` | [UsageStatistics_Source_AccessLog.php:328](../UsageStatistics_Source_AccessLog.php#L328) |
| `w3tc_varnish_flush_post_queued_urls` | `add_filter()` | 1 | `$full_urls` | [Varnish_Flush.php:526](../Varnish_Flush.php#L526) |
| `w3tc_varnish_http_ports` | `add_filter()` | 1 | `$ports` | [Util_Url.php:522](../Util_Url.php#L522) |
| `w3tc_varnish_skip_host_check` | `add_filter()` | 3 | `false, $varnish_host, $varnish_port` | [Varnish_Flush.php:159](../Varnish_Flush.php#L159) |
