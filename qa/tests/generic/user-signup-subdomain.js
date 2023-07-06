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
variable_equals('W3D_WP_NETWORK', ['subdir', 'subdomain'],
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



	it('signup', async() => {
		testUserPassword = await wp.userSignUp(adminPage, {
    		user_login: 'testuser',
    		email: 'subscriber@subscriber.com',
    		role : 'subscriber'
  		});
	});



	it('check login works', async() => {
		await page.goto(env.blogSiteUrl + 'wp-login.php');
		await page.$eval('#user_login', (e, v) => { e.value = v }, 'testuser');
		await page.$eval('#user_pass', (e, v) => { e.value = v }, testUserPassword);

		let wpSubmitButton = '#wp-submit';
		await Promise.all([
			page.evaluate((wpSubmitButton) => document.querySelector(wpSubmitButton).click(), wpSubmitButton),
			page.waitForNavigation({timeout:0}),
		]);

		expect(await page.title()).contains('Profile');
	});
});
