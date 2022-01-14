function requireRoot(p) {
  return require('../' + p);
}

const expect = require('chai').expect;
const http = require('http');
const https = require('https');
const log = require('mocha-logger');
const puppeteer = require('puppeteer');
const util = require('util');

const exec = util.promisify(require('child_process').exec);

const wp = requireRoot('lib/wp');
const env = requireRoot('lib/environment');



exports.suiteTimeout = 90000;



exports.beforeDefault = async function() {
	global.adminPage = null;
	global.page = null;
	global.browser = await puppeteer.launch({
		ignoreHTTPSErrors: true,
		args: ['--no-sandbox']
	});

	await exports.restoreStateFinal();
	// each test starts with settings changes and following http server restart
	// so it's just a waste of time doing it now
	// await exports.afterRulesChange()

	// clear extenal cache engine that may keep state between tests
	const r = await exec('/share/scripts/restart-http.rb');
	expect(r.stdout).contains('restartHttpSuccess');

	global.adminPage = await browser.newPage();

	adminPage.setViewport({width: 1187, height: 1000});
	await adminPage.setCacheEnabled(false);
	await wp.login(adminPage);

	await adminPage.on("dialog", async (dialog) => {
		log.log('adminPage modal dialog appears');
		if (!adminPage._overwriteSystemDialogPrompt) {
			log.log('accept');
			await dialog.accept();
		}
	});

	const context = await browser.createIncognitoBrowserContext();
	global.page = await context.newPage();
	page.setViewport({width: 1187, height: 1000});
	await page.setCacheEnabled(false);

	await page.on("dialog", async (dialog) => {
		log.log('regular page modal dialog appears');
		if (!page._overwriteSystemDialogPrompt) {
			log.log('accept');
			await dialog.accept();
		}
	});
}



exports.after = async function() {
	if (global.page)
		await page.close();
	if (global.adminPage)
		await adminPage.close();
	if (global.browser)
		await browser.close();
}



exports.restoreStateFinal = async function() {
	log.log('Restore wp state - to final');
	const r = await exec('/share/scripts/restore-final.rb');
	//expect(r.stdout).contains('restoreFinalSuccess');
	await exports.afterSourceFileContentsChanges();
}




exports.restoreStateW3tcInactive = async function() {
	log.log('Restore wp state - to w3tc inactive');
	const r = await exec('/share/scripts/restore-w3tc-inactive.sh');
	//expect(r.stdout).contains('restoreFinalSuccess');
	await exports.afterSourceFileContentsChanges();
}



exports.afterRulesChange = async function() {
	if (process.env['W3D_HTTP_SERVER'] == 'nginx' ||
			process.env['W3D_HTTP_SERVER'] == 'lightspeed') {
		log.log('Restarting http server after rules change');
		const r = await exec('/share/scripts/restart-http.rb');
		expect(r.stdout).contains('restartHttpSuccess');
	}
}



exports.afterSourceFileContentsChanges = async function() {
	log.log('Restarting http server after source file contents change');
	const r = await exec('/share/scripts/restart-http.rb');
	expect(r.stdout).contains('restartHttpSuccess');
}



exports.copyPhpToRoot = async function(filename) {
	log.log('copying ' + filename);
	let targetPath = env.wpPath;
	const r = await exec('cp -f ' + filename + ' ' + targetPath);
	expect(r.stdout).empty;
}



exports.copyPhpToPath = async function(from, to) {
	log.log('copying custom template to ' + to);

	const r = await exec('mkdir -p ' + to);
	expect(r.stdout).empty;
	const r2 = await exec('cp -f ' + from + ' ' + to);
	expect(r2.stdout).empty;
}



exports.httpGet = function(url) {
	process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

	let p = new Promise((resolve, reject) => {
		let httpModule = (url.substr(0, 7) == 'http://' ? http : https);
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



exports.repeatOnFailure = async function(pPage, operation) {
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

		log.log(new Date().toISOString() + ' doing next ' +
			(n <= 0 ? '' : ' attempt' + n));
		await pPage.waitFor(1000);
	}
}
