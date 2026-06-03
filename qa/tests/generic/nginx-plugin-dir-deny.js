/**
 * File: qa/tests/generic/nginx-plugin-dir-deny.js
 *
 * Asserts that `Generic_Environment::get_required_rules()` emits
 * the plugin-dir deny block into the W3TC-managed `nginx.conf` on
 * nginx installs. The block scopes deny rules to the plugin's
 * `pub/` (non-sns.php PHP files) and `ini/` (sample-config
 * templates) directories — the nginx equivalent of the shipped
 * `pub/.htaccess` and `ini/.htaccess` — and to the W3TC debug-log
 * directory (`wp-content/cache/log/`), whose Apache/LiteSpeed
 * `.htaccess` deny nginx would otherwise ignore (rt9-18).
 *
 * The matching live-deny HTTP assertions are exercised by
 * `sample-config-deny.js` and `public-endpoint-deny.js`. This
 * spec is the static counterpart: it verifies the emitter
 * actually wrote the block to disk after the environment-writer
 * runs, which is the fact a future refactor would most likely
 * silently regress.
 *
 * Skips on non-nginx matrices (Apache + LiteSpeed defense lives
 * in the per-directory `.htaccess` files, not in any emitted
 * config).
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');
const util     = require('util');
const exec     = util.promisify(require('child_process').exec);
const execFile = util.promisify(require('child_process').execFile);

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

/**
 * Run PHP in WP context without a shell so `$variables` are not
 * expanded by bash (exec() + double quotes turns `$d=` into `=…` and
 * triggers wp eval parse errors).
 *
 * @param {string} php PHP snippet for `wp eval`.
 * @return {Promise<string>} Trimmed stdout.
 */
async function wpEval(php) {
	let r = await execFile('sudo', [
		'-u', 'www-data',
		'wp', 'eval', php,
		'--path=' + env.wpPath,
	]);
	return (r.stdout || '').trim();
}

describe('Nginx plugin-dir deny rule emission', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);

	it('emits the deny block into nginx.conf when run under nginx', async function() {
		const httpServer = process.env['W3D_HTTP_SERVER'] || '';
		if (httpServer !== 'nginx') {
			log.log('SKIP: server is ' + httpServer + '; deny lives in .htaccess');
			this.skip();
			return;
		}

		/**
		 * Trigger the environment writer. Loading any admin page
		 * causes Root_Environment::fix_on_wpadmin_request() to run,
		 * which calls Generic_Environment::get_required_rules() and
		 * writes the resulting block to nginx.conf via Util_Rule.
		 */
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_general',
			{waitUntil: 'domcontentloaded'});

		/**
		 * Resolve the W3TC nginx-rules path. Default is
		 * `${site_path}nginx.conf`. The wp-cli call evaluates this
		 * inside WordPress so the same logic the plugin uses
		 * resolves the path.
		 */
		let nginxConfPath = await wpEval(
			'echo \\W3TC\\Util_Rule::get_nginx_rules_path();');
		log.log('nginx rules path: ' + nginxConfPath);
		expect(nginxConfPath).not.empty;

		/**
		 * Read the file and assert the block + key directives are
		 * present. We use sudo because the file is typically
		 * owned by www-data.
		 */
		let catResult = await exec('sudo cat ' + nginxConfPath +
			' 2>/dev/null || echo MISSING');
		let conf = catResult.stdout || '';
		if (conf === 'MISSING' || conf === '') {
			log.log('nginx.conf not present at ' + nginxConfPath);
			expect.fail('Expected W3TC nginx.conf at ' + nginxConfPath +
				', but file is missing or empty.');
		}

		log.log('nginx.conf length: ' + conf.length + ' bytes');

		// Marker pair must surround the block.
		expect(conf).contains('# BEGIN W3TC Plugin Dir Deny');
		expect(conf).contains('# END W3TC Plugin Dir Deny');

		// ini/ deny rule.
		expect(conf).contains('location ~* /w3-total-cache/ini/');

		// pub/ deny rule with sns.php negative lookahead.
		expect(conf).contains('/w3-total-cache/pub/');
		expect(conf).contains('(?!sns\\.php$)');

		/**
		 * `deny all;` directive — appears at least twice (once
		 * per location block).
		 */
		let denyCount = (conf.match(/deny\s+all\s*;/g) || []).length;
		expect(denyCount).is.at.least(2);
		log.success('deny block present in nginx.conf with ' + denyCount + ' `deny all;` directives');

		/**
		 * Debug-log directory deny (rt9-18). Apache / LiteSpeed protect the
		 * W3TC log dir through its shipped `.htaccess`; nginx needs an
		 * equivalent `location` deny so cdn.log / pgcache.log etc. are not
		 * web-readable. The rule is only emitted when the log directory
		 * resolves inside the document root (the default wp-content/cache/log
		 * layout) — resolve that the same way the plugin does and assert
		 * accordingly.
		 */
		let logUri = await wpEval(
			'$d=\\W3TC\\Util_Environment::normalize_path(\\W3TC\\Util_Environment::cache_dir(\'log\'));' +
			'$r=\\W3TC\\Util_Environment::normalize_path(\\W3TC\\Util_Environment::document_root());' +
			'$u=str_replace($r,\'\',$d);' +
			'echo (($u!==$d) && strpos($u,\'/\')===0) ? $u : \'\';');
		if (logUri !== '') {
			log.log('expected log-deny location: ' + logUri);
			expect(conf).contains('location ~* ' + logUri + '/');
			let denyCountWithLog = (conf.match(/deny\s+all\s*;/g) || []).length;
			expect(denyCountWithLog).is.at.least(3);
			log.success('debug-log deny block present for ' + logUri);
		} else {
			log.log('log dir resolves outside docroot; no log-deny rule expected');
		}
	});
});
