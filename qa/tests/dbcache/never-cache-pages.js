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

let testPageUrl;
let newPostUri;

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

		await sys.copyPhpToRoot('../../plugins/dbcache/db-never-cache-pages.php');
	});



	it('create post', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'never-cache-v1-title',
			content: 'never-cache-content'
		});
		testPageUrl = testPage.url;

		var m = testPageUrl.match(/^http(s)?\:\/\/([^\/]*)(.*)$/);
		newPostUri = m[3];
	});



	it('check title is cached', async() => {
		// first requests contain update queries sometimes
		await sys.repeatOnFailure(page, async() => {
			await page.goto(testPageUrl);
			let c = await w3tc.w3tcComment(page);
			expect(c).matches(/Database Caching ([0-9/]+) queries in ([0-9.]+) seconds using/);
		});

		let html = await page.content();
		expect(html).not.contains('(Request-wide Request URI is rejected)');
		expect(await page.title()).contains('never-cache-v1-title');

		// update title and make sure its still cached with old value
		await updatePostViaDb('never-cache-v2-title');

		await page.goto(testPageUrl);
		expect(await page.title()).contains('never-cache-v1-title');
	});



	it('add to reject url', async() => {
		log.log('Set ' + newPostUri + ' as rejected for cache url...');

		await w3tc.setOptions(adminPage, 'w3tc_dbcache', {
			dbcache_reject_uri: newPostUri
		});
	});



	it('checking if it really was not cached', async() => {
		await page.goto(testPageUrl);
		let html2 = await page.content();
		expect(html2).matches(new RegExp('Database Caching .+?\\(Request-wide Request' +
			' URI is rejected\\)'));
		expect(await page.title()).contains('never-cache-v2-title');


		await updatePostViaDb('never-cache-v3-title');

		await page.goto(testPageUrl);
		expect(await page.title()).contains('never-cache-v3-title');
	});
});



async function updatePostViaDb(title) {
	log.log('changing the title via db');
	await page.goto(env.blogSiteUrl + 'db-never-cache-pages.php?title=' + title);
	let html = await page.content();
	expect(html).contains('ok');
}
