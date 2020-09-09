<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

$key = $config->get_string( 'cdnfsd.transparentcdn.client_id' );
$authorized = !empty( $key );

?>
<form id="cdn_form" action="admin.php?page=w3tc_cdn" method="post">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( __( 'Configuration: Full-Site Delivery', 'w3-total-cache' ),
	'', 'configuration' ); ?>
    <table class="form-table">
        <tr>
            <th style="width: 300px;">
            <label for="cdnfsd_transparentcdn_company_id"> <?php _e( 'Company id:', 'w3-total-cache' ); ?> </label>
            </th>
            <td>
                <input id="cdnfsd_transparentcdn_company_id" class="w3tc-ignore-change" type="text"
                <?php Util_Ui::sealing_disabled( 'cdnfsd.transparentcdn.company_id' ) ?> name="cdnfsd__transparentcdn__company_id" value="<?php echo esc_attr( $config->get_string( 'cdnfsd.transparentcdn.company_id' ) ); ?>" size="60" />
            </td>
        </tr>
        <tr>
            <th style="width: 300px;"><label for="cdnfsd_transparentcdn_clientid"><?php _e( 'Client id:', 'w3-total-cache' ); ?></label></th>
            <td>
                <input id="cdnfsd_transparentcdn_clientid" class="w3tc-ignore-change"
                <?php Util_Ui::sealing_disabled( 'cdnfsd.transparentcdn.client_id' ) ?> type="text" name="cdnfsd__transparentcdn__client_id" value="<?php echo esc_attr( $config->get_string( 'cdnfsd.transparentcdn.client_id' ) ); ?>" size="60" />
            </td>
        </tr>
        <tr>
            <th style="width: 300px;"><label for="cdnfsd_transparentcdn_clientsecret"><?php _e( 'Client secret:', 'w3-total-cache' ); ?></label></th>
            <td>
                <input id="cdnfsd_transparentcdn_clientsecret" class="w3tc-ignore-change"
                <?php Util_Ui::sealing_disabled( 'cdnfsd.transparentcdn.client_secret' ) ?> type="text" name="cdnfsd__transparentcdn__client_secret" value="<?php echo esc_attr( $config->get_string( 'cdnfsd.transparentcdn.client_secret' ) ); ?>" size="60" />
            </td>
        </tr>
        <tr>
            <th style="width: 300px;">
            <button id="transparentcdn_test" class="button {type: 'transparentcdn', nonce: '<?php echo wp_create_nonce( 'w3tc' ); ?>'}">
                <?php _e( 'Test TransparentCDN', 'w3-total-cache' ); ?>
            </button>
            </th>
            <td>
                <p class="description" id="tcdn_test_text">
                    Probar los parámetros de TransparentCDN ofrecidos para su site.
                </p>
            </td>
            <td colspan="1">
                <span id="tcdn_test_status" class="w3tc-status w3tc-process"></span>
            </td>
        </tr>
    </table>


		<?php Util_Ui::button_config_save( 'cdn_configuration' ); ?>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>

<script type="text/javascript">

    document.getElementById('transparentcdn_test').addEventListener('click', function(e){
        e.preventDefault()
        p = document.getElementById('tcdn_test_text')
        box = document.getElementById("tcdn_test_status")
        url = "https://api.transparentcdn.com/v1/oauth2/access_token/"
        
        client_id="client_id"+"="+document.getElementById('cdnfsd_transparentcdn_clientid').value
        client_secret="client_secret"+"="+document.getElementById('cdnfsd_transparentcdn_clientsecret').value
        grant_type="grant_type"+"="+"client_credentials"

        params = grant_type+"&"+client_id+"&"+client_secret
        req = new XMLHttpRequest()
        req.open("POST", url, true)
        req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded')
        req.onreadystatechange = function(e) {
            if (req.readyState == 4){
                if (req.status == 200){
                    box.innerHTML = "OK: Parámetros correctos"
                    box.className = "w3tc-status w3tc-success" 
                    console.log(req.responseText)
                } else {
                    box.innerHTML = "Error: Parámetros incorrectos"
                    box.className = "w3tc-status w3tc-error"
                    console.log(req.responseText)
                }
            }
        }
        req.send(params)
        

    });
</script>
