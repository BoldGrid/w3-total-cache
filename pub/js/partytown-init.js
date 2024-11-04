// Check if partytownConfig is defined and has a valid `dst` path.
if (typeof partytownInit !== 'undefined' && partytownInit.dst) {
	console.log('Initializing PartyTown Service Worker with configuration:', partytownInit);

	const registerServiceWorker = async () => {
		if ('serviceWorker' in navigator) {
			try {
				const registration = await navigator.serviceWorker.register(`${partytownInit.dst}`);
				if (registration.installing) {
					console.log('PartyTown Service worker installing');
				} else if (registration.waiting) {
					console.log('PartyTown Service worker installed');
				} else if (registration.active) {
					console.log('PartyTown Service worker active');
				}
			} catch (error) {
				console.error(`PartyTown registration failed with ${error}`);
			}
		} else {
			console.warn('Service Workers are not supported in this browser.');
		}
	};

	registerServiceWorker();
} else {
	console.error('partytownInit is not defined or missing destination path.');
}