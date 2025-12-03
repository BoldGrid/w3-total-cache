=== W3 Total Cache ===
Contributors: boldgrid, fredericktownes, maxicusc, gidomanders, bwmarkle, harryjackson1221, joemoto, vmarko, jacobd91, avonville1, jamesros161, elanasparkle, abrender
Tags: CDN, pagespeed, caching, performance, optimize
Requires at least: 5.3
Tested up to: 6.9
Stable tag: 2.8.15
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Search Engine (SEO) &amp; Performance Optimization (WPO) via caching. Integrated caching: CDN, Page, Minify, Object, Fragment, Database support.

== Description ==

W3 Total Cache (W3TC) improves the SEO, Core Web Vitals and overall user experience of your site by increasing website performance and reducing load times by leveraging features like content delivery network (CDN) integration and the latest best practices.

W3TC is the **only** web host agnostic Web Performance Optimization (WPO) framework for WordPress trusted by millions of publishers, web developers, and web hosts worldwide for more than a decade. It is the total performance solution for optimizing WordPress Websites.

*BENEFITS*

* Improvements in search engine result page rankings, especially for mobile-friendly websites and sites that use SSL
* At least 10x improvement in overall site performance (Grade A in [WebPagetest](https://www.webpagetest.org/) or significant [Google PageSpeed](http://code.google.com/speed/page-speed/) improvements) **when fully configured**
* Improved conversion rates and "[site performance](http://googlewebmastercentral.blogspot.com/2009/12/your-sites-performance-in-webmaster.html)" which [affect your site's rank](http://googlewebmastercentral.blogspot.com/2010/04/using-site-speed-in-web-search-ranking.html) on Google.com
* "Instant" repeat page views: browser caching
* Optimized progressive render: pages start rendering quickly and can be interacted with more quickly
* Reduced page load time: increased visitor time on site; visitors view more pages
* Improved web server performance; sustain high traffic periods
* Up to 80% bandwidth savings when you minify HTML, minify CSS and minify JS files.

*KEY FEATURES*

* Compatible with shared hosting, virtual private / dedicated servers and dedicated servers / clusters
* Transparent content delivery network (CDN) management with Media Library, theme files and WordPress itself
* Mobile support: respective caching of pages by referrer or groups of user agents including theme switching for groups of referrers or user agents
* Accelerated Mobile Pages (AMP) support
* Secure Socket Layer (SSL/TLS) support
* Caching of (minified and compressed) pages and posts in memory or on disk or on (FSD) CDN (by user agent group)
* Caching of (minified and compressed) CSS and JavaScript in memory, on disk or on CDN
* Caching of feeds (site, categories, tags, comments, search results) in memory or on disk or on CDN
* Caching of search results pages (i.e. URIs with query string variables) in memory or on disk
* Caching of database objects in memory or on disk
* Caching of objects in memory or on disk
* Caching of fragments in memory or on disk
* Caching methods include local Disk, Redis, Memcached, APC, APCu, eAccelerator, XCache, and WinCache
* Minify CSS, Minify JavaScript and Minify HTML with granular control
* Minification of posts and pages and RSS feeds
* Minification of inline, embedded or 3rd party JavaScript with automated updates to assets
* Minification of inline, embedded or 3rd party CSS with automated updates to assets
* Defer non critical CSS and Javascript for rendering pages faster than ever before
* Defer offscreen images using Lazy Load to improve the user experience
* Browser caching using cache-control, future expire headers and entity tags (ETag) with "cache-busting"
* JavaScript grouping by template (home page, post page etc) with embed location control
* Non-blocking JavaScript embedding
* Import post attachments directly into the Media Library (and CDN)
* Leverage our multiple CDN integrations to optimize images
* WP-CLI support for cache purging, query string updating and more
* Various security features to help ensure website safety
* Caching statistics for performance insights of any enabled feature
* Extension framework for customization or extensibility for Cloudflare, WPML and much more
* Reverse proxy integration via Nginx or Varnish
* WebP Converter extension provides WebP image format conversion from common image formats (on upload and on demand)

<h3>W3 Total Cache Pro Features</h3>

With over a million active installs, W3 Total Cache is the most comprehensive WordPress caching plugin available and has robust premium features that help deliver an exceptional user experience.

* Full Site Delivery: Serve your entire site from a Content Delivery Network (CDN), ensuring faster load times worldwide.
* Fragment Cache: Optimize the caching of dynamic content while still improving performance.
* REST API Caching: Speed up your headless WordPress site by caching REST API calls.
* Eliminate Render-Blocking CSS: Ensure your CSS doesn't hold up page loading, providing faster initial paint.
* Delay Scripts: Improve performance by delaying the loading of non-essential scripts until they are needed.
* Preload Requests: Boost page performance by preloading critical resources before they're requested.
* Remove CSS/JS: Clean up unnecessary CSS and JavaScript files that slow down your pages.
* Lazy Load Google Maps: Load Google Maps only when it's visible, reducing unnecessary requests.
* WPML Extension: Optimize performance on multilingual sites powered by WPML.
* Caching Statistics: Get detailed insights on cache usage and performance improvements.
* Purge Logs: Keep your site clean by automatically purging unnecessary cache logs.

<h3>30-Day Money-Back Guarantee</h3>

Try [W3 Total Cache Pro](https://www.boldgrid.com/w3-total-cache/) risk-free with our 30-day money-back guarantee. If you're not satisfied, we will refund your purchase.

<h3>PAGESPEED SCORE IMPROVEMENTS</h3>

To help you understand the impact of individual features on your website's performance, we've tested each feature separately to see its effect on Google PageSpeed scores. While optimal results come from configuring several different caching tools together, the following individual features also show significant improvements on their own:

<h4>Remove Unused CSS/JS</h4>

This feature removes CSS and JavaScript files that are not needed for the current page, reducing the load time.

* Added over 27 points to the Google PageSpeed score (Before: 57.2 / After: 86.7)
* Reduced the Potential Savings From Unused JavaScript from 127.5 KiB to 84 KiB
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/remove-scripts/)

<h4>Full Site Delivery</h4>

Full Site Delivery optimizes the delivery of your entire site, enhancing the server response time.

* Added a 99% performance enhancement  to the Average Server Response Time (Before: 3413 ms / After: 34 ms)
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/fsd-full-site-delivery/)

<h4>Eliminate Render Blocking CSS</h4>

This feature eliminates CSS that blocks the rendering of your page, speeding up the initial load time.

* Added over 17 points to the Google PageSpeed score (Before: 53.75 / After: 71)
* Reduced the Potential Savings From Render-Blocking Resources by over 94% (Before: 2432.5 ms / After: 125 ms)
* Improved the Largest Contentful Paint time by over 56% (Before: 7s / After: 3.04s)
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/eliminate-render-blocking-css/)

<h4>Delay Scripts</h4>

Delay Scripts postpones the loading of certain scripts until they are needed, reducing initial load times.

* Added 14 points to the Google PageSpeed Performance score (Before: 54.25 / After: 68.5)
* Reduced the Time Third-Party Code Blocked The Main Thread For by 62% (Before: 825 ms / After: 197.5 ms)
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/delay-scripts-test/)

<h4>Rest API Caching</h4>

This feature caches API responses, reducing server load and speeding up API interactions.

* Reduced the Average Server Load by 40% (Before: 0.62 / After: 0.37)
* Sped up API Responses by 84.5% (Before: 968ms / After: 150ms)
* Reduced the Average Server Load by 24% under during a major traffic spike (Before: 34.55 / After: 26.19)
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/rest-api-testing/)

<h4>WebP Images</h4>

Converts images to the WebP format, which is more efficient and faster to load.

* Added over 9 points to the Google PageSpeed score (Before: 84.67 / After: 93.83)
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/webp/)

<h4>Lazy Load Google Maps</h4>

Delays the loading of Google Maps until the user interacts with them, reducing initial load time.

* Added 10 points to the Google PageSpeed score (Before: 66 / After: 76)
* Reduced the Total Blocking Time Performance score by 72% (Before: 287.5 ms / After: 80 ms)
* [View the test results](https://www.boldgrid.com/support/w3-total-cache/pagespeed-tests/lazy-load-maps/)

Speed up your site tremendously, improve core web vitals and the overall user experience for your visitors without having to change your WordPress host, theme, plugins or your content production workflow.

== Frequently Asked Questions ==

= Why does speed matter? =

Search engines like Google, measure and factor in the speed of web sites in their ranking algorithm. When they recommend a site they want to make sure users find what they're looking for quickly. So in effect you and Google should have the same objective.

Speed is among the most significant success factors web sites face. In fact, your site's speed directly affects your income (revenue) &mdash; it's a fact. Some high traffic sites conducted research and uncovered the following:

* Google.com: **+500 ms** (speed decrease) -> **-20% traffic loss** [[1](http://home.blarg.net/~glinden/StanfordDataMining.2006-11-29.ppt)]
* Yahoo.com: **+400 ms** (speed decrease) -> **-5-9% full-page traffic loss** (visitor left before the page finished loading) [[2](http://www.slideshare.net/stoyan/yslow-20-presentation)]
* Amazon.com: **+100 ms** (speed decrease) -> **-1% sales loss** [[1](http://home.blarg.net/~glinden/StanfordDataMining.2006-11-29.ppt)]

A thousandth of a second is not a long time, yet the impact is quite significant. Even if you're not a large company (or just hope to become one), a loss is still a loss. W3 Total Cache is your solution for faster websites, happier visitors and better results.

Many of the other consequences of poor performance were discovered more than a decade ago:

* Lower perceived credibility (Fogg et al. 2001)
* Lower perceived quality (Bouch, Kuchinsky, and Bhatti 2000)
* Increased user frustration (Ceaparu et al. 2004)
* Increased blood pressure (Scheirer et al. 2002)
* Reduced flow rates (Novak, Hoffman, and Yung 200)
* Reduced conversion rates (Akamai 2007)
* Increased exit rates (Nielsen 2000)
* Are perceived as less interesting (Ramsay, Barbesi, and Preece 1998)
* Are perceived as less attractive (Skadberg and Kimmel 2004)

There are a number of [resources](http://www.websiteoptimization.com/speed/tweak/psychology-web-performance/) that have been documenting the role of performance in success on the web, W3 Total Cache exists to give you a framework to tune your application or site without having to do years of research.

= Why is W3 Total Cache better than other caching solutions? =

**It's a complete framework.** Most cache plugins available do a great job at achieving a couple of performance gains. Total Cache is different because it remedies numerous performance reducing aspects of any web site. It goes farther than the basics, beyond merely reducing CPU usage (load) or bandwidth consumption for HTML pages. Equally important, the plugin requires no theme modifications, modifications to your .htaccess (mod_rewrite rules) or programming compromises to get started. Most importantly, it's the only plugin designed to optimize all practical hosting environments small or large. The options are many and setup is easy.

= I've never heard of any of this stuff; my site is fine, no one complains about the speed. Why should I install this? =

Rarely do readers take the time to complain. They typically just stop browsing earlier than you'd prefer and may not return altogether. This is the only plugin specifically designed to make sure that all aspects of your site are as fast as possible. Google is placing more emphasis on the [speed of a site as a factor in rankings](http://searchengineland.com/site-speed-googles-next-ranking-factor-29793); this plugin helps with that too.

It's in every web site owner's best interest is to make sure that the performance of your site is not hindering its success.

= Which WordPress versions are supported? =

To use all features in the suite, a minimum of version WordPress 5.3 with PHP 7.2.5 is required. Earlier versions will benefit from our Media Library Importer to get them back on the upgrade path and into a CDN of their choosing.

= Why doesn't minify work for me? =

Great question. W3 Total Cache uses several open source tools to attempt to combine and optimize CSS, JavaScript and HTML etc. Unfortunately some trial and error is required on the part of developers is required to make sure that their code can be successfully minified with the various libraries W3 Total Cache supports. Even still, if developers do test their code thoroughly, they cannot be sure that interoperability with other code your site may have. This fault does not lie with any single party here, because there are thousands of plugins and theme combinations that a given site can have, there are millions of possible combinations of CSS, JavaScript etc.

A good rule of thumb is to try auto mode, work with a developer to identify the code that is not compatible and start with combine only mode (the safest optimization) and increase the optimization to the point just before functionality (JavaScript) or user interface / layout (CSS) breaks in your site.

We're always working to make this more simple and straight forward in future releases, but this is not an undertaking we can realize on our own. When you find a plugin, theme or file that is not compatible with minification reach out to the developer and ask them either to provide a minified version with their distribution or otherwise make sure their code is minification-friendly.

= What about comments? Does the plugin slow down the rate at which comments appear? =

On the contrary, as with any other action a user can perform on a site, faster performance will encourage more of it. The cache is so quickly rebuilt in memory that it's no trouble to show visitors the most current version of a post that's experiencing Digg, Slashdot, Drudge Report, Yahoo Buzz or Twitter effect.

= Will the plugin interfere with other plugins or widgets? =

No, on the contrary if you use the minify settings you will improve their performance by several times.

= Does this plugin work with WordPress in network mode? =

Indeed it does.

= Does this plugin work with BuddyPress (bbPress)? =

Yes.

= Will this plugin speed up WP Admin? =

Yes, indirectly - if you have a lot of bloggers working with you, you will find that it feels like you have a server dedicated only to WP Admin once this plugin is enabled; the result, increased productivity.

= Which web servers do you support? =

We are aware of no incompatibilities with [apache](http://httpd.apache.org/) 1.3+, [nginx](https://www.nginx.com/solutions/web-server/) 0.7+, [IIS](http://www.iis.net/) 5+ or [litespeed](https://www.litespeedtech.com/products/litespeed-web-server/overview) 4.0.2+. If there's a web server you feel we should be actively testing (e.g. [lighttpd](https://www.lighttpd.net/)), we're [interested in hearing](https://www.w3-edge.com/contact/).

= Is this plugin server cluster and load balancer friendly? =

Yes, built from the ground up with scale and current hosting paradigms in mind.

= What is the purpose of the "Media Library Import" tool and how do I use it? =

The media library import tool is for old or "messy" WordPress installations that have attachments (images etc in posts or pages) scattered about the web server or "hot linked" to 3rd party sites instead of properly using the media library.

The tool will scan your posts and pages for the cases above and copy them to your media library, update your posts to use the link addresses and produce a .htaccess file containing the list of of permanent redirects, so search engines can find the files in their new location.

You should backup your database before performing this operation.

= How do I find the JS and CSS to optimize (minify) them with this plugin? =

Use the "Help" button available on the Minify settings tab. Once open, the tool will look for and populate the CSS and JS files used in each template of the site for the active theme. To then add a file to the minify settings, click the checkbox next to that file. The embed location of JS files can also be specified to improve page render performance. Minify settings for all installed themes can be managed from the tool as well by selecting the theme from the drop down menu. Once done configuring minify settings, click the apply and close button, then save settings in the Minify settings tab.

= I don't understand what a CDN has to do with caching, that's completely different, no? =

Technically no, a CDN is a high performance cache that stores static assets (your theme files, media library etc) in various locations throughout the world in order to provide low latency access to them by readers in those regions. Use Total Cache to accelerate your site by putting your content closer to your users with our many CDN integrations including Cloudflare, StackPath, AWS and more.

= How do I use an Origin Pull (Mirror) CDN? =
Login to your CDN providers control panel or account management area. Following any set up steps they provide, create a new "pull zone" or "bucket" for your site's domain name. If there's a set up wizard or any troubleshooting tips your provider offers, be sure to review them. In the CDN tab of the plugin, enter the hostname your CDN provider provided in the "replace site's hostname with" field. You should always do a quick check by opening a test file from the CDN hostname, e.g. http://cdn.domain.com/favicon.ico. Troubleshoot with your CDN provider until this test is successful.

Now go to the General tab and click the checkbox and save the settings to enable CDN functionality and empty the cache for the changes to take effect.

= How do I configure Amazon Simple Storage Service (Amazon S3) or Amazon CloudFront as my CDN? =

First [create an S3 account](http://aws.amazon.com/) (unless using origin pull); it may take several hours for your account credentials to be functional. Next, you need to obtain your "Access key ID" and "Secret key" from the "Access Credentials" section of the "[Security Credentials](http://aws-portal.amazon.com/gp/aws/developer/account/index.html?action=access-key)" page of "My Account." Make sure the status is "active." Next, make sure that "Amazon Simple Storage Service (Amazon S3)" is the selected "CDN type" on the "General Settings" tab, then save the changes. Now on the "Content Delivery Network Settings" tab enter your "Access key," "Secret key" and enter a name (avoid special characters and spaces) for your bucket in the "Create a bucket" field by clicking the button of the same name. If using an existing bucket simply specify the bucket name in the "Bucket" field. Click the "Test S3 Upload" button and make sure that the test is successful, if not check your settings and try again. Save your settings.

Unless you wish to use CloudFront, you're almost done, skip to the next paragraph if you're using CloudFront. Go to the "General Settings" tab and click the "Enable" checkbox and save the settings to enable CDN functionality. Empty the cache for the changes to take effect. If preview mode is active you will need to "deploy" your changes for them to take effect.

To use CloudFront, perform all of the steps above, except select the "Amazon CloudFront" "CDN type" in the "Content Delivery Network" section of the "General Settings" tab. When creating a new bucket, the distribution ID will automatically be populated. Otherwise, proceed to the [AWS Management Console](https://console.aws.amazon.com/cloudfront/) and create a new distribution: select the S3 Bucket you created earlier as the "Origin," enter a [CNAME](http://docs.amazonwebservices.com/AmazonCloudFront/latest/DeveloperGuide/index.html?CNAMEs.html) if you wish to add one or more to your DNS Zone. Make sure that "Distribution Status" is enabled and "State" is deployed. Now on "Content Delivery Network" tab of the plugin, copy the subdomain found in the AWS Management Console and enter the CNAME used for the distribution in the "CNAME" field.

You may optionally, specify up to 10 hostnames to use rather than the default hostname, doing so will improve the render performance of your site's pages. Additional hostnames should also be specified in the settings for the distribution you're using in the AWS Management Console.

Now go to the General tab and click the "Enable" checkbox and save the settings to enable CDN functionality and empty the cache for the changes to take effect. If preview mode is active you will need to "deploy" your changes for them to take effect.

= How do I configure Rackspace Cloud Files as my CDN? =

First [create an account](http://www.rackspacecloud.com/cloud_hosting_products/files). Next, in the "Content Delivery Network" section of the "General Settings" tab, select Rackspace Cloud Files as the "CDN Type." Now, in the "Configuration" section of the "Content Delivery Network" tab, enter the "Username" and "API key" associated with your account (found in the API Access section of the [rackspace cloud control panel](https://manage.rackspacecloud.com/APIAccess.do)) in the respective fields. Next enter a name for the container to use (avoid special characters and spaces). If the operation is successful, the container's ID will automatically appear in the "Replace site's hostname with" field. You may optionally, specify the container name and container ID of an [existing container](https://manage.rackspacecloud.com/CloudFiles.do) if you wish. Click the "Test Cloud Files Upload" button and make sure that the test is successful, if not check your settings and try again. Save your settings. You're now ready to export your media library, theme and any other files to the CDN.

You may optionally, specify up to 10 hostnames to use rather than the default hostname, doing so will improve the render performance of your site's pages.

Now go to the General tab and click the "Enable" checkbox and save the settings to enable CDN functionality and empty the cache for the changes to take effect.  If preview mode is active you will need to "deploy" your changes for them to take effect.

= What is the purpose of the "modify attachment URLs" button? =

If the domain name of your site has changed, this tool is useful in updating your posts and pages to use the current addresses. For example, if your site used to be www.domain.com, and you decided to change it to domain.com, the result would either be many "broken" images or many unnecessary redirects (which slow down the visitor's browsing experience). You can use this tool to correct this and similar cases. Correcting the URLs of your images also allows the plugin to do a better job of determining which images are actually hosted with the CDN.

As always, it never hurts to back up your database first.

= Is this plugin comptatible with TDO Mini Forms? =

Captcha and recaptcha will work fine, however you will need to prevent any pages with forms from being cached. Add the page's URI to the "Never cache the following pages" box on the Page Cache Settings tab.

= Is this plugin comptatible with GD Star Rating? =

Yes. Follow these steps:

1. Enable dynamic loading of ratings by checking GD Star Rating -> Settings -> Features "Cache support option"
1. If Database cache enabled in W3 Total Cache add `wp_gdsr` to "Ignored query stems" field in the Database Cache settings tab, otherwise ratings will not updated after voting
1. Empty all caches

= I see garbage characters instead of the normal web site, what's going on here? =

If a theme or it's files use the call `php_flush()` or function `flush()` that will interfere with the plugins normal operation; making the plugin send cached files before essential operations have finished. The `flush()` call is no longer necessary and should be removed.

= How do I cache only the home page? =

Add `/.+` to page cache "Never cache the following pages" option on the page cache settings tab.

= I'm getting blank pages or 500 error codes when trying to upgrade on WordPress in network mode =

First, make sure the plugin is not active (disabled) network-wide. Then make sure it's deactivated network-wide. Now you should be able to successful upgrade without breaking your site.

= A notification about file owner appears along with an FTP form, how can I resolve this? =

The plugin uses WordPress FileSystem functionality to write to files. It checks if the file owner, file owner group of created files match process owner. If this is not the case it cannot write or modify files.

Typically, you should tell your web host about the permission issue and they should be able to resolve it.

You can however try adding <em>define('FS_METHOD', 'direct');</em> to wp-config.php to circumvent the file and folder checks.

= Does the WebP Converter extension use a lot of resources to convert images to WebP? =

No.  The WebP Converter extension converts common image file formats to the modern WebP format using our API services.  The conversions occur on our API service, so that resource usage does not impact your website server.

= Is image data retained by the Total Cache WebP Converter API? =

Image data received by our API is destroyed after a converted image is generated.  The converted iamges are destroyed once picked-up/downloaded to your website by the Total Cache plugin.

= This is too good to be true, how can I test the results? =

You will be able to see the results instantly on each page load, but for tangible metrics, you should consider using the following tools:

* [Google PageSpeed](https://developers.google.com/speed/pagespeed/)
* [Google Search Console Core Web Vitals Report](https://search.google.com/search-console/core-web-vitals/)
* [WebPagetest](https://www.webpagetest.org/test)
* [Pingdom](https://tools.pingdom.com/)
* [GTmetrix](https://gtmetrix.com/)

= I don't have time to deal with this, but I know I need it. Will you help me? =

Yes! Please [reach out to us](https://www.w3-edge.com/contact/) and we'll get you acclimated so you can "set it and forget it."

Install the plugin to read the full FAQ on the plugins FAQ tab.
= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the W3 Total Cache plugin through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/d5047161-3e39-4462-9250-1b04385021dd). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Installation ==

1. Deactivate and uninstall any other caching plugin you may be using. Pay special attention if you have customized the rewrite rules for fancy permalinks, have previously installed a caching plugin or have any browser caching rules as W3TC will automate management of all best practices. Also make sure wp-content/ and wp-content/uploads/ (temporarily) have 777 permissions before proceeding, e.g. in the terminal: `# chmod 777 /var/www/vhosts/domain.com/httpdocs/wp-content/` using your web hosting control panel or your FTP / SSH account.
1. Login as an administrator to your WordPress Admin account. Using the "Add New" menu option under the "Plugins" section of the navigation, you can either search for: w3 total cache or if you've downloaded the plugin already, click the "Upload" link, find the .zip file you download and then click "Install Now". Or you can unzip and FTP upload the plugin to your plugins directory (wp-content/plugins/). In either case, when done wp-content/plugins/w3-total-cache/ should exist.
1. Locate and activate the plugin on the "Plugins" page. Page caching will **automatically be running** in basic mode. Set the permissions of wp-content and wp-content/uploads back to 755, e.g. in the terminal: `# chmod 755 /var/www/vhosts/domain.com/httpdocs/wp-content/`.
1. Now click the "Settings" link to proceed to the "General Settings" tab; in most cases, "disk enhanced" mode for page cache is a "good" starting point.
1. The "Compatibility mode" option found in the advanced section of the "Page Cache Settings" tab will enable functionality that optimizes the interoperablity of caching with WordPress, is disabled by default, but highly recommended. Years of testing in hundreds of thousands of installations have helped us learn how to make caching behave well with WordPress. The tradeoff is that disk enhanced page cache performance under load tests will be decreased by ~20% at scale.
1. *Recommended:* On the "Minify Settings" tab, all of the recommended settings are preset. If auto mode causes issues with your web site's layout, switch to manual mode and use the help button to simplify discovery of your CSS and JS files and groups. Pay close attention to the method and location of your JS group embeddings. See the plugin's FAQ for more information on usage.
1. *Recommended:* On the "Browser Cache" tab, HTTP compression is enabled by default. Make sure to enable other options to suit your goals.
1. *Recommended:* If you already have a content delivery network (CDN) provider, proceed to the "Content Delivery Network" tab and populate the fields and set your preferences. If you do not use the Media Library, you will need to import your images etc into the default locations. Use the Media Library Import Tool on the "Content Delivery Network" tab to perform this task. If you do not have a CDN provider, you can still improve your site's performance using the "Self-hosted" method. On your own server, create a subdomain and matching DNS Zone record; e.g. static.domain.com and configure FTP options on the "Content Delivery Network" tab accordingly. Be sure to FTP upload the appropriate files, using the available upload buttons.
1. *Optional:* On the "Database Cache" tab, the recommended settings are preset. If using a shared hosting account use the "disk" method with caution, the response time of the disk may not be fast enough, so this option is disabled by default. Try object caching instead for shared hosting.
1. *Optional:* On the "Object Cache" tab, all of the recommended settings are preset. If using a shared hosting account use the "disk" method with caution, the response time of the disk may not be fast enough, so this option is disabled by default. Test this option with and without database cache to ensure that it provides a performance increase.
1. *Optional:* On the "User Agent Groups" tab, specify any user agents, like mobile phones if a mobile theme is used.

== What users have to say: ==

* Read [testimonials](https://twitter.com/w3edge/favorites) from W3TC users.

== Who do I thank for all of this? ==

It's quite difficult to recall all of the innovators that have shared their thoughts, code and experiences in the blogosphere over the years, but here are some names to get you started:

* [Steve Souders](http://stevesouders.com/)
* [Steve Clay](http://mrclay.org/)
* [Ryan Grove](http://wonko.com/)
* [Nicholas Zakas](http://www.nczonline.net/blog/2009/06/23/loading-javascript-without-blocking/)
* [Ryan Dean](http://rtdean.livejournal.com/)
* [Andrei Zmievski](http://gravitonic.com/)
* George Schlossnagle
* Daniel Cowgill
* [Rasmus Lerdorf](http://toys.lerdorf.com/)
* [Gopal Vijayaraghavan](http://notmysock.org/)
* [Bart Vanbraban](http://eaccelerator.net/)
* [mOo](http://xcache.lighttpd.net/)
* [villu164] (https://www.wordfence.com/threat-intel/vulnerabilities/researchers/villu164)

Please reach out to all of these people and support their projects if you're so inclined.

== Changelog ==

= 2.8.15 =
* Fix: Elementor: Carousel lazy load
* Fix: Elementor: Cache clearing issues
* Fix: Strip all mfunc/mclude tags from REST, feeds, and comments
* Fix: Better validation for file directory cleanup
* Fix: Bunny CDN: Settings page purge URL section
* Fix: Minify: Auto JS: Handle async and defer attributes with values
* Fix: Google PageSpeed: Lighthouse changes
* Fix: Cloudflare: Undefined array warning
* Fix: Rackspace API: Reponse code handling
* Fix: License deactivation messages
* Update: ChartJS updated to v4.4.1
* Enhancement: Added support links

= 2.8.14 =
* Fix: Better logic for mfunc/mclude processing
* Enhancement: More consistent purge notices

= 2.8.13 =
* Fix: Sanitize mfunc/mclude content in REST calls
* Fix: Resolved plugin check errors
* Fix: Discard simplexml errors
* Fix: Missing text domains
* Fix: Ensure array type for filter "w3tc_footer_comment"
* Enhancement: WebP Converter: WP_Query optimizations

= 2.8.12 =
* Fix: Lazy load background-image style handing
* Fix: Elementor: Also flush Object Cache after Page Cache is flushed
* Fix: Canonicalize Cache read path to avoid variants

= 2.8.11 =
* Fix: Avoid redundant object cache misses in WP 6.4 - 6.7
* Fix: Admin bar: Do not show "Purge All Caches Except Cloudflare" if disabled after it was configured
* Fix: Error handling for URL downloads
* Fix: Menu items for non-administrators
* Update: Lazy load library: 12.2.0 => 19.1.2
* Enhancement: Use SimpleXMLElement to parse sitemaps and RSS feeds
* Enhancement: Flush Elementor cache when all caches are flushed

= 2.8.10 =
* Fix: Exception handling on activation
* Fix: wp_resource_hint handling for arrays
* Enhancement: Added X-W3TC-CDN header

= 2.8.9 =
* Fix: AWS S3 test
* Fix: Gravity Forms submissions
* Fix: Windows: Configuration import
* Fix: Redis: Fix PHP 8 warning for incrBy value not being an integer
* Fix: DbCache Cluster: Check for mysqli_result before using the object
* Fix: PHP 8 warnings
* Fix: Typos on settings pages

= 2.8.8 =
* Fix: Usage Statistics JavaScript error
* Fix: Regex matching for Cookie Cache Groups
* Fix: Image Service: Error when get_current_screen() is run before admin_init
* Fix: _load_textdomain_just_in_time timing issue for WP-CLI and the Setup Guide
* Fix: "DOMDocument::loadHTML(): ID  already defined in Entity" errors
* Fix: Cloudflare: Saving settings with a value of 0
* Update: Removed StackPath, Limelight, and Highwinds CDNs due to end of service

= 2.8.7 =
* Fix: Exit survey email field submission
* Fix: Setup Guide analytics
* Update: Allow deleting plugin data when skipping the exit survey on deactivation
* Update: aws/aws-php-sns-message-validator (1.9.0 => 1.9.1)

= 2.8.6 =
* Fix: Error deactivating when selected to delete plugin data
* Fix: WP-CLI: Enable Object Cache depending on settings
* Fix: Delete all plugin WordPress Options if selected on deactivation
* Enhancement: Automatically disable Object Cache after plugin update if set to Disk and display a notice
* Enhancement: WP-CLI: Added settings to enable Object and DB Cache for WP-CLI
* Enhancement: Added an email field to the exit survey for requesting help
* Enhancement: Added a popup modal to accept the risk when enabling Object Cache using Disk

= 2.8.5 =
* Fix: CDN: Amazon S3 long hostname for default region
* Fix: WP-CLI: Error running "wp w3tc alwayscached_*" commands
* Fix: WP-CLI: Remove HTML in output
* Enhancement: Simplified license messsaging

= 2.8.4 =
* Fix: Deactivation modal JS error

= 2.8.3 =
* Fix: HTTP API calls for checking required files
* Fix: script-src-elem and style-src-attr security headers
* Fix: Handle multiple line srcset attributes for CDN URL replacement
* Fix: Fragment Cache: Fixed logic for navigation links
* Fix: Check for modified advanced-cache.php dropin/addin file
* Fix: Log directory name is made unique
* Enhancement: Added an exit survey with option to delete plugin data on deactivation
* Enhancement: Fragment Cache: Added notices for configuration
* Enhancement: Use admin-ajax for settings help tab content links
* Update: Handle XML MIME types in cache by default
* Update: Added "immutable" options for cache-control headers
* Update: Added WP-CLI command descriptions
* Update: CDN widget notices for BunnyCDN
* Update: WebP Converter widget notice

= 2.8.2 =
* Fix: Added additional user capability checks
* Fix: Ensure Object Cache garbage collection (disk) WP Cron event is scheduled
* Fix: Added additional checks when loading the Object Cache dropin
* Fix: Disable Database, Object, and Fragment Cache when using WP-CLI
* Fix: Object Cache debug logging
* Fix: FAQ help tabs
* Update: Coding standards

= 2.8.1=
* Fix: Ensure WP Cron events get scheduled when using the Setup Guide wizard and on upgrade
* Fix: Undefined variable when the Object Cache purge debug log is enabled
* Update: Added warnings in the Setup Guide and the General Settings page when using Disk for Database and Object Caches
* Update: Skip Database and Object caches when using WP-CLI

= 2.8.0 =
* Feature: Always Cached extension
* Feature: Purge caches on WP-Cron schedules
* Fix: Cloudflare: Some settings were not saved correctly
* Fix: Check and update file mode/permissions for cache files
* Fix: Issue prompting for credentials for some non-direct filesystem types
* Enhancement: Added an admin notice if WP-Cron is not functioning correctly
* Enhancement: Added Browser Cache filters
* Update: Upgraded JSMin library to 2.4.3
* Update: Added Premium Services tabs

== Upgrade Notice ==

= 2.8.13 =
This is a security update.  Users that implement mfunc/mclude should update to this version.

= 2.8.2 =
This is a security update.  All users are encouraged to update to this version.

= 2.8.1 =
Users with Object Cache using Disk should upgrade to ensure proper garbage collection.  A memory-based engine is recommended for database and object caches.  Using Disk can lead to a large number of files.  Hosting accounts with inode limits may experience issues, including downtime.

= 2.7.3 =
Thanks for using W3 Total Cache! The minimum required PHP version has been raised to PHP 7.2.5.  We recommend using PHP 8.  StackPath CDN has cased all operations and will be removed in a future release.  We recommend switching to Bunny CDN.

= 0.9.7.5 =
Users running Cloudflare CDN may experience issues beginning June 6th. Please upgrade to W3 Total Cache 0.9.7.5 for the latest Cloudflare patches.

= 0.9.5.3 =
Thanks for using W3 Total Cache! This release includes compatibility fixes that have been reported. In addition, numerous other improvements are now yours!

= 0.9.5.2 =
Thanks for using W3 Total Cache! This release includes security fixes that have been reported. In addition, numerous other improvements are now yours!

= 0.9.5.1 =
Thanks for using W3 Total Cache! This release includes security fixes that have been reported. In addition, numerous other improvements are now yours!

= 0.9.5 =
Thanks for using W3 Total Cache! This release includes fixes for recent XSS security issues that have been reported. In addition, hundreds of other improvements are now yours!

= 0.9.4 =
Thanks for using W3 Total Cache! This release introduces hundreds of well-tested stability fixes since the last release as well as a new mode called "edge mode," which allows us to make releases more often containing new features that are still undergoing testing or active iteration.

= 0.9.2.11 =
This release includes various fixes for MaxCDN and minify users. As always there are general stability / compatibility improvements. Make sure to test in a sandbox or staging environment and report any issues via the bug submission form available on the support tab of the plugin.

= 0.9.2.10 =
This release includes performance improvements for every type of caching and numerous bug fixes and stability / compatbility improvements. Make sure to keep W3TC updated to ensure optimal reliability and security.

= 0.9.2.9 =
This release addresses security issues for Cloudflare users as well as users that implement fragment caching via the mfunc functionality. For those using mfunc, temporarily disable page caching to allow yourself time to check the FAQ tab for new usage instructions.

= 0.9.2.8 =
WordPress attempts to use built-in support for managing files had issues. File management is a critical issue that will cause lots of issues if it doesn't work perfectly. This release is an attempt to restore file management back to the reliability of previous versions.
