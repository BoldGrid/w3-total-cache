/**
 * Shared Disk Enhanced page-cache helpers for QA tests.
 */

const log = require('mocha-logger');

const env = require('./environment');
const sys = require('./sys');

/**
 * Extra HTTP warm-up requests for Disk Enhanced (Apache rewrite path).
 *
 * @param {string} url Page URL to warm.
 * @returns {Promise<void>}
 */
async function warmCache(url) {
	if ('file_generic' !== env.cacheEngineLabel) {
		return;
	}

	const headers = {
		'User-Agent': sys.qaPageCacheUserAgent
	};

	for (let i = 0; i < 2; i++) {
		await sys.httpGet(url, {headers: headers, followRedirects: true});
	}
}

/**
 * Runs disk-enhanced-probe.php for a URL.
 *
 * @param {string} url Page URL to probe.
 * @returns {Promise<Object|null>} Parsed probe JSON or null on failure.
 */
async function probe(url) {
	try {
		const stdout = await sys.runQaEvalFile('disk-enhanced-probe.php', {
			W3TC_QA_PROBE_URL: url
		});
		log.log('disk enhanced probe: ' + stdout);
		return JSON.parse(stdout);
	} catch (e) {
		log.log('disk enhanced probe failed: ' + e.message);
		return null;
	}
}

/**
 * Polls until the plain Disk Enhanced HTML file exists (handles stale *_old-only state).
 *
 * @param {string} url        Page URL.
 * @param {number} timeoutMs  Maximum wait time in milliseconds.
 * @returns {Promise<Object|null>} Final probe result.
 */
async function waitForFile(url, timeoutMs) {
	const deadline = Date.now() + timeoutMs;
	let clearedStaleOld = false;

	while (Date.now() < deadline) {
		const result = await probe(url);
		if (result && result.variants && result.page_key && result.variants[result.page_key]) {
			return result;
		}

		if (
			result &&
			result.stale_old_only &&
			!clearedStaleOld &&
			result.old_age_sec !== null &&
			result.old_age_sec < 35
		) {
			log.log('stale *_old-only enhanced cache; clearing and re-warming');
			clearedStaleOld = true;
			await sys.clearDiskEnhancedHost();
			await warmCache(url);
			continue;
		}

		await new Promise((resolve) => setTimeout(resolve, 500));
	}

	return probe(url);
}

module.exports = {
	warmCache,
	probe,
	waitForFile
};
