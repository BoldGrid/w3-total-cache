function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/* dont run under varnish - not related to it by any means */
/**environments:
variable_not_equals('W3D_VARNISH', ['varnish'],
	multiply(environments('blog'), environments('cache'))
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/objectcache/default-lifetime-objects.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			objectcache__enabled: true,
			objectcache__engine: env.cacheEngineLabel
		});

		await w3tc.setOptions(adminPage, 'w3tc_objectcache', {
			objectcache_lifetime: '2'
		});

	});



	it('check', async() => {
		log.log('set value');
		await page.goto(env.blogSiteUrl +
			'default-lifetime-objects.php?group=test-group&action=setCache');
		expect(await page.content()).contains('setCache ok');


		log.log('check value present');
		await page.goto(env.blogSiteUrl +
			'default-lifetime-objects.php?group=test-group&action=getCache');
		expect(await page.content()).contains('object cache test');


		// wait 3 seconds when the cache automatically purges
		log.log('wait 3 seconds till expire');
		await page.waitFor(3000);


		log.log('check value not present');
		await page.goto(env.blogSiteUrl +
			'default-lifetime-objects.php?group=test-group&action=getCache');
		expect(await page.content()).not.contains('object cache test');
	});
});
