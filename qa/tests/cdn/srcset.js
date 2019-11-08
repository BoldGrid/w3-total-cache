function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const dom = requireRoot('lib/dom');
const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments:environments('blog') */

describe('srcset tag urls replacement', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('test', async() => {
		//
		// set options
		//
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			cdn__enabled: true,
		    cdn__engine: 'ftp',
		});

		await w3tc.setOptions(adminPage, 'w3tc_cdn', {
	      cdn_ftp_host: 'wp.sandbox',
	      cdn_ftp_user: 'www-data',
	      cdn_ftp_pass: 'sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU',
	      cdn_ftp_path: env.cdnFtpExportDir,
	      cdn_cnames_0: env.cdnFtpExportHostPort
	    });

		await sys.afterRulesChange();


		//
		// checking srcset, it has to replace all urls
		//
		let themeSrc = w3tc.regExpForOption(
			env.wpContentPath.replace(
				/\/var\/www\/wp-sandbox\//g,
				env.scheme + '://for-tests.sandbox' + env.wpMaybeColonPort + '/'
			).replace(/b2\/wp\//g, 'wp/b2/') + 'themes/my-theme/');

		let imageSrc = env.wpContentPath.replace(
			/\/var\/www\/wp-sandbox\/(wp\/)?/g,
			env.homeUrl
		) + 'themes/my-theme/';

		await checkSrsetReplaced(
			'test',
			new RegExp(
				themeSrc + 'smorespieGF1-212x212\\.jpg 1x, ' +
				themeSrc + 'smorespieGF1-424x424\\.jpg 2\\.5x,' +
				themeSrc + 'smorespieGF1-424x424\\.jpg 300w, ' +
				themeSrc + 'smorespieGF1-424x424\\.jpg 400wff f   ,  ' +
				themeSrc + 'smorespieGF1-424x424\\.jpg 560w'
			),
			'<img id="testSrcset" ' +
				'src="' + imageSrc + 'smorespieGF1-212x212.jpg" ' +
				'srcset="' + imageSrc + 'smorespieGF1-212x212.jpg 1x, ' +
				imageSrc + 'smorespieGF1-424x424.jpg 2.5x,' +
				imageSrc + 'smorespieGF1-424x424.jpg 300w, ' +
				imageSrc + 'smorespieGF1-424x424.jpg 400wff f   ,  ' +
				imageSrc + 'smorespieGF1-424x424.jpg 560w" alt="S’more Pie">',
			'srcset');


			//
			// checking src, it has to replace only first url
			//
			let homeUrlEscaped = w3tc.regExpForOption(env.homeUrl);
			themeSrc = w3tc.regExpForOption(
				env.wpContentPath.replace(/\/var\/www\/wp-sandbox\/(wp\/)?/g,  '') + 'themes/my-theme/'
			);

			await checkSrsetReplaced(
				'test2',
				new RegExp(
					'https?:\\/\\/for-tests\\.sandbox' + env.wpMaybeColonPort +
					'\\/(wp\\/)?' + themeSrc +
					'smorespieGF1-212x212\\.jpg 1x, ' +
					homeUrlEscaped + themeSrc + 'smorespieGF1-424x424\\.jpg 2\\.5x,' +
					homeUrlEscaped + themeSrc + 'smorespieGF1-424x424\\.jpg 300w, ' +
					homeUrlEscaped + themeSrc + 'smorespieGF1-424x424\\.jpg 400wff f   ,  ' +
					homeUrlEscaped + themeSrc + 'smorespieGF1-424x424\\.jpg 560w'
				),
				'<img id="testSrcset" src="' +
					imageSrc + 'smorespieGF1-212x212.jpg 1x, ' +
					imageSrc + 'smorespieGF1-424x424.jpg 2.5x,' +
					imageSrc + 'smorespieGF1-424x424.jpg 300w, ' +
					imageSrc + 'smorespieGF1-424x424.jpg 400wff f   ,  ' +
					imageSrc + 'smorespieGF1-424x424.jpg 560w" alt="S’more Pie">',
				'src');


			//
			// checking srcset, it has to replace only wp.sanbox url and leave other domain untouched
			//
			let anotherContentPath = env.wpContentPath.replace(
				/\/var\/www\/wp-sandbox\/(wp\/)?/g,  'http://anotherhost/') + 'themes/my-theme/';

			await checkSrsetReplaced(
				'test3',
				new RegExp(
					'https?:\\/\\/for-tests\\.sandbox' + env.wpMaybeColonPort + '\\/(wp\\/)?' + themeSrc + 'smorespieGF1-212x212\\.jpg 1x, ' +
					'https?:\\/\\/anotherhost\\/' + themeSrc + 'smorespieGF1-424x424\\.jpg 2\\.5x,' +
					'https?:\\/\\/for-tests\\.sandbox' + env.wpMaybeColonPort + '\\/(wp\\/)?' + themeSrc + 'smorespieGF1-424x424\\.jpg 300w, ' +
					'https?:\\/\\/anotherhost\\/' + themeSrc + 'smorespieGF1-424x424\\.jpg 400wff f   ,  ' +
					'https?:\\/\\/for-tests\\.sandbox' + env.wpMaybeColonPort + '\\/(wp\\/)?' + themeSrc + 'smorespieGF1-424x424\\.jpg 560w'
				),
				'<img id="testSrcset" src="' +
					imageSrc + 'smorespieGF1-212x212.jpg" ' + 'srcset="' +
					imageSrc + 'smorespieGF1-212x212.jpg 1x, ' +
					anotherContentPath + 'smorespieGF1-424x424.jpg 2.5x,' +
					imageSrc + 'smorespieGF1-424x424.jpg 300w, ' +
					anotherContentPath + 'smorespieGF1-424x424.jpg 400wff f   ,  ' +
					imageSrc + 'smorespieGF1-424x424.jpg 560w" alt="S’more Pie">',
				'srcset');
	});
});




async function checkSrsetReplaced(title, regexp, content, attr) {
	let testPage = await wp.postCreate(adminPage, {
		type: 'post',
		title: title,
		content: content
	});
	let testPageUrl = testPage.url;

	await page.goto(testPageUrl);

	let srcset = await page.$eval('#testSrcset', (e, attr) => e.getAttribute(attr), attr);

	log.log('check ' + attr + ' was replaced correctly and matches ' + regexp);
	expect(srcset).matches(regexp);
}
