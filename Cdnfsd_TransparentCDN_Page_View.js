/**
 * File: Cdnfsd_TransparentCDN_Page_View.js
 *
 * @since 0.15.0
 */
jQuery(document).ready(function ($) {
  var box = document.getElementById("tcdn_test_status");
  var strings = transparent_configuration_strings;

  function setPlain(el, text) {
    el.textContent = text;
  }

  function setFailure(el) {
    var prefix = document.createTextNode(strings.test_failure_prefix);
    var link = document.createElement("a");
    var suffix = document.createTextNode(strings.test_failure_suffix);

    el.textContent = "";
    w3tc_cdn_set_url(link, strings.support_url);
    link.textContent = strings.test_failure_link;
    el.appendChild(prefix);
    el.appendChild(link);
    el.appendChild(suffix);
  }

  if (box) {
    setPlain(box, strings.test_string);

    $("#transparentcdn_test").on("click", function (e) {
      var url = "https://api.transparentcdn.com/v1/oauth2/access_token/",
        client_id =
          "client_id" +
          "=" +
          document.getElementById("cdnfsd_transparentcdn_clientid").value,
        client_secret =
          "client_secret" +
          "=" +
          document.getElementById("cdnfsd_transparentcdn_clientsecret").value,
        grant_type = "grant_type=client_credentials",
        params = grant_type + "&" + client_id + "&" + client_secret,
        req = new XMLHttpRequest();

      e.preventDefault();

      req.open("POST", url, true);
      req.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      req.onreadystatechange = function () {
        if (4 == req.readyState) {
          if (200 == req.status) {
            setPlain(box, strings.test_success);
            box.className = "w3tc-status w3tc-success";
          } else {
            setFailure(box);
            box.className = "w3tc-status w3tc-error";
          }
        }
      };
      req.send(params);
    });
  }
});
