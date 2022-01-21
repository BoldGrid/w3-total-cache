<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';

?>
<div id="w3tcps_container">
    <div class="w3tcps_content">
        <div id="w3tcps_home">
            <h3>Homepage</h3>
            <div class="page_post">
                <p class="page_post_url" page_post_id="<?php echo get_option('page_on_front'); ?>"></p>
                <div class="w3tcps_feedback">
                    <div class="w3tcps_loading w3tc_none"></div>
                    <div class="w3tcps_error w3tc_none">An error occurred</div>
                </div>
                <div id="<?php echo get_option('page_on_front'); ?>" class="page_post_psresults"></div>
                <input class="button w3tcps_analyze" type="button" value="Analyze" />
            </div>
        </div>
    </div>
</div>
