function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const fs = require('fs');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/* dont run under varnish - not related to it by any means */
/**environments:
variable_not_equals('W3D_VARNISH', ['varnish'],
	environments('blog')
)
*/

let cacheFilePath;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			dbcache__enabled: true,
			dbcache__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_dbcache', {
			dbcache_file_gc: '3',
			dbcache_lifetime: '3'
		});

		await sys.copyPhpToRoot('../../plugins/dbcache/garbage-collection.php');

		// prevent cron in-process operations causing dbcache flush
		await wp.addWpConfigConstant(adminPage, 'DISABLE_WP_CRON', true);

		await sys.afterRulesChange();
	});

	it('Prime the blog site', async() => {
		log.log(env.blogSiteUrl);
		await page.goto(env.blogSiteUrl);
	});

	it('add cache', async() => {
		log.log(env.blogSiteUrl +
			'garbage-collection.php?action=add_cache&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?action=add_cache&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		let added = await page.$eval('#added', (e) => e.textContent);
		expect(added).equals('ok');

		log.log(env.blogSiteUrl +
			'garbage-collection.php?action=get_path&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?action=get_path&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		cacheFilePath = await page.$eval('#path', (e) => e.textContent);
		log.log('Expect cache file ' + cacheFilePath + ' to exist');
		expect(fs.existsSync(cacheFilePath)).is.true;
	});

	it('run cron hook to delete cache', async() => {
		// checking in 5 seconds if GS worked out
		log.log('Waiting 5 seconds to check if the file will be deleted by garbage collection');
		await new Promise(r => setTimeout(r, 5000));
		log.log(env.blogSiteUrl +
			'garbage-collection.php?action=garbage_collection&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		await page.goto(env.blogSiteUrl +
			'garbage-collection.php?action=garbage_collection&' +
			'blog_id=' + env.blogId + '&url=' + env.homeUrl);
		let schedule = await page.$eval('#schedule', (e) => e.textContent);
		let interval =  schedule.split(' ');

		expect(interval[0]).contains('w3_dbcache_cleanup');
		log.success('Schedule hook set up successfully');

		expect(parseInt(interval[1])).equals(3);
		log.success('GC Interval set to 3 seconds');

		log.log('check if cache file ' + cacheFilePath + ' was successfully deleted');
		expect(fs.existsSync(cacheFilePath)).is.false;
	});
});
