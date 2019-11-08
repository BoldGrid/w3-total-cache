function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments:environments('blog') */



let testPageUrl;
let testPageHtml;

describe('minify html', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy theme files', async() => {
		let theme = await wp.getCurrentTheme(adminPage);
		let targetPath = env.wpContentPath + 'themes/' + theme + '/qa';
		await sys.copyPhpToPath('../../plugins/minify-auto-theme/*', targetPath);
	});



	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			browsercache__enabled: false,
			minify__enabled: true,
			minify__engine: 'file'
		});

		await w3tc.setOptions(adminPage, 'w3tc_minify', {
			minify__html__enable: true
		});

		await sys.afterRulesChange();
	});



	it('create test page', async() => {
		let testPage = await wp.postCreate(adminPage, {
			type: 'page',
			title: 'test',
			content: 'page content',
			template: 'qa/minify-html.php'
		});
		testPageUrl = testPage.url;
	});



	it('load test page', async() => {
		log.log('opening ' + testPageUrl);
		await page.goto(testPageUrl, {
			timeout: 0,
			waitUntil: 'domcontentloaded'
		});

		let r = await sys.httpGet(testPageUrl);
		testPageHtml = r.body;
	});



	it('whitespace compression', async() => {
		// check that DOM is still valid
		let dataAttr = await page.$eval('#multiple-whitespace1', (e) => e.getAttribute('data-attr'));
		expect(dataAttr).equals("   space-in-attr   ");

		let html = await page.$eval('#multiple-whitespace1', (e) => e.outerHTML);
		expect(html).equals('<div id="multiple-whitespace1" data-attr="   space-in-attr   "> multiple whitespace around element</div>');

		expect(testPageHtml).contains('<div\nid=multiple-whitespace1 data-attr="   space-in-attr   "> multiple whitespace around element</div>');

		expect(testPageHtml).contains('<div\nid=multiple-whitespace2> multiple whitespace around element</div>');

		// unknown tags processed differently than block elements - trailing spaces may mean something
		expect(testPageHtml).contains('<unkTag\nid="multiple-whitespace3"> multiple whitespace around element </unkTag>');

		// auto closed tags
		// while it's not allowed in HTML5, some tags are closed in start tag
		// make sure its not broken
		expect(testPageHtml).contains('<div\nid=auto-closed6><div\ndata-value=6 /></div>');
		expect(testPageHtml).contains('<div\nid=auto-closed7><div\ndata-value=7/ /></div>');

		// a href with trailing "/" not turned into closing tag
		// <a href=bla/> parsed as <a href=bla></a> by some browsers
		// (XHTML? since HTML5 doesnt allow that)
		expect(testPageHtml).contains('<div\nid=link-ending-slash><a\nhref=https://www.website.com/path/ >bla</a></div>');
		expect(testPageHtml).contains('<div\nid=link-ending-slash-extra-tags><a\nhref=https://www.website.com/path/ class=my>bla2</a></div>');
	});



	it('trailing slash removal on void elements', async() => {
		// check that DOM is still valid
		let e1br = await page.$eval('#void-elements1 br', (e) => e.outerHTML);
		expect(e1br).equals('<br>');
		let e1hr = await page.$eval('#void-elements1 hr', (e) => e.outerHTML);
		expect(e1hr).equals('<hr>');
		expect(testPageHtml).contains('id=void-elements1><br><hr></div>');

		let e2br = await page.$eval('#void-elements2 br', (e) => e.outerHTML);
		expect(e2br).equals('<br>');
		let e2hr = await page.$eval('#void-elements2 hr', (e) => e.outerHTML);
		expect(e2hr).equals('<hr>');
		expect(testPageHtml).contains('id=void-elements2>\n<br><hr></div>');

		let e3br = await page.$eval('#void-elements3 br', (e) => e.outerHTML);
		expect(e3br).equals('<br>');
		expect(testPageHtml).contains('id=void-elements3>\n<br></div>');

		let e4br = await page.$eval('#void-elements4 br', (e) => e.outerHTML);
		expect(e4br).equals('<br>');
		expect(testPageHtml).contains('id=void-elements4>\n<br></div>');

		let e5input = await page.$eval('#void-elements5 input[value="5"]',
			(e) => e.type);
		expect(e5input).equals('text');
		expect(testPageHtml).contains('id=void-elements5>\n<input\ntype=text value=5></div>');
	});



	it('attribute quotes removal', async() => {
		let e1class = await page.$eval('#quoted-attribute1',
			(e) => e.getAttribute('class'));
		expect(e1class).equals('some-class');

		// html view is correct - quotes removed
		expect(testPageHtml).contains('id=quoted-attribute1 class=some-class ');


		let e2q1 = await page.$eval('#quoted-attribute2',
			(e) => e.getAttribute('data-test-quote1'));
		expect(e2q1).empty;
		let e2q2 = await page.$eval('#quoted-attribute2',
			(e) => e.getAttribute('data-test-quote2'));
		expect(e2q2).empty;

		// html view is correct - no value
		expect(testPageHtml).contains('id=quoted-attribute2 data-test-quote1 data-test-quote2>');


		let e3q1 = await page.$eval('#quoted-attribute3',
			(e) => e.getAttribute('data-test-quote1'));
		expect(e3q1).equals("'");
		let e3q2 = await page.$eval('#quoted-attribute3',
			(e) => e.getAttribute('data-test-quote2'));
		expect(e3q2).equals('"');

		let mixed1q1 = await page.$eval('#quoted-attribute-mixed1',
			(e) => e.getAttribute('data-test-quote1'));
		expect(mixed1q1).equals("' at start");
		let mixed1q2 = await page.$eval('#quoted-attribute-mixed1',
			(e) => e.getAttribute('data-test-quote2'));
		expect(mixed1q2).equals("at ' middle");
		let mixed1q3 = await page.$eval('#quoted-attribute-mixed1',
			(e) => e.getAttribute('data-test-quote3'));
		expect(mixed1q3).equals("at end'");

		let mixed2q1 = await page.$eval('#quoted-attribute-mixed2',
			(e) => e.getAttribute('data-test-quote1'));
		expect(mixed2q1).equals('" at start');
		let mixed2q2 = await page.$eval('#quoted-attribute-mixed2',
			(e) => e.getAttribute('data-test-quote2'));
		expect(mixed2q2).equals('at " middle');
		let mixed2q3 = await page.$eval('#quoted-attribute-mixed2',
			(e) => e.getAttribute('data-test-quote3'));
		expect(mixed2q3).equals('at end"');
	});



	it('javascript in html', async() => {
		log.log('check js in html');
		let scriptContent = "var m = '   no spaces removal   <a title=\"here\\'\">  and here</a><br \\/>'";
		console.log(scriptContent);
		expect(testPageHtml).contains(scriptContent);
	});

	it('json in html', async() => {
		log.log('check script-json');
		expect(testPageHtml).contains('{"br_tag":"test<br /> tag", "spaces around":"   spaces  "}');
	});
});
