/**
 * W3TC stats widgets Google Charts driver.
 *
 * @file    Google Charts driver for W3TC dashboard stats widgets.
 * @author  W3TC.
 * @version 1.0
 * @since   2.7.0
 * @package W3TC
 */

jQuery(document).ready(function ($) {
  google.charts.load("current", { packages: ["corechart", "line"] });
  google.charts.setOnLoadCallback(load);

  // Interval to refresh stats widgets every 60 seconds.
  setInterval(function () {
    load();
  }, 60000);

  // Refresh charts on resize.
  jQuery(window).resize(function () {
    load();
  });

  // Load method for stat charts. Fires on document ready, window resize, and on 60 second interval.
  function load() {
    $.getJSON(
      ajaxurl +
        "?action=w3tc_ajax&_wpnonce=" +
        w3tcGetAjaxNonce("ustats_get") +
        "&w3tc_action=ustats_get",
      function (data) {
        if (!data.period.seconds) {
          $("#w3tc_page_cache").addClass("w3tc_none");
          $("#w3tc_object_cache").addClass("w3tc_none");
          $("#w3tc_database_cache").addClass("w3tc_none");
          return;
        }
        processed_data = preprocess_data(data);
        draw_charts(processed_data);
      },
    );
  }

  // Preprocesses statistics data for chart use.
  /**
   * @param {array} data Statistics data.
   * @returns {array} Statistics data in format required for Google charts.
   */
  function preprocess_data(data) {
    var processed_data = {
      page_cache: { data: [], color: "#6f9654" },
      object_cache: { data: [], color: "#e2431e" },
      database_cache: { data: [], color: "#43459d" },
    };
    // Disk: Enhanced hits bypass PHP; derive them from access log entries instead.
    var fileGeneric =
      "undefined" !== typeof w3tcWidgetStatsData &&
      w3tcWidgetStatsData.pgcacheFileGeneric;
    var validEntries = [];
    var history = data.history || [];

    for (var i = 0; i < history.length; i++) {
      var entry = history[i];
      if (!entry || !entry.timestamp_start) {
        continue;
      }

      var ts = parseInt(entry.timestamp_start, 10);
      if (isNaN(ts) || ts <= 0) {
        continue;
      }

      var d = new Date(ts * 1000);
      if (isNaN(d.getTime())) {
        continue;
      }

      var timestamp = dateFormat(d);
      if (!timestamp) {
        continue;
      }

      validEntries.push({
        entry: entry,
        timestamp: timestamp,
      });
    }

    // Show the most recent 20 slots (matches prior index-40 window when history is full).
    var startIndex = Math.max(0, validEntries.length - 20);
    for (var j = startIndex; j < validEntries.length; j++) {
      var row = validEntries[j];
      var entry = row.entry;
      var pagecache_hits;
      if (fileGeneric) {
        pagecache_hits =
          (entry.access_log ? Number(entry.access_log.dynamic_count) : 0) -
          Number(entry.php_requests || 0);
        if (!(pagecache_hits > 0)) {
          pagecache_hits = 0;
        }
      } else {
        pagecache_hits = Number(entry.php_requests_pagecache_hit || 0);
      }
      processed_data["page_cache"]["data"].push([row.timestamp, pagecache_hits]);
      processed_data["object_cache"]["data"].push([
        row.timestamp,
        Number(entry.objectcache_get_hits || 0),
      ]);
      processed_data["database_cache"]["data"].push([
        row.timestamp,
        Number(entry.dbcache_calls_hits || 0),
      ]);
    }
    return processed_data;
  }

  // Draws the stats charts.
  /**
   * @param {array} data - Preprocessed statistics data.
   */
  function draw_charts(data) {
    for (var key in data) {
      var rows = data[key]["data"];
      if (!rows.length) {
        continue;
      }

      var chart_data = google.visualization.arrayToDataTable(
        [["Time", "Hits"]].concat(rows),
      );
      var chart_options = {
        series: { 0: { color: data[key]["color"] } },
        legend: { position: "none" },
      };
      if (document.getElementById(key + "_chart")) {
        var chart = new google.charts.Line(
          document.getElementById(key + "_chart"),
        );
        chart.draw(chart_data, chart_options);
      }
    }
  }

  // Formats a timestamp into a human readable string.
  /**
   * @param {Object} d Timestamp.
   * @returns {string} Human readable date/time string.
   */
  function dateFormat(d) {
    return (
      ("0" + d.getUTCHours()).slice(-2) +
      ":" +
      ("0" + d.getUTCMinutes()).slice(-2)
    );
  }

  // Time since last refresh.
  var seconds_timer_id;

  // Interval for the stats refresh.
  /**
   * @param {Number} new_seconds_till_refresh Interval to trigger refresh.
   */
  function setRefresh(new_seconds_till_refresh) {
    clearTimeout(seconds_timer_id);
    var seconds_till_refresh = new_seconds_till_refresh;
    seconds_timer_id = setInterval(function () {
      seconds_till_refresh--;
      if (seconds_till_refresh <= 0) {
        clearInterval(seconds_timer_id); // Change clearTimeout to clearInterval here
        seconds_timer_id = null;
        load();
        setRefresh(new_seconds_till_refresh); // Restart the timer after calling load()
        return;
      }
    }, 1000);
  }
});
