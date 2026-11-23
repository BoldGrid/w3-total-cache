<?php
namespace W3TC;
class Cache_File_Generic {
	private function escape_header_value( $v ) {
		return preg_replace( '~[\r\n]~m', '_', trim( $v ) );
	}
}
