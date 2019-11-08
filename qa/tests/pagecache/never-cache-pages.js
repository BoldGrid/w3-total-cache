function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('pagecache')) */

let testPageId;
let testPageUrl;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		await sys.copyPhpToRoot('../../plugins/pagecache/never-cache-pages.php');
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'post',
			title: 'Never Cache',
			content: 'test'
		});

		testPageId = testPage.id;
		testPageUrl = testPage.url;
	});



	it('mirror redirects to home', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(page, testPageUrl);
		let content = await page.content();
		expect(content).contains('Page Caching');
		expect(content).not.contains('(Requested URI is rejected)');
		expect(await page.title()).contains('Never Cache');
	});



	it('change title', async() => {
		await updatePostViaDB('change title first', testPageId);

		await page.goto(testPageUrl);
		expect(await page.title()).contains('Never Cache');
		log.success('The page title is still "Never Cache", the page was cached');
	});



	it('add to rejected url', async() => {
		let m = testPageUrl.match(/^http(s)?\:\/\/([^\/]*)(.*)$/);
		let testPageUri = m[3];

		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache_reject_uri: testPageUri
		});

		await w3tc.flushAll(adminPage);
	});



	it('check not cached', async() => {
		await page.goto(testPageUrl);
		let content = await page.content();
		expect(content).contains('Page Caching');
		expect(content).contains('(Requested URI is rejected)');
	});



	it('update title via db', async() => {
		await updatePostViaDB('change title second', testPageId);

		await page.goto(testPageUrl);
		let content = await page.content();
		expect(await page.title()).contains('change title second');
	});
});



async function updatePostViaDB(title, postId) {
	log.log('Changing the title via db...');
	console.log(env.blogSiteUrl + 'never-cache-pages.php?title=' +
		title + '&post_id=' + postId);
	await page.goto(env.blogSiteUrl + 'never-cache-pages.php?title=' +
		title + '&post_id=' + postId);
	expect(await page.content()).contains('ok');
}
