function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const exec = util.promisify(require('child_process').exec);

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments: multiply(environments('blog'), environments('pagecache')) */

let testPageUrl;
let bcVersion;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let themePath = env.wpContentPath + 'themes/' + theme;
		await wp.addQaBootstrap(adminPage, `${themePath}/functions.php`, '/qa/basic-sc.php');
		await sys.copyPhpToPath('../../plugins/browsercache/*', `${themePath}/qa`);
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
	      pgcache__enabled: true,
	      browsercache__enabled: true,
	      pgcache__engine: env.cacheEngineLabel
	    });

	    await w3tc.setOptions(adminPage, 'w3tc_browsercache', {
	      browsercache_replace_exceptions: '.*\.css',
	      browsercache__cssjs__replace: true,
	      browsercache__other__replace: true,
	      browsercache_etag: true,
	      browsercache__cssjs__etag: true,
	      browsercache__html__etag: true,
	      browsercache__other__etag: true
	    });

	    await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content [w3tcqa]'
		});
		testPageUrl = testPage.url;
		console.log(testPageUrl);
	});



	it('etag is present', async() => {
		let response = await page.goto(testPageUrl, {waitUntil: 'domcontentloaded'});
		let headers = response.headers();
		expect(headers.etag).is.not.empty;
	});

	it('img tag contains version', async() => {
		let imgUrl = await page.$eval('#image', (e) => e.src);
		let imgSrcVersion = imgUrl.match(/\?(.*?)$/);
		bcVersion = imgSrcVersion[1];
		log.log('bc version used: ' + bcVersion);
		expect(bcVersion).is.not.empty;
	});

	it('check scripts postfixes', async() => {
		let scripts = await dom.listScriptSrc(page);
		scripts.forEach(function(url) {
			log.log('testing ' + url);
			expect(url).contains(bcVersion);
		});
	});

	it('check css postfixes - excluded by *.css mask', async() => {
		let linkHrefs = await dom.listLinkCssHref(page);
		linkHrefs.forEach(function(url) {
			log.log('testing ' + url);
			expect(url).not.contains(bcVersion);
		});
	});
});
