(function() {
	document.querySelectorAll('.w3tc_alwayscached_queue').forEach(function(i) {
		i.addEventListener('click', function(e) {
			let mode = e.target.dataset.mode;
			let elContainer = e.target.parentElement.querySelector('section');

			fetch(ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
            		'&w3tc_action=extension_alwayscached_queue&mode=' + mode)
				.then(function(response) {
					return response.text();
				}).then(function(data) {
					elContainer.innerHTML = data;
				});
		});
	});
}());
