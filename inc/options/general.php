<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';
?>

<p>
	<?php
echo sprintf( 'The plugin is currently %1$s If an option is disabled it means that either your current installation is not compatible or software installation is required.', '<span class="w3tc-'.( $enabled ? 'enabled">' . __( 'enabled', 'w3-total-cache' ) : 'disabled">' . __( 'disabled', 'w3-total-cache' ) ) . '</span>.' )
?>
</p>
<form id="w3tc_form" action="admin.php?page=<?php echo $this->_page; ?>" method="post">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( __( 'General', 'w3-total-cache' ), '' ); ?>
		<table class="form-table">
			<tr>
				<th>Preview mode:</th>
				<td>
					<?php echo Util_Ui::nonce_field( 'w3tc' ); ?>
					<?php if ( $this->_config->is_preview() ): ?>
						<input type="submit" name="w3tc_config_preview_disable" class="button-primary" value="<?php _e( 'Disable', 'w3-total-cache' ); ?>" />
						<?php echo Util_Ui::button_link( __( 'Deploy', 'w3-total-cache' ), wp_nonce_url( sprintf( 'admin.php?page=%s&w3tc_config_preview_deploy', $this->_page ), 'w3tc' ) ); ?>
						<p class="description"> <?php printf( __( 'To preview any changed settings (without deploying): %s', 'w3-total-cache' ), Util_Ui::preview_link() ) ?> </p>
					<?php else: ?>
						<input type="submit" name="w3tc_config_preview_enable" class="button-primary" value="<?php _e( 'Enable', 'w3-total-cache' ); ?>" />
					<?php endif; ?>
					<p class="description"><?php _e( 'Use preview mode to test configuration scenarios prior to releasing them (deploy) on the actual site. Preview mode remains active even after deploying settings until the feature is disabled.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
		</table>

		<?php Util_Ui::button_config_save( 'general_general' ); ?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php
Util_Ui::postbox_header( __( 'Page Cache', 'w3-total-cache' ), '', 'page_cache' );
Util_Ui::config_overloading_button( array(
		'key' => 'pgcache.configuration_overloaded'
	) );
?>

		<p><?php _e( 'Enable page caching to decrease the response time of the site.', 'w3-total-cache' ); ?></p>

		<table class="form-table">
			<?php
Util_Ui::config_item( array(
		'key' => 'pgcache.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'description' => __( 'Caching pages will reduce the response time of your site and increase the scale of your web server.',
			'w3-total-cache' )
	) );
Util_Ui::config_item( array(
		'key' => 'pgcache.engine',
		'control' => 'selectbox',
		'selectbox_values' => array(
			'file' => array(
				'label' => __( 'Disk: Basic', 'w3-total-cache' ),
				'optgroup' => 0
			),
			'file_generic' => array(
				'label' => __( 'Disk: Enhanced', 'w3-total-cache' ),
				'optgroup' => 0
			),
			'apc' => array(
				'disabled' => !Util_Installed::apc(),
				'label' => __( 'Opcode: Alternative PHP Cache (APC / APCu)', 'w3-total-cache' ),
				'optgroup' => 1
			),
			'eaccelerator' => array(
				'disabled' => !Util_Installed::eaccelerator(),
				'label' => __( 'Opcode: eAccelerator', 'w3-total-cache' ),
				'optgroup' => 1
			),
			'xcache' => array(
				'disabled' => !Util_Installed::xcache(),
				'label' => __( 'Opcode: XCache', 'w3-total-cache' ),
				'optgroup' => 1
			),
			'wincache' => array(
				'disabled' => !Util_Installed::wincache(),
				'label' => __( 'Opcode: WinCache', 'w3-total-cache' ),
				'optgroup' => 1
			),
			'memcached' => array(
				'disabled' => !Util_Installed::memcached(),
				'label' => __( 'Memcached', 'w3-total-cache' ),
				'optgroup' => 2
			),
			'nginx_memcached' => array(
				'disabled' => !Util_Installed::memcached_memcached() || !$is_pro,
				'label' => __( 'Nginx + Memcached', 'w3-total-cache' ) .
					( $is_pro ? '' : ' (available after upgrade)' ),
				'optgroup' => 2
			),
			'redis' => array(
				'disabled' => !Util_Installed::redis(),
				'label' => __( 'Redis', 'w3-total-cache' ),
				'optgroup' => 2
			)
		),
		'selectbox_optgroups' => array(
			__( 'Shared Server (disk enhanced is best):', 'w3-total-cache' ),
			__( 'Dedicated / Virtual Server:', 'w3-total-cache' ),
			__( 'Multiple Servers:', 'w3-total-cache' )
		)
	) );
?>
		</table>

		<?php
Util_Ui::button_config_save( 'general_pagecache',
	'<input type="submit" name="w3tc_flush_pgcache" value="' .
	__( 'Empty cache', 'w3-total-cache' ) . '"' .
	( $pgcache_enabled ? '' : ' disabled="disabled" ' ) .
	' class="button" />' );
?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php
Util_Ui::postbox_header( __( 'Minify', 'w3-total-cache' ), '', 'minify' );
Util_Ui::config_overloading_button( array(
		'key' => 'minify.configuration_overloaded'
	) );
?>
		<p><?php w3tc_e( 'minify.general.header', 'Reduce load time by decreasing the size and number of <acronym title="Cascading Style Sheet">CSS</acronym> and <acronym title="JavaScript">JS</acronym> files. Automatically remove unnecessary data from <acronym title="Cascading Style Sheet">CSS</acronym>, <acronym title="JavaScript">JS</acronym>, feed, page and post <acronym title="Hypertext Markup Language">HTML</acronym>.' ) ?></p>

		<table class="form-table">
			<?php
Util_Ui::config_item(
	array(
		'key'            => 'minify.enabled',
		'control'        => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'description'    => __( 'Minification can decrease file size of <acronym title="Hypertext Markup Language">HTML</acronym>, <acronym title="Cascading Style Sheet">CSS</acronym>, <acronym title="JavaScript">JS</acronym> and feeds respectively by ~10% on average.', 'w3-total-cache' ),
		'control_after'  => ' <a class="w3tc-control-after" target="_blank" href="https://www.boldgrid.com/support/w3-total-cache/w3-total-cache-minify-faq/?utm_source=w3tc&utm_medium=learn_more_links&utm_campaign=minify_faq" title="' .
			__('Minify frequently asked questions', 'w3-total-cache' ) . '">' . __( 'Learn more', 'w3-total-cache' ) .
			'<span class="dashicons dashicons-external"></span></a>',
	)
);

Util_Ui::config_item(
	array(
		'key'               => 'minify.auto',
		'value'             => ( $this->_config->get_boolean( 'minify.auto' ) ? 1 : 0 ),
		'control'           => 'radiogroup',
		'radiogroup_values' => array(
			'1' => __( 'Auto', 'w3-total-cache' ),
			'0' => __( 'Manual', 'w3-total-cache' ),
		),
		'description'       => __(
			'Select manual mode to use fields on the minify settings tab to specify files to be minified, otherwise files will be minified automatically.',
			'w3-total-cache'
		),
		'control_after'     => ' <a class="w3tc-control-after" target="_blank" href="https://www.boldgrid.com/support/w3-total-cache/how-to-use-manual-minify-for-css-and-js/?utm_source=w3tc&utm_medium=learn_more_links&utm_campaign=manual_minify#difference-between-auto-and-manual-minify" title="'
			. __( 'How to use manual minify', 'w3-total-cache' ) . '">' . __( 'Learn more', 'w3-total-cache' ) .
			'<span class="dashicons dashicons-external"></span></a>',
	)
);

Util_Ui::config_item_engine(
	array(
		'key'           => 'minify.engine',
		'control_after' => ' <a class="w3tc-control-after" target="_blank" href="https://www.boldgrid.com/support/w3-total-cache/choosing-a-minification-method-for-w3-total-cache/?utm_source=w3tc&utm_medium=learn_more_links&utm_campaign=minify_engine" title="' .
			__('Choosing a minification method', 'w3-total-cache' ) . '">' . __( 'Learn more', 'w3-total-cache' ) .
			'<span class="dashicons dashicons-external"></span></a>',
	)
);

Util_Ui::config_item(
	array(
		'key'              => 'minify.html.engine',
		'control'          => 'selectbox',
		'selectbox_values' => array(
			'html'     => __( 'Minify (default)', 'w3-total-cache' ),
			'htmltidy' => array(
				'disabled' => !Util_Installed::tidy(),
				'label'    => __( 'HTML Tidy', 'w3-total-cache' ),
			),
		),
		'control_after'     => ' <a class="w3tc-control-after" target="_blank" href="https://www.boldgrid.com/support/w3-total-cache/minify/html-minify-or-tidy/?utm_source=w3tc&utm_medium=learn_more_links&utm_campaign=minify_html#minify-default" title="' .
			__('How to use minify HTML', 'w3-total-cache' ) . '">' . __( 'Learn more', 'w3-total-cache' ) .
			'<span class="dashicons dashicons-external"></span></a>',
	)
);

Util_Ui::config_item( array(
		'key' => 'minify.js.engine',
		'control' => 'selectbox',
		'selectbox_values' => array(
			'js' => __( 'JSMin (default)', 'w3-total-cache' ),
			'googleccjs' => __( 'Google Closure Compiler (Web Service)', 'w3-total-cache' ),
			'ccjs' => __( 'Google Closure Compiler (Local Java)', 'w3-total-cache' ),
			'jsminplus' => __( 'Narcissus', 'w3-total-cache' ),
			'yuijs' => __( 'YUI Compressor', 'w3-total-cache' )
		)
	) );
Util_Ui::config_item( array(
		'key' => 'minify.css.engine',
		'control' => 'selectbox',
		'selectbox_values' => array(
			'css' => __( 'Minify (default)', 'w3-total-cache' ),
			'csstidy' => array( 'label' => __( 'CSS Tidy', 'w3-total-cache' ), 'disabled' => ( version_compare( PHP_VERSION, '5.4.0', '<') ? true : false ) ),
			'cssmin' => __( 'YUI Compressor (PHP)', 'w3-total-cache' ),
			'yuicss' => __( 'YUI Compressor', 'w3-total-cache' )
		)
	) );
?>
		</table>

		<?php
Util_Ui::button_config_save( 'general_minify',
	'<input type="submit" name="w3tc_flush_minify" value="' .
	__( 'Empty cache', 'w3-total-cache' ) . '" ' .
	( $minify_enabled ? '' : ' disabled="disabled" ' ) .
	' class="button" />' );
?>
		<?php Util_Ui::postbox_footer(); ?>


		<?php

do_action( 'w3tc_settings_general_boxarea_system_opcache' ) ?>
		<?php
Util_Ui::postbox_header( __( 'Database Cache', 'w3-total-cache' ), '', 'database_cache' );
Util_Ui::config_overloading_button( array(
		'key' => 'dbcache.configuration_overloaded'
	) );
?>
		<p><?php _e( 'Enable database caching to reduce post, page and feed creation time.', 'w3-total-cache' ); ?></p>

		 <table class="form-table">
			<?php
Util_Ui::config_item( array(
		'key' => 'dbcache.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'description' => __( 'Caching database objects decreases the response time of your site. Best used if object caching is not possible.', 'w3-total-cache' )
	) );
Util_Ui::config_item_engine( array(
		'key' => 'dbcache.engine'
	) );
?>

			<?php if ( Util_Environment::is_w3tc_pro() && is_network_admin() ): ?>
			<?php include W3TC_INC_OPTIONS_DIR . '/enterprise/dbcluster_general_section.php' ?>
			<?php endif; ?>
		</table>

		<?php
Util_Ui::button_config_save( 'general_dbcache',
	'<input type="submit" name="w3tc_flush_dbcache" value="' .
	__( 'Empty cache', 'w3-total-cache' ) . '" ' .
	( $dbcache_enabled ? '' : ' disabled="disabled" ' ) .
	' class="button" />' );
?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php
Util_Ui::postbox_header( 'Object Cache', '', 'object_cache' );
Util_Ui::config_overloading_button( array(
		'key' => 'objectcache.configuration_overloaded'
	) );
?>
		<p><?php _e( 'Enable object caching to further reduce execution time for common operations.', 'w3-total-cache' ); ?></p>

		<table class="form-table">
			<?php
Util_Ui::config_item( array(
		'key' => 'objectcache.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'description' => __( 'Object caching greatly increases performance for highly dynamic sites that use the <a href="http://codex.wordpress.org/Class_Reference/WP_Object_Cache" target="_blank">Object Cache <acronym title="Application Programming Interface">API</acronym></a>.', 'w3-total-cache' )
	) );
Util_Ui::config_item_engine( array(
		'key' => 'objectcache.engine'
	) );
?>
		</table>

		<?php
Util_Ui::button_config_save( 'general_objectcache',
	'<input type="submit" name="w3tc_flush_objectcache" value="' .
	__( 'Empty cache', 'w3-total-cache' ) . '" ' .
	( $objectcache_enabled ? '' : ' disabled="disabled" ' ) .
	' class="button" />' );
?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php
Util_Ui::postbox_header( __( 'Browser Cache', 'w3-total-cache' ), '', 'browser_cache' );
Util_Ui::config_overloading_button( array(
		'key' => 'browsercache.configuration_overloaded'
	) );
?>
		<p><?php _e( 'Reduce server load and decrease response time by using the cache available in site visitor\'s web browser.', 'w3-total-cache' ); ?></p>

		<table class="form-table">
			<?php
Util_Ui::config_item( array(
		'key' => 'browsercache.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable', 'w3-total-cache' ),
		'description' => __( 'Enable <acronym title="Hypertext Transfer Protocol">HTTP</acronym> compression and add headers to reduce server load and decrease file load time.', 'w3-total-cache' )
	) );
?>
		</table>

		<?php Util_Ui::button_config_save( 'general_browsercache' ); ?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php do_action( 'w3tc_settings_general_boxarea_cdn' ); ?>

		<?php
Util_Ui::postbox_header( __( 'Reverse Proxy', 'w3-total-cache' ), '', 'reverse_proxy' );
Util_Ui::config_overloading_button( array(
		'key' => 'varnish.configuration_overloaded'
	) );
?>
		<p>
			<?php
echo sprintf(
	w3tc_er( 'reverseproxy.general.header', 'A reverse proxy adds scale to an server by handling requests before WordPress does. Purge settings are set on the <a href="%s">Page Cache settings</a> page and <a href="%s">Browser Cache settings</a> are set on the browser cache settings page.' ),
	self_admin_url( 'admin.php?page=w3tc_pgcache' ),
	self_admin_url( 'admin.php?page=w3tc_browsercache' ) );
?>
		</p>
		<table class="form-table">
			<tr>
				<th colspan="2">
					<?php $this->checkbox( 'varnish.enabled' ); ?> <?php Util_Ui::e_config_label( 'varnish.enabled' ) ?></label><br />
				</th>
			</tr>
			 <tr>
				 <th><label for="pgcache_varnish_servers"><?php Util_Ui::e_config_label( 'varnish.servers' ) ?></label></th>
				 <td>
					<textarea id="pgcache_varnish_servers" name="varnish__servers"
						  cols="40" rows="5" <?php Util_Ui::sealing_disabled( 'varnish.' ); ?>><?php echo esc_textarea( implode( "\r\n", $this->_config->get_array( 'varnish.servers' ) ) ); ?></textarea>
					<p class="description"><?php _e( 'Specify the IP addresses of your varnish instances above. The <acronym title="Varnish Configuration Language">VCL</acronym>\'s <acronym title="Access Control List">ACL</acronym> must allow this request.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
		</table>

		<?php
Util_Ui::button_config_save( 'general_varnish',
	'<input type="submit" name="w3tc_flush_varnish" value="' .
	__( 'Purge cache', 'w3-total-cache' ) . '"' .
	( $varnish_enabled ? '' : ' disabled="disabled" ' ) .
	' class="button" />' );
?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php if ( $is_pro ): ?>
		<?php Util_Ui::postbox_header( 'Message Bus', '', 'amazon_sns' ); ?>
		<p>
			Allows policy management to be shared between a dynamic pool of servers. For example, each server in a pool to use opcode caching (which is not a shared resource) and purging is then syncronized between any number of servers in real-time; each server therefore behaves identically even though resources are not shared.
		</p>
		<table class="form-table">
			<tr>
				<th colspan="2">
					<input type="hidden" name="cluster__messagebus__enabled" value="0" />
					<label><input class="enabled" type="checkbox" name="cluster__messagebus__enabled" value="1"<?php checked( $this->_config->get_boolean( 'cluster.messagebus.enabled' ), true ); ?> /> <?php Util_Ui::e_config_label( 'cluster.messagebus.enabled' ) ?></label><br />
				</th>
			</tr>
			<tr>
				<th><label for="cluster_messagebus_sns_region"><?php Util_Ui::e_config_label( 'cluster.messagebus.sns.region' ) ?></label></th>
				<td>
					<input id="cluster_messagebus_sns_region"
						class="w3tc-ignore-change" type="text"
						name="cluster__messagebus__sns__region"
						value="<?php echo esc_attr( $this->_config->get_string( 'cluster.messagebus.sns.region' ) ); ?>" size="60" />
					<p class="description"><?php _e( 'Specify the Amazon <acronym title="Simple Notification Service">SNS</acronym> service endpoint hostname. If empty, then default "sns.us-east-1.amazonaws.com" will be used.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cluster_messagebus_sns_api_key"><?php Util_Ui::e_config_label( 'cluster.messagebus.sns.api_key' ) ?></label></th>
				<td>
					<input id="cluster_messagebus_sns_api_key"
						class="w3tc-ignore-change" type="text"
						name="cluster__messagebus__sns__api_key"
						value="<?php echo esc_attr( $this->_config->get_string( 'cluster.messagebus.sns.api_key' ) ); ?>" size="60" />
					<p class="description"><?php _e( 'Specify the <acronym title="Application Programming Interface">API</acronym> Key.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cluster_messagebus_sns_api_secret"><?php Util_Ui::e_config_label( 'cluster.messagebus.sns.api_secret' ) ?></label></th>
				<td>
					<input id="cluster_messagebus_sns_api_secret"
						class="w3tc-ignore-change" type="text"
						name="cluster__messagebus__sns__api_secret"
						value="<?php echo esc_attr( $this->_config->get_string( 'cluster.messagebus.sns.api_secret' ) ); ?>" size="60" />
					<p class="description"><?php _e( 'Specify the <acronym title="Application Programming Interface">API</acronym> secret.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="cluster_messagebus_sns_topic_arn"><?php Util_Ui::e_config_label( 'cluster.messagebus.sns.topic_arn' ) ?></label></th>
				<td>
					<input id="cluster_messagebus_sns_topic_arn"
						class="w3tc-ignore-change" type="text"
						name="cluster__messagebus__sns__topic_arn"
						value="<?php echo esc_attr( $this->_config->get_string( 'cluster.messagebus.sns.topic_arn' ) ); ?>" size="60" />
					<p class="description"><?php _e( 'Specify the <acronym title="Simple Notification Service">SNS</acronym> topic.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
		</table>

		<?php Util_Ui::button_config_save( 'general_dbcluster' ); ?>
		<?php Util_Ui::postbox_footer(); ?>
		<?php endif; ?>

		<?php
foreach ( $custom_areas as $area )
	do_action( "w3tc_settings_general_boxarea_{$area['id']}" );
?>
		<?php if ( $licensing_visible ): ?>
			<?php Util_Ui::postbox_header( __( 'Licensing', 'w3-total-cache' ), '', 'licensing' ); ?>
			<table class="form-table">
					<tr>
						<th>
							<label for="plugin_license_key"><?php Util_Ui::e_config_label( 'plugin.license_key' ) ?></label>
						</th>
						<td>
							<input id="plugin_license_key" name="plugin__license_key" type="text" value="<?php echo esc_attr( $this->_config->get_string( 'plugin.license_key' ) )?>" size="45"/>
							<input id="plugin_license_key_verify" type="button" class="button" value="<?php _e( 'Verify license key', 'w3-total-cache' ) ?>"/>
							<span class="w3tc_license_verification"></span>
							<p class="description"><?php printf( __( 'Please enter the license key provided after %s.', 'w3-total-cache' ), '<a class="button-buy-plugin"  data-src="generic_license" href="#">' . __( 'upgrading', 'w3-total-cache' ) . '</a>' )?></p>
						</td>
					</tr>

			</table>
			<?php Util_Ui::button_config_save( 'general_licensing' ); ?>
			<?php Util_Ui::postbox_footer(); ?>
		<?php endif ?>

		<?php Util_Ui::postbox_header( __( 'Miscellaneous', 'w3-total-cache' ), '', 'miscellaneous' ); ?>
		<table class="form-table">
			<?php
Util_Ui::config_item( array(
		'key' => 'widget.pagespeed.enabled',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Enable Google Page Speed dashboard widget', 'w3-total-cache' ),
		'description' => __( 'Display Google Page Speed results on the WordPress dashboard.', 'w3-total-cache' ),
		'label_class' => 'w3tc_single_column'
	) );
?>
			<tr>
				<th><label for="widget_pagespeed_key"><?php Util_Ui::e_config_label( 'widget.pagespeed.key' ) ?></label></th>
				<td>
					<input id="widget_pagespeed_key" type="text" name="widget__pagespeed__key" value="<?php echo esc_attr( $this->_config->get_string( 'widget.pagespeed.key' ) ); ?>" <?php Util_Ui::sealing_disabled( 'common.' ) ?> size="60" />
					<p class="description"><?php _e( 'Learn more about obtaining a <a href="https://support.google.com/cloud/answer/6158862" target="_blank"><acronym title="Application Programming Interface">API</acronym> key here</a>.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			 <tr>
				 <th><label for="widget_pagespeed_key"><?php Util_Ui::e_config_label( 'widget.pagespeed.key.restrict.referrer', 'general' ) ?></label></th>
				 <td>
					 <input id="widget_pagespeed_key_restrict_referrer" type="text" name="widget__pagespeed__key__restrict__referrer" value="<?php echo esc_attr( $this->_config->get_string( 'widget.pagespeed.key.restrict.referrer' ) ); ?>" size="60" />
					 <p class="description">Although not required, to prevent unauthorized use and quota theft, you have the option to restrict your key using a designated HTTP referrer. If you decide to use it, you will need to set this referrer within the API Console's "Http Referrers (web sites)" key restriction area (under Credentials).</p>
				 </td>
			</tr>
			<?php
Util_Ui::config_item( array(
		'key' => 'widget.pagespeed.show_in_admin_bar',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Show page rating in admin bar', 'w3-total-cache' ),
		'label_class' => 'w3tc_single_column'
	) );
?>

			<?php if ( is_network_admin() ): ?>
			<tr>
				<th colspan="2">
					<?php $this->checkbox( 'common.force_master' ) ?> <?php Util_Ui::e_config_label( 'common.force_master' ) ?></label>
					<p class="description"><?php _e( 'Only one configuration file for whole network will be created and used. Recommended if all sites have the same configuration.', 'w3-total-cache' ); ?></p>
				</th>
			</tr>
			<?php endif; ?>
			<?php if ( Util_Environment::is_nginx() ): ?>
			<tr>
				<th><?php Util_Ui::e_config_label( 'config.path' ) ?></th>
				<td>
					<input type="text" name="config__path" value="<?php echo esc_attr( $this->_config->get_string( 'config.path' ) ); ?>" size="80" <?php Util_Ui::sealing_disabled( 'common.' ) ?>/>
					<p class="description"><?php _e( 'If empty the default path will be used..', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th colspan="2">
					<input type="hidden" name="config__check" value="0" <?php Util_Ui::sealing_disabled( 'common.' ) ?> />
					<label><input type="checkbox" name="config__check" value="1"<?php checked( $this->_config->get_boolean( 'config.check' ), true ); Util_Ui::sealing_disabled( 'common.' ); ?> /> <?php Util_Ui::e_config_label( 'config.check' ) ?></label>
					<p class="description"><?php _e( 'Notify of server configuration errors, if this option is disabled, the server configuration for active settings can be found on the <a href="admin.php?page=w3tc_install">install</a> tab.', 'w3-total-cache' ); ?></p>
				</th>
			</tr>
			<tr>
				<th colspan="2">
					<input type="hidden" name="file_locking" value="0"<?php Util_Ui::sealing_disabled( 'common.' ) ?>  />
					<label><input type="checkbox" name="file_locking" value="1"<?php checked( $file_locking, true ); Util_Ui::sealing_disabled( 'common.' ) ?>  /> <?php _e( 'Enable file locking', 'w3-total-cache' ); ?></label>
					<p class="description"><?php _e( 'Not recommended for <acronym title="Network File System">NFS</acronym> systems.', 'w3-total-cache' ); ?></p>
				</th>
			</tr>
			<tr>
				<th colspan="2">
					<input type="hidden" name="file_nfs" value="0" <?php Util_Ui::sealing_disabled( 'common.' ) ?> />
					<label><input type="checkbox" name="file_nfs" value="1"<?php checked( $file_nfs, true ); Util_Ui::sealing_disabled( 'common.' ); ?> /> <?php _e( 'Optimize disk enhanced page and minify disk caching for <acronym title="Network File System">NFS</acronym>', 'w3-total-cache' ); ?></label>
					<p class="description"><?php _e( 'Try this option if your hosting environment uses a network based file system for a possible performance improvement.', 'w3-total-cache' ); ?></p>
				</th>
			</tr>
			<?php

				Util_Ui::config_item(
					array(
						'key'            => 'docroot_fix.enable',
						'control'        => 'checkbox',
						'checkbox_label' => __( 'Fix document root path', 'w3-total-cache' ),
						'label_class'    => 'w3tc_single_column',
						'description'    => sprintf(
							// translators: 1: WordPress ABSPATH value, 2: Server document root value.
							__( 'Fix incorrect server document root path.  Uses the WordPress ABSPATH ("%1$s") in place of the current server document root ("%2$s").', 'w3-total-cache' ),
							esc_html( untrailingslashit( ABSPATH ) ),
							esc_html( $_SERVER['DOCUMENT_ROOT'] )
						),
					)
				);

Util_Ui::config_item( array(
		'key' => 'common.track_usage',
		'control' => 'checkbox',
		'checkbox_label' => __( 'Anonymously track usage to improve product quality', 'w3-total-cache' ),
		'label_class' => 'w3tc_single_column'
	) );
?>
		</table>

		<?php Util_Ui::button_config_save( 'general_misc' ); ?>
		<?php Util_Ui::postbox_footer(); ?>

		<?php Util_Ui::postbox_header( 'Debug', '', 'debug' ); ?>
		<p><?php _e( 'Detailed information about each cache will be appended in (publicly available) <acronym title="Hypertext Markup Language">HTML</acronym> comments in the page\'s source code. Performance in this mode will not be optimal, use sparingly and disable when not in use.', 'w3-total-cache' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php _e( 'Debug mode:', 'w3-total-cache' ); ?></th>
				<td>
					<?php $this->checkbox_debug( 'pgcache.debug' ) ?> <?php Util_Ui::e_config_label( 'pgcache.debug' ) ?></label><br />
					<?php $this->checkbox_debug( 'minify.debug' ) ?> <?php Util_Ui::e_config_label( 'minify.debug' ) ?></label><br />
					<?php $this->checkbox_debug( 'dbcache.debug' ) ?> <?php Util_Ui::e_config_label( 'dbcache.debug' ) ?></label><br />
					<?php $this->checkbox_debug( 'objectcache.debug' ) ?> <?php Util_Ui::e_config_label( 'objectcache.debug' ) ?></label><br />
					<?php if ( Util_Environment::is_w3tc_pro( $this->_config ) ): ?>
					<?php $this->checkbox_debug( array( 'fragmentcache', 'debug' ) ) ?> <?php _e( 'Fragment Cache', 'w3-total-cache' ) ?></label><br />
					<?php endif; ?>
					<?php $this->checkbox_debug( 'cdn.debug' ) ?> <?php Util_Ui::e_config_label( 'cdn.debug' ) ?></label><br />
					<?php $this->checkbox_debug( 'cdnfsd.debug' ) ?> <?php Util_Ui::e_config_label( 'cdnfsd.debug' ) ?></label><br />
					<?php $this->checkbox_debug( 'varnish.debug' ) ?> <?php Util_Ui::e_config_label( 'varnish.debug' ) ?></label>
					<?php if ( Util_Environment::is_w3tc_pro() ): ?>
						<br />
						<?php $this->checkbox_debug( 'cluster.messagebus.debug' ) ?> <?php Util_Ui::e_config_label( 'cluster.messagebus.debug' ) ?></label>
					<?php endif ?>
					<p class="description"><?php _e( 'If selected, detailed caching information will appear at the end of each page in a <acronym title="Hypertext Markup Language">HTML</acronym> comment. View a page\'s source code to review.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
		</table>
		<table class="<?php echo esc_attr( Util_Ui::table_class() ); ?>">
			<tr>
				<th><?php _e( 'Purge Logs:', 'w3-total-cache' ); ?></th>
				<td>
					<?php \W3TC\Util_Ui::pro_wrap_maybe_start() ?>

					<?php
					$this->checkbox_debug_pro( 'pgcache.debug_purge',
						'Page Cache Purge Log',
						' (<a href="?page=w3tc_general&view=purge_log&module=pagecache">view log</a>)' )
					?>
					<br />

					<?php
					$this->checkbox_debug_pro( 'dbcache.debug_purge',
						'Database Cache Purge Log',
						' (<a href="?page=w3tc_general&view=purge_log&module=dbcache">view log</a>)' )
					?>
					<br />

					<?php
					$this->checkbox_debug_pro( 'objectcache.debug_purge',
						'Object Cache Purge Log',
						' (<a href="?page=w3tc_general&view=purge_log&module=objectcache">view log</a>)' )
					?>
					<br />

					<?php
					\W3TC\Util_Ui::pro_wrap_description(
						__( 'Purge Logs provide information on when your cache has been purged and what triggered it.', 'w3-total-cache' ),
						array(
							__( 'Sometimes, you\'ll encounter a complex issue involving your cache being purged for an unknown reason. The Purge Logs functionality can help you easily resolve those issues.', 'w3-total-cache' )
						),
						'general-purge-log'
					);
					?>
					<?php \W3TC\Util_Ui::pro_wrap_maybe_end( 'debug_purge' ) ?>
				</td>
			</tr>

		</table>

		<?php Util_Ui::button_config_save( 'general_debug' ); ?>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>

<form action="admin.php?page=<?php echo $this->_page; ?>" method="post" enctype="multipart/form-data">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( __( 'Import / Export Settings', 'w3-total-cache' ), '', 'settings' ); ?>
		<?php echo Util_Ui::nonce_field( 'w3tc' ); ?>
		<table class="form-table">
			<tr>
				<th><?php _e( 'Import configuration:', 'w3-total-cache' ); ?></th>
				<td>
					<input type="file" name="config_file" />
					<input type="submit" name="w3tc_config_import" class="w3tc-button-save button" value="<?php _e( 'Upload', 'w3-total-cache' ); ?>" />
					<p class="description"><?php _e( 'Upload and replace the active settings file.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e( 'Export configuration:', 'w3-total-cache' ); ?></th>
				<td>
					<input type="submit" name="w3tc_config_export" class="button" value="<?php _e( 'Download', 'w3-total-cache' ); ?>" />
					<p class="description"><?php _e( 'Download the active settings file.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e( 'Reset configuration:', 'w3-total-cache' ); ?></th>
				<td>
					<input type="submit" name="w3tc_config_reset" class="button" value="<?php _e( 'Restore Default Settings', 'w3-total-cache' ); ?>" />
					<p class="description"><?php _e( 'Revert all settings to the defaults. Any settings staged in preview mode will not be modified.', 'w3-total-cache' ); ?></p>
				</td>
			</tr>
		</table>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
<?php include W3TC_INC_DIR . '/options/common/footer.php'; ?>
