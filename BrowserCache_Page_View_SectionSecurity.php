<?php
/**
 * File: BrowserCache_Page_View_SectionSecurity.php
 *
 * @package W3TC
 *
 * phpcs:disable WordPress.Security.EscapeOutput
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c         = Dispatcher::config();
$fp_values = $c->get_array( 'browsercache.security.fp.values' );

$feature_policies = array(
	array(
		'label'       => 'accelerometer',
		'description' => __( 'Controls whether the current document is allowed to gather information about the acceleration of the device through the Accelerometer interface.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'ambient-light-sensor',
		'description' => __( 'Controls whether the current document is allowed to gather information about the amount of light in the environment around the device through the AmbientLightSensor interface.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'autoplay',
		'description' => __( 'Controls whether the current document is allowed to autoplay media requested through the HTMLMediaElement interface.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'battery',
		'description' => __( 'Controls whether the use of the Battery Status API is allowed. When this policy is disabled, the Promise returned by Navigator.getBattery() will reject with a NotAllowedError DOMException.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'camera',
		'description' => __( 'Controls whether the current document is allowed to use video input devices.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'display-capture',
		'description' => __( 'Controls whether or not the document is permitted to use Screen Capture API.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'document-domain',
		'description' => __( 'Controls whether the current document is allowed to set document.domain.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'encrypted-media',
		'description' => __( 'Controls whether the current document is allowed to use the Encrypted Media Extensions API (EME).', 'w3-total-cache' ),
	),
	array(
		'label'       => 'execution-while-not-rendered',
		'description' => __( 'Controls whether tasks should execute in frames while they\'re not being rendered (e.g. if an iframe is hidden or display: none).', 'w3-total-cache' ),
	),
	array(
		'label'       => 'execution-while-out-of-viewport',
		'description' => __( 'Controls whether tasks should execute in frames while they\'re outside of the visible viewport.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'fullscreen',
		'description' => __( 'Controls whether the current document is allowed to use Element.requestFullScreen().', 'w3-total-cache' ),
	),
	array(
		'label'       => 'gamepad',
		'description' => __( 'Controls whether the current document is allowed to use the Gamepad API. When this policy is disabled, calls to Navigator.getGamepads() will throw a SecurityError DOMException, and the gamepadconnected and gamepaddisconnected events will not fire.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'geolocation',
		'description' => __( 'Controls whether the current document is allowed to use the Geolocation Interface.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'gyroscope',
		'description' => __( 'Controls whether the current document is allowed to gather information about the orientation of the device through the Gyroscope interface.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'layout-animations',
		'description' => __( 'Controls whether the current document is allowed to show layout animations.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'legacy-image-formats',
		'description' => __( 'Controls whether the current document is allowed to display images in legacy formats.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'magnetometer',
		'description' => __( 'Controls whether the current document is allowed to gather information about the orientation of the device through the Magnetometer interface.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'microphone',
		'description' => __( 'Controls whether the current document is allowed to use audio input devices.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'midi',
		'description' => __( 'Controls whether the current document is allowed to use the Web MIDI API.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'navigation-override',
		'description' => __( 'Controls the availability of mechanisms that enables the page author to take control over the behavior of spatial navigation, or to cancel it outright.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'oversized-images',
		'description' => __( 'Controls whether the current document is allowed to download and display large images.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'payment',
		'description' => __( 'Controls whether the current document is allowed to use the Payment Request API.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'picture-in-picture',
		'description' => __( 'Controls whether the current document is allowed to play a video in a Picture-in-Picture mode via the corresponding API.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'publickey-credentials-get',
		'description' => __( 'Controls whether the current document is allowed to use the Web Authentication API to retrieve already stored public-key credentials, i.e. via navigator.credentials.get({publicKey: ..., ...}).', 'w3-total-cache' ),
	),
	array(
		'label'       => 'screen-wake-lock',
		'description' => __( 'Controls whether the current document is allowed to use Screen Wake Lock API to indicate that device should not turn off or dim the screen.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'speaker',
		'description' => __( 'Controls whether the current document is allowed to play audio via any methods.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'sync-xhr',
		'description' => __( 'Controls whether the current document is allowed to make synchronous XMLHttpRequest requests.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'unoptimized-images',
		'description' => __( 'Controls whether the current document is allowed to download and display unoptimized images.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'unsized-media',
		'description' => __( 'Controls whether the current document is allowed to change the size of media elements after the initial layout is complete.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'usb',
		'description' => __( 'Controls whether the current document is allowed to use the WebUSB API.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'vibrate',
		'description' => __( 'Controls whether the current document is allowed to trigger device vibrations via Navigator.vibrate() method of Vibration API.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'vr',
		'description' => __( 'Controls whether the current document is allowed to use the WebVR API. When this policy is disabled, the Promise returned by Navigator.getVRDisplays() will reject with a DOMException. Keep in mind that the WebVR standard is in the process of being replaced with WebXR.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'wake-lock',
		'description' => __( 'Controls whether the current document is allowed to use Wake Lock API to indicate that device should not enter power-saving mode.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'web-share',
		'description' => __( 'Controls whether or not the current document is allowed to use the Navigator.share() of Web Share API to share text, links, images, and other content to arbitrary destinations of user\'s choice, e.g. mobile apps.', 'w3-total-cache' ),
	),
	array(
		'label'       => 'xr-spatial-tracking',
		'description' => __( 'Controls whether the current document is allowed to use the WebXR Device API.', 'w3-total-cache' ),
	),
);

?>

<?php Util_Ui::postbox_header( __( 'Security Headers', 'w3-total-cache' ), '', 'security' ); ?>
<p><?php _e( '<acronym title="Hypertext Transfer Protocol">HTTP</acronym> security headers provide another layer of protection for your website by helping to mitigate attacks and security vulnerabilities.', 'w3-total-cache' ); ?></p>
<p><a onclick="w3tc_csp_reference()" href="javascript:void(0);">Quick Reference Chart</a></p>

<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'              => 'browsercache.security.session.use_only_cookies',
			'control'          => 'selectbox',
			'selectbox_values' => $security_session_values,
			'description'      => __( 'This setting prevents attacks that are caused by passing session IDs in <acronym title="Uniform Resource Locator">URL</acronym>s.', 'w3-total-cache' ),
		)
	);
	?>
	<?php
	Util_Ui::config_item(
		array(
			'key'              => 'browsercache.security.session.cookie_httponly',
			'control'          => 'selectbox',
			'selectbox_values' => $security_session_values,
			'description'      => __( 'This tells the user\'s browser not to make the session cookie accessible to client side scripting such as JavaScript. This makes it harder for an attacker to hijack the session ID and masquerade as the effected user.', 'w3-total-cache' ),
		)
	);
	?>
	<?php
	Util_Ui::config_item(
		array(
			'key'              => 'browsercache.security.session.cookie_secure',
			'control'          => 'selectbox',
			'selectbox_values' => $security_session_values,
			'description'      => __( 'This will prevent the user\'s session ID from being transmitted in plain text, making it much harder to hijack the user\'s session.', 'w3-total-cache' ),
		)
	);
	?>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.hsts' ); ?> <?php Util_Ui::e_config_label( 'browsercache.hsts' ); ?></label>
			<p class="description"><?php _e( '<acronym title="Hypertext Transfer Protocol">HTTP</acronym> Strict-Transport-Security (<acronym title="HTTP Strict Transport Security">HSTS</acronym>) enforces secure (<acronym title="Hypertext Transfer Protocol">HTTP</acronym> over <acronym title="Secure Sockets Layer">SSL</acronym>/<acronym title="Transport Layer Security">TLS</acronym>) connections to the server. This can help mitigate adverse effects caused by bugs and session leaks through cookies and links. It also helps defend against man-in-the-middle attacks.  If there are <acronym title="Secure Sockets Layer">SSL</acronym> negotiation warnings then users will not be permitted to ignore them.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_hsts_directive"><?php Util_Ui::e_config_label( 'browsercache.security.hsts.directive' ); ?></label>
		</th>
		<td>
			<select id="browsercache_security_hsts_directive"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?>
				name="browsercache__security__hsts__directive">
				<?php $value = $this->_config->get_string( 'browsercache.security.hsts.directive' ); ?>
				<option value="maxage"<?php selected( $value, 'maxage' ); ?>><?php _e( 'max-age=EXPIRES_SECONDS', 'w3-total-cache' ); ?></option>
				<option value="maxagepre"<?php selected( $value, 'maxagepre' ); ?>><?php _e( 'max-age=EXPIRES_SECONDS; preload', 'w3-total-cache' ); ?></option>
				<option value="maxageinc"<?php selected( $value, 'maxageinc' ); ?>><?php _e( 'max-age=EXPIRES_SECONDS; includeSubDomains', 'w3-total-cache' ); ?></option>
				<option value="maxageincpre"<?php selected( $value, 'maxageincpre' ); ?>><?php _e( 'max-age=EXPIRES_SECONDS; includeSubDomains; preload', 'w3-total-cache' ); ?></option>
			</select>
			<div id="browsercache_security_hsts_directive_description"></div>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.security.xfo' ); ?> <?php Util_Ui::e_config_label( 'browsercache.security.xfo' ); ?></label>
			<p class="description"><?php _e( 'This tells the browser if it is permitted to render a page within a frame-like tag (i.e., &lt;frame&gt;, &lt;iframe&gt; or &lt;object&gt;). This is useful for preventing clickjacking attacks.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_xfo_directive"><?php Util_Ui::e_config_label( 'browsercache.security.xfo.directive' ); ?></label>
		</th>
		<td>
			<select id="browsercache_security_xfo_directive"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?>
				name="browsercache__security__xfo__directive">
				<?php $value = $this->_config->get_string( 'browsercache.security.xfo.directive' ); ?>
				<option value="same"<?php selected( $value, 'same' ); ?>><?php _e( 'SameOrigin', 'w3-total-cache' ); ?></option>
				<option value="deny"<?php selected( $value, 'deny' ); ?>><?php _e( 'Deny', 'w3-total-cache' ); ?></option>
				<option value="allow"<?php selected( $value, 'allow' ); ?>><?php _e( 'Allow-From', 'w3-total-cache' ); ?></option>
			</select>
			<input id="browsercache_security_xfo_allow" type="text" name="browsercache__security__xfo__allow"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.xfo.allow' ) ); ?>" size="50" placeholder="Enter URL" />
			<div id="browsercache_security_xfo_directive_description"></div>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.security.xss' ); ?> <?php Util_Ui::e_config_label( 'browsercache.security.xss' ); ?></label>
			<p class="description"><?php _e( 'This header enables the <acronym title="Cross-Site Scripting">XSS</acronym> filter. It helps to stop malicious scripts from being injected into your website. Although this is already built into and enabled by default in most browsers today it is made available here to enforce its reactivation if it was disabled within the user\'s browser.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_xss_directive"><?php Util_Ui::e_config_label( 'browsercache.security.xss.directive' ); ?></label>
		</th>
		<td>
			<select id="browsercache_security_xss_directive"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?>
				name="browsercache__security__xss__directive">
				<?php $value = $this->_config->get_string( 'browsercache.security.xss.directive' ); ?>
				<option value="0"<?php selected( $value, '0' ); ?>><?php _e( '0', 'w3-total-cache' ); ?></option>
				<option value="1"<?php selected( $value, '1' ); ?>><?php _e( '1', 'w3-total-cache' ); ?></option>
				<option value="block"<?php selected( $value, 'block' ); ?>><?php _e( '1; mode=block', 'w3-total-cache' ); ?></option>
			</select>
			<div id="browsercache_security_xss_directive_description"></div>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.security.xcto' ); ?> <?php Util_Ui::e_config_label( 'browsercache.security.xcto' ); ?></label>
			<p class="description"><?php _e( 'This instructs the browser to not MIME-sniff a response outside its declared content-type. It helps to reduce drive-by download attacks and stops sites from serving malevolent content that could masquerade as an executable or dynamic HTML file.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.security.pkp' ); ?> <?php Util_Ui::e_config_label( 'browsercache.security.pkp' ); ?></label>
			<p class="description"><?php _e( '<acronym title="Hypertext Transfer Protocol">HTTP</acronym> Public Key Pinning (<acronym title="HTTP Public Key Pinning">HPKP</acronym>) is a security feature for <acronym title="Hypertext Transfer Protocol">HTTP</acronym>S websites that can prevent fraudulently issued certificates from being used to impersonate existing secure websites.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_pkp_pin"><?php Util_Ui::e_config_label( 'browsercache.security.pkp.pin' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_pkp_pin" type="text" name="browsercache__security__pkp__pin"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.pkp.pin' ) ); ?>" size="50" placeholder="Enter the Base64-Encode of the SHA256 Hash" />
			<div><i><?php _e( 'This field is <b>required</b> and represents a <acronym title="Subject Public Key Information">SPKI</acronym> fingerprint. This pin is any public key within your current certificate chain.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_pkp_pin_backup"><?php Util_Ui::e_config_label( 'browsercache.security.pkp.pin.backup' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_pkp_pin_backup" type="text" name="browsercache__security__pkp__pin__backup"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.pkp.pin.backup' ) ); ?>" size="50" placeholder="Enter the Base64-Encode of the SHA256 Hash" />
				<div><i><?php _e( 'This field is <b>also required</b> and represents your backup <acronym title="Subject Public Key Information">SPKI</acronym> fingerprint. This pin is any public key not in your current certificate chain and serves as a backup in case your certificate expires or has to be revoked.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_pkp_extra"><?php Util_Ui::e_config_label( 'browsercache.security.pkp.extra' ); ?></label>
		</th>
		<td>
			<select id="browsercache_security_pkp_extra"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?>
				name="browsercache__security__pkp__extra">
				<?php $value = $this->_config->get_string( 'browsercache.security.pkp.extra' ); ?>
				<option value="maxage"<?php selected( $value, 'maxage' ); ?>><?php _e( 'max-age=EXPIRES_SECONDS', 'w3-total-cache' ); ?></option>
				<option value="maxageinc"<?php selected( $value, 'maxageinc' ); ?>><?php _e( 'max-age=EXPIRES_SECONDS; includeSubDomains', 'w3-total-cache' ); ?></option>
			</select>
			<div id="browsercache_security_pkp_extra_description"></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_pkp_report_url"><?php Util_Ui::e_config_label( 'browsercache.security.pkp.report.url' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_pkp_report_url" type="text" name="browsercache__security__pkp__report__url"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.pkp.report.url' ) ); ?>" size="50" placeholder="Enter URL" />
			<div><i><?php _e( 'This optional field can be used to specify a <acronym title="Uniform Resource Locator">URL</acronym> that clients will send reports to if pin validation failures occur. The report is sent as a POST request with a JSON body.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_pkp_report_only"><?php Util_Ui::e_config_label( 'browsercache.security.pkp.report.only' ); ?></label>
		</th>
		<td>
			<select id="browsercache_security_pkp_report_only"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?>
				name="browsercache__security__pkp__report__only">
				<?php $value = $this->_config->get_string( 'browsercache.security.pkp.report.only' ); ?>
				<option value="0"<?php selected( $value, '0' ); ?>><?php _e( 'No = Enforce HPKP', 'w3-total-cache' ); ?></option>
				<option value="1"<?php selected( $value, '1' ); ?>><?php _e( 'Yes = Don\'t Enforce HPKP', 'w3-total-cache' ); ?></option>
			</select>
			<div id="browsercache_security_pkp_report_only_description"></div>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.security.referrer.policy' ); ?> <?php Util_Ui::e_config_label( 'browsercache.security.referrer.policy' ); ?></label>
			<p class="description"><?php _e( 'This header restricts the values of the referer header in outbound links.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_referrer_policy_directive"><?php Util_Ui::e_config_label( 'browsercache.security.referrer.policy.directive' ); ?></label>
		</th>
		<td>
			<select id="browsercache_security_referrer_policy_directive"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?>
				name="browsercache__security__referrer__policy__directive">
				<?php $value = $this->_config->get_string( 'browsercache.security.referrer.policy.directive' ); ?>
				<option value="0"<?php selected( $value, '0' ); ?>><?php _e( 'Not Set', 'w3-total-cache' ); ?></option>
				<option value="no-referrer"<?php selected( $value, 'no-referrer' ); ?>><?php _e( 'no-referrer', 'w3-total-cache' ); ?></option>
				<option value="no-referrer-when-downgrade"<?php selected( $value, 'no-referrer-when-downgrade' ); ?>><?php _e( 'no-referrer-when-downgrade', 'w3-total-cache' ); ?></option>
				<option value="same-origin"<?php selected( $value, 'same-origin' ); ?>><?php _e( 'same-origin', 'w3-total-cache' ); ?></option>
				<option value="origin"<?php selected( $value, 'origin' ); ?>><?php _e( 'origin', 'w3-total-cache' ); ?></option>
				<option value="strict-origin"<?php selected( $value, 'strict-origin' ); ?>><?php _e( 'strict-origin', 'w3-total-cache' ); ?></option>
				<option value="origin-when-cross-origin"<?php selected( $value, 'origin-when-cross-origin' ); ?>><?php _e( 'origin-when-cross-origin', 'w3-total-cache' ); ?></option>
				<option value="strict-origin-when-cross-origin"<?php selected( $value, 'strict-origin-when-cross-origin' ); ?>><?php _e( 'strict-origin-when-cross-origin', 'w3-total-cache' ); ?></option>
				<option value="unsafe-url"<?php selected( $value, 'unsafe-url' ); ?>><?php _e( 'unsafe-url', 'w3-total-cache' ); ?></option>
			</select>
			<div id="browsercache_security_referrer_policy_directive_description"></div>
		</td>
	</tr>
	<tr>
		<th colspan="2">
			<?php $this->checkbox( 'browsercache.security.csp' ); ?> <?php Util_Ui::e_config_label( 'browsercache.security.csp' ); ?></label>
			<p class="description"><?php _e( 'The Content Security Policy (<acronym title="Content Security Policy">CSP</acronym>) header reduces the risk of <acronym title="Cross-Site Scripting">XSS</acronym> attacks by allowing you to define where resources can be retrieved from, preventing browsers from loading data from any other locations. This makes it harder for an attacker to inject malicious code into your site.', 'w3-total-cache' ); ?></p>
		</th>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_base"><?php Util_Ui::e_config_label( 'browsercache.security.csp.base' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_base" type="text" name="browsercache__security__csp__base"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.base' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Restricts the <acronym title="Uniform Resource Locator">URL</acronym>s which can be used in a document\'s &lt;base&gt; element.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_connect"><?php Util_Ui::e_config_label( 'browsercache.security.csp.connect' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_connect" type="text" name="browsercache__security__csp__connect"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.connect' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Limits the origins to which you can connect via XMLHttpRequest, WebSockets, and EventSource.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_font"><?php Util_Ui::e_config_label( 'browsercache.security.csp.font' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_font" type="text" name="browsercache__security__csp__font"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.font' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Specifies the origins that can serve web fonts.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_frame"><?php Util_Ui::e_config_label( 'browsercache.security.csp.frame' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_frame" type="text" name="browsercache__security__csp__frame"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.frame' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Restricts from where the protected resource can embed frames.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_img"><?php Util_Ui::e_config_label( 'browsercache.security.csp.img' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_img" type="text" name="browsercache__security__csp__img"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.img' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Specifies valid sources for images and favicons.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_media"><?php Util_Ui::e_config_label( 'browsercache.security.csp.media' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_media" type="text" name="browsercache__security__csp__media"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.media' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Specifies valid sources for loading media using the &lt;audio&gt; and &lt;video&gt; elements.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_object"><?php Util_Ui::e_config_label( 'browsercache.security.csp.object' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_object" type="text" name="browsercache__security__csp__object"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.object' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Allows control over the &lt;object&gt;, &lt;embed&gt;, and &lt;applet&gt; elements used by Flash and other plugins.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_script"><?php Util_Ui::e_config_label( 'browsercache.security.csp.script' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_script" type="text" name="browsercache__security__csp__script"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.script' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Specifies valid sources for JavaScript.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_style"><?php Util_Ui::e_config_label( 'browsercache.security.csp.style' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_style" type="text" name="browsercache__security__csp__style"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.style' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Specifies valid sources for <acronym title="Cascading Style Sheet">CSS</acronym> stylesheets.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_form"><?php Util_Ui::e_config_label( 'browsercache.security.csp.form' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_form" type="text" name="browsercache__security__csp__form"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.form' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Restricts the <acronym title="Uniform Resource Locator">URL</acronym>s which can be used as the target of form submissions from a given context.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_frame_ancestors"><?php Util_Ui::e_config_label( 'browsercache.security.csp.frame.ancestors' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_frame_ancestors" type="text" name="browsercache__security__csp__frame__ancestors"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.frame.ancestors' ) ); ?>" size="50" placeholder="Example: 'none'" />
			<div><i><?php _e( 'Specifies valid parents that may embed a page using &lt;frame&gt;, &lt;iframe&gt;, &lt;object&gt;, &lt;embed&gt;, or &lt;applet&gt;.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_plugin"><?php Util_Ui::e_config_label( 'browsercache.security.csp.plugin' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_plugin" type="text" name="browsercache__security__csp__plugin"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.plugin' ) ); ?>" size="50" placeholder="Example: application/x-shockwave-flash" />
			<div><i><?php _e( 'Restricts the set of plugins that can be embedded into a document by limiting the types of resources which can be loaded.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_sandbox"><?php Util_Ui::e_config_label( 'browsercache.security.csp.sandbox' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_sandbox" type="text" name="browsercache__security__csp__sandbox"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.sandbox' ) ); ?>" size="50" placeholder="Example: allow-popups" />
			<div><i><?php _e( 'This directive operates similarly to the &lt;iframe&gt; sandbox attribute by applying restrictions to a page\'s actions, including preventing popups, preventing the execution of plugins and scripts, and enforcing a same-origin policy.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<tr>
		<th>
			<label for="browsercache_security_csp_default"><?php Util_Ui::e_config_label( 'browsercache.security.csp.default' ); ?></label>
		</th>
		<td>
			<input id="browsercache_security_csp_default" type="text" name="browsercache__security__csp__default"
				<?php Util_Ui::sealing_disabled( 'browsercache.' ); ?> value="<?php echo esc_attr( $this->_config->get_string( 'browsercache.security.csp.default' ) ); ?>" size="50" placeholder="Example: 'self' 'unsafe-inline' *.domain.com" />
			<div><i><?php _e( 'Defines the defaults for directives you leave unspecified. Generally, this applies to any directive that ends with -src.', 'w3-total-cache' ); ?></i></div>
		</td>
	</tr>
	<?php
	Util_Ui::config_item(
		array(
			'key'            => 'browsercache.security.fp',
			'disabled'       => Util_Ui::sealing_disabled( 'browsercache.' ),
			'control'        => 'checkbox',
			'checkbox_label' => __( 'Feature-Policy', 'w3-total-cache' ),
			'description'    => __( 'Allows you to control which origins can use which features.', 'w3-total-cache' ),
			'label_class'    => 'w3tc_single_column',
		)
	);
	?>

	<?php
	foreach ( $feature_policies as $i ) {
		Util_Ui::config_item(
			array(
				'key'                 => 'browsercache.security.fp.values.keyvalues.' . $i['label'],
				'value'               => ! empty( $fp_values[ $i['label'] ] ) ? $fp_values[ $i['label'] ] : '',
				'disabled'            => Util_Ui::sealing_disabled( 'browsercache.' ),
				'control'             => 'textbox',
				'label'               => $i['label'],
				'textbox_size'        => '50',
				'textbox_placeholder' => "One of: * 'self' 'src' 'none' *.domain.com",
				'description'         => $i['description'],
			)
		);
	}
	?>
</table>

<?php Util_Ui::button_config_save( 'browsercache_security' ); ?>
<?php Util_Ui::postbox_footer(); ?>
