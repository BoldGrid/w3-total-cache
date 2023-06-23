function requireRoot(p) {
	return require('../../' + p);
}

const expect = require('chai').expect;
const log = require('mocha-logger');
const http = require('http');
const https = require('https');
const fs = require('fs');
const util = require('util');

fs.writeFileAsync = util.promisify(fs.writeFile);

const env = requireRoot('lib/environment');
const sys = requireRoot('lib/sys');
const w3tc = requireRoot('lib/w3tc');

describe('import/export config', function() {
	this.timeout(sys.suiteTimeout);
	before(sys.beforeDefault);
	after(sys.after);



	it('set options to something non-default', async() => {
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: true
		});
	});



	it('export config', async() => {
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general');
		log.log('Downloading (exporting) config file...');

		let cookies = await adminPage.cookies();

		await adminPage.setRequestInterception(true);

		let requestPromise = new Promise(resolve => {
		    adminPage.once('request', request => {
		        request.abort();
		        resolve(request);
		    });
		});

		let configExport = 'input[name="w3tc_config_export"]';
		await adminPage.evaluate((configExport) => document.querySelector(configExport).click(), configExport);

		let request = await requestPromise;

		let headers = request.headers();
		headers.Cookie = cookies.map(ck => ck.name + '=' + ck.value).join(';');

		log.log('doing download');
		let r = await httpPost(request.url(), request.postData(), headers);
		log.log('writing file');
		await fs.writeFileAsync(env.wpPath + '/export-data.json', r.body, 'utf8');
		await adminPage.setRequestInterception(false);
	});



	it('import', async() => {
		//change settings again
		await w3tc.setOptions(adminPage, 'w3tc_general', {
			pgcache__enabled: false
		});

		// checking if we disabled pgcache
		await adminPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general');
		let checked = await adminPage.$eval('#pgcache__enabled', (e) => e.getAttribute('checked'));
		expect(checked).null;

		// uploading our exported before config
		log.log('importing file');
		let fileInput = await adminPage.$('input[name=config_file]');
		await fileInput.uploadFile(env.wpPath + '/export-data.json');

		let configImport = 'input[name=w3tc_config_import]';
		await Promise.all([
			adminPage.evaluate((configImport) => document.querySelector(configImport).click(), configImport),
			adminPage.waitForNavigation({timeout:0})
		]);

		//checking if all settings was exported
		let checked2 = await adminPage.$eval('#pgcache__enabled', (e) => e.getAttribute('checked'));
		expect(checked2).equals('checked');
	});
});



function httpPost(url, data, headers) {
	process.env["NODE_TLS_REJECT_UNAUTHORIZED"] = 0;

	let p = new Promise((resolve, reject) => {
		let httpModule = (url.substr(0, 7) == 'http://' ? http : https);

		const u = new URL(url);
		const options = {
			hostname: u.hostname,
			port: env.httpServerPort,
			path: u.pathname + u.search,
			method: 'POST',
			headers: headers
	  	};

		let request = httpModule.request(options, (response) => {
			let responseData = '';
			response.on('data', (chunk) => {
				responseData += chunk;
			});

			response.on('end', () => {
				resolve({
					headers: response.headers,
					body: responseData
				});
			});

		}).on('error', (err) => {
			reject(err.message);
		});

		request.write(data);
		request.end();
	});

	return p;
}
