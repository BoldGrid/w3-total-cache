/**
 * File: PgCache_Page_View.js
 *
 * JavaScript for Page Cache settings page.
 *
 * @since 2.1.0
 *
 * @global W3TCPgCacheQsExemptsData
 */

jQuery(function() {
	jQuery('.w3tc-pgcache-qsexempts-default').on(
		'click',
		function(){
			var pgcacheQsExempts = jQuery('#pgcache_accept_qs').val().split("\n");
			pgcacheQsExempts = pgcacheQsExempts.filter(item=>item).concat(W3TCPgCacheQsExemptsData.defaultQsExempts.filter((item)=>pgcacheQsExempts.indexOf(item)<0)).sort();
			jQuery('#pgcache_accept_qs').val(pgcacheQsExempts.join("\n"));
		}
	);
});
