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
	multiply(environments('blog'), environments('cache'))
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/objectcache/basic.php');
	});

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			objectcache__enabled: true,
			objectcache__engine: env.cacheEngineLabel
		});
	});

	it('check', async() => {
		log.log('Setting value...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-existing&value=existing-value&action=setInCache');

		expect(await page.content()).contains('setInCache ok');

		log.log('getFromCache existing-value');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-existing&action=getFromCache');
		expect(await page.content()).contains('"value":"existing-value","found":true');

		log.log('getFromCache non existing-value');
		await page.goto(env.blogSiteUrl + 'basic.php?group=group-existing&id=object-non-existing&action=getFromCache');
		expect(await page.content()).contains('"found":false');

		// Check that 2 extractions one after other will give the same result (in-call cache test).
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-non-existing&action=doubleGetFromCache');
		expect(await page.content()).contains('"found":false');

		// Check that 2 extractions one after other will give the same result (in-call cache test).
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-existing&action=doubleGetFromCache');
		expect(await page.content()).contains('"value":"existing-value","found":true');

		// Check a case when we store boolean "false" to object cache - specific case.
		log.log('Setting bool false value');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-false&action=setInCacheBooleanFalse');
		expect(await page.content()).contains('setInCache ok');

		log.log('The cache entry retrieved for object-false');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-false&action=getFromCache');
		expect(await page.content()).contains('"value":false,"found":true');

		log.log('The cache entry retrieved for object-false');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group-existing&id=object-false&action=doubleGetFromCache');
		expect(await page.content()).contains('"value":false,"found":true');
	});

	it('check groups', async() => {
		log.log('Set object1...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group1&id=object1&value=test1&action=setInCache');

		expect(await page.content()).contains('setInCache ok');

		log.log('Set object2...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group2&id=object2&value=test2&action=setInCache');
		expect(await page.content()).contains('setInCache ok');

		log.log('Get object1...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group1&id=object1&action=getFromCache');
		expect(await page.content()).contains('"value":"test1","found":true');

		log.log('Get object2...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group2&id=object2&action=getFromCache');
		expect(await page.content()).contains('"value":"test2","found":true');

		log.log('Flush group1...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group1&action=flushGroup');
		expect(await page.content()).contains('flushGroup ok');

		log.log('Get object1...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group1&id=object1&action=getFromCache');
		expect(await page.content()).contains('"value":false,"found":false');

		log.log('Get object2...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group2&id=object2&action=getFromCache');
		expect(await page.content()).contains('"value":"test2","found":true');

		log.log('Flush...');
		await page.goto(env.blogSiteUrl +
			'basic.php?action=flush');
		expect(await page.content()).contains('flush ok');

		log.log('Get object1...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group1&id=object1&action=getFromCache');
		expect(await page.content()).contains('"value":false,"found":false');

		log.log('Get object2...');
		await page.goto(env.blogSiteUrl +
			'basic.php?group=group2&id=object2&action=getFromCache');
		expect(await page.content()).contains('"value":false,"found":false');
	});
});
