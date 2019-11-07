function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const fs = require('fs');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

/**environments: environments('blog') */

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/pagecache/disk-enhanced-garbage-collection.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: true,
			pgcache__engine: 'file_generic'
		});
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache_file_gc: '5'
		});

		if (env.homeUrl.indexOf('b2') != -1) {
			// fix schedule on subblog, i.e. activate it
			await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard', {
				waitUntil: 'networkidle0'
			});
		}

		await sys.afterRulesChange();
	});



	it('check gc', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, env.homeUrl);


		let hpFilename = w3tc.pageCacheFileGenericUrlToFilename(env.homeUrl);
		await w3tc.pageCacheFileGenericChangeFileTimestamp(env.homeUrl);


		await page.goto(env.blogSiteUrl + '/disk-enhanced-garbage-collection.php');
		let content = await page.content();
		log.log('checking schedule hook set up successfully');
		expect(content).contains('w3_pgcache_cleanup 5');

		expect(!fs.existsSync(hpFilename));
		log.success(hpFilename + ' not found');
		expect(fs.existsSync(hpFilename + '_old'));
		log.success(hpFilename + '_old found');
		// no gzip content - BC disabled
		expect(!fs.existsSync(hpFilename + '_gzip'));
		log.success(hpFilename + '_gzip not found');
		expect(fs.existsSync(hpFilename + '_gzip_old'));
		log.success(hpFilename + '_gzip_old found');
	});
});
