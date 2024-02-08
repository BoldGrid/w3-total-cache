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

		// Skip the Setup Guide wizard.
		if (await pPage.$('#w3tc-wizard-skip') != null) {
			log.log('Encountered the Setup Guide wizard; skipping...');

			let wizardSkip = '#w3tc-wizard-skip';
			let skipped = await Promise.all([
				pPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
				pPage.waitForNavigation({timeout:0}),
			]);

			expect(skipped).is.not.null;
		}

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
				await pPage.evaluate((keySelector) => document.querySelector(keySelector).click(), keySelector);
			}
			if (key == 'minify__enabled') {
				await pPage.waitForSelector('.lightbox-close', {
					visible: true
				});
				log.log('click minify popup close');
				await pPage.screenshot({path: '/var/www/wp-sandbox/01.png'});

				let lightboxClose = '.lightbox-close';
				await pPage.evaluate((lightboxClose) => document.querySelector(lightboxClose).click(), lightboxClose);

				await pPage.waitForSelector('.lightbox-close', {
					hidden: true
				});
				log.log('minify popup closed');

				// very weird issue - first button click hangs, while all other
				// works in that case. it cant scroll up?
				saveSelector = 'input[name="w3tc_save_options"]';
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
		pPage.evaluate((saveSelector) => document.querySelector(saveSelector).click(), saveSelector)
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



exports.setOptionInternal = async function(pPage, name, value) {
	let r = await exec('cp ../../plugins/w3tc-set-option-internal.php ' +
		env.wpPath + 'w3tc-set-option-internal.php');

	let controlUrl = env.blogSiteUrl + 'w3tc-set-option-internal.php?blog_id=' +
		env.blogId +
		'&name=' + encodeURIComponent(JSON.stringify(name)) + '&value=' +
		encodeURIComponent(JSON.stringify(value));
	await pPage.goto(controlUrl, {waitUntil: 'domcontentloaded'});
	let html = await pPage.content();
	expect(html).contains('ok');
}



exports.activateExtension = async function(pPage, extenstion_id) {
	await pPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_extensions', {waitUntil: 'domcontentloaded'});

	// Skip the Setup Guide wizard.
	if (await pPage.$('#w3tc-wizard-skip') != null) {
		log.log('Encountered the Setup Guide wizard; skipping...');
		let wizardSkip = '#w3tc-wizard-skip';
		let skipped = await Promise.all([
			pPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
			pPage.waitForNavigation({timeout:0}),
		]);

		expect(skipped).is.not.null;
	}

	await pPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_extensions', {waitUntil: 'domcontentloaded'});
	let isActive = await pPage.$('#' + extenstion_id + ' .deactivate');
	if (isActive != null) {
		log.success('extension is already active');
		return;
	}

	let extensionActivate = '#' + extenstion_id + ' .activate a';
	await Promise.all([
		pPage.evaluate((extensionActivate) => document.querySelector(extensionActivate).click(), extensionActivate),
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
		let emptyStaticCache = 'input[value="Empty the static files cache"]';
		await Promise.all([
			pPage.evaluate((emptyStaticCache) => document.querySelector(emptyStaticCache).click(), emptyStaticCache),
			pPage.waitForNavigation({timeout:0}),
		]);
	}
}



exports.expectW3tcErrors = async function(pPage, ifShouldExist) {
	await pPage.goto(env.networkAdminUrl + 'admin.php?page=w3tc_general',
		{waitUntil: 'domcontentloaded'});

	// Skip the Setup Guide wizard.
	if (await pPage.$('#w3tc-wizard-skip') != null) {
		log.log('Encountered the Setup Guide wizard; skipping...');

		let wizardSkip = '#w3tc-wizard-skip';
		let skipped = await Promise.all([
			pPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
			pPage.waitForNavigation({timeout:0}),
		]);

		expect(skipped).is.not.null;
	}

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



exports.pageCacheEntryChange = async function(pPage, cacheEngineLabel, cacheEngineName, url, pageKeyPostfix) {
	if (cacheEngineLabel == null) {
		cacheEngineLabel = env.cacheEngineLabel;
	}
	if (cacheEngineName == null) {
		cacheEngineName = env.cacheEngineName;
	}
	if (url == null) {
		url = env.homeUrl;
	}
	if (pageKeyPostfix == null) {
		pageKeyPostfix = '';
	}

	await pPage.goto(env.blogSiteUrl + 'cache-entry.php?blog_id=' + env.blogId +
			'&wp_content_path=' + env.wpContentPath +
			'&url=' + encodeURIComponent(url) +
			'&page_key_postfix=' + pageKeyPostfix +
			'&engine=' + cacheEngineLabel,
		{waitUntil: 'domcontentloaded'});
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
	let filenameSlash = exports.pageCacheFileGenericUrlToFilename(url, extension, '_slash');
	let tryouts = [
		filename,
		filename + '_old',
		filename + '_gzip_old',
		filenameSlash,
		filenameSlash + '_old',
		filenameSlash + '_gzip_old'
	];

	let someUpdated = false;
	for (let f of tryouts) {
		if (fs.existsSync(f)) {
			updateUTimes(f);
			someUpdated = true;
		}
	}

	if (!someUpdated) {
		log.error('file doesnt exists ' + filename);
		expect(false).is.true;
	}
}



exports.pageCacheFileGenericUrlToFilename = function(url, extension, postfix = '') {
	if (!extension) {
		extension = 'html';
	}

	let m = url.match(/https?:\/\/([A-Za-z0-9\.]+(:[0-9]+)?)(\/.*)?$/);
	let uri = m[3];
	if (uri[uri.length - 1] != '/')
	uri += '/';

	let cf = '_index' + postfix +
		(env.scheme == 'https' ? '_ssl' : '') +
		'.' + extension;

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

		// Skip the Setup Guide wizard.
		if (await pPage.$('#w3tc-wizard-skip') != null) {
			log.log('Encountered the Setup Guide wizard; skipping...');

			let wizardSkip = '#w3tc-wizard-skip';
			let skipped = await Promise.all([
				pPage.evaluate((wizardSkip) => document.querySelector(wizardSkip).click(), wizardSkip),
				pPage.waitForNavigation({timeout:0}),
			]);

			expect(skipped).is.not.null;
		}

		await pPage.goto(env.adminUrl + 'admin.php?page=w3tc_dashboard',
			{waitUntil: 'domcontentloaded'});

		let flushAll = '#wp-admin-bar-w3tc_flush_all a';
		await Promise.all([
			pPage.evaluate((flushAll) => document.querySelector(flushAll).click(), flushAll),
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

	let cdnExportStart = '#cdn_export_file_start';
	await pPage.evaluate((cdnExportStart) => document.querySelector(cdnExportStart).click(), cdnExportStart);
	await pPage.waitFor((filesNumberToExport) => {
		let onPage = document.querySelector('#cdn_export_file_processed').textContent;
		return onPage == filesNumberToExport;
	}, {}, filesNumberToExport);

	let onPage = await pPage.$eval('#cdn_export_file_processed', (e) => e.textContent);
	expect(onPage).equals(filesNumberToExport);
}



exports.setOptionsMinifyAddJsEntry = async function(pPage, i, inputValue, optionValue) {
	log.log('click add');
	let jsAdd = '#js_file_add';
	await pPage.evaluate((jsAdd) => document.querySelector(jsAdd).click(), jsAdd);

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
	let cssAdd = '#css_file_add';
	await pPage.evaluate((cssAdd) => document.querySelector(cssAdd).click(), cssAdd);

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
