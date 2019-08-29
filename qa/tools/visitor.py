import random
import time
import urllib2

urls = [
	'http://wp.sandbox/',
	'http://wp.sandbox/sample-page',
	'http://wp.sandbox/feed',
	'http://wp.sandbox/some-404',
	'http://wp.sandbox/sample-page?blocking-query-string=1',
	'http://wp.sandbox/wp-includes/js/wp-embed.min.js?ver=5.2.1'
];


while True:
	url = random.choice(urls)
	print 'opening ' + url
	try:
		response = urllib2.urlopen(url)
		response.read()
	except:
		print 'failed'

	sleep = random.randrange(1, 5)
	print 'waiting ' + str(sleep)
	time.sleep(sleep)
