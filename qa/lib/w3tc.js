const expect = require('chai').expect;
const fs = require('fs');
const log = require('mocha-logger');
const env = require('./environment');
const sys = require('./sys');
const util = require('util');
const exec = util.promisify(require('child_process').exec);



// just load ?page=w3tc_bla page
// regular place of failures so with retries
async function setOptions_loadPage(pPage, queryPage) {
	await sys.repeatOnFailure(pPage, async() => {
		log.log('opening options page ' + queryPage);

		await pPage.goto(env.networkAdminUrl + 'admin.php?page=' + queryPage,
			{waitUntil: 'domcontentloaded'});

		let nonce = await pPage.$eval('input[name=_wpnonce]', (e) => e.value);
		expect(nonce).not.empty;
	});
}



exports.setOptions = async function(pPage, queryPage, values) {
	await setOptions_loadPage(pPage, queryPage);

	let saveSelector = 'input[name="w3tc_save_options"]';

	for (key in values) {
		let keySelector = '#' + key;
		let tagType = await pPage.$eval(keySelector, (e) => {
			return e.tagName +
				(e.tagName != 'INPUT' ? '' : ' ' + e.getAttribute('type'))
		});

		if (tagType == 'SELECT') {
			await pPage.select(keySelector, values[key]);
		} else if (tagType == 'INPUT checkbox' || tagType == 'INPUT radio') {
			let checked = await pPage.$eval(keySelector, (e) => e.getAttribute('checked'));
			if ((checked && !values[key]) || (!checked && values[key])) {
				await pPage.click(keySelector);
			}
			if (key == 'minify__enabled') {
				await pPage.waitForSelector('.lightbox-close', {
					visible: true
				});
				log.log('click minify popup close');
				await pPage.screenshot({path: '/var/www/wp-sandbox/01.png'});
				await pPage.click('.lightbox-close');
				await pPage.waitForSelector('.lightbox-close', {
					hidden: true
				});
				log.log('minify popup closed');

				// very weird issue - first button click hangs, while all other
				// works in that case. it cant scroll up?
				saveSelector = '#w3tc_save_options_general_minify';
			}
		} else if (tagType == 'INPUT text' || tagType == 'INPUT password' ||
				tagType == 'TEXTAREA') {
			await pPage.$eval(keySelector, (e, v) => { e.value = v },
				values[key]);
		} else {
			throw new Error('unknown type ' + tagType);
		}
	}

	log.log('click save - ' + saveSelector);
	await Promise.all([
		pPage.waitForNavigation({timeout: 0}),
		pPage.click(saveSelector)
	]);

	log.log('check w3tc options modified - loading page');
	await setOptions_loadPage(pPage, queryPage);

	for (key in values) {
		let keySelector = '#' + key;
		let tagType = await pPage.$eval(keySelector, (e) => {
			return e.tagName +
				(e.tagName != 'INPUT' ? '' : ' ' + e.getAttribute('type'))
		});

		let v;
		if (tagType == 'SELECT') {
			v = await await pPage.$eval(keySelector, (e) => e.value);
		} else if (tagType == 'INPUT checkbox' || tagType == 'INPUT radio') {
			let checked = await pPage.$eval(keySelector, (e) => e.getAttribute('checked'));
			v = (checked == 'checked');
		} else if (tagType == 'INPUT password') {
			v = values[key];   // check not supported
		} else if (tagType == 'INPUT text' || tagType == 'TEXTAREA') {
			v = await await pPage.$eval(keySelector, (e) => e.value);
		} else {
			throw new Error('unknown type ' + tagType);
		}

		log.log('test ' + key + ' is ' + values[key]);
		expect(values[key]).is.equal(v);
	}

	log.success('w3tc options modified successfully');
}



exports.activateExtension = async function(pPage, extenstion_id) {
	await pPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_extensions');
	let isActive = await pPage.$('#' + extenstion_id + ' .deactivate');
	if (isActive != null) {
		log.success('extension is already active');
		return;
	}

	await Promise.all([
		pPage.click('#' + id + ' .activate a'),
		pPage.waitForNavigation()
	]);

	let isActive2 = await pPage.$('#' + extenstion_id + ' .deactivate');
	expect(isActive2).is.not.null;
	log.success(extenstion_id + ' extension activated successfully');
}



/**
 * Called when html content of static files has been changed and note appears about
 * required flush
 */
exports.followNoteFlushStatics = async function(pPage) {
	if (await pPage.$('input[value="Empty the static files cache"]') != null) {
		await Promise.all([
			pPage.click('input[value="Empty the static files cache"]'),
			pPage.waitForNavigation({timeout:0}),
		]);
	}
}



exports.expectW3tcErrors = async function(pPage, ifShouldExist) {
	await pPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general',
		{waitUntil: 'domcontentloaded'});

	let errorExists = (await pPage.$('.w3tc_error') != null);
	if (ifShouldExist) {
		expect(errorExists).true;
	} else {
		if (errorExists) {
			let errorText = await pPage.$eval('.error', (e) => e.textContent);
			log.error(errorText);
		}

		expect(errorExists).false;
	}
}



exports.updateCacheEntry = async function(pPage, url, addParam, cacheEngineLabel, cacheEngineName) {
	log.log('updating cache entry for ' + url);
	if (cacheEngineLabel == null) {
		cacheEngineLabel = env.cacheEngineLabel;
	}
	if (cacheEngineName == null) {
		cacheEngineName = env.cacheEngineName;
	}

	let r = await exec('cp ../../plugins/cache-entry.php ' + env.wpPath + 'cache-entry.php');

	let controlUrl = env.blogSiteUrl + 'cache-entry.php?blog_id=' + env.blogId +
		'&wp_content_path=' + env.wpContentPath + '&url=' + url +
		'&engine=' + cacheEngineLabel;
	if (addParam == true) {
		controlUrl += '&param';
	}

	await pPage.goto(controlUrl, {waitUntil: 'domcontentloaded'});
	let html = await pPage.content();
	expect(html).contains('Page Caching using ' + cacheEngineName);
}



exports.gotoWithPotentialW3TCRepeat = async function(pPage, url) {
	let response = await pPage.goto(url, {waitUntil: 'domcontentloaded'});
	if (pPage.url().indexOf("repeat=w3tc") >= 0) {
		log.log('repeat=w3tc found, doing one more request');
		let response = await pPage.goto(url, {waitUntil: 'domcontentloaded'});
	}

	return response;
}



exports.expectPageCachingMethod = function(pageContent, cacheEngineName) {
	let regex = new RegExp('Page Caching using ' + cacheEngineName + '(\\s*\\(([^)])+\\))?');
    let m = pageContent.match(regex);
    expect(m).not.null;

    if (m[1] != null) {
		log.error('caching exception is in action: "' + m[0]);
		expect(false).is.true;
	}
}



exports.pageCacheEntryChange = async function(pPage, cacheEngineLabel, cacheEngineName, url) {
	if (cacheEngineLabel == null) {
		cacheEngineLabel = env.cacheEngineLabel;
	}
	if (cacheEngineName == null) {
		cacheEngineName = env.cacheEngineName;
	}
	if (url == null) {
		url = env.homeUrl;
	}

	await pPage.goto(env.blogSiteUrl + 'cache-entry.php?blog_id=' + env.blogId +
		'&wp_content_path=' + env.wpContentPath +
		'&url=' + encodeURIComponent(url) +
		'&engine=' + cacheEngineLabel);
	expect(await pPage.content()).contains('Page Caching using ' + cacheEngineName);
}



function updateUTimes(filename) {
	let stat = fs.statSync(filename);
	let newTime = stat.mtime;
	newTime.setSeconds(newTime.getSeconds() - 3600);

	fs.utimesSync(filename, newTime, newTime);
	log.success('updated timestamp for ' + filename);
}



exports.pageCacheFileGenericChangeFileTimestamp = async function(url, extension) {
	log.log("Changing timestamp for the old cache file of " + url);
	let filename = exports.pageCacheFileGenericUrlToFilename(url, extension);
	let someUpdated = false;
	if (fs.existsSync(filename)) {
		updateUTimes(filename);
		someUpdated = true;
	}
	if (fs.existsSync(filename + '_old')) {
		updateUTimes(filename + '_old');
		someUpdated = true;
	}
	if (fs.existsSync(filename + '_gzip_old')) {
		updateUTimes(filename + '_gzip_old');
		someUpdated = true;
	}

	if (!someUpdated) {
		log.error('file doesnt exists ' + filename);
		expect(false).is.true;
	}
}



exports.pageCacheFileGenericUrlToFilename = function(url, extension) {
	if (!extension) {
		extension = 'html';
	}

	let m = url.match(/https?:\/\/([A-Za-z0-9\.]+(:[0-9]+)?)(\/.*)?$/);
	let uri = m[3];
	if (uri[uri.length - 1] != '/')
	uri += '/';

	let cf = (env.scheme == 'https' ? '_index_ssl.' : '_index.') + extension;

	return env.wpContentPath + 'cache/page_enhanced/' +
		m[1].toString().toLowerCase() + uri + cf;
}



exports.commentTimestamp = async function(pPage, cacheEngineName) {
	if (cacheEngineName == null) {
    	cacheEngineName = env.cacheEngineName;
	}

    log.log('Checking has "Page Caching using ' + cacheEngineName + '" text...');

    let html = await pPage.content();
	let regex = new RegExp('Page Caching using ' + cacheEngineName + '(\\s*\\(([^)])+\\))?');
	let m = html.match(regex);
	expect(m != null).true;

	if (m[1] != null) {
		log.error('caching exception is in action: "' + m[0]);
		expect(false).is.true;
	}

    /** Looking for timestamp in the source code */
    let matches = html.match(/Served from: ([^@]+)@ ([^b]+)by W3 Total Cache/);
	expect(matches.length > 0);
    return matches[0];
}



exports.flushAll = async function(pPage) {
	await sys.repeatOnFailure(pPage, async() => {
		await pPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
			{waitUntil: 'domcontentloaded'});

		await Promise.all([
			pPage.click('#flush_all'),
			pPage.waitForNavigation({timeout:0}),
		]);

		let html = await pPage.content();
		expect(html).contains('All caches successfully emptied');
	});
}



exports.regExpForOption = function(string) {
  return string.replace(/\//g, '\\/').replace(/\./g, '\\.').replace(/\?/g, '\\?').replace(/\\/g, '\\');
}



exports.cdnPushExportFiles = async function(pPage, sectionToExport) {
	log.log('Exporting ' + sectionToExport + ' files...');
	await pPage.goto(
		env.networkAdminUrl + 'admin.php?page=w3tc_cdn',
		{waitUntil: 'domcontentloaded'});

	let cssClass = await pPage.$eval('input[value="Upload ' + sectionToExport + ' files"]',
		(e) => e.getAttribute('class'));
	let nonce = cssClass.match(/nonce: \'(.+)\'/)[1];

	await pPage.goto(
		env.networkAdminUrl + 'admin.php?page=w3tc_cdn&w3tc_cdn_export&' +
			'cdn_export_type=' + sectionToExport + '&_wpnonce=' + nonce,
		{waitUntil: 'domcontentloaded'});

	let filesNumberToExport = await pPage.$eval('tr', (e) => e.childNodes[3].textContent);
	log.log('Found ' + filesNumberToExport + ' files to export in ' +
		sectionToExport + ' section');
	expect(filesNumberToExport > 0).is.true;

	await pPage.click('#cdn_export_file_start');
	await pPage.waitFor((filesNumberToExport) => {
		let onPage = document.querySelector('#cdn_export_file_processed').textContent;
		return onPage == filesNumberToExport;
	}, {}, filesNumberToExport);

	let onPage = await pPage.$eval('#cdn_export_file_processed', (e) => e.textContent);
	expect(onPage).equals(filesNumberToExport);
}



exports.setOptionsMinifyAddJsEntry = async function(pPage, i, inputValue, optionValue) {
	log.log('click add');
	await pPage.click('#js_file_add');

	await pPage.waitFor((ii) => {
		return document.querySelectorAll('#js_files li table').length >= ii;
	}, {}, i);

	log.log('fill element ' + i + ' with ' + inputValue + ', ' + optionValue);
	await pPage.$$eval('#js_files li table',
		function(els, ii, inputValue, optionValue) {
			let table = els[ii - 1];
			table.querySelector('input').value = inputValue;

			if (optionValue != 'include') {
				let oldName = table.querySelector('input').name;
				let newName = oldName.replace('include', optionValue);
				table.querySelector('input').name = newName;
			}
		},
		i, inputValue, optionValue
	);
}



exports.setOptionsMinifyAddCssEntry = async function(pPage, i, inputValue, optionValue) {
	log.log('click add');
	await pPage.click('#css_file_add');

	await pPage.waitFor((ii) => {
		return document.querySelectorAll('#css_files li table').length >= ii;
	}, {}, i);

	log.log('fill element ' + i + ' with ' + inputValue + ', ' + optionValue);
	await pPage.$$eval('#css_files li table',
		function(els, ii, inputValue, optionValue) {
			let table = els[ii - 1];
			table.querySelector('input').value = inputValue;

			if (optionValue != 'include') {
				let oldName = table.querySelector('input').name;
				let newName = oldName.replace('include', optionValue);
				table.querySelector('input').name = newName;
			}
		},
		i, inputValue, optionValue
	);
}



exports.w3tcComment = async function(pPage) {
	let html = await pPage.content();
	let m = html.match(/<!--([\s\n]*)Performance optimized by(.*)-->/ms);
	if (m == null) {
		return null;
	}

	return m[0];
}
