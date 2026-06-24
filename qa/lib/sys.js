/**
 * File: sys.js
 */

/**
 * requireRoot.
 *
 * @param {string} p Path to required file.
 * @returns {require}
 */
function requireRoot(p) {
	return require('../' + p);
}

const expect    = require('chai').expect;
const http      = require('http');
const https     = require('https');
const { URL }   = require('url');
const log       = require('mocha-logger');
const puppeteer = require('puppeteer');
const util      = require('util');
const exec      = util.promisify(require('child_process').exec);
const path      = require('path');
const wp        = requireRoot('lib/wp');
const env       = requireRoot('lib/environment');
const w3tc      = require('./w3tc');

/** Stable UA for page-cache tests (matches generic/user-agent-groups.js). */
const qaPageCacheUserAgent =
	'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111';
const qaPageCacheSafariUserAgent =
	'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.36 (KHTML, like Gecko) Safari/537.36';

/**
 * Map *.sandbox hostnames to RFC1918 on loopback (see init-box/115 script).
 */
async function ensureSandboxHostsRfc1918() {
	const script = path.join(__dirname, '../env/scripts/init-box/115-sandbox-hosts-rfc1918.sh');
	log.log('Ensuring *.sandbox hosts map to RFC1918 for CDN test-button validation');
	await exec('bash ' + script);
	const r = await exec(
		"php -r \"\\$a=@gethostbynamel('wp.sandbox'); echo \\$a?implode(',',\\$a):'';\""
	);
	if (r.stdout.indexOf('127.0.0.1') !== -1) {
		throw new Error(
			'wp.sandbox still resolves to loopback after 115-sandbox-hosts-rfc1918.sh: ' +
			r.stdout.trim()
		);
	}
}

/**
* beforeDefault.
*/
async function beforeDefault() {
	global.adminPage = null;
	global.page     = null;
	await ensureSandboxHostsRfc1918();
	global.browserI  = await puppeteer.launch({
		ignoreHTTPSErrors: true,
		args: [
			'--no-sandbox',
			'--disable-setuid-sandbox',
			'--disable-dev-shm-usage',
			'--disable-accelerated-2d-canvas',
			'--no-first-run',
			'--no-zygote',
			'--disable-gpu',
			'--incognito',
			'--ignore-certificate-errors'
		]
	});

	global.browser  = await puppeteer.launch({
		ignoreHTTPSErrors: true,
		args: [
			'--no-sandbox',
			'--disable-setuid-sandbox',
			'--disable-dev-shm-usage',
			'--disable-accelerated-2d-canvas',
			'--no-first-run',
			'--no-zygote',
			'--disable-gpu',
			'--ignore-certificate-errors'
		]
	});

	await module.exports.restoreStateFinal();

	// Clear extenal cache engine that may keep state between tests.
	const r = await exec('/share/scripts/restart-http.rb');
	expect(r.stdout).contains('restartHttpSuccess');

	// Mark generic tasks complete for "2.8.6".
	await w3tc.w3tcMarkGenericTasksVersionsComplete('2.8.6');

	global.adminPage = await browser.newPage();

	adminPage.setViewport({width: 1900, height: 1000});
	await adminPage.setCacheEnabled(false);
	await wp.login(adminPage);

	await adminPage.on("dialog", async (dialog) => {
		log.log('adminPage modal dialog appears');
		if (!adminPage._overwriteSystemDialogPrompt) {
			log.log('accept');
			await dialog.accept();
		}
	});

	global.page   = await browserI.newPage();
	page.setViewport({width: 1900, height: 1000});
	await page.setCacheEnabled(false);
	await page.setUserAgent(qaPageCacheUserAgent);

	await page.on("dialog", async (dialog) => {
		log.log('regular page modal dialog appears');
		if (!page._overwriteSystemDialogPrompt) {
			log.log('accept');
			await dialog.accept();
		}
	});
}

/**
 * Truncate the http server error log (and WP debug.log) so w3test's
 * post-run "log must be empty" check passes after tests that
 * intentionally trigger expected Apache authz denies (AH01630).
 *
 * @returns {Promise<void>}
 */
async function clearHttpErrorLog() {
	const errlog = process.env.W3D_HTTP_SERVER_ERROR_LOG_FILENAME;
	if (errlog) {
		await exec('truncate -s 0 ' + errlog);
	}

	const contentPath = process.env.W3D_WP_CONTENT_PATH;
	if (contentPath) {
		await exec('truncate -s 0 ' + contentPath + 'debug.log 2>/dev/null || true');
	}
}

/**
* after.
*
* After all tests.
*/
async function after() {
	if (global.page) {
		await page.close();
	}

	if (global.adminPage) {
		await adminPage.close();
	}

	if (global.browserI) {
		await browserI.close();
	}

	if (global.browser) {
		await browser.close();
	}
}

/**
* Restore plugin final state.
*/
async function restoreStateFinal() {
	log.log('Restore wp state - to final');
	const r = await exec('/share/scripts/restore-final.rb');
	await module.exports.afterSourceFileContentsChanges();
}

/**
* Restore plugin intactive state.
*/
async function restoreStateW3tcInactive() {
	log.log('Restore wp state - to w3tc inactive');
	const r = await exec('/share/scripts/restore-w3tc-inactive.sh');
	await module.exports.afterSourceFileContentsChanges();
}

/**
 * Remove Disk Enhanced cache files for the current blog host (avoids stale *_old-only state).
 *
 * @returns {Promise<void>}
 */
async function clearDiskEnhancedHost() {
	if ('file_generic' !== process.env['W3D_CACHE_ENGINE_LABEL']) {
		return;
	}

	log.log('Clearing Disk Enhanced cache for blog host');
	await exec(
		'. /etc/environment; ' +
		'host_dir="$W3D_WP_CONTENT_PATH/cache/page_enhanced/${W3D_WP_BLOG_HOST}${W3D_WP_MAYBE_COLON_PORT}"; ' +
		'mkdir -p "$host_dir" && find "$host_dir" -mindepth 1 -maxdepth 1 -exec rm -rf {} +'
	);
}

/**
* Restart web server after rules change.
*/
async function afterRulesChange() {
	if ('nginx' === process.env['W3D_HTTP_SERVER'] || 'litespeed' === process.env['W3D_HTTP_SERVER']) {
		log.log('Restarting http server after rules change');
		const r = await exec('/share/scripts/restart-http.rb');
		expect(r.stdout).contains('restartHttpSuccess');
		return;
	}

	if ('apache' === process.env['W3D_HTTP_SERVER']) {
		log.log('Applying W3TC environment rules after options change');
		const r = await exec(
			'. /etc/environment; cd "$W3D_WP_PATH" && ' +
			'sudo -u www-data --preserve-env=PATH env DOCUMENT_ROOT="$W3D_WP_PATH" ' +
			'wp w3tc fix_environment apache 2>&1'
		);
		expect(r.stdout).contains('Success: Environment adjusted');

		if ('file_generic' === process.env['W3D_CACHE_ENGINE_LABEL']) {
			await clearDiskEnhancedHost();
			log.log('Ensuring Disk Enhanced cache directory is writable');
			await exec(
				'. /etc/environment; ' +
				'mkdir -p "$W3D_WP_CONTENT_PATH/cache/page_enhanced/${W3D_WP_BLOG_HOST}${W3D_WP_MAYBE_COLON_PORT}" && ' +
				'chown -R www-data:www-data "$W3D_WP_CONTENT_PATH/cache" && ' +
				'chmod -R g+rwX "$W3D_WP_CONTENT_PATH/cache"'
			);
		}

		log.log('Restarting http server after W3TC environment fix');
		const restart = await exec('/share/scripts/restart-http.rb');
		expect(restart.stdout).contains('restartHttpSuccess');
	}
}

/**
* Restart web server after file contents changes.
*/
async function afterSourceFileContentsChanges() {
	log.log('Restarting http server after source file contents change');
	const r = await exec('/share/scripts/restart-http.rb');
	expect(r.stdout).contains('restartHttpSuccess');
}

/**
* Copy PHP files to web root.
*
* @param {string} filename Filename.
*/
async function copyPhpToRoot(filename) {
	log.log('copying ' + filename);
	let targetPath = env.wpPath;
	const r = await exec('cp -f ' + filename + ' ' + targetPath);
	expect(r.stdout).empty;
}

/**
 * Run a QA plugin probe via wp eval-file (WordPress already bootstrapped).
 *
 * @param {string} probeBasename Basename under qa/plugins/ (e.g. disk-enhanced-probe.php).
 * @param {Object} probeEnv      Extra env vars for the probe (e.g. W3TC_QA_PROBE_URL).
 * @returns {Promise<string>} Probe stdout.
 */
async function runQaEvalFile(probeBasename, probeEnv = {}) {
	const probePath = env.wpPluginsPath + 'w3-total-cache/qa/plugins/' + probeBasename;
	const envNames = Object.keys(probeEnv);
	const preserve = envNames.length ? '--preserve-env=' + envNames.join(',') + ',PATH' : '--preserve-env=PATH';
	let envPrefix = '';

	for (const name of envNames) {
		envPrefix += name + '=' + JSON.stringify(probeEnv[name]) + ' ';
	}

	const r = await exec(
		'. /etc/environment; cd "$W3D_WP_PATH" && ' + envPrefix +
		'sudo -u www-data ' + preserve + ' wp eval-file ' + JSON.stringify(probePath) +
		' --url=' + JSON.stringify(env.blogSiteUrl) + ' 2>&1'
	);

	return r.stdout.trim();
}

/**
* Copy PHP files to a specific path.
*
* @param {string} from From.
* @param {string} to To.
*/
async function copyPhpToPath(from, to) {
	log.log('copying custom template to ' + to);
	const r = await exec('mkdir -p ' + to);
	expect(r.stdout).empty;
	const r2 = await exec('cp -f ' + from + ' ' + to);
	expect(r2.stdout).empty;
}

/**
 * Request headers: PHP mu-plugin w3tc-qa-x-accel-buffering.php responds with
 * X-Accel-Buffering: no so nginx disables FastCGI response buffering for that hit.
 *
 * @type {Object<string,string>}
 */
const qaNginxStreamRequestHeaders = {
	'X-W3TC-QA': 'no-buffer'
};

/**
 * Copy QA mu-plugin that emits X-Accel-Buffering: no when X-W3TC-QA: no-buffer is sent.
 *
 * @returns {Promise<void>}
 */
async function installQaNginxStreamMuPlugin() {
	log.log('Installing W3TC QA mu-plugin (nginx stream / X-Accel-Buffering)');
	const dir = env.wpContentPath + 'mu-plugins';
	const r = await exec('mkdir -p ' + dir);
	expect(r.stdout).empty;
	const r2 = await exec(
		'cp -f ../../plugins/w3tc-qa-x-accel-buffering.php ' + dir + '/'
	);
	expect(r2.stdout).empty;
}

/**
 * Single GET without following redirects.
 *
 * @param {string} requestUrl URL.
 * @param {Object} requestHeaders Headers.
 * @returns {Promise<{statusCode: number, headers: Object, body: string}>}
 */
function httpGetOnce(requestUrl, requestHeaders) {
	return new Promise((resolve, reject) => {
		const httpModule = (requestUrl.substr(0, 7) === 'http://' ? http : https);
		httpModule.get(requestUrl, { headers: requestHeaders }, (response) => {
			let data = '';
			response.on('data', (chunk) => {
				data += chunk;
			});

			response.on('end', () => {
				resolve({
					statusCode: response.statusCode,
					headers: response.headers,
					body: data
				});
			});
		}).on('error', (err) => {
			reject(err.message);
		});
	});
}

/**
* Perform an HTTP request.
*
* @param {string} url URL.
* @param {Object} options Optional. { headers: Object } merged into request headers.
*   Set followRedirects: true to follow 301/302/303/307/308 (e.g. W3TC multisite ?repeat=w3tc).
* @returns {Promise}
*/
async function httpGet(url, options) {
	options = options || {};
	let extraHeaders = options.headers || {};
	process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = 0;

	let requestHeaders = Object.assign(
		{
			'Connection': 'close'
		},
		extraHeaders
	);

	const followRedirects = options.followRedirects === true;
	const maxRedirects = typeof options.maxRedirects === 'number' ? options.maxRedirects : 10;

	let currentUrl = url;
	let redirects = 0;

	for (;;) {
		const r = await httpGetOnce(currentUrl, requestHeaders);
		if (!followRedirects) {
			return r;
		}
		const code = r.statusCode;
		if (code !== 301 && code !== 302 && code !== 303 && code !== 307 && code !== 308) {
			return r;
		}
		if (redirects >= maxRedirects) {
			return r;
		}
		const loc = r.headers.location;
		if (!loc) {
			return r;
		}
		currentUrl = new URL(loc, currentUrl).href;
		redirects++;
	}
}

/**
 * Skip the current Mocha test if any of the named environment
 * variables is unset or empty. Use for engine specs whose live-API
 * path requires real credentials the CI matrix does not provision.
 *
 * Example:
 *   it('does the thing', function() {
 *       sys.skipIfMissingEnv(this, ['CLOUDFLARE_EMAIL', 'CLOUDFLARE_KEY']);
 *       // ... live-API assertions ...
 *   });
 *
 * The form-save / readback portion of a spec should NOT be wrapped
 * in this guard — those run in every matrix. Only gate the bits
 * that actually contact the external service.
 *
 * @param {*} testCtx Mocha test context (`this` from inside an `it`/`before`).
 * @param {Array<string>} envKeys Env-var names that must all be present and non-empty.
 * @returns {boolean} true if the test was skipped (so callers can early-return).
 */
function skipIfMissingEnv(testCtx, envKeys) {
	for (let i = 0; i < envKeys.length; i++) {
		let k = envKeys[i];
		if (!process.env[k] || process.env[k] === '') {
			log.log('SKIP: required env-var ' + k + ' is not set');
			testCtx.skip();
			return true;
		}
	}
	return false;
}

/**
* Repeat page operations on failure.
*
* @param {pPage} pPage Page.
* @param {*} operation Operation.
*/
async function repeatOnFailure(pPage, operation) {
	for (let n = 0; n < 100; n++) {
		try {
			await operation();
			break;
		} catch (e) {
			log.error(e.message);
			log.error(e.stack);

			let content = await pPage.content();
			log.error(content.substr(0, 500) + '\n...\n' + content.substr(-500));
		}

		log.log(new Date().toISOString() + ' doing next ' + (n <= 0 ? '' : ' attempt' + n));
		await new Promise(r => setTimeout(r, 1000));
	}
}

// Add functions to module.exports.
module.exports = module.exports || {};
module.exports = Object.assign(
module.exports,
	{
		beforeDefault,
		after,
		restoreStateFinal,
		restoreStateW3tcInactive,
		afterRulesChange,
		clearDiskEnhancedHost,
		afterSourceFileContentsChanges,
		copyPhpToRoot,
		copyPhpToPath,
		runQaEvalFile,
		httpGet,
		installQaNginxStreamMuPlugin,
		qaNginxStreamRequestHeaders,
		qaPageCacheUserAgent,
		qaPageCacheSafariUserAgent,
		repeatOnFailure,
		skipIfMissingEnv,
		clearHttpErrorLog
	}
);

// Add suiteTimeout.
module.exports.suiteTimeout = 300000;
