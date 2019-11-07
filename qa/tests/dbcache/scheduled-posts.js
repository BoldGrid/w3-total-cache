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

		// prevent cron in-process operations causing dbcache flush
		await wp.addWpConfigConstant(adminPage, 'DISABLE_WP_CRON', true);

		await sys.copyPhpToRoot('../../plugins/dbcache/scheduled-posts.php');
	});



	it('create post 1', async() => {
		let testPage1 = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'test-future-1',
			content: 'page-content',
			date_publish_offset_seconds: 600
		});

		await verifyPost(testPage1, 'test-future-1');
	});


	it('create post 2', async() => {
		let testPage2 = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'test-future-2',
			content: 'page-content',
			date_publish_offset_seconds: 600
		});

		await verifyPost(testPage2, 'test-future-2');
	});
});



async function verifyPost(post, postTitle) {
	log.log('check no future post on home page');
	await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
	expect(await page.content()).not.contains(postTitle);

	log.log('publish post ' + post.id);
	await page.goto(env.blogSiteUrl + 'scheduled-posts.php?ID=' + post.id);
	expect(await page.content()).contains('Future post #' + post.id + ' published successfully');

	log.log('check post is on home page');
	await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);
	expect(await page.content()).contains(postTitle);
}
