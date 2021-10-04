<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<?php if ( is_null( $stats ) ): ?>
<?php _e( 'You have not configured well email, API key or domain', 'w3-total-cache' ) ?>
<?php else: ?>

<p class="cloudflare_p">
    Period
    <?php
    if( $stats['interval'] >= -1440 ) {
        echo $this->date_time( $stats['since'] );
    } else {
        echo $this->date( $stats['since'] );
    }
    ?>
      -
    <?php
    if( $stats['interval'] >= -1440 ) {
        echo $this->date_time( $stats['until'] );
    } else {
        echo $this->date( $stats['until'] );
    }
    ?>
</p>
<table class="cloudflare_table">
    <tr>
        <td></td>
        <td class="cloudflare_td_header">All</td>
        <td class="cloudflare_td_header">Cached</td>
    </tr>
    <tr>
        <td class="cloudflare_td">Bandwidth</td>
        <?php $this->value( $stats['bandwidth_all'] ) ?>
        <?php $this->value( $stats['bandwidth_cached'] ) ?>
    </tr>
    <tr>
        <td class="cloudflare_td">Requests</td>
        <?php $this->value( $stats['requests_all'] ) ?>
        <?php $this->value( $stats['requests_cached'] ) ?>
    </tr>
    <tr>
        <td class="cloudflare_td">Page Views</td>
        <?php $this->value( $stats['pageviews_all'] ) ?>
    </tr>
    <tr>
        <td class="cloudflare_td">Uniques</td>
        <?php $this->value( $stats['uniques_all'] ) ?>
    </tr>
    <tr>
        <td class="cloudflare_td">Threats</td>
        <?php $this->value( $stats['threats_all'] ) ?>
    </tr>
</table>
<p class="cloudflare_p"><small>Statistics cached for <?php $this->value( $stats['cached_tf'] ) ?> minutes on <?php $this->date_time( $stats['cached_ts'] ) ?></small></p>

<?php endif; ?>
