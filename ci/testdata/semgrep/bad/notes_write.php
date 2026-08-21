<?php
/**
 * Expected: w3tc-notes-write-requires-allowlist.
 *
 * Writes a notes.* config key built from request input without checking the
 * note id against the known-note allowlist.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Bad_Notes_Writer
 */
class Bad_Notes_Writer {
	/**
	 * Hides a note.
	 *
	 * @return void
	 */
	public function w3tc_default_hide_note() {
		$w3tc_note    = Util_Request::get_string( 'note' );
		$w3tc_setting = sprintf( 'notes.%s', $w3tc_note );

		$this->_config->set( $w3tc_setting, false );
		$this->_config->save();
	}
}
