document.addEventListener('DOMContentLoaded', () => {
	// Check if partytownConfig is defined.
	if (typeof partytownConfig !== 'undefined') {
		console.log('Initializing Partytown with:', partytownConfig);

		// Initialize Partytown with settings from partytownConfig.
		if (window.Partytown) {
			partytown({
				lib: partytownConfig.lib,  // Use the configured lib path.
				debug: partytownConfig.debug,
				timeout: partytownConfig.timeout,
				workerConcurrency: partytownConfig.workerConcurrency,
			});

			// Debug logging if enabled.
			if (partytownConfig.debug) {
				console.log('PartyTown Debug Mode is enabled');
				console.log('Lib Path:', partytownConfig.lib);
				console.log('Timeout Setting:', partytownConfig.timeout);
				console.log('Worker Concurrency Limit:', partytownConfig.workerConcurrency);
			}
		} else {
			console.error('Partytown is not defined in the window.');
		}

		// Register the service worker.
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', function() {
				navigator.serviceWorker.register(partytownConfig.lib + 'partytown-sw.js')
					.then(registration => {
						console.log('Service Worker registered with scope:', registration.scope);
						return navigator.serviceWorker.ready;
					})
					.then(readyWorker => {
						console.log('Service Worker is ready and active:', readyWorker);
					})
					.catch(error => {
						console.error('Service Worker registration failed:', error);
					});
			});
		} else {
			console.warn('Service Worker not supported in this browser');
		}
	} else {
		console.error('partytownConfig is not defined.');
	}
});
