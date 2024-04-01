function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');
const wp = requireRoot('lib/wp');

/**environments:
multiply(
	environments('blog'),
	environments('pagecache')
)
*/

describe('', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('copy qa files', async() => {
		await sys.copyPhpToPath('../../plugins/pagecache/user-roles-single.php',
			env.wpContentPath + 'mu-plugins');
	});

	it('set options', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true,
			browsercache__enabled: false,
			pgcache__engine: env.cacheEngineLabel
		});

		//setting up "admin" as user role for whom the page won't be cached
		await w3tc.setOptions(adminPage, 'w3tc_pgcache', {
			pgcache__reject__logged: false,
			pgcache__reject__logged_roles: true,
			role_administrator: true
		});

		await sys.afterRulesChange();
	});

	it('relogin to issue new cookies', async() => {
		let logoutUrl = await adminPage.$eval('#wp-admin-bar-logout a', (e) => e.href);
		await adminPage.goto(logoutUrl);

		await wp.login(adminPage);
	});

	it('page not cached for the administrator', async() => {
		await w3tc.gotoWithPotentialW3TCRepeat(adminPage, env.homeUrl);
		console.log(adminPage.url());
		expect(await adminPage.content()).matches(
			new RegExp('Page Caching using .+? \\(Rejected user role is logged in\\)'));
		log.success('The page is not cached for the administrator');
	});

	it('user signup', async() => {
    	let password = await wp.userSignUp(adminPage, {
      		user_login: 'subscriber',
      		email: 'subscriber@subscriber.com',
      		role : 'subscriber'
    	});

		await page.goto(env.networkAdminUrl, {waitUntil: 'domcontentloaded'});
		await page.goto(env.adminUrl + 'profile.php');
		expect(await page.title()).contains('Log In');
		await page.$eval('#user_login', (e, v) => { e.value = v }, 'subscriber');
		await page.$eval('#user_pass', (e, v) => { e.value = v }, password);

		let wpSubmit = '#wp-submit';
		await Promise.all([
			page.evaluate((wpSubmit) => document.querySelector(wpSubmit).click(), wpSubmit),
			page.waitForNavigation({timeout:0}),
		]);

		expect(await page.title()).contains('Profile');
		log.success('Logged in under subscriber');
	});

	it('page cached for subscriber', async() => {
		await page.goto(env.homeUrl);
		expect(await page.content()).not.matches(
			new RegExp('Page Caching using .+? \\(Rejected user role is logged in\\)'));
	});
});
