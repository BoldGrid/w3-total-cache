/**
 * File: Extension_AiCrawler_Page.js
 *
 * JavaScript for the AI Crawler extension settings page.
 *
 * @since X.X.X
 *
 * @global w3tcData Object containing localized strings and settings.
 */

document.addEventListener("DOMContentLoaded", function () {
	const testTokenButton      = document.getElementById('w3tc-aicrawler-test-ctoken-button');
	const regenerateUrlButton  = document.getElementById('w3tc-aicrawler-regenerate-url-button');
	const regenerateUrlInput   = document.getElementById('w3tc-aicrawler-regenerate-url');
	const regenerateUrlMessage = document.getElementById('w3tc-aicrawler-regenerate-url-message');
	const regenerateAllButton  = document.getElementById('w3tc-aicrawler-regenerate-all-button');

	if (testTokenButton) {
		// Add a click event listener to the button.
		testTokenButton.addEventListener('click', function (event) {
			event.preventDefault(); // Prevent the default button behavior.

			// Display a loading message or spinner (optional).
			testTokenButton.textContent = w3tcData.lang.testing;

			// Example AJAX request to test the token.
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					_wpnonce: w3tcData.nonces.testToken, // Nonce for security.
					action: 'test_aicrawler_token', // WordPress AJAX action.
					token: document.getElementById('aicrawler___imh_central_token').value, // InMotion Central token to be tested.
				}),
			})
				.then((response) => response.json())
				.then((data) => {
					// Handle the response from the server.
					if (data.success) {
						alert(w3tcData.lang.tokenValid);
					} else {
						alert(w3tcData.lang.tokenInvalid);
					}
				})
				.catch((error) => {
					console.error(w3tcData.lang.error + ':', error);
					alert(w3tcData.lang.tokenError + '.');
				})
				.finally(() => {
					// Reset the button text.
					testTokenButton.textContent = w3tcData.lang.test;
				});
		});
	}

	// Handle the "Regenerate URL" button click.
	if (regenerateUrlButton && regenerateUrlInput && regenerateUrlMessage) {
		regenerateUrlButton.addEventListener('click', function (event) {
			event.preventDefault(); // Prevent default button behavior.

			const url = regenerateUrlInput.value.trim(); // Get the URL from the input field.

			regenerateUrlMessage.textContent = '';
			regenerateUrlMessage.classList.remove(
				'w3tc-aicrawler-message-success',
				'w3tc-aicrawler-message-error',
			);

			if (!url) {
				regenerateUrlMessage.textContent = w3tcData.lang.noUrl + '.';
				regenerateUrlMessage.classList.add('w3tc-aicrawler-message-error');
				return;
			}

			// Display a loading message or spinner (optional).
			regenerateUrlButton.textContent = w3tcData.lang.regenerating + '...';

			// AJAX request to regenerate the specified URL.
			fetch(ajaxurl, {
				method: 'POST',
				body: JSON.stringify({
					_wpnonce: w3tcData.nonces.regenerateUrl, // Nonce for security.
					action: 'regenerate_aicrawler_url', // WordPress AJAX action.
					url: url, // Pass the URL to the server.
				}),
			})
				.then((response) => response.json())
				.then((data) => {
					// Handle the response from the server.
					regenerateUrlMessage.textContent = '';
					regenerateUrlMessage.classList.remove(
						'w3tc-aicrawler-message-success',
						'w3tc-aicrawler-message-error',
					);

					if (data.success) {
						regenerateUrlMessage.textContent = data.data.message;
						regenerateUrlMessage.classList.add(
							'w3tc-aicrawler-message-success',
						);
					} else {
						regenerateUrlMessage.textContent =
						data.data && data.data.message
							? data.data.message
							: w3tcData.lang.regenerateUrlFailed + '.';
						regenerateUrlMessage.classList.add('w3tc-aicrawler-message-error');
					}
				})
				.catch((error) => {
					console.error('Error:', error);
					regenerateUrlMessage.textContent =
						w3tcData.lang.regenerateUrlError + '.';
					regenerateUrlMessage.classList.add('w3tc-aicrawler-message-error');
				})
				.finally(() => {
					// Reset the button text.
					regenerateUrlButton.textContent = w3tcData.lang.regenerate;
				});
		});
	}

	// Handle the "Regenerate All" button click.
	if (regenerateAllButton) {
		regenerateAllButton.addEventListener('click', function (event) {
			event.preventDefault(); // Prevent default button behavior.

			// Display a loading message or spinner (optional).
			regenerateAllButton.textContent = w3tcData.lang.regenerating + '...';

			// Example AJAX request to regenerate all URLs.
			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					_wpnonce: w3tcData.nonces.regenerateAll, // Nonce for security.
					action: 'regenerate_aicrawler_all', // WordPress AJAX action.
				}),
			})
				.then((response) => response.json())
				.then((data) => {
					// Handle the response from the server.
					if (data.success) {
						alert(w3tcData.lang.regenerateAll + '.');
					} else {
						alert(w3tcData.lang.regenerateAllFailed + '.');
					}
				})
				.catch((error) => {
					console.error('Error:', error);
					alert(w3tcData.lang.regenerateAllError + '.');
				})
				.finally(() => {
					// Reset the button text.
					regenerateAllButton.textContent = w3tcData.lang.regenerate;
				});
		});
	}
});
