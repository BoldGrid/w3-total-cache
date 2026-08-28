/**
 * File: pub/js/cdn-admin-ui.js
 *
 * CDN admin UI rendering helpers. Server-supplied path/error/status
 * strings are text, never HTML.
 *
 * @package W3TC
 * @since   2.10.6
 */

(function (root) {
  /**
   * Build a popup log row from a server path/error pair.
   *
   * @param {string} path   Remote or local path.
   * @param {number} result 1 on success.
   * @param {string} error  Status or error text.
   * @return {HTMLElement}
   */
  function logEntry(path, result, error) {
    var doc = root.document;
    var row = doc.createElement("div");
    var label = doc.createElement("strong");

    row.className = "log-" + (1 == result ? "success" : "error");
    row.appendChild(
      doc.createTextNode((null == path ? "" : String(path)) + " "),
    );
    label.textContent = null == error ? "" : String(error);
    row.appendChild(label);

    return row;
  }

  /**
   * Assign a URL to an anchor using the attribute sink.
   *
   * Rejects javascript: and data: URLs so a server field cannot
   * become a scripted navigation.
   *
   * @param {HTMLAnchorElement} anchor Anchor element.
   * @param {string}            url    Candidate href.
   * @return {void}
   */
  function setUrl(anchor, url) {
    if (!anchor || typeof url !== "string") {
      return;
    }

    if (!/^(https?:\/\/|\/)/i.test(url) || /^(javascript|data):/i.test(url)) {
      return;
    }

    anchor.setAttribute("href", url);
  }

  root.w3tc_cdn_log_entry = logEntry;
  root.w3tc_cdn_set_url = setUrl;
})(typeof window !== "undefined" ? window : globalThis);
