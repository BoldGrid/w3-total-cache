<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<p>
	<div class="notice notice-warning inline">
		<p>
			<?php
			// HighWinds sunset is 12:00 am Central (UTC-6:00) on November, 22, 2023 (1700629200).
			$date_time_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			printf(
				// translators: 1 HighWinds sunset datetime.
				__(
					'HighWinds will cease operations at %1$s.',
					'w3-total-cache'
				),
				wp_date( $date_time_format, '1700629200' )
			);
			?>	
		</p>
	</div>
</p>
<div class="wrapper">
    Not configured
</div>
