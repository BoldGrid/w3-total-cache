function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');
const env    = requireRoot('lib/environment');
const sys    = requireRoot('lib/sys');
const w3tc   = requireRoot('lib/w3tc');

/* dont run under varnish - not related to it by any means */
/**environments:
variable_not_equals('W3D_VARNISH', ['varnish'],
	environments('blog')
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/objectcache/garbage-collection.php');
	});

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			objectcache__enabled: true,
			objectcache__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_objectcache', {
			objectcache_file_gc: '2',
			objectcache_lifetime: '2'
		});

	});

	it('check', async() => {
		log.log('set value');
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?group=test-group&action=setCache');
		expect(await page.content()).contains('setCache ok');

		log.log('check value present');
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?group=test-group&action=getCache&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		expect(await page.content()).contains('cache exists');

		// Wait 3 seconds when the cache automatically purges.
		log.log('wait 3 seconds till expire');
		await new Promise(r => setTimeout(r, 3000));

		log.log('check flush hook fired');
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?group=test-group&action=flush');
		let content = await page.content();
		expect(content).contains('w3_objectcache_cleanup 2<');

		log.log('check value deleted');
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?group=test-group&action=getCache&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		expect(await page.content()).contains('cache not exists');
	});
});
