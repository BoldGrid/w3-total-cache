<?php
/**
 * QA: Nginx FastCGI response streaming for buffer/shutdown regressions.
 *
 * When the client sends request header X-W3TC-QA: no-buffer, this plugin responds with
 * X-Accel-Buffering: no so nginx disables FastCGI response buffering for that request
 * (see ngx_http_fastcgi_module fastcgi_buffering). Apache ignores the response header.
 *
 * @package W3TC\QA
 *
 * @since X.X.X
 */

if ( ! empty( $_SERVER['HTTP_X_W3TC_QA'] ) && 'no-buffer' === $_SERVER['HTTP_X_W3TC_QA'] && ! headers_sent() ) {
	header( 'X-Accel-Buffering: no' );
}
