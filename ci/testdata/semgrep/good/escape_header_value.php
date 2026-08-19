<?php
namespace W3TC;
class Cache_File_Generic {
	private function escape_header_value( $v ) {
		$v = preg_replace( '/[\r\n\x00]/', '', trim( $v ) );
		return str_replace( "'", "\\'", $v );
	}
}
