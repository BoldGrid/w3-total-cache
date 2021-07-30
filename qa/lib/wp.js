const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');
const env = require('./environment');
const sys = require('./sys');

fs.readFileAsync = util.promisify(fs.readFile);
fs.writeFileAsync = util.promisify(fs.writeFile);



exports.login = async function(pPage, data) {
	await sys.repeatOnFailure(pPage, async() => {
		log.log('logging in to wp-admin ' + env.networkAdminUrl);

		await pPage.goto(env.networkAdminUrl, {waitUntil: 'domcontentloaded'});
		let title = await pPage.title();

		expect(await pPage.title()).contains('Log In');
		await pPage.$eval('#user_login', (e, v) => { e.value = v }, 'admin');
		await pPage.$eval('#user_pass', (e, v) => { e.value = v }, '1');

		await Promise.all([
			pPage.click('#wp-submit'),
			pPage.waitForNavigation({timeout:0}),
		]);

		expect(await pPage.title()).contains('Dashboard');
		log.success('Logged in to wp-admin');
	});
}



exports.getCurrentTheme = async function(pPage) {
	await pPage.goto(env.adminUrl + 'themes.php', {waitUntil: 'domcontentloaded'});

	if (env.wpVersion.match(/^3\.(.+)/)) {
		let description = await pPage.$eval('#current-theme .hide-if-customize',
			(e) => e.src);
		let m = description.match(/themes\/(.+)\/screenshot\.png$/);
		return m[1];
	} else {   // env.wpVersion.match(/^4\.*/)
		let description = await pPage.$eval('.theme.active .theme-actions a',
			(e) => e.getAttribute('href'));
		let m = description.match(/theme=([^&]+)/);
		return m[1];
	}
}



exports.postCreate = async function(pPage, data) {
	expect(data.type).not.empty;
	let createPostUrl = env.adminUrl + 'post-new.php' +
		(data.type != 'post' ? '?post_type=' + data.type : '');

	log.log('opening ' + createPostUrl);
	await pPage.goto(createPostUrl, {waitUntil: 'domcontentloaded'});

	if (env.wpVersion.match(/^5\.(.+)/)) {
		return await postCreateWP5(pPage, data);
	}

	return await postCreateWP4(pPage, data);
}



async function postCreateWP4(pPage, data) {
	log.log('create page - wp4');
	await pPage.$eval('#title', (e, v) => { e.value = v }, data.title);
	await pPage.click('#content-html');
	await pPage.$eval('#content', (e, v) => { e.value = v }, data.content);

	// set page template
	if (data.template) {
		await pPage.select('#page_template', data.template);
	}

	// set publish date
	if (data.date_publish_offset_seconds) {
		let d = new Date();
	    d.setSeconds(d.getSeconds() + data.date_publish_offset_seconds);
	    let month = d.getMonth() + 1,
	    	day = d.getDate(),
	    	minutes = d.getMinutes(),
	    	hours = d.getHours(),
	    	year = d.getFullYear();
		month = (month <= 9 ? '0' : '') + month;

		log.log('click .edit-timestamp');
		await pPage.click('.edit-timestamp');
		await pPage.waitForSelector('#mm', {visible: true});
		await pPage.select('#mm', month);
		await pPage.$eval('#jj', (e, v) => e.value = v, day);
		await pPage.$eval('#aa', (e, v) => e.value = v, year);
		await pPage.$eval('#hh', (e, v) => e.value = v, hours);
		await pPage.$eval('#mn', (e, v) => e.value = v, minutes);
		log.log('click save');
		await pPage.waitForSelector('.save-timestamp', {visible: true});
		await pPage.click('.save-timestamp');
		log.log('wait for .edit-timestamp');
		await pPage.waitForSelector('.edit-timestamp', {visible: true});
	}

	let url;

	await sys.repeatOnFailure(pPage, async() => {
		let value = await pPage.$eval('#publish', (e) => e.value);
		log.log(new Date().toISOString() + ' create page - publish button has value ' + value);
		if (value == 'Update') {
			log.log(new Date().toISOString() + ' already published');
			return;
		}

		log.log(new Date().toISOString() + ' create page - click publish');
		await Promise.all([
			pPage.click('#publish'),
			pPage.waitForNavigation({
				waitUntil: 'networkidle0',
				timeout: 5000
			})
		]);

		await pPage.waitForSelector('#title', {
			timeout: 5000
		});

		if (await pPage.$('#message') == null) {
			throw new Error('no message - click actually failed');
		}

		log.log(new Date().toISOString() + ' create page - after navigation');

		// wp >= 4.4
		if (await pPage.$('#view-post-btn a') != null) {
			// wp<4.4
			log.log('create page - use #view-post-btn');
			url = await pPage.$eval('#view-post-btn a', (e) => e.href);
		} else {
			log.log('create page - use #sample-permalink');
			url = await pPage.$eval('#sample-permalink', (e) => e.childNodes[0].href);
		}

		if (url.indexOf('preview') > 0) {
			log.log('got preview ' + url);
			await pPage.screenshot({path: '/root/wp-create.png'});
			let html = await pPage.content();
			log.log(html);
			throw new Error('got preview link - publish button didnt work out');
		}
	});

	let postId = await pPage.$eval('#post_ID', (e) => e.value);

	expect(url).not.empty;
	log.success('created post ' + url);
	return {
		url: url,
		id: postId
	};
}



function postCreateWP5MoreButtonSelector() {
	if (parseFloat(env.wpVersion) < 5.3) {
		return 'button[aria-label="Show more tools & options"]';
	} else if (parseFloat(env.wpVersion) < 5.6) {
		return 'button[aria-label="More tools & options"]';
	}

	return 'button[aria-label="Options"]';
}



async function postCreateWP5(pPage, data) {
	log.log('create page - wp5 (' + parseFloat(env.wpVersion) + ') - switch to code editor')

	let moreButtonSelector = postCreateWP5MoreButtonSelector();

	if (parseFloat(env.wpVersion) < 5.3) {
		await pPage.click(moreButtonSelector);
	} else {
		try {
			await pPage.waitForSelector(moreButtonSelector, {
				timeout: 3000
			});
		} catch (e) {
			log.log('close welcome guide shit');
			if (await pPage.$('.edit-post-welcome-guide__heading') !== null) {
				log.log('found modal');
				await pPage.waitForSelector('button[aria-label="Close dialog"]', {
					timeout: 3000
				});
				log.log('found modal close button');
				await pPage.click('button[aria-label="Close dialog"]');
			}

			await pPage.waitForSelector(moreButtonSelector, {
				timeout: 3000
			});
		}

		await pPage.click(moreButtonSelector);

		try {
			await pPage.waitForSelector(moreButtonSelector + '[aria-expanded="true"]', {
				timeout: 3000
			});
		} catch (e) {
			log.error('click failed, repeating');

			await pPage.click(moreButtonSelector);

			await pPage.waitForSelector(moreButtonSelector + '[aria-expanded="true"]', {
				timeout: 5000
			});
		}
	}

	if (parseFloat(env.wpVersion) < 5.2) {
		await pPage.click('button[aria-label="Code Editor"]');
	} else {
		if (parseFloat(env.wpVersion) < 5.5) {
			await pPage.waitForSelector('.components-popover__content', {
				visible: true
			});
		} else {
			await pPage.waitForSelector('.components-dropdown-menu__popover', {
				visible: true
			});
		}

		let clicked = await pPage.evaluate(() => {
			let elements = document.getElementsByClassName('components-menu-item__button');
			for (let element of elements) {
				if (element.innerHTML.toLowerCase().indexOf('code editor') >= 0) {
					element.click();
					return 'clicked';
				}
			}

			return 'code editor button notfound';
		});

		expect(clicked).equals('clicked');
	}

	log.log('create page - type title, content')
	await pPage.focus('.editor-post-title__input');
	await pPage.keyboard.type(data.title);

	await pPage.focus('#post-content-0');
	await pPage.keyboard.type(data.content);

	if (data.template) {
		log.log('create page - set template (' + data.template + ')')
		let templateControlId;
		if (parseFloat(env.wpVersion) < 5.8) {
			templateControlId = await pPage.evaluate(() => {
				let elements = document.getElementsByClassName('components-panel__body-toggle');
				for (let element of elements) {
					element.click();
				}

				let labels = document.querySelectorAll('label.components-base-control__label');
				for (let element of labels) {
					if (element.innerHTML == 'Template:') {
						return element.getAttribute('for');
					}
				}

				labels = document.querySelectorAll('label.components-input-control__label');
				for (let element of labels) {
					if (element.innerHTML == 'Template:') {
						return element.getAttribute('for');
					}
				}

				let dropdowns = document.querySelectorAll('.components-select-control__input');
				for (let dropdown of dropdowns) {
						if (dropdown.outerHTML.indexOf('>Default template<') > 0) {
								return dropdown.id;
						}
				}

				return x1;   // fail here means something wrong with DOM structure
			});
		} else {
			templateControlId = await pPage.evaluate(() => {
				let elements = document.getElementsByClassName('components-panel__body-toggle');
				for (let element of elements) {
					if (element.innerText.indexOf("Template") >= 0) {
						element.click();
						let block = element.closest('.components-panel__body');
						return block.querySelector('select').getAttribute('id');
					}
				}

				return x2;
			});
		}
		console.log(templateControlId);
		expect(templateControlId).not.empty;

		await pPage.select('#' + templateControlId, data.template);
	}

	if (data.date_publish_offset_seconds) {
		let d = new Date();
	    d.setSeconds(d.getSeconds() + data.date_publish_offset_seconds);
	    let month = d.getMonth() + 1,
	    	day = d.getDate(),
	    	minutes = d.getMinutes(),
	    	hours = d.getHours(),
	    	year = d.getFullYear();
		month = (month <= 9 ? '0' : '') + month;
		minutes = (minutes <= 9 ? '0' : '') + minutes;   // looks like otherwise unstable


		await sys.repeatOnFailure(pPage, async() => {
			log.log('click .edit-post-post-schedule__toggle');
			await pPage.click('.edit-post-post-schedule__toggle');
			await pPage.waitForSelector('select[aria-label="Month"]', {
				visible: true,
				timeout: 2000
			});
		});

		let v = await pPage.$eval('.edit-post-post-schedule__toggle',
			(e) => e.getAttribute('aria-expanded'));
		expect(v).equals('true');
		await pPage.select('select[aria-label="Month"]', month);
		await postCreateWP5_setValue(pPage, 'input[aria-label="Day"]', day);
		await postCreateWP5_setValue(pPage, 'input[aria-label="Year"]', year);
		log.log(new Date().toISOString() + ' set time ' + hours + ':' + minutes);
		await postCreateWP5_setValue(pPage, 'input[aria-label="Hours"]', hours);
		await postCreateWP5_setValue(pPage, 'input[aria-label="Minutes"]', minutes);

		log.log('click save');
		await pPage.click('.edit-post-post-schedule__toggle');

		await pPage.waitFor(function() {
			var v = document.querySelector('.edit-post-post-schedule__toggle');
			var attr = v.getAttribute('aria-expanded');
			return attr == 'false';
		});
	}

	log.log('create page - open publish panel')
	await pPage.click('.editor-post-publish-panel__toggle');
	await pPage.waitForSelector('.editor-post-publish-button[aria-disabled="false"]');

	if (data.date_publish_offset_seconds) {
		let label = await pPage.$eval('.editor-post-publish-button', (e) => e.innerHTML);
		expect(label).equals('Schedule');
	}

	log.log('create page - click publish button')
	await pPage.click('.editor-post-publish-button');
	log.log('create page - waiting for published state');
	try {
		await pPage.waitFor(function() {
			let v = document.querySelector('.editor-post-publish-button');
			return v.innerHTML == 'Update' || v.innerHTML == 'Schedule';
		}, {timeout: 5000});
	} catch (e) {
		log.error('failed');

		log.log('click2');
		await pPage.click('.editor-post-publish-button');
		log.log('create page - waiting for published state2');

		await pPage.waitFor(function() {
			let v = document.querySelector('.editor-post-publish-button');
			return v.innerHTML == 'Update' || v.innerHTML == 'Schedule';
		}, {timeout: 5000});

		log.log('now seems ok');
	}

	log.log('create page - get url')
	let url = null;
	if (!data.date_publish_offset_seconds) {
		if (await pPage.$('.post-publish-panel__postpublish-buttons a') !== null) {
			url = await pPage.$eval('.post-publish-panel__postpublish-buttons a', (e) => e.href);
		} else {
			url = await postCreateWP5_getUrl(pPage);
		}
	}

	let pageUrl = pPage.url();
	let m = pageUrl.match(/post=([0-9]+)/);
	let postId = m[1];

	log.success('created post ' + url + ' ' + postId);

	return {
		id: postId,
		url: url
	};
}



// attempts to set value directly for textbox doesnt work in React app
// typing it by keyboard is much more stable
async function postCreateWP5_setValue(pPage, selector, value) {
	await pPage.focus(selector);
	await pPage.keyboard.press('Home');
	await pPage.keyboard.press('Delete');
	await pPage.keyboard.press('Delete');
	await pPage.keyboard.type('' + value);
}



// get url of the edited wordpress page
async function postCreateWP5_getUrl(pPage) {
	for (let n = 0; n < 3; n++) {
		log.log('opening permalink tab');
		let clicked = await pPage.evaluate(() => {
			let elements = document.querySelectorAll('.components-panel__body button');
			for (let element of elements) {
				if (element.innerHTML.toLowerCase().indexOf('permalink') >= 0) {
					element.click();
					return 'clicked';
				}
			}

			return 'permalink tab notfound';
		});

		expect(clicked).equals('clicked');

		log.log('waiting permalink tab to open');
		try {
			await pPage.waitForSelector('a.edit-post-post-link__link', {
				timeout: 5000
			});
		} catch (e) {
			await pPage.screenshot({path: '/var/www/wp-sandbox/01-b.png'});
			log.log('failed, retrying');
			continue;
		}

		return await pPage.$eval('a.edit-post-post-link__link', (e) => e.href);
	}

	throw 'failed';
}



exports.postUpdate = async function(pPage, data) {
	let postType = typeof data.post_type != 'undefined' ? data.post_type : 'post';

	await pPage.goto(env.adminUrl + 'post.php?post=' + data.post_id + '&action=edit');
	log.log(new Date().toISOString() + ' Updating the ' + postType + ' ' + data.post_title);

	if (env.wpVersion.match(/^5\.(.+)/)) {
		return await postUpdateWP5(pPage, data);
	}

	return await postUpdateWP4(pPage, data);
}



async function postUpdateWP4(pPage, data) {
	await pPage.$eval('#title', (e, v) => { e.value = v}, data.post_title);

	await Promise.all([
		pPage.click('#publish'),
		pPage.waitForNavigation({
			timeout: 0,
			waitUntil: 'networkidle0'
		})
	]);

	await pPage.waitForSelector('#message', {timeout: 5000});
	let noticeText = await pPage.$eval('#message',
		(e) => e.innerHTML);
	expect(noticeText).contains('Post updated');

	log.log('post updated');
}



async function postUpdateWP5(pPage, data) {
	log.log('update page - switch to code editor')

	let moreButtonSelector = postCreateWP5MoreButtonSelector();

	if (parseFloat(env.wpVersion) < 5.3) {
		await pPage.click(moreButtonSelector);
	} else {
		await pPage.waitForSelector(moreButtonSelector, {
			timeout: 3000
		});
		await pPage.click(moreButtonSelector);

		try {
			await pPage.waitForSelector(moreButtonSelector + '[aria-expanded="true"]', {
				timeout: 3000
			});
		} catch (e) {
			log.error('click failed, repeating');

			await pPage.click(moreButtonSelector);

			await pPage.waitForSelector(moreButtonSelector + '[aria-expanded="true"]', {
				timeout: 5000
			});
		}
	}

	if (parseFloat(env.wpVersion) < 5.2) {
		await pPage.click('button[aria-label="Code Editor"]');
	} else {
		if (parseFloat(env.wpVersion) < 5.5) {
			await pPage.waitForSelector('.components-popover__content', {
				visible: true
			});
		} else {
			await pPage.waitForSelector('.components-dropdown-menu__popover', {
				visible: true
			});
		}

		let clicked = await pPage.evaluate(() => {
			let elements = document.getElementsByClassName('components-menu-item__button');
			for (let element of elements) {
				if (element.innerHTML.toLowerCase().indexOf('code editor') >= 0) {
					element.click();
					return 'clicked';
				}
			}

			return 'code editor button notfound';
		});

		expect(clicked).equals('clicked');
	}

	log.log('update page - type title, content')
	await pPage.focus('.editor-post-title__input');
	await pPage.keyboard.down('ControlLeft');
	await pPage.keyboard.press('KeyA');
	await pPage.keyboard.up('ControlLeft');
	await pPage.keyboard.type(data.post_title);

	log.log('update page - click publish button')
	await pPage.click('.editor-post-publish-button');
	log.log('update page - waiting for published state');

	let noticeText;
	if (parseFloat(env.wpVersion) < 5.3) {
		await pPage.waitForSelector('.components-notice', {timeout: 5000});
		noticeText = await pPage.$eval('.components-notice__content',
			(e) => e.innerHTML);
	} else {
		await pPage.waitForSelector('.components-snackbar-list__notice-container', {timeout: 5000});
		noticeText = await pPage.$eval('.components-snackbar-list__notice-container',
			(e) => e.innerHTML);
	}
	expect(noticeText).contains('Post updated');

	log.log('post updated');
}



exports.addWpConfigConstant = async function(pPage, name, value) {
	log.log('set constant ' + name);
	let filename = env.wpPath + '/wp-config.php';
    let content = await fs.readFileAsync(filename, 'utf8');
	await fs.writeFileAsync(filename,
		'<\?php' + "\n" + 'define("' + name + '", "' + value + '");' + "\n" +
		content.replace(/^<\?php/, ''),
		'utf8');

	let checkFilename = env.wpPath + '/check-constant.php';
	await fs.writeFileAsync(checkFilename,
		'<\?php' + "\n" +
			'include(dirname(__FILE__) . "/wp-load.php");\n' +
			'if (defined("' + name + '")) echo "constant-defined";',
		'utf8');

	for (let n = 0; n < 100; n++) {
		await pPage.goto(env.wpUrl + '/check-constant.php');
		let html = await pPage.content();
		if (html.indexOf('constant-defined') >= 0) {
			log.success('constant is defined');
			return;
		}

		log.log(html);
		log.log('constant is still not defined - waiting PHP to catch filesystem updates');
		await pPage.waitFor(1000);
	}

	log.error('constant is not defined');
}



exports.networkActivatePlugin = async function(pPage, pluginFilename) {
	await pPage.goto(env.networkAdminUrl + '/plugins.php');

	if (parseFloat(env.wpVersion) < 4.4) {
		let parts = pluginFilename.split('/');
		let pluginName = parts[0];
		let pluginRow = await pPage.$('tr#' + pluginName);
		expect(pluginRow).not.null;

		await Promise.all([
			pPage.click('#' + pluginName + ' .activate a'),
			pPage.waitForNavigation()
		]);
	} else {
		let pluginRow = await pPage.$('tr[data-plugin="' + pluginFilename + '"]');
		expect(pluginRow).not.null;

		await Promise.all([
			pPage.click('tr[data-plugin="' + pluginFilename + '"] .activate a'),
			pPage.waitForNavigation()
		]);
	}

	let ifActivated = await pPage.$eval('#message', (e) => e.innerText.trim());
	expect(ifActivated).contains('Plugin activated.');
	log.success('activated plugin ' + pluginFilename);
}



exports.userSignUp = async function(pPage, data) {
	if (env.isWpmu) {
		return await userSignUpNetwork(pPage, data);
	} else {
		return await userSignUpSingle(pPage, data);
	}
}



async function userSignUpSingle(pPage, data) {
	// add user
    await pPage.goto(env.adminUrl + 'user-new.php');
	await pPage.$eval('#user_login', (e, v) => e.value = v, data.user_login);
	await pPage.$eval('#email', (e, v) => e.value = v, data.email);
	await pPage.select('#role', data.role);

	if (parseFloat(env.wpVersion) >= 4.4) {
		await pPage.click('#send_user_notification');   // dont send confirmation
	}

	await pPage.click('.wp-generate-pw');

	let password;
	if (parseFloat(env.wpVersion) < 5.3) {
		await pPage.waitFor('#pass1-text', {visible: true});
		password = await pPage.$eval('#pass1-text', (e) => e.value);
	} else {
		await pPage.waitFor('#pass1', {visible: true});
		password = await pPage.$eval('#pass1', (e) => e.value);
	}

	await Promise.all([
		pPage.click('#createusersub'),
		pPage.waitForNavigation()
	]);

	let m = await pPage.$eval('#message', (e) => e.outerHTML);
	expect(m).contains('New user created.');

	return password;
}



async function userSignUpNetwork(pPage, data) {
	// enable signup
	await pPage.goto(env.networkAdminUrl + 'settings.php');
	await pPage.click('#registration2');

	await Promise.all([
		pPage.click('#submit'),
		pPage.waitForNavigation()
	]);

	let message = await pPage.$eval('#message', (e) => e.innerHTML);
	if (parseFloat(env.wpVersion) < 4.4) {
		expect(message).contains('Options saved.');
	} else {
		expect(message).contains('Settings saved.');
	}

	log.success('signup allowed');

	// add user
    await pPage.goto(env.adminUrl + 'user-new.php');
	await pPage.$eval('#user_login', (e, v) => e.value = v, data.user_login);
	await pPage.$eval('#email', (e, v) => e.value = v, data.email);
	await pPage.select('#role', data.role);

	await Promise.all([
		pPage.click('#createusersub'),
		pPage.waitForNavigation()
	]);

	let m = await pPage.$eval('#message', (e) => e.outerHTML);
	expect(m).contains('Invitation email sent to new user.');

	//we're "catching" the email with activation key and activated a subscriber
	let emailContent = await fs.readFileAsync(env.wpContentPath + 'mail.txt', 'utf8');
	expect(emailContent).not.empty;
	let emailMatch = emailContent.match(new RegExp('http.*wp-activate.php([^< ]+)'));
	let emailUrl = emailMatch[0];
	expect(emailUrl).not.empty;

	// open signup verification url
	await adminPage.goto(emailUrl);
	let m2 = await adminPage.$eval('#signup-welcome', (e) => e.outerHTML);
	expect(m2).not.empty;
	let match = m2.match(new RegExp('Password:\\s*<[^>]+>\\s*([^< ]+)'));
	return match[1];
}
