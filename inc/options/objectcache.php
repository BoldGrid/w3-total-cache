<?php
namespace W3TC;

defined( 'W3TC' ) || die();

?>
<?php require W3TC_INC_DIR . '/options/common/header.php'; ?>

<?php
$objectcache_doc_url         = 'https://www.boldgrid.com/support/w3-total-cache/object-cache-settings-guide/';
$objectcache_learn_more_link = static function ( $anchor, $setting_label ) use ( $objectcache_doc_url ) {
	if ( empty( $anchor ) || empty( $setting_label ) ) {
		return '';
	}

	$title = sprintf(
		/* translators: %s: Object Cache setting name. */
		__( 'Learn more about %s', 'w3-total-cache' ),
		$setting_label
	);

	return ' <a class="w3tc-control-after" target="_blank" href="' . esc_url( $objectcache_doc_url . '#' . $anchor ) . '" title="' . esc_attr( $title ) . '">' . esc_html__( 'Learn more', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>';
};
$objectcache_anchor_tags     = array(
	'a'    => array(
		'class'  => array(),
		'href'   => array(),
		'title'  => array(),
		'target' => array(),
	),
	'span' => array(
		'class' => array(),
	),
);
$objectcache_learn_more_output = static function ( $anchor, $config_key = '' ) use ( $objectcache_learn_more_link ) {
	if ( empty( $anchor ) ) {
		return '';
	}

	$label = $config_key ? Util_Ui::config_label( $config_key ) : '';
	$label = $label ? wp_strip_all_tags( $label ) : $anchor;

	return $objectcache_learn_more_link( $anchor, $label );
};
?>

<p>
	<?php
	echo wp_kses(
		sprintf(
			// translators: 1 HTML strong tag containing Objectcache Engine value, 2 HTML span tag containing Objectcache Engine enabled/disabled value.
			__(
				'Object caching via %1$s is currently %2$s.',
				'w3-total-cache'
			),
			'<strong>' . Cache::engine_name( $this->_config->get_string( 'objectcache.engine' ) ) . '</strong>',
			'<span class="w3tc-' . ( $objectcache_enabled ? 'enabled">' . esc_html__( 'enabled', 'w3-total-cache' ) : 'disabled">' . esc_html__( 'disabled', 'w3-total-cache' ) ) . ( ! $this->_config->getf_boolean( 'objectcache.enabled' ) && has_filter( 'w3tc_config_item_objectcache.enabled' ) ? esc_html__( ' via filter', 'w3-total-cache' ) : '' ) . '</span>'
		),
		array(
			'strong' => array(),
			'span'   => array(
				'class' => array(),
			),
		)
	);
	?>
</p>

<form action="admin.php?page=<?php echo esc_attr( $this->_page ); ?>" method="post">
	<?php Util_UI::print_control_bar( 'objectcache_form_control' ); ?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Advanced', 'w3-total-cache' ), '', 'advanced' ); ?>
		<table class="form-table">
			<?php
			if ( 'memcached' === $this->_config->get_string( 'objectcache.engine' ) ) {
				$module = 'objectcache';
				include W3TC_INC_DIR . '/options/parts/memcached.php';
			} elseif ( 'redis' === $this->_config->get_string( 'objectcache.engine' ) ) {
				$module = 'objectcache';
				include W3TC_INC_DIR . '/options/parts/redis.php';
			}
			?>
			<tr>
				<th style="width: 250px;"><label for="objectcache_lifetime"><?php Util_Ui::e_config_label( 'objectcache.lifetime' ); ?></label></th>
				<td>
					<input id="objectcache_lifetime" type="text"
						<?php Util_Ui::sealing_disabled( 'objectcache.' ); ?> name="objectcache__lifetime" value="<?php echo esc_attr( $this->_config->get_integer( 'objectcache.lifetime' ) ); ?>" size="8" /> <?php esc_html_e( 'seconds', 'w3-total-cache' ); ?>
					<p class="description">
						<?php esc_html_e( 'Determines the natural expiration time of unchanged cache items. The higher the value, the larger the cache.', 'w3-total-cache' ); ?>
						<?php echo wp_kses( $objectcache_learn_more_output( 'default-lifetime-of-cache-objects', 'objectcache.lifetime' ), $objectcache_anchor_tags ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="objectcache_file_gc"><?php Util_Ui::e_config_label( 'objectcache.file.gc' ); ?></label></th>
				<td>
					<input id="objectcache_file_gc" type="text"
						<?php Util_Ui::sealing_disabled( 'objectcache.' ); ?> name="objectcache__file__gc" value="<?php echo esc_attr( $this->_config->get_integer( 'objectcache.file.gc' ) ); ?>" size="8" /> <?php esc_html_e( 'seconds', 'w3-total-cache' ); ?>
					<p class="description">
						<?php esc_html_e( 'If caching to disk, specify how frequently expired cache data is removed. For busy sites, a lower value is best.', 'w3-total-cache' ); ?>
						<?php echo wp_kses( $objectcache_learn_more_output( 'garbage-collection-interval', 'objectcache.file.gc' ), $objectcache_anchor_tags ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="objectcache_groups_global"><?php Util_Ui::e_config_label( 'objectcache.groups.global' ); ?></label></th>
				<td>
					<textarea id="objectcache_groups_global"
						<?php Util_Ui::sealing_disabled( 'objectcache.' ); ?> name="objectcache__groups__global" cols="40" rows="5"><?php echo esc_textarea( implode( "\r\n", $this->_config->get_array( 'objectcache.groups.global' ) ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Groups shared amongst sites in network mode.', 'w3-total-cache' ); ?>
						<?php echo wp_kses( $objectcache_learn_more_output( 'global-groups', 'objectcache.groups.global' ), $objectcache_anchor_tags ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="objectcache_groups_nonpersistent"><?php Util_Ui::e_config_label( 'objectcache.groups.nonpersistent' ); ?></label></th>
				<td>
					<textarea id="objectcache_groups_nonpersistent"
						<?php Util_Ui::sealing_disabled( 'objectcache.' ); ?> name="objectcache__groups__nonpersistent" cols="40" rows="5"><?php echo esc_textarea( implode( "\r\n", $this->_config->get_array( 'objectcache.groups.nonpersistent' ) ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Groups that should not be cached.', 'w3-total-cache' ); ?>
						<?php echo wp_kses( $objectcache_learn_more_output( 'non-persistent-groups', 'objectcache.groups.nonpersistent' ), $objectcache_anchor_tags ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th colspan="2">
					<?php $this->checkbox( 'objectcache.enabled_for_wp_admin' ); ?><?php esc_html_e( 'Enable caching for wp-admin requests', 'w3-total-cache' ); ?></label>
					<p class="description">
						<?php esc_html_e( 'Enabling this option will increase wp-admin performance, but may cause side-effects', 'w3-total-cache' ); ?>
						<?php echo wp_kses( $objectcache_learn_more_output( 'enable-caching-for-wp-admin-requests', __( 'Enable caching for wp-admin requests', 'w3-total-cache' ) ), $objectcache_anchor_tags ); ?>
					</p>
				</th>
			</tr>
			<tr>
				<th colspan="2">
					<?php $this->checkbox( 'objectcache.fallback_transients' ); ?><?php esc_html_e( 'Store transients in database', 'w3-total-cache' ); ?></label>
					<p class="description">
						<?php esc_html_e( 'Store transients in database even when external cache is used, which allows transient values to survive object cache cleaning/expiration', 'w3-total-cache' ); ?>
						<?php echo wp_kses( $objectcache_learn_more_output( 'store-transients-in-database', __( 'Store transients in database', 'w3-total-cache' ) ), $objectcache_anchor_tags ); ?>
					</p>
				</th>
			</tr>
			<?php if ( $this->_config->get_boolean( 'cluster.messagebus.enabled' ) ) : ?>
			<tr>
				<th colspan="2">
					<?php $this->checkbox( 'objectcache.purge.all' ); ?> <?php Util_Ui::e_config_label( 'objectcache.purge.all' ); ?></label>
					<p class="description">
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 opening HTML em tag, 2 closing HTML em tag.
								__(
									'Enabling this option will increase load on server on certain actions but will guarantee that the Object Cache is always clean and contains latest changes. %1$sEnable if you are experiencing issues with options displaying wrong value/state (checkboxes etc).%2$s',
									'w3-total-cache'
								),
								'<em>',
								'</em>'
							),
							array(
								'em' => array(),
							)
						);
						?>
					</p>
				</th>
			</tr>
			<?php endif; ?>
			<?php
			Util_Ui::config_item(
				array(
					'key'            => 'objectcache.wpcli_disk',
					'label'          => Util_Ui::config_label( 'objectcache.wpcli_disk' ),
					'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'disabled'       => ! $objectcache_enabled,
					'description'    => esc_html__( 'WP-CLI already uses Memcached, Redis, and other persistent engines. Enable this only if your object cache engine is Disk and you want WP-CLI to use it.', 'w3-total-cache' ) .
						wp_kses( $objectcache_learn_more_output( 'allow-objectcache-wpcli-disk', 'objectcache.wpcli_disk' ), $objectcache_anchor_tags ),
				)
			);
			?>
		</table>

		<?php Util_Ui::postbox_footer(); ?>

		<?php Util_Ui::postbox_header( esc_html__( 'Purge via WP Cron', 'w3-total-cache' ), '', 'objectcache_wp_cron' ); ?>
		<table class="form-table">
			<p>
				<?php esc_html_e( 'Enabling this will schedule a WP-Cron event that will flush the Object Cache. If you prefer to use a system cron job instead of WP-Cron, you can schedule the following command to run at your desired interval: "wp w3tc flush object".', 'w3-total-cache' ); ?>
				<a target="_blank" href="<?php echo esc_url( 'https://www.boldgrid.com/support/w3-total-cache/schedule-cache-purges/?utm_source=w3tc&utm_medium=learn_more&utm_campaign=object_cache_wp_cron' ); ?>" title="<?php esc_attr_e( 'Scheduling cache purges', 'w3-total-cache' ); ?>">
					<?php esc_html_e( 'Learn more', 'w3-total-cache' ); ?>
					<span class="dashicons dashicons-external"></span>
				</a>
			</p>
			<?php

			if ( ! $objectcache_enabled ) {
				echo wp_kses(
					sprintf(
						// Translators: 1 opening HTML div tag followed by opening HTML p tag, 2 opening HTML a tag,
						// Translators: 3 closing HTML a tag, 4 closing HTML p tag followed by closing HTML div tag.
						__( '%1$sObject Cache is disabled! Enable it %2$shere%3$s to enable this feature.%4$s', 'w3-total-cache' ),
						'<div class="notice notice-error inline"><p>',
						'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_general#object_cache' ) ) . '">',
						'</a>',
						'</p></div>'
					),
					array(
						'div' => array(
							'class' => array(),
						),
						'p'   => array(),
						'a'   => array(
							'href' => array(),
						),
					)
				);
			}

			Util_Ui::config_item(
				array(
					'key'            => 'objectcache.wp_cron',
					'label'          => Util_Ui::config_label( 'objectcache.wp_cron' ),
					'checkbox_label' => esc_html__( 'Enable', 'w3-total-cache' ),
					'control'        => 'checkbox',
					'disabled'       => ! $objectcache_enabled,
				)
			);

			$time_options = array();
			for ( $hour = 0; $hour < 24; $hour++ ) {
				foreach ( array( '00', '30' ) as $minute ) {
					$time_value                  = $hour * 60 + intval( $minute );
					$scheduled_time              = new \DateTime( "{$hour}:{$minute}", wp_timezone() );
					$time_label                  = $scheduled_time->format( 'g:i a' );
					$time_options[ $time_value ] = $time_label;
				}
			}

			$wp_disabled = ! $this->_config->get_boolean( 'objectcache.wp_cron' );

			Util_Ui::config_item(
				array(
					'key'              => 'objectcache.wp_cron_time',
					'label'            => Util_Ui::config_label( 'objectcache.wp_cron_time' ),
					'control'          => 'selectbox',
					'selectbox_values' => $time_options,
					'description'      => esc_html__( 'This setting controls the initial start time of the cron job. If the selected time has already passed, it will schedule the job for the following day at the selected time.', 'w3-total-cache' ),
					'disabled'         => ! $objectcache_enabled || $wp_disabled,
				)
			);

			Util_Ui::config_item(
				array(
					'key'              => 'objectcache.wp_cron_interval',
					'label'            => Util_Ui::config_label( 'objectcache.wp_cron_interval' ),
					'control'          => 'selectbox',
					'selectbox_values' => array(
						'hourly'     => esc_html__( 'Hourly', 'w3-total-cache' ),
						'twicedaily' => esc_html__( 'Twice Daily', 'w3-total-cache' ),
						'daily'      => esc_html__( 'Daily', 'w3-total-cache' ),
						'weekly'     => esc_html__( 'Weekly', 'w3-total-cache' ),
					),
					'description'      => esc_html__( 'This setting controls the interval that the cron job should occur.', 'w3-total-cache' ),
					'disabled'         => ! $objectcache_enabled || $wp_disabled,
				)
			);
			?>
		</table>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
