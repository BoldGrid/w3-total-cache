<?php
function change_fdate() {
	if (touch($_GET['filename'], time() - 3600) ) {
		echo 'success change time';
	}
}
change_fdate();