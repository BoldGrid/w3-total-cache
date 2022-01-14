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
	<th style="width: 300px;"><label for="cdn_s3_key"><?php _e( 'Access key ID:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_s3_key" class="w3tc-ignore-change" type="text"
				   <?php Util_Ui::sealing_disabled( 'cdn.' ) ?> name="cdn__s3__key" value="<?php echo esc_attr( $this->_config->get_string( 'cdn.s3.key' ) ); ?>" size="30" />
	</td>
</tr>
<tr>
	<th><label for="cdn_s3_secret"><?php _e( 'Secret key:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_s3_secret" class="w3tc-ignore-change"
				   <?php Util_Ui::sealing_disabled( 'cdn.' ) ?> type="password" name="cdn__s3__secret" value="<?php echo esc_attr( $this->_config->get_string( 'cdn.s3.secret' ) ); ?>" size="60" />
	</td>
</tr>
<tr>
	<th><label for="cdn_s3_bucket"><?php _e( 'Bucket:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_s3_bucket" type="text" name="cdn__s3__bucket"
		<?php Util_Ui::sealing_disabled( 'cdn.' ) ?> value="<?php echo esc_attr( strtolower( $this->_config->get_string( 'cdn.s3.bucket' ) ) ); ?>" size="30" />
		<?php

		Util_Ui::selectbox(
			'cdn_s3_bucket_location',
			'cdn__s3__bucket__location',
			$this->_config->get_string( 'cdn.s3.bucket.location' ),
			CdnEngine_S3::regions_list() )

		?>
		<b>or</b>
		<input id="cdn_create_container" class="button {type: 's3', nonce: '<?php echo wp_create_nonce( 'w3tc' ); ?>'}" type="button" value="<?php _e( 'Create as new bucket', 'w3-total-cache' ); ?>" /> <span id="cdn_create_container_status" class="w3tc-status w3tc-process"></span>
	</td>
</tr>
<tr>
	<th><label for="cdn_s3_ssl"><?php _e( '<acronym title="Secure Sockets Layer">SSL</acronym> support:', 'w3-total-cache' ); ?></label></th>
	<td>
		<select id="cdn_s3_ssl" name="cdn__s3__ssl" <?php Util_Ui::sealing_disabled( 'cdn.' ) ?>>
			<option value="auto"<?php selected( $this->_config->get_string( 'cdn.s3.ssl' ), 'auto' ); ?>><?php _e( 'Auto (determine connection type automatically)', 'w3-total-cache' ); ?></option>
			<option value="enabled"<?php selected( $this->_config->get_string( 'cdn.s3.ssl' ), 'enabled' ); ?>><?php _e( 'Enabled (always use SSL)', 'w3-total-cache' ); ?></option>
			<option value="disabled"<?php selected( $this->_config->get_string( 'cdn.s3.ssl' ), 'disabled' ); ?>><?php _e( 'Disabled (always use HTTP)', 'w3-total-cache' ); ?></option>
		</select>
		<p class="description"><?php _e( 'Some <acronym title="Content Delivery Network">CDN</acronym> providers may or may not support <acronym title="Secure Sockets Layer">SSL</acronym>, contact your vendor for more information.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><?php _e( 'Replace site\'s hostname with:', 'w3-total-cache' ); ?></th>
	<td>
		<?php if ( ( $cdn_s3_bucket = $this->_config->get_string( 'cdn.s3.bucket' ) ) != '' ): ?>
			<?php echo htmlspecialchars( $cdn_s3_bucket ); ?>.s3.amazonaws.com
		<?php else: ?>
			&lt;bucket&gt;.s3.amazonaws.com
		<?php endif; ?> <?php _e( 'or CNAME:', 'w3-total-cache' ); ?>
		<?php $cnames = $this->_config->get_array( 'cdn.s3.cname' ); include W3TC_INC_DIR . '/options/cdn/common/cnames.php'; ?>
		<p class="description"><?php _e( 'If you have already added a <a href="https://docs.aws.amazon.com/AmazonS3/latest/userguide/VirtualHosting.html#VirtualHostingCustomURLs" target="_blank">CNAME</a> to your <acronym title="Domain Name System">DNS</acronym> Zone, enter it here.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th colspan="2">
		<input id="cdn_test" class="button {type: 's3', nonce: '<?php echo wp_create_nonce( 'w3tc' ); ?>'}" type="button" value="<?php _e( 'Test S3 upload', 'w3-total-cache' ); ?>" /> <span id="cdn_test_status" class="w3tc-status w3tc-process"></span>
	</th>
</tr>
