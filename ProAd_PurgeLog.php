<?php namespace W3TC ?>
<table class="<?php echo esc_attr( Util_Ui::table_class() ); ?>">
	<tr>
		<th><?php _e( 'Purge Logs:', 'w3-total-cache' ); ?></th>
		<td>
			<?php Util_Ui::pro_wrap_maybe_start() ?>

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
			Util_Ui::pro_wrap_description(
				__( 'Purge Logs provide information on when your cache has been purged and what triggered it.', 'w3-total-cache' ),
				array(
					__( 'Sometimes, you\'ll encounter a complex issue involving your cache being purged for an unknown reason. The Purge Logs functionality can help you easily resolve those issues.', 'w3-total-cache' )
				),
				'general-purge-log'
			);
			?>
			<?php Util_Ui::pro_wrap_maybe_end( 'debug_purge' ) ?>
		</td>
	</tr>
</table>
