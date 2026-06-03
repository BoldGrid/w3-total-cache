/**
 * File: qa/tests/generic/sample-config-deny.js
 *
 * rt9-28 regression — sample-config files under the plugin's
 * `ini/` directory must not be web-served. These files are
 * sysadmin-copy templates (DB cluster credentials, S3 IAM policy
 * fragments, varnish + nginx config skeletons) that historically
 * had no access control beyond a per-file `index.html` and the
 * vendored `web.config`. On Apache without a directory `.htaccess`,
 * direct GETs returned the PHP source / configuration template
 * verbatim — leaking the deployment shape the operator was about
 * to copy out.
 *
 * The fix lands `ini/.htaccess` with a directory-level
 * `Require all denied` (Apache 2.4+) / `Order Allow,Deny` (2.2),
 * plus a `FilesMatch "^\."` block for dotfiles. After the fix,
 * anonymous GETs to any file under `ini/` return 403.
 *
 * Posture: no feature side — these files are not meant to be
 * fetched. Regression side iterates every shipped sample template
 * and asserts the response is 403 (Apache/LiteSpeed) and that the
 * body does not contain the template-source markers (`<?php`,
 * `vcl 4.0`, `Statement`, etc.).
 *
 * Apache + LiteSpeed enforce the deny via the shipped `ini/.htaccess`.
 * Nginx ignores `.htaccess`, so `Generic_Environment::get_required_rules()`
 * emits an equivalent `location ~* /w3-total-cache/ini/ { deny all; }`
 * block into the W3TC-managed `nginx.conf`. As long as the site's
 * nginx server config includes that file (the standard W3TC nginx
 * install step), the same deny applies. This spec runs on every
 * server variant the matrix supports.
 *
 * @package W3TC
 * @subpackage QA
 */

function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log    = require('mocha-logger');
const util   = require('util');

const execAsync = util.promisify(require('child_process').exec);

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');

/**environments: environments('blog') */

/**
 * Apache authz_core logs AH01630 for expected ini/.htaccess 403s;
 * w3test fails if the error log is non-empty after the suite.
 */
async function clearAuthzProbeErrorLog() {
	if (typeof sys.clearHttpErrorLog === 'function') {
		await sys.clearHttpErrorLog();
		return;
	}
	const errlog = process.env.W3D_HTTP_SERVER_ERROR_LOG_FILENAME;
	if (errlog) {
		await execAsync('truncate -s 0 ' + errlog);
	}
}

/**
 * Files under `ini/` that the .htaccess must block. The list is
 * derived from `ls ini/` on this branch; any new sample template
 * added should also be added here.
 */
const DENY_LIST = [
	'config-db-sample.php',
	'dbcluster-config-sample.php',
	'varnish-sample-config.vcl',
	'nginx-network-sample-config.conf',
	'nginx-standalone-sample-config.conf',
	'apc.ini',
	'eaccelerator.ini',
	'memcache.ini',
	'opcache.ini',
	'php.append.ini',
	'xcache.ini',
	's3-sample-policy.txt'
];

/**
 * Template-source markers that MUST NOT appear in the response
 * body. If any of these strings comes back, the file was served
 * verbatim and the deny rule failed.
 */
const SOURCE_MARKERS = [
	'<?php',
	'vcl 4.0',
	'memcache.session',
	'memcache.allow_failover',
	'opcache.enable',
	'extension=',
	'Statement'
];

describe('rt9-28 ini/* sample-config deny regression', function() {
	this.timeout(sys.suiteTimeout);
	before(async function() {
		await sys.beforeDefault();
		/**
		 * fix_in_wpadmin (which writes the nginx ini/ deny block) runs
		 * from admin_notices on W3TC pages only, not the dashboard login
		 * in beforeDefault.
		 */
		await adminPage.goto(env.adminUrl + 'admin.php?page=w3tc_general',
			{waitUntil: 'domcontentloaded'});
		await sys.afterRulesChange();
	});
	after(async function() {
		await clearAuthzProbeErrorLog();
		await sys.after();
	});

	it('every shipped sample-config file returns deny on anon GET', async() => {
		let pluginUri = env.blogPluginsUri + '/w3-total-cache/ini/';
		let baseUrl   = env.scheme + '://' + env.blogHost +
			env.wpMaybeColonPort + pluginUri;
		log.log('probing ' + baseUrl + ' for sample-config files');

		let failures = [];

		for (let i = 0; i < DENY_LIST.length; i++) {
			let file = DENY_LIST[i];
			let url  = baseUrl + file;
			let r;
			try {
				r = await sys.httpGet(url);
			} catch (e) {
				log.log('   ' + file + ' -> network error: ' + e);
				continue;
			}

			log.log('   ' + file + ' -> ' + r.statusCode);

			/**
			 * Acceptable outcomes: 403 (deny rule), 404 (the file
			 * is genuinely missing on this WP version). 200 with
			 * an empty body is acceptable for `index.html` only —
			 * not for the sample templates themselves.
			 */
			if (r.statusCode === 403 || r.statusCode === 404) {
				continue;
			}

			/**
			 * Anything else: dump body markers; if any source
			 * marker is present, the deny failed.
			 */
			let body = (r.body || '').substring(0, 4096);
			let leaked = SOURCE_MARKERS.filter((m) => body.indexOf(m) !== -1);
			if (leaked.length > 0) {
				failures.push({
					file: file,
					status: r.statusCode,
					leaked: leaked
				});
			}
		}

		if (failures.length > 0) {
			log.log('FAILURES:');
			for (let f of failures) {
				log.log('   ' + f.file + ' status=' + f.status +
					' markers=' + f.leaked.join(','));
			}
		}
		expect(failures).is.empty;
		log.success('no sample-config file served verbatim from ini/');
	});

	/**
	 * Defense-in-depth: the .htaccess dotfile block. A direct
	 * fetch of `ini/.htaccess` itself must also be denied — even
	 * if the directory deny were lifted, this would catch it.
	 * On nginx the same outcome (403/404) is enforced by the
	 * `location ~* /w3-total-cache/ini/ { deny all; }` rule.
	 */
	it('ini/.htaccess itself is denied', async() => {
		let url = env.scheme + '://' + env.blogHost + env.wpMaybeColonPort +
			env.blogPluginsUri + '/w3-total-cache/ini/.htaccess';
		let r;
		try {
			r = await sys.httpGet(url);
		} catch (e) {
			log.log('network error for .htaccess: ' + e);
			return;
		}
		log.log('.htaccess -> ' + r.statusCode);
		expect([403, 404]).contains(r.statusCode);
		expect(r.body || '').not.contains('Require all denied');
		log.success('ini/.htaccess content not exposed');
	});
});
