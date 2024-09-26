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
const log       = require('mocha-logger');
const puppeteer = require('puppeteer');
const util      = require('util');
const exec      = util.promisify(require('child_process').exec);
const wp        = requireRoot('lib/wp');
const env       = requireRoot('lib/environment');

/**
* beforeDefault.
*/
async function beforeDefault() {
	global.adminPage = null;
	global.page     = null;
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

	await page.on("dialog", async (dialog) => {
		log.log('regular page modal dialog appears');
		if (!page._overwriteSystemDialogPrompt) {
			log.log('accept');
			await dialog.accept();
		}
	});
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
* Restart web server after rules change.
*/
async function afterRulesChange() {
	if ('nginx' === process.env['W3D_HTTP_SERVER'] || 'litespeed' === process.env['W3D_HTTP_SERVER']) {
		log.log('Restarting http server after rules change');
		const r = await exec('/share/scripts/restart-http.rb');
		expect(r.stdout).contains('restartHttpSuccess');
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
* Perform an HTTP request.
*
* @param {string} url URL.
* @returns {Promise}
*/
async function httpGet(url) {
	process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = 0;

	let p = new Promise((resolve, reject) => {
		let httpModule = (url.substr(0, 7) === 'http://' ? http : https);
		httpModule.get(url, (response) => {
			let data = '';
			response.on('data', (chunk) => {
				data += chunk;
			});

			response.on('end', () => {
				resolve({
					headers: response.headers,
					body: data
				});
			});
		}).on('error', (err) => {
				reject(err.message);
		});
	});

	return p;
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
		afterSourceFileContentsChanges,
		copyPhpToRoot,
		copyPhpToPath,
		httpGet,
		repeatOnFailure
	}
);

// Add suiteTimeout.
module.exports.suiteTimeout = 300000;
