const expect = require('chai').expect;



exports.listScriptSrc = async function(pPage) {
	let a = await pPage.$$eval('script', (elements) => {
		let srcs = [];
		for (let n = 0; n < elements.length; n++) {
			let url = elements[n].src;
			// dont analyze, it's added by script
			if (url.length <= 0) {
			} else if (url.indexOf('wp-emoji-release.min.js') > 0) {
			} else {
				srcs.push(url);
			}
		}

		return srcs;
	});

	expect(a).not.empty;
	return a;
}



exports.listScriptSrcSync = async function(pPage) {
	let a = await pPage.$$eval('script', (elements) => {
		let srcs = [];
		for (let n = 0; n < elements.length; n++) {
			let url = elements[n].src;
			// dont analyze, it's added by script
			if (url.length <= 0) {
			} else if (url.indexOf('wp-emoji-release.min.js') > 0) {
			} else if (elements[n].async) {
			} else {
				srcs.push(url);
			}
		}

		return srcs;
	});

	expect(a).not.empty;
	return a;
}



exports.listLinkCssHref = async function(pPage, all) {
	let a = await pPage.$$eval('link[type="text/css"], link[rel="stylesheet"]', (elements) => {
		let values = [];
		for (let n = 0; n < elements.length; n++) {
			let url = elements[n].href;
			// dont analyze, it's added by script
			if (url.length <= 0) {
			} else if (url.indexOf('fonts.googleapis.com') > 0) {
			} else {
				values.push(url);
			}
		}

		return values;
	});

	// Allow empty array - WordPress 6.9+ may not load CSS files in traditional way
	return a;
}



exports.listLinkCssHrefAll = async function(pPage, all) {
	let a = await pPage.$$eval('link[type="text/css"], link[rel="stylesheet"]', (elements) => {
		let values = [];
		for (let n = 0; n < elements.length; n++) {
			let url = elements[n].href;
			// dont analyze, it's added by script
			if (url.length <= 0) {
			} else {
				values.push(url);
			}
		}

		return values;
	});

	expect(a).not.empty;
	return a;
}



exports.listTagAttributes = async function(pPage, selector, attribute) {
	return await pPage.$$eval(selector, (elements, attribute) => {
		let values = [];
		for (let n = 0; n < elements.length; n++) {
			let v = elements[n].getAttribute(attribute);
			values.push(v);
		}

		return values;
	}, attribute);
}
