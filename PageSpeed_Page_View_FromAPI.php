<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

$r = $api_response;

function w3tcps_gauge_color( $score )  {
    $color = '#fff';
    if( isset( $score ) && $score >= 90 ) {
        $color = '#0c6';
    } elseif( isset( $score ) && $score >= 50 && $score < 90 ) {
        $color = '#fa3';
    } elseif( isset( $score ) && $score >= 0 && $score < 50 ) {
        $color = '#f33';
    }
    return $color;
}

function w3tcps_gauge_angle( $score ) {
    return ( isset( $score ) ? ( $score / 100 ) * 180 : 0 );
}

function w3tcps_gauge( $data, $icon ) {
    $color = w3tcps_gauge_color( $data['score'] );
    $angle = w3tcps_gauge_angle( $data['score'] );

    ?>
    <div class="gauge" style="width: 120px; --rotation:<?php echo $angle; ?>deg; --color:<?php echo $color; ?>; --background:#888;">
        <div class="percentage"></div>
        <div class="mask"></div>
        <span class="value">
            <i class="material-icons" aria-hidden="true"><?php echo $icon; ?></i>
            <?php echo ( isset( $data['score'] ) ? esc_html( $data['score'] ) : '' ); ?>
        </span>
    </div>
    <?php
}

function w3tcps_breakdown_grade( $score ) {
    $grade = 'w3tcps_blank';
    if( $score >= 90 ) {
        $grade = 'w3tcps_pass';
    } elseif( $score >= 50 && $score < 90 ) {
        $grade = 'w3tcps_average';
    } elseif( $score > 0 && $score < 50 ) {
        $grade = 'w3tcps_fail';
    }
    return $grade;
}

function w3tcps_final_screenshot( $data ) {
    echo '<img src="' . $data['screenshots']['final']['screenshot'] . '" alt="' . $data['screenshots']['final']['title'] . '"/>';
}

function w3tcps_screenshots( $data ) {
    foreach( $data['screenshots']['other']['screenshots'] as $screenshot ) {
        echo '<img src="' . $screenshot['data'] . '" alt="' . $api_response['mobile']['screenshots']['other']['title'] . '"/>';
    }
}

function w3tcps_breakdown( $data ) {
    $opportunities = '';
    $diagnostics = '';
    $passed_audits = '';
    
    foreach( $data['opportunities'] as $opportunity ) {
        if( empty( $opportunity['details'] ) ) {
            continue;
        }

        $opportunity['score'] *= 100;

        $grade = 'w3tcps_blank';
        if( isset( $opportunity['score'] ) ) {
            $grade = w3tcps_breakdown_grade( $opportunity['score'] );
        } 
    
        $audit_classes = '';
        foreach( $opportunity['type'] as $type ) {
            $audit_classes .= ' ' . $type;
        }

        $opportunity['description'] = preg_replace('/(.*)(\[Learn more\])\((.*?)\)(.*)/i','$1<a href="$3">$2</a>$4',$opportunity['description']);

        $headers = '';
        $items = '';
        foreach( $opportunity['details'] as $item ) {
            $headers = '';
            $items .= '<tr class="w3tcps_passed_audit_item">';
            if( isset( $item['url'] ) ) {
                $headers .= '<th>URL</th>';
                $items .= '<td>...' . parse_url($item['url'])['path'] . '</td>';
            } 
            if( isset( $item['totalBytes'] ) ) {
                $headers .= '<th>Total Bytes</th>';
                $items .= '<td>' . $item['totalBytes'] . '</td>';
            } 
            if( isset( $item['wastedBytes'] ) ) {
                $headers .= '<th>Wasted Bytes</th>';
                $items .= '<td>' . $item['wastedBytes'] . '</td>';
            }
            if( isset( $item['wastedPercent'] ) ) {
                $headers .= '<th>Wasted Percentage</th>';
                $items .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
            }
            if( isset( $item['wastedMs'] ) ) {
                $headers .= '<th>Wasted Miliseconds</th>';
                $items .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
            }
            if( isset( $item['label'] ) ) {
                $headers .= '<th>Type</th>';
                $items .= '<td>' . $item['label'] . '</td>';
            }
            if( isset( $item['groupLabel'] ) ) {
                $headers .= '<th>Group</th>';
                $items .= '<td>' . $item['groupLabel'] . '</td>';
            }
            if( isset( $item['requestCount'] ) ) {
                $headers .= '<th>Requests</th>';
                $items .= '<td>' . $item['requestCount'] . '</td>';
            }
            if( isset( $item['transferSize'] ) ) {
                $headers .= '<th>Transfer Size</th>';
                $items .= '<td>' . $item['transferSize'] . '</td>';
            }
            if( isset( $item['startTime'] ) ) {
                $headers .= '<th>Start Time</th>';
                $items .= '<td>' . $item['startTime'] . '</td>';
            }
            if( isset( $item['duration'] ) ) {
                $headers .= '<th>Duration</th>';
                $items .= '<td>' . $item['duration'] . '</td>';
            }
            if( isset( $item['scriptParseCompile'] ) ) {
                $headers .= '<th>Parse/Compile Time</th>';
                $items .= '<td>' . $item['scriptParseCompile'] . '</td>';
            }
            if( isset( $item['scripting'] ) ) {
                $headers .= '<th>Execution Time</th>';
                $items .= '<td>' . $item['scripting'] . '</td>';
            }
            if( isset( $item['total'] ) ) {
                $headers .= '<th>Total</th>';
                $items .= '<td>' . $item['total'] . '</td>';
            }
            if( isset( $item['cacheLifetimeMs'] ) ) {
                $headers .= '<th>Cache Lifetime Miliseconds</th>';
                $items .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
            }
            if( isset( $item['cacheHitProbability'] ) ) {
                $headers .= '<th>Cache Hit Probability</th>';
                $items .= '<td>' . $item['cacheHitProbability'] . '</td>';
            }
            $items .= '</tr>';
        }    

        if( $opportunity['score'] >= 90 ) {
            $passed_audits .= '<div class="audits w3tcps_passed_audit' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_pass_audit_items"><p>' . $opportunity['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table></div></div>';
        } else {
            $opportunities .= '<div class="audits w3tcps_opportunities' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_opportunity_items"><p>' . $opportunity['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table></div></div>';
        }
    }
    
    foreach( $data['diagnostics'] as $diagnostic ) {
        if( empty( $diagnostic['details'] ) ) {
            continue;
        }

        $diagnostic['score'] *= 100;

        $grade = 'w3tcps_blank';
        if( isset( $diagnostic['score'] ) ) {
            $grade = w3tcps_breakdown_grade( $diagnostic['score'] );
        } 
        
        $audit_classes = '';
        foreach( $opportunity['type'] as $type ) {
            $audit_classes .= ' ' . $type;
        }

        $diagnostic['description'] = preg_replace('/(.*)(\[Learn more\])\((.*?)\)(.*)/i','$1<a href="$3">$2</a>$4',$diagnostic['description']);

        $headers = '';
        $items = '';
        foreach( $diagnostic['details'] as $item ) {
            $headers = '';
            $items .= '<tr class="w3tcps_passed_audit_item">';
            if( isset( $item['url'] ) ) {
                $headers .= '<th>URL</th>';
                $items .= '<td>...' . parse_url($item['url'])['path'] . '</td>';
            } 
            if( isset( $item['totalBytes'] ) ) {
                $headers .= '<th>Total Bytes</th>';
                $items .= '<td>' . $item['totalBytes'] . '</td>';
            } 
            if( isset( $item['wastedBytes'] ) ) {
                $headers .= '<th>Wasted Bytes</th>';
                $items .= '<td>' . $item['wastedBytes'] . '</td>';
            }
            if( isset( $item['wastedPercent'] ) ) {
                $headers .= '<th>Wasted Percentage</th>';
                $items .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
            }
            if( isset( $item['wastedMs'] ) ) {
                $headers .= '<th>Wasted Miliseconds</th>';
                $items .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
            }
            if( isset( $item['label'] ) ) {
                $headers .= '<th>Type</th>';
                $items .= '<td>' . $item['label'] . '</td>';
            }
            if( isset( $item['groupLabel'] ) ) {
                $headers .= '<th>Group</th>';
                $items .= '<td>' . $item['groupLabel'] . '</td>';
            }
            if( isset( $item['requestCount'] ) ) {
                $headers .= '<th>Requests</th>';
                $items .= '<td>' . $item['requestCount'] . '</td>';
            }
            if( isset( $item['transferSize'] ) ) {
                $headers .= '<th>Transfer Size</th>';
                $items .= '<td>' . $item['transferSize'] . '</td>';
            }
            if( isset( $item['startTime'] ) ) {
                $headers .= '<th>Start Time</th>';
                $items .= '<td>' . $item['startTime'] . '</td>';
            }
            if( isset( $item['duration'] ) ) {
                $headers .= '<th>Duration</th>';
                $items .= '<td>' . $item['duration'] . '</td>';
            }
            if( isset( $item['scriptParseCompile'] ) ) {
                $headers .= '<th>Parse/Compile Time</th>';
                $items .= '<td>' . $item['scriptParseCompile'] . '</td>';
            }
            if( isset( $item['scripting'] ) ) {
                $headers .= '<th>Execution Time</th>';
                $items .= '<td>' . $item['scripting'] . '</td>';
            }
            if( isset( $item['total'] ) ) {
                $headers .= '<th>Total</th>';
                $items .= '<td>' . $item['total'] . '</td>';
            }
            if( isset( $item['cacheLifetimeMs'] ) ) {
                $headers .= '<th>Cache Lifetime Miliseconds</th>';
                $items .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
            }
            if( isset( $item['cacheHitProbability'] ) ) {
                $headers .= '<th>Cache Hit Probability</th>';
                $items .= '<td>' . ( $item['cacheHitProbability'] * 100 ) . '%</td>';
            }
            $items .= '</tr>';
        } 
        
        if( $diagnostic['score'] >= 90 ) {
            $passed_audits .= '<div class="audits w3tcps_passed_audit' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_pass_audit_items"><p>' . $diagnostic['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table></div></div>';
        } else {
            $diagnostics .= '<div class="audits w3tcps_diagnostics' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_diagnostic_items"><p>' . $diagnostic['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table></div></div>';
        }
    }

    echo '<div class="opportunities"><p class="title">Opportunities</p>' . $opportunities . '</div>';
    echo '<div class="diagnostics"><p class="title">Diagnostics</p>' . $diagnostics . '</div>';
    echo '<div class="passed_audits"><p class="title">Passed Audits</p>' . $passed_audits . '</div>';
}

function w3tcps_barline( $metric ) {
	if ( !isset( $metric['score'] ) ) {
		return;
	}

	$metric['score'] *= 100;

	$bar = '';
    
    if( $metric['score'] >= 90 ) {
		$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_pass">' . $metric['displayValue'] . '</span></div>';
    } elseif( $metric['score'] >= 50 && $metric['score'] < 90 ) {
		$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_average">' . $metric['displayValue'] . '</span></div>';
    } elseif( $metric['score'] < 50 ) {
		$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_fail">' . $metric['displayValue'] . '<span></div>';
    }

	return '<div class="w3tcps_barline">' . $bar . '</div>';
}

function w3tcps_bar($data, $metric, $name, $icon) {
	if ( !isset( $data[$metric] ) ) {
		return;
	}

    ?>
    <div class="w3tcps_metric">
        <p class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></p>
        <div class="w3tcps_metric_stats">
            <i class="material-icons" aria-hidden="true"><?php echo $icon; ?></i> 
            <?php echo w3tcps_barline( $data[$metric] ); ?>
        </div>
    </div>
    <?php
}
?>
<script>
    jQuery(document).ready(function($) {
        function filter_audits(event) {
            event.preventDefault();
            var breakdown = $(this).closest('.w3tcps_breakdown');
            breakdown.find('.opportunities').slideUp('fast');
            breakdown.find('.diagnostics').slideUp('fast');
            breakdown.find('.passed_audits').slideUp('fast');
            if($(this).text() == 'ALL'){
                breakdown.find('.audits').show();
            } else {
                breakdown.find('.audits').hide();
                breakdown.find('.' + $(this).text()).show();
            }
            if(breakdown.find('.opportunities').find('.audits:not([style*="display: none"])').length != 0){
                breakdown.find('.opportunities').slideDown('slow');
            }
            if(breakdown.find('.diagnostics').find('.audits:not([style*="display: none"])').length != 0){
                breakdown.find('.diagnostics').slideDown('slow');
            }
            if(breakdown.find('.passed_audits').find('.audits:not([style*="display: none"])').length != 0){
                breakdown.find('.passed_audits').slideDown('slow');
            }
        }

        $(document).on('click', '.w3tcps_audit_filter', filter_audits);
    });
</script>
<div id="w3tcps_control">
    <div id="w3tcps_control_mobile"><i class="material-icons" aria-hidden="true">smartphone</i><span>Mobile</span></div>
    <div id="w3tcps_control_desktop"><i class="material-icons" aria-hidden="true">computer</i><span>Desktop</span></div>
</div>
<div id="w3tcps_mobile">
    <div id="w3tcps_legend_mobile">
        <div class="w3tcps_gauge_mobile">
            <?php w3tcps_gauge( $api_response['mobile'], 'smartphone' ); ?>
        </div>
        <span>Values are estimated and may vary. The <a rel="noopener" target="_blank" href="https://web.dev/performance-scoring/?utm_source=lighthouse&amp;utm_medium=lr">performance score is calculated</a> directly from these metrics. </span><a target="_blank" href="https://googlechrome.github.io/lighthouse/scorecalc/#FCP=1028&amp;TTI=1119&amp;SI=1028&amp;TBT=18&amp;LCP=1057&amp;CLS=0&amp;FMP=1028&amp;device=desktop&amp;version=9.0.0">See calculator.</a>
        <div class="w3tcps_ranges">
            <span class="w3tcps_range w3tcps_fail">0–49</span> 
            <span class="w3tcps_range w3tcps_average">50–89</span> 
            <span class="w3tcps_range w3tcps_pass">90–100</span> 
        </div>
    </div>
    <div class="w3tcps_metrics_mobile">
    	<?php w3tcps_bar( $api_response['mobile'], 'first-contentful-paint', 'First Contentful Paint', 'smartphone' ); ?>
    	<?php w3tcps_bar( $api_response['mobile'], 'speed-index', 'Speed Index', 'smartphone' ); ?>
    	<?php w3tcps_bar( $api_response['mobile'], 'largest-contentful-paint', 'Largest Contentful Paint', 'smartphone' ); ?>
    	<?php w3tcps_bar( $api_response['mobile'], 'interactive', 'Time to Interactive', 'smartphone' ); ?>
    	<?php w3tcps_bar( $api_response['mobile'], 'total-blocking-time', 'Total Blocking Time', 'smartphone' ); ?>
    	<?php w3tcps_bar( $api_response['mobile'], 'cumulative-layout-shift', 'Cumulative Layout Shift', 'smartphone' ); ?>
    </div>
    <div class="w3tcps_screenshots_other_mobile">
        <p>Pageload Thumbnails</p>
        <?php w3tcps_screenshots( $api_response['mobile'] ); ?>
    </div>    
    <div class="w3tcps_screenshots_final_mobile">
        <p>Final Screenshot</p>
        <div class="w3tcps_final_screenshot_container"><?php w3tcps_final_screenshot( $api_response['mobile'] ); ?></div>
    </div>
    <div class="w3tcps_breakdown w3tcps_breakdown_mobile">
        <div id="w3tcps_audit_filters_mobile">
            <a href="#" class="w3tcps_audit_filter">ALL</a>
            <a href="#" class="w3tcps_audit_filter">FCP</a>
            <a href="#" class="w3tcps_audit_filter">TBT</a>
            <a href="#" class="w3tcps_audit_filter">LCP</a>
            <a href="#" class="w3tcps_audit_filter">CLS</a>
        </div>
        <?php w3tcps_breakdown( $api_response['mobile'] ); ?>
    </div>
</div>
<div id="w3tcps_desktop">
    <div id="w3tcps_legend_desktop">
        <div class="w3tcps_gauge_desktop">
            <?php w3tcps_gauge( $api_response['desktop'], 'computer' ); ?>
        </div>
        <span>Values are estimated and may vary. The <a rel="noopener" target="_blank" href="https://web.dev/performance-scoring/?utm_source=lighthouse&amp;utm_medium=lr">performance score is calculated</a> directly from these metrics. </span><a target="_blank" href="https://googlechrome.github.io/lighthouse/scorecalc/#FCP=1028&amp;TTI=1119&amp;SI=1028&amp;TBT=18&amp;LCP=1057&amp;CLS=0&amp;FMP=1028&amp;device=desktop&amp;version=9.0.0">See calculator.</a>
        <div class="w3tcps_ranges">
            <span class="w3tcps_range w3tcps_fail">0–49</span> 
            <span class="w3tcps_range w3tcps_average">50–89</span> 
            <span class="w3tcps_range w3tcps_pass">90–100</span> 
        </div>
    </div>
    <div class="w3tcps_metrics_desktop">
    	<?php w3tcps_bar( $api_response['desktop'], 'first-contentful-paint', 'First Contentful Paint', 'computer' ); ?>
    	<?php w3tcps_bar( $api_response['desktop'], 'speed-index', 'Speed Index', 'computer' ); ?>
    	<?php w3tcps_bar( $api_response['desktop'], 'largest-contentful-paint', 'Largest Contentful Paint', 'computer' ); ?>
    	<?php w3tcps_bar( $api_response['desktop'], 'interactive', 'Time to Interactive', 'computer' ); ?>
    	<?php w3tcps_bar( $api_response['desktop'], 'total-blocking-time', 'Total Blocking Time', 'computer' ); ?>
    	<?php w3tcps_bar( $api_response['desktop'], 'cumulative-layout-shift', 'Cumulative Layout Shift', 'computer' ); ?>
    </div>
    <div class="w3tcps_screenshots_other_desktop">
        <p>Pageload Thumbnails</p>
        <?php w3tcps_screenshots( $api_response['desktop'] ); ?>
    </div> 
    <div class="w3tcps_screenshots_final_desktop">
        <p>Final Screenshot</p>
        <div class="w3tcps_final_screenshot_container"><?php w3tcps_final_screenshot( $api_response['desktop'] ); ?></div>
    </div>
    <div class="w3tcps_breakdown w3tcps_breakdown_desktop">
        <div id="w3tcps_audit_filters_desktop">
            <a href="#" class="w3tcps_audit_filter">ALL</a>
            <a href="#" class="w3tcps_audit_filter">FCP</a>
            <a href="#" class="w3tcps_audit_filter">TBT</a>
            <a href="#" class="w3tcps_audit_filter">LCP</a>
            <a href="#" class="w3tcps_audit_filter">CLS</a>
        </div>
        <?php w3tcps_breakdown( $api_response['desktop'] ); ?>
    </div>
</div>