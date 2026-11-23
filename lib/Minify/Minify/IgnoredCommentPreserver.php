<?php
namespace W3TCL\Minify;

class Minify_IgnoredCommentPreserver {
    protected $_replacementHash = '';
    protected $_ignoredComments = array();
    protected $_placeholders = array();

    public function __construct() {
        $this->_ensurePlaceholderHash();
    }

    public function setIgnoredComments($ignoredComments = array()) {
        $this->_ignoredComments = $ignoredComments;
    }

    public function search($html) {
        $this->_ensurePlaceholderHash( (string) $html );
        $html = preg_replace_callback('/<!--[\\s\\S]*?-->/',
            array($this, '_callback'),
            $html);
        return $html;
    }

    public function replace($html) {
        $html = str_replace(array_keys($this->_placeholders),
            array_values($this->_placeholders),
            $html);
        return $html;
    }

    protected function _callback($match) {
        list($comment) = $match;

        if ($this->_isIgnoredComment($comment)) {
            return $this->_reservePlace($comment);
        }

        return $comment;
    }

    protected function _isIgnoredComment(&$comment) {
        foreach ($this->_ignoredComments as $ignoredComment) {
            if ( ! empty( $ignoredComment ) && stristr($comment, $ignoredComment ) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Per-request placeholder id, extended when it already occurs in $source.
     *
     * @since 2.11.0
     *
     * @param string|null $source Input before placeholders are inserted.
     * @return string
     */
    protected function _ensurePlaceholderHash( $source = null ) {
        if ( ! \is_string( $this->_replacementHash ) || '' === $this->_replacementHash ) {
            $this->_replacementHash = 'IgnoredCommentPreserver_' . \bin2hex( \random_bytes( 16 ) );
        }

        while ( \is_string( $source ) && false !== \strpos( $source, $this->_replacementHash ) ) {
            $this->_replacementHash .= \bin2hex( \random_bytes( 8 ) );
        }

        return $this->_replacementHash;
    }

    protected function _getPlaceholder() {
        return '%%' . $this->_replacementHash . '_' . count($this->_placeholders) . '%%';
    }

    protected function _reservePlace(&$content) {
        $placeholder = $this->_getPlaceholder();

        $this->_placeholders[$placeholder] = &$content;

        return $placeholder;
    }
}
