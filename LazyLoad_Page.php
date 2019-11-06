<?php
namespace W3TC;



class LazyLoad_Page {
	public function render_content() {
		$c = Dispatcher::config();
		include  W3TC_DIR . '/LazyLoad_Page_View.php';
	}
}
