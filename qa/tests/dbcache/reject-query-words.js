function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/* dont run under varnish - not related to it by any means */
/**environments:
variable_not_equals('W3D_VARNISH', ['varnish'],
	multiply(
		environments('blog'),
		environments('cache')
	)
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			dbcache__enabled: true,
			browsercache__enabled: false,
			dbcache__engine: env.cacheEngineLabel
		});

		await w3tc.setOptions(adminPage, 'w3tc_dbcache', {
			dbcache_reject_words: 'test_rejected_words'
		});

		// prevent cron in-process operations causing dbcache flush
		await wp.addWpConfigConstant(adminPage, 'DISABLE_WP_CRON', true);

		await sys.copyPhpToRoot('../../plugins/dbcache/reject-query-words.php');

		await sys.afterRulesChange();
	});



	it('checking if normal cache is being created', async() => {
		// do all system db updates that block dbcache on first request
		await page.goto(env.blogSiteUrl);

		log.log('doing select');
		log.log(env.blogSiteUrl +
			'reject-query-words.php?action=get_cache&title=Hello world!' +
			'&blog_id=' + env.blogId +
			'&engine=' + env.cacheEngineLabel);
		await page.goto(env.blogSiteUrl +
			'reject-query-words.php?action=get_cache&title=Hello world!' +
			'&blog_id=' + env.blogId +
			'&engine=' + env.cacheEngineLabel);
		log.log(await page.content());
		let postId = await page.$eval('#post_id', (e) => e.innerHTML);
		expect(postId).equals('1');

		// make update out of wp
		log.log('doing direct update');
		await page.goto(env.blogSiteUrl +
			'reject-query-words.php?action=update_record' +
			'&title=test_update' +
			'&blog_id=' + env.blogId +
			'&engine=' + env.cacheEngineLabel);
		expect(await page.content()).contains('ok');

		// checking if normal cache is being created
		log.log('doing select');
		await page.goto(env.blogSiteUrl +
			'reject-query-words.php?action=get_cache&title=Hello world!' +
			'&blog_id=' + env.blogId +
			'&engine=' + env.cacheEngineLabel);
		log.log(await page.content());
		let postId2 = await page.$eval('#post_id', (e) => e.innerHTML);
		expect(postId2).equals('1');
	});



	it('rejected queries', async() => {
		// updating "hello world" post title with the rejected words
		await page.goto(env.blogSiteUrl +
			'reject-query-words.php?action=update_record' +
			'&title=test_rejected_words' +
			'&blog_id=' + env.blogId +
			'&engine=' + env.cacheEngineLabel);
		expect(await page.content()).contains('ok');

		await page.goto(env.blogSiteUrl +
			'reject-query-words.php?action=get_cache' +
			'&title=test_rejected_words' +
			'&blog_id=' + env.blogId +
			'&engine=' + env.cacheEngineLabel);
		expect(await page.content()).contains('1');
	});
});
