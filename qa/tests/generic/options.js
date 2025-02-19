function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('cache')) */

let pluginFileUrl = env.blogSiteUrl + 'options.php';

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy file', async() => {
		await sys.copyPhpToRoot('../../plugins/generic/options.php');
	});



	it('set_options, get_option works', async() => {
		//adding option with autoload
		await testWithDifferentOptions(true, false, 'yes');
		await testWithDifferentOptions(true, false, 'no');

		//oc: false, db:1
		await testWithDifferentOptions(false, true, 'yes');
		await testWithDifferentOptions(false, true, 'no');

		//oc: true, db:1
		await testWithDifferentOptions(true, true, 'yes');
		await testWithDifferentOptions(true, true, 'no');
	});
});



async function testWithDifferentOptions(oc, db, autoload) {
	log.log('Testing Object Cache set to "' + oc + '" and Database Cache set to "' + db + '" with options autoload set to "' + autoload + '"...');
	await w3tc.setOptions(adminPage, 'w3tc_general', {
		dbcache__enabled: db,
		objectcache__enabled: oc,
		browsercache__enabled: false,
		dbcache__engine: env.cacheEngineLabel,
		objectcache__engine: env.cacheEngineLabel
	});

	if (db) {
		await flushDBCache();
	}

	if (oc) {
		await flushObjectCache();
	}

	await page.goto(pluginFileUrl + '?action=add_option&value=test1&autoload=' +
		autoload);
	expect(await page.content()).contains('added');
	log.success('Option added');

	await expectOptionValue('test1');

	await page.goto(pluginFileUrl + '?action=update_option&value=test2');
	expect(await page.content()).contains('updated');
	log.success('Option updated');

	await expectOptionValue('test2');

	await page.goto(pluginFileUrl + '?action=delete_option');
	expect(await page.content()).contains('deleted');
	log.success('Option deleted');

	await expectOptionValue('');
}



async function expectOptionValue(value, fileUrl) {
	await page.goto(pluginFileUrl + '?action=get_option');
	expect(await page.content()).contains(value);
	log.success('Option equals ' + value);
}



async function flushObjectCache() {
	log.log('Flushing objectcache...');
	await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard');

	let flushLink = await adminPage.$eval('#wp-admin-bar-w3tc_flush_objectcache a', (e) => e.href);
	await adminPage.goto(flushLink);

	let content = await adminPage.$eval('#flush_objectcache', (e) => e.textContent);
	expect(content).contains('Object cache successfully emptied.');
	log.success('Object cache successfully emptied.');
}



async function flushDBCache() {
	log.log('Flushing dbcache...');
	await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard');

	let flushLink = await adminPage.$eval('#wp-admin-bar-w3tc_flush_dbcache a', (e) => e.href);
	await adminPage.goto(flushLink);

	let content = await adminPage.$eval('#flush_dbcache', (e) => e.textContent);
	expect(content).contains('Database cache successfully emptied.');
	log.success('Database cache successfully emptied.');
}
