// Initialize PartyTown with settings from the localized partytownConfig object
if (window.Partytown) {
    Partytown({
        debug: partytownConfig.debugMode,
        timeout: partytownConfig.timeoutSetting,
        workerConcurrency: partytownConfig.workerConcurrencyLimit,
    });
}

// Register the service worker if preloading resources is enabled
if ('serviceWorker' in navigator && partytownConfig.preloadResources) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/lib/partytown/partytown-sw.js')
            .then(function(registration) {
                console.log('Service Worker registered with scope:', registration.scope);
            })
            .catch(function(error) {
                console.error('Service Worker registration failed:', error);
            });
    });
}