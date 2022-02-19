<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<tr>
	<th colspan="2">
		<p class="description"><?php _e( 'We recommend that you use <a href="http://docs.amazonwebservices.com/IAM/latest/UserGuide/AccessPolicyLanguage_KeyConcepts.html" target="_blank"><acronym title="AWS Identity and Access Management">IAM</acronym></a> to create a new policy for <acronym title="Amazon Web Services">AWS</acronym> services that have limited permissions. A helpful tool: <a href="http://awspolicygen.s3.amazonaws.com/policygen.html" target="_blank"><acronym title="Amazon Web Services">AWS</acronym> Policy Generator</a>', 'w3-total-cache' ); ?></p>
	</th>
</tr>
<tr>
	<th style="width: 300px;"><label for="cdn_cf_key"><?php _e( 'Access key ID:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_cf_key" class="w3tc-ignore-change" type="text"
					   <?php Util_Ui::sealing_disabled( 'cdn.' ) ?> name="cdn__cf__key" value="<?php echo esc_attr( $this->_config->get_string( 'cdn.cf.key' ) ); ?>" size="30" />
	</td>
</tr>
<tr>
	<th><label for="cdn_cf_secret"><?php _e( 'Secret key:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_cf_secret" class="w3tc-ignore-change" type="password"
					   <?php Util_Ui::sealing_disabled( 'cdn.' ) ?> name="cdn__cf__secret" value="<?php echo esc_attr( $this->_config->get_string( 'cdn.cf.secret' ) ); ?>" size="60" />
	</td>
</tr>
<tr>
	<th><label for="cdn_cf_bucket"><?php _e( 'Bucket:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_cf_bucket" type="text" name="cdn__cf__bucket"
		<?php Util_Ui::sealing_disabled( 'cdn.' ) ?> value="<?php echo esc_attr( strtolower( $this->_config->get_string( 'cdn.cf.bucket' ) ) ); ?>" size="30" />
		<?php

		Util_Ui::selectbox(
			'cdn_cf_bucket_location',
			'cdn__cf__bucket__location',
			$this->_config->get_string( 'cdn.cf.bucket.location' ),
			CdnEngine_S3::regions_list() )

		?>
		<b>or</b>
		<input id="cdn_create_container" class="button {type: 'cf', nonce: '<?php echo wp_create_nonce( 'w3tc' ); ?>'}" type="button" value="<?php _e( 'Create as new bucket with distribution', 'w3-total-cache' ); ?>" /> <span id="cdn_create_container_status" class="w3tc-status w3tc-process"></span>
	</td>
</tr>
<tr>
	<th><label for="cdn_cf_ssl"><?php _e( '<acronym title="Secure Sockets Layer">SSL</acronym> support:', 'w3-total-cache' ); ?></label></th>
	<td>
		<select id="cdn_cf_ssl" name="cdn__cf__ssl" <?php Util_Ui::sealing_disabled( 'cdn.' ) ?>>
			<option value="auto"<?php selected( $this->_config->get_string( 'cdn.cf.ssl' ), 'auto' ); ?>><?php _e( 'Auto (determine connection type automatically)', 'w3-total-cache' ); ?></option>
			<option value="enabled"<?php selected( $this->_config->get_string( 'cdn.cf.ssl' ), 'enabled' ); ?>><?php _e( 'Enabled (always use SSL)', 'w3-total-cache' ); ?></option>
			<option value="disabled"<?php selected( $this->_config->get_string( 'cdn.cf.ssl' ), 'disabled' ); ?>><?php _e( 'Disabled (always use HTTP)', 'w3-total-cache' ); ?></option>
		</select>
		<p class="description"><?php _e( 'Some <acronym title="Content Delivery Network">CDN</acronym> providers may or may not support <acronym title="Secure Sockets Layer">SSL</acronym>, contact your vendor for more information.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><label for="cdn_cf_id"><?php _e( 'Replace site\'s hostname with:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_cf_id" type="text" name="cdn__cf__id"
					   <?php Util_Ui::sealing_disabled( 'cdn.' ) ?> value="<?php echo esc_attr( $this->_config->get_string( 'cdn.cf.id' ) ); ?>" size="18" style="text-align: right;" />.cloudfront.net or <acronym title="Canonical Name">CNAME</acronym>:
		<?php $cnames = $this->_config->get_array( 'cdn.cf.cname' ); include W3TC_INC_DIR . '/options/cdn/common/cnames.php'; ?>
		<p class="description"><?php _e( 'If you have already added a <a href="http://docs.amazonwebservices.com/AmazonCloudFront/latest/DeveloperGuide/index.html?CNAMEs.html" target="_blank"><acronym title="Canonical Name">CNAME</acronym></a> to your <acronym title="Domain Name System">DNS</acronym> Zone, enter it here.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><label for="cdn_cf_public_objects"><?php _e( 'Set objects to publicly accessible on upload:', 'w3-total-cache' ); ?></label></th>
	<td>
		<select id="cdn_cf_public_objects" name="cdn__cf__public_objects" <?php Util_Ui::sealing_disabled( 'cdn.' ) ?> >
			<option value="enabled"<?php selected( $this->_config->get_string( 'cdn.cf.public_objects' ), 'enabled' ); ?>><?php esc_html_e( 'Enabled (apply the \'public-read\' acl)', 'w3-total-cache' ); ?></option>
			<option value="disabled"<?php selected( $this->_config->get_string( 'cdn.cf.public_objects' ), 'disabled' ); ?>><?php esc_html_e( 'Disabled (don\'t apply an ACL)', 'w3-total-cache' ); ?></option>
		</select>
		<p class="description"><?php _e( 'Objects in an S3 bucket served from CloudFront do not need to be publicly accessible. Set this value to disabled to ensure that objects are not publicly accessible and can only be accessed via CloudFront or with a suitable IAM role.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th colspan="2">
		<input id="cdn_test" class="button {type: 'cf', nonce: '<?php echo wp_create_nonce( 'w3tc' ); ?>'}" type="button" value="<?php esc_html_e( 'Test S3 upload &amp; CloudFront distribution', 'w3-total-cache' ); ?>" /> <span id="cdn_test_status" class="w3tc-status w3tc-process"></span>
	</th>
</tr>
