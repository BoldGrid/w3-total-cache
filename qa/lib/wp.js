const expect = require('chai').expect;
const log = require('mocha-logger');
const util = require('util');
const fs = require('fs');
const env = require('./environment');
const sys = require('./sys');
const exec = util.promisify(require('child_process').exec);

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
	} else if (env.wpVersion.match(/^5\.9(.*)/)) { // WP 5.9*
		let theme = await pPage.$eval('.theme.active', (e) => e.getAttribute('data-slug'));
		return theme;
	} else {   // env.wpVersion.match(/^4\.*/)
		let description = await pPage.$eval('.theme.active .theme-actions a',
			(e) => e.getAttribute('href'));
		let m = description.match(/theme=([^&]+)/);
		return m[1];
	}
}



function postCreateApiUrl(type) {
	if (type == 'post') {
		return env.homeUrl + '/wp-json/wp/v2/posts';
	} else if (type == 'page') {
		return env.homeUrl + '/wp-json/wp/v2/pages';
	}

	throw new Error('unknown type ' + type);
}



exports.postCreate = async function(pPage, data) {
	expect(data.type).not.empty;

	let r = await exec('cp ../../plugins/w3tcqa-json.php ' + env.wpPath + 'w3tcqa-json.php');

	let apiUrl = postCreateApiUrl(data.type);
	let apiBody = data;
	apiBody.status = 'draft';

	let controlUrl = env.blogSiteUrl + 'w3tcqa-json.php' +
		'?url=' + encodeURIComponent(apiUrl) +
		'&body=' + encodeURIComponent(JSON.stringify(apiBody));

	log.log(`opening ${controlUrl}`);
	await pPage.goto(controlUrl, {waitUntil: 'domcontentloaded'});
	await pPage.waitForSelector('#resultReady', {
		visible: true
	});
	let resultString = await pPage.$eval('#result', (e) => { return e.value });
	let result;
	try {
		result = JSON.parse(resultString);
	} catch (e) {
		log.log('result from postCreate request:');
		log.log(resultString);
		throw e;
	}

	let postId = result.id;
	console.log(postId);
	expect(postId > 0).true;
	log.log(`post created: ${postId}`);

	let apiUrl2 = postCreateApiUrl(data.type) + '/' + postId;
	let apiBody2 = {
		id: postId
	};

	if (!data.date_publish_offset_seconds) {
		apiBody2.status = 'publish';
	} else {
		apiBody2.status = 'future';

		let d = new Date();
		d.setSeconds(d.getSeconds() + data.date_publish_offset_seconds);
		apiBody2.date = d.toISOString().substr(0, 19);
		console.log(apiBody2);
	}

	let controlUrl2 = env.blogSiteUrl + 'w3tcqa-json.php' +
		'?url=' + encodeURIComponent(apiUrl2) +
		'&body=' + encodeURIComponent(JSON.stringify(apiBody2));
	log.log(`opening ${controlUrl2}`);
	await pPage.goto(controlUrl2, {waitUntil: 'domcontentloaded'});
	await pPage.waitForSelector('#resultReady', {
		visible: true
	});
	let resultString2 = await pPage.$eval('#result', (e) => { return e.value });
	let result2 = JSON.parse(resultString2);
	expect(result2.id > 0).true;
	expect(result2.link).not.empty;

	return {
		id: result2.id,
		url: result2.link
	};
}



exports.postUpdate = async function(pPage, data) {
	let postType = typeof data.post_type != 'undefined' ? data.post_type : 'post';
	log.log(`wp.postUpdate`);
	console.log(data);

	await pPage.goto(env.adminUrl + 'post.php?post=' + data.post_id + '&action=edit');
	log.log(new Date().toISOString() + ' Updating the ' + postType + ' ' + data.post_title);

	let r = await exec('cp ../../plugins/w3tcqa-json.php ' + env.wpPath + 'w3tcqa-json.php');

	let apiUrl = postCreateApiUrl(data.post_type) + '/' + data.post_id;
	let apiBody = {
		title: data.post_title
	};

	let controlUrl = env.blogSiteUrl + 'w3tcqa-json.php' +
		'?url=' + encodeURIComponent(apiUrl) +
		'&body=' + encodeURIComponent(JSON.stringify(apiBody));

	log.log(`opening ${controlUrl}`);
	await pPage.goto(controlUrl, {waitUntil: 'domcontentloaded'});
	await pPage.waitForSelector('#resultReady', {
		visible: true
	});
	let resultString = await pPage.$eval('#result', (e) => { return e.value });
	let result = JSON.parse(resultString);
	log.log(`post ${data.post_id} updated`);
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
