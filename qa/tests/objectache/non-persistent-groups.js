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
		await sys.copyPhpToRoot('../../plugins/objectcache/non-persistent-groups.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			objectcache__enabled: true,
			objectcache__engine: env.cacheEngineLabel
		});

		await w3tc.setOptions(adminPage, 'w3tc_objectcache', {
			objectcache_groups_global: 'test-regular-group',
			objectcache_groups_nonpersistent: 'test-non-persistent-group'
		});

	});



	it('check', async() => {
		log.log('set value - global group');
		await page.goto(env.blogSiteUrl +
			'non-persistent-groups.php?group=test-regular-group&action=setCache');
		expect(await page.content()).contains('setCache ok');


		log.log('get value - global group');
		await page.goto(env.blogSiteUrl +
			'non-persistent-groups.php?group=test-regular-group&action=getCache');
		expect(await page.content()).contains('object cache test');


		log.log('set value - non-persistent');
		await page.goto(env.blogSiteUrl +
			'non-persistent-groups.php?group=test-non-persistent-group&action=setCache');
		expect(await page.content()).contains('setCache ok');


		await page.goto(env.blogSiteUrl +
			'non-persistent-groups.php?group=test-non-persistent-group&action=getCache');
		expect(await page.content()).not.contains('object cache test');
	});
});
