<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<?php $this->checkbox( 'minify.yuijs.options.nomunge', false, 'js_' ); ?> <?php Util_Ui::e_config_label( 'minify.yuijs.options.nomunge' ); ?></label><br />
<?php $this->checkbox( 'minify.yuijs.options.preserve-semi', false, 'js_' ); ?> <?php Util_Ui::e_config_label( 'minify.yuijs.options.preserve-semi' ); ?></label><br />
<?php $this->checkbox( 'minify.yuijs.options.disable-optimizations', false, 'js_' ); ?> <?php Util_Ui::e_config_label( 'minify.yuijs.options.disable-optimizations' ); ?></label><br />
