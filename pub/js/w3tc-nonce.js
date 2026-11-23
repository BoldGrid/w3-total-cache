/**
 * File: w3tc-nonce.js
 *
 * @since 2.10.0
 *
 * @package W3TC
 */

/**
 * Return a localized AJAX hub nonce for a sub-action.
 *
 * @since 2.10.0
 *
 * @param {string} action AJAX sub-action key.
 * @return {string}
 */
function w3tcGetAjaxNonce(action) {
  if ("undefined" !== typeof w3tc_ajax_nonces && w3tc_ajax_nonces[action]) {
    return w3tc_ajax_nonces[action];
  }
  return "";
}

/**
 * Return a localized admin dispatcher nonce for a handler key.
 *
 * @since 2.10.0
 *
 * @param {string} action Admin handler request key.
 * @return {string}
 */
function w3tcGetAdminNonce(action) {
  if ("undefined" !== typeof w3tc_admin_nonces && w3tc_admin_nonces[action]) {
    return w3tc_admin_nonces[action];
  }
  return "";
}

/**
 * Copy the submit button nonce into the enclosing form hidden field.
 *
 * @since 2.10.0
 *
 * @param {HTMLElement} input Submit button element.
 * @return {void}
 */
function w3tcSetAdminSubmitNonce(input) {
  var nonce = jQuery(input).attr("data-w3tc-nonce");
  var name = jQuery(input).attr("name");
  if (!nonce && name) {
    // Flush/save buttons carry data-w3tc-nonce; fall back to the localized map by handler name.
    nonce = w3tcGetAdminNonce(name);
  }
  if (nonce && input.form) {
    jQuery(input.form).find('input[name="_wpnonce"]').val(nonce);
  }
}

/**
 * Read the AJAX nonce for the w3tc_action field inside a lightbox form.
 *
 * Used when load_form() POSTs a dynamic sub-action taken from the form body.
 *
 * @since 2.10.0
 *
 * @param {string} formSelector jQuery selector for the form.
 * @return {string}
 */
function w3tcGetAjaxNonceForForm(formSelector) {
  var action = jQuery(formSelector).find('input[name="w3tc_action"]').val();
  if (!action) {
    return "";
  }
  return w3tcGetAjaxNonce(action);
}
