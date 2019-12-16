function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');

fs.readFileAsync = util.promisify(fs.readFile);

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments:
variable_equals('W3D_WP_NETWORK', ['single'],
	multiply(
		environments('blog'),
		environments('pagecache')
	)
)
*/

let testUserPassword;

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('copy qa files', async() => {
		await sys.copyPhpToPath('../../plugins/user-signup.php',
			env.wpContentPath + 'mu-plugins');
	});



	it('set options', async() => {
		let ocdcengine = (env.cacheEngineLabel == 'file_generic' ? 'file' :
			env.cacheEngineLabel);

		await w3tc.setOptions(adminPage, 'w3tc_general', {
			dbcache__enabled: true,
			pgcache__enabled: true,
			objectcache__enabled: true,
			dbcache__engine: ocdcengine,
			pgcache__engine: env.cacheEngineLabel,
			objectcache__engine: ocdcengine,
			browsercache__enabled: false
		});

		await sys.afterRulesChange();
	});



	it('enable users registration', async() => {
		await adminPage.goto(env.networkAdminUrl + 'options-general.php');
		await adminPage.click('#users_can_register');

		await Promise.all([
			adminPage.click('#submit'),
			adminPage.waitForNavigation()
		]);

		let checked = await adminPage.$eval('#users_can_register',
			(e) => e.getAttribute('checked'));
		expect(checked).equals('checked');
	});



	it('signup', async() => {
		await page.goto(env.blogSiteUrl + 'wp-login.php?action=register');

		await page.$eval('#user_login', (e, v) => { e.value = v }, 'testuser');
		await page.$eval('#user_email', (e, v) => { e.value = v }, 'test2@example.com');

		await Promise.all([
			page.click('#wp-submit'),
			page.waitForNavigation({timeout:0}),
		]);

		expect(page.url()).equals(env.blogSiteUrl.toLowerCase() +
			'wp-login.php?checkemail=registered');

		let successMessage = await page.$eval('.message', (e) => e.innerHTML);
		expect(successMessage).contains('Registration complete');
	});



	it('signup verification', async() => {
		let mail = await fs.readFileAsync(env.wpContentPath + 'mail.txt', 'utf8');
		console.log(mail);
		let passwordMath = mail.match(/^Password: (.+?)$/m);

		if (passwordMath != null) {
			// before wp4.3
			testUserPassword = passwordMath[0].split(' ')[1];
		} else if (parseFloat(env.wpVersion) < 5.3) {
			// before wp5.3 - follow url
			let m = mail.match(/visit the following address:\s*<(http[^>]+)>/m);
			let followUrl = m[1];

			log.log('found ' + followUrl);
			await page.goto(followUrl);
			await page.waitFor(function() {
				return document.getElementById('pass1-text') &&
					document.getElementById('pass1-text').value != '';
			});

			testUserPassword = await page.$eval('#pass1-text', (e) => e.value);

			log.log('got password ' + testUserPassword);
			await Promise.all([
				page.click('#wp-submit'),
				page.waitForNavigation()
			]);

			expect(await page.content()).contains('Your password has been reset');
		} else {
			let m = mail.match(/visit the following address:\s*<(http[^>]+)>/m);
			let followUrl = m[1];

			log.log('found ' + followUrl);
			await page.goto(followUrl);
			await page.waitFor(function() {
				return document.getElementById('pass1') &&
					document.getElementById('pass1').value != '';
			});

			testUserPassword = await page.$eval('#pass1', (e) => e.value);

			log.log('got password ' + testUserPassword);
			await Promise.all([
				page.click('#wp-submit'),
				page.waitForNavigation()
			]);

			expect(await page.content()).contains('Your password has been reset');
		}

		log.log('user pw ' + testUserPassword);
	});



	it('check login works', async() => {
		await page.goto(env.blogSiteUrl + 'wp-login.php');
		await page.$eval('#user_login', (e, v) => { e.value = v }, 'testuser');
		await page.$eval('#user_pass', (e, v) => { e.value = v }, testUserPassword);

		await Promise.all([
			page.click('#wp-submit'),
			page.waitForNavigation({timeout:0}),
		]);

		expect(await page.title()).contains('Profile');
	});
});
