<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<p>
	Lazy loading is currently
	<?php if ( $c->get_boolean( 'lazyload.enabled' ) ): ?>
		<span class="w3tc-enabled">enabled</span>.
	<?php else: ?>
		<span class="w3tc-disabled">disabled</span>.
	<?php endif ?>
<p>

<form action="admin.php?page=w3tc_userexperience" method="post">
	<?php Util_UI::print_control_bar( 'pagecache_form_control' ); ?>
	<div class="metabox-holder">
		<?php /* decouple it when too much */ ?>
		<?php include  W3TC_DIR . '/UserExperience_LazyLoad_Page_View.php' ?>
		<?php do_action( 'w3tc_userexperience_page' ) ?>
	</div>
</form>
