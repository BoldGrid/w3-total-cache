<?php
/**
 * Compliant: the note id is checked against the known-note allowlist before the
 * notes.* config key is written.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Good_Notes_Writer
 */
class Good_Notes_Writer {
	/**
	 * Hides a note.
	 *
	 * @return void
	 */
	public function w3tc_default_hide_note() {
		$w3tc_note = Util_Request::get_string( 'note' );

		if ( ! ConfigKeysSchema::is_known_note_id( $w3tc_note ) ) {
			Util_Admin::redirect( array(), true );
			return;
		}

		$w3tc_setting = sprintf( 'notes.%s', $w3tc_note );

		$this->_config->set( $w3tc_setting, false );
		$this->_config->save();
	}
}
