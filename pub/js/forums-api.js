/**
 * File: forums-api.js
 *
 * Client parser for the Forums help-topic AJAX contract.
 *
 * @since 2.10.6
 */
(function (root) {
  "use strict";

  function isHttpUrl(url) {
    return typeof url === "string" && /^https?:\/\//i.test(url.trim());
  }

  function sanitizeTopic(topic) {
    if (!topic || typeof topic !== "object") {
      return null;
    }
    var link = typeof topic.link === "string" ? topic.link.trim() : "";
    if (!isHttpUrl(link)) {
      return null;
    }
    var title = typeof topic.title === "string" ? topic.title : "";
    return { title: title, link: link };
  }

  function listFromDecoded(decoded) {
    if (Array.isArray(decoded)) {
      return decoded;
    }
    if (decoded && Array.isArray(decoded.topics)) {
      return decoded.topics;
    }
    if (decoded && Array.isArray(decoded.data)) {
      return decoded.data;
    }
    if (decoded && (decoded.title || decoded.link)) {
      return [decoded];
    }
    return null;
  }

  /**
   * Normalize an AJAX success payload to `{ ok, topics }`.
   *
   * Accepts the current `{ topics }` contract plus legacy envelopes
   * (`body` JSON string, top-level array, WP_Error-shaped `errors`).
   * Transport headers and raw error strings are not returned.
   *
   * @param {*} data jQuery AJAX success data.
   * @return {{ok: boolean, topics: Array<{title: string, link: string}>}}
   */
  function topicsFromResponse(data) {
    if (data == null) {
      return { ok: false, topics: [] };
    }

    if (Array.isArray(data)) {
      return {
        ok: true,
        topics: data.map(sanitizeTopic).filter(Boolean),
      };
    }

    if (typeof data !== "object") {
      return { ok: false, topics: [] };
    }

    if (
      data.error ||
      data.success === false ||
      (data.errors && data.errors.http_request_failed)
    ) {
      return { ok: false, topics: [] };
    }

    var list = null;
    if (Array.isArray(data.topics)) {
      list = data.topics;
    } else if (
      data.success === true &&
      data.data &&
      Array.isArray(data.data.topics)
    ) {
      list = data.data.topics;
    } else if (typeof data.body === "string") {
      var trimmed = data.body.replace(/^\s+|\s+$/g, "");
      if (trimmed === "") {
        return { ok: true, topics: [] };
      }
      try {
        list = listFromDecoded(JSON.parse(trimmed));
      } catch (e) {
        return { ok: false, topics: [] };
      }
      if (list == null) {
        return { ok: false, topics: [] };
      }
    } else if (data.data) {
      list = listFromDecoded(data.data);
    }

    if (list == null) {
      return { ok: false, topics: [] };
    }

    return { ok: true, topics: list.map(sanitizeTopic).filter(Boolean) };
  }

  root.W3tcForumsApi = {
    topicsFromResponse: topicsFromResponse,
    sanitizeTopic: sanitizeTopic,
  };
})(typeof window !== "undefined" ? window : this);
