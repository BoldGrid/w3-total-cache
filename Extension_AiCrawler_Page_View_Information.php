<?php
/**
 * File: Extension_AiCrawler_Page_View_Information.php
 *
 * Render the AI Crawler settings page - Information box.
 *
 * @package W3TC
 * @since X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
        die();
}

// Dummy responses for testing the UI when the API is unavailable.
// To use a dummy response, append "&aicrawler_dummy=mixed" or
// "&aicrawler_dummy=all_good" to the settings page URL.
$dummy_reports = array(
        'all_good' => array(
                'success' => true,
                'url'     => home_url(),
                'report'  => array(
                        home_url( '/robots.txt' )  => array(
                                'present'    => true,
                                'sufficient' => true,
                                'evaluation' => __( 'The robots.txt file is present and well-formed.', 'w3-total-cache' ),
                        ),
                        home_url( '/llms.txt' )    => array(
                                'present'    => true,
                                'sufficient' => true,
                                'evaluation' => __( 'The llms.txt file is present and well-formed.', 'w3-total-cache' ),
                        ),
                        home_url( '/sitemap.xml' ) => array(
                                'present'    => true,
                                'sufficient' => true,
                                'evaluation' => __( 'The sitemap.xml file is present and well-formed.', 'w3-total-cache' ),
                        ),
                ),
                'metadata' => array(),
        ),
        'mixed'    => array(
                'success' => true,
                'url'     => home_url(),
                'report'  => array(
                        home_url( '/robots.txt' )  => array(
                                'present'    => true,
                                'sufficient' => true,
                                'evaluation' => __( 'The robots.txt file is present and well-formed.', 'w3-total-cache' ),
                        ),
                        home_url( '/llms.txt' )    => array(
                                'present'    => false,
                                'sufficient' => false,
                                'evaluation' => __( 'The file was not found', 'w3-total-cache' ),
                        ),
                        home_url( '/sitemap.xml' ) => array(
                                'present'    => false,
                                'sufficient' => false,
                                'evaluation' => __( 'The file was not found', 'w3-total-cache' ),
                        ),
                ),
                'metadata' => array(),
        ),
);

if ( isset( $_GET['aicrawler_dummy'] ) && isset( $dummy_reports[ $_GET['aicrawler_dummy'] ] ) ) {
        $response = array(
                'success' => true,
                'data'    => $dummy_reports[ $_GET['aicrawler_dummy'] ],
        );
} else {
        $response = Extension_AiCrawler_Central_Api::call(
                'report',
                'POST',
                array(
                        'url' => home_url(),
                )
        );
}

$report = array();
if ( $response['success'] && isset( $response['data']['report'] ) ) {
        $report = $response['data']['report'];
}

/**
 * Render a report block.
 *
 * @param string $title  Report heading.
 * @param array  $report Report data keyed by URL.
 *
 * @return void
 */
function w3tc_aicrawler_render_report( $title, $report ) {
        if ( empty( $report ) ) {
                return;
        }
        ?>
        <h4 class="w3tc-aicrawler-report-heading"><?php echo esc_html( $title ); ?></h4>
        <div class="w3tc-aicrawler-report">
                <?php foreach ( $report as $url => $data ) :
                        $file       = basename( parse_url( $url, PHP_URL_PATH ) );
                        $present    = ! empty( $data['present'] );
                        $sufficient = ! empty( $data['sufficient'] );
                        $color      = $present && $sufficient ? 'green' : ( $present ? 'yellow' : 'red' );
                        $icon       = $present && $sufficient ? '&#10003;' : ( $present ? '!' : '&#10005;' );
                        ?>
                        <div class="w3tc-aicrawler-report-item">
                                <div class="w3tc-aicrawler-report-label"><?php echo esc_html( $file ); ?></div>
                                <div class="w3tc-aicrawler-report-circle w3tc-aicrawler-report-circle-<?php echo esc_attr( $color ); ?>"><?php echo $icon; ?></div>
                                <?php if ( 'green' !== $color && ! empty( $data['evaluation'] ) ) : ?>
                                        <div class="w3tc-aicrawler-report-eval"><?php echo esc_html( $data['evaluation'] ); ?></div>
                                <?php endif; ?>
                        </div>
                <?php endforeach; ?>
        </div>
        <?php
}
?>
<div class="metabox-holder">
        <?php Util_Ui::postbox_header( esc_html__( 'Information', 'w3-total-cache' ), '', 'information' ); ?>
        <?php
        $reports = array(
                array(
                        'title' => __( 'AI Crawlability Summary', 'w3-total-cache' ),
                        'data'  => $report,
                ),
        );

        foreach ( $reports as $r ) {
                w3tc_aicrawler_render_report( $r['title'], $r['data'] );
        }
        ?>
        <?php Util_Ui::postbox_footer(); ?>
</div>
