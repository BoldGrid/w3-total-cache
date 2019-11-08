<?php
add_filter( 'pre_http_request', 'http_request_args_customs', 10, 3 );

function http_request_args_customs($true, $request, $url) {
	if (preg_match('/https?\:\/\/api\.wordpress\.org\/plugins\/update\-check\/1\.1\//', $url)) {
		return array(
			'headers' => array(),
	        'body' => '{"plugins":{"blah.php":{"id":1}}, "translations":{}, "no_update": {"a02-groups.php":{"id":1}}}',
	        'response' => array(
	            'code' => 200,
	            'message' => 'OK'
	        ),
	        'method' => 'POST',
	        'redirection' => '',
	        'reject_unsafe_urls' => false,
	        'stream' => false,
	        'httpversion' => '1.1',
	        'decompress' => false,
	        'sslcertificates' => '',
	        'user-agent' => '',
	        'timeout' => 120,
	        'filename' => ''
		);
	}

	if (preg_match('/https?\:\/\/api\.wordpress\.org\/core\/browse-happy\/.+?/', $url)) {
		return array(
			'headers' => array(),
			'body' => '{"platform":"Linux","name":"Chrome","version":"41.0.2272.76","update_url":"http:\/\/www.g.com","img_src":"http:\/\/s.org","img_src_ssl":"https:\/\/w.org","current_version":"18","upgrade":false,"insecure":false}',
			'response' => array(
				'code' => 200,
				'message' => 'OK'
			),
			'method' => 'POST',
			'redirection' => '',
			'reject_unsafe_urls' => false,
			'stream' => false,
			'httpversion' => '1.1',
			'decompress' => false,
			'sslcertificates' => '',
			'user-agent' => '',
			'timeout' => 120,
			'filename' => ''
		);
	}

	if (preg_match('/https?\:\/\/api\.wordpress\.org\/themes\/update-check\/.+?/', $url) ||
		preg_match('/https?\:\/\/api\.wordpress\.org\/core\/.+?/', $url)) {
		return array(
			'headers' => array(),
			'body' => '{"themes":{"blah.php":{"id":1}}, "translations":{}}',
			'response' => array(
				'code' => 200,
				'message' => 'OK'
			),
			'method' => 'POST',
			'redirection' => '',
			'httpversion' => '1.1',
			'decompress' => false,
			'user-agent' => '',
		);
    }

	if (preg_match('/http\:\/\/www\.google\.[a-z]+\/\?ie=utf-8&q=.+?/', $url)) {
		return array(
			'headers' => array(),
			'body' => '',
			'response' => array(
				'code' => 200,
				'message' => 'OK'
			),
			'redirection' => '',
			'user-agent' => ''
		);
    }
	if (preg_match('/fonts.googleapis.com/', $url)) {
	        return array(
				'headers' => array(),
				'body' => '@font-face {font-style: normal; replacement-of: fonts-googleapis}',
				'response' => array(
					'code' => 200,
					'message' => 'OK'
				),
				'redirection' => '',
				'user-agent' => ''
			);
	}

	if ($url == 'http://wordpress.org/news/feed/' || $url == 'http://wordpress.org/news/feed/' ||
	$url == 'https://wordpress.org/plugins/rss/browse/popular/' ||
	$url == 'http://wordpress.org/plugins/rss/browse/popular/') {
		return array(
			'headers' => array(
				'content-type' => 'application/rss+xml; charset=UTF-8'
			),
			'body' => '<?' . 'xml version="1.0" encoding="UTF-8"?>
			<rss><channel></channel></rss>',
			'response' => array(
					'code' => 200,
					'message' => 'OK'
				),
			'redirection' => '',
			'user-agent' => ''
		);
    }

	return false;
}