/**
 * File: support.js
 *
 * Support page third-party form loader. The script from Wufoo is
 * injected only after page context, configuration, consent, and a
 * click all pass. Contact fields are not read from localization.
 *
 * @since 2.10.6
 *
 * @global w3tc_support_data
 */
(function (window, document) {
  "use strict";

  var ALLOWED_SCRIPT =
    "https://www.wufoo.com/scripts/embed/form.js";

  var W3tcSupport = {
    loaded: false,

    /**
     * True when every load gate passes.
     *
     * @param {Object} data Localized payload.
     * @param {boolean} consentChecked Consent checkbox.
     * @return {boolean}
     */
    gatesPass: function (data, consentChecked) {
      if (!data || typeof data !== "object") {
        return false;
      }
      if (data.page !== "w3tc_support") {
        return false;
      }
      if (!consentChecked) {
        return false;
      }
      if (typeof data.form_hash !== "string" || data.form_hash.length < 1) {
        return false;
      }
      if (data.form_script !== ALLOWED_SCRIPT) {
        return false;
      }
      if (this.loaded) {
        return false;
      }
      return true;
    },

    /**
     * Encode a Wufoo defaultValues pair. `encodeURIComponent` is
     * required so `&` and `=` in a value cannot append extra keys.
     *
     * @param {string} name Field name.
     * @param {string} value Field value.
     * @return {string}
     */
    queryPair: function (name, value) {
      return (
        encodeURIComponent(name || "") +
        "=" +
        encodeURIComponent(value || "")
      );
    },

    /**
     * Wufoo defaultValues without contact fields.
     *
     * @param {Object} data Localized payload.
     * @return {string}
     */
    defaultValues: function (data) {
      var values =
        this.queryPair("field221", data.postprocess || "") +
        "&" +
        this.queryPair("field8", data.home_url || "");

      if (data.field_name && data.field_name.length > 0) {
        values +=
          "&" + this.queryPair(data.field_name, data.field_value || "");
      }

      return values;
    },

    /**
     * Fallback node used when the third-party form cannot load.
     *
     * @return {HTMLElement|null}
     */
    fallbackNode: function () {
      return document.getElementById("w3tc-support-fallback");
    },

    /**
     * Show the fallback copy when the third-party form cannot load.
     *
     * @return {void}
     */
    showFallback: function () {
      var fallback = this.fallbackNode();
      if (fallback) {
        fallback.hidden = false;
      }
    },

    /**
     * Hide the fallback copy when a load or retry starts, and after a
     * successful embed, so a prior failure is not left on screen.
     *
     * @return {void}
     */
    hideFallback: function () {
      var fallback = this.fallbackNode();
      if (fallback) {
        fallback.hidden = true;
      }
    },

    /**
     * Unlock retry after a failed embed.
     *
     * @return {void}
     */
    failLoad: function () {
      this.loaded = false;
      this.showFallback();
    },

    /**
     * Wufoo requires the mount node id `wufoo-{formHash}`.
     *
     * @param {string} formHash Sanitized form hash.
     * @return {void}
     */
    syncMountId: function (formHash) {
      var mount =
        document.getElementById("w3tc-support-form-mount") ||
        document.getElementById("wufoo-" + formHash);
      if (!mount || typeof formHash !== "string" || formHash.length < 1) {
        return;
      }
      mount.id = "wufoo-" + formHash;
    },

    /**
     * Inject and initialize the third-party form.
     *
     * @param {Object} data Localized payload.
     * @param {boolean} consentChecked Consent checkbox.
     * @return {boolean} Whether injection started.
     */
    loadForm: function (data, consentChecked) {
      if (!this.gatesPass(data, consentChecked)) {
        return false;
      }

      this.hideFallback();
      this.loaded = true;
      this.syncMountId(data.form_hash);

      var options = {
        userName: "w3edge",
        formHash: data.form_hash,
        autoResize: true,
        height: "1145",
        async: true,
        host: "wufoo.com",
        header: "show",
        defaultValues: this.defaultValues(data),
        ssl: true,
      };

      var script = document.createElement("script");
      script.src = ALLOWED_SCRIPT;
      script.async = true;
      script.onerror = function () {
        W3tcSupport.failLoad();
      };
      script.onload = script.onreadystatechange = function () {
        var rs = this.readyState;
        if (rs && rs !== "complete" && rs !== "loaded") {
          return;
        }
        try {
          var form = new window.WufooForm();
          form.initialize(options);
          form.display();
          W3tcSupport.hideFallback();
        } catch (e) {
          W3tcSupport.failLoad();
        }
      };

      var first = document.getElementsByTagName("script")[0];
      if (first && first.parentNode) {
        first.parentNode.insertBefore(script, first);
      } else {
        document.head.appendChild(script);
      }

      return true;
    },

    /**
     * Bind consent + click. Does not load the form on parse.
     *
     * @return {void}
     */
    bind: function () {
      var consent = document.getElementById("w3tc-support-consent");
      var button = document.getElementById("w3tc-support-load");
      if (!consent || !button) {
        return;
      }

      var syncButton = function () {
        button.disabled = !consent.checked;
      };
      consent.addEventListener("change", syncButton);
      syncButton();

      button.addEventListener("click", function () {
        W3tcSupport.loadForm(
          window.w3tc_support_data,
          !!consent.checked,
        );
      });
    },
  };

  window.W3tcSupport = W3tcSupport;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      W3tcSupport.bind();
    });
  } else {
    W3tcSupport.bind();
  }
})(window, document);
