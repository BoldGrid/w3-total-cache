<?php
namespace W3TCL\Minify;

/**
 * Class Minify_HTML
 * @package Minify
 */

/**
 * Compress HTML
 *
 * This is a heavy regex-based removal of whitespace, unnecessary comments and
 * tokens. IE conditional comments are preserved. There are also options to have
 * STYLE and SCRIPT blocks compressed by callback functions.
 *
 * A test suite is available.
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
class Minify_HTML {
	/**
	 * @var boolean
	 */
	protected $_jsCleanComments = true;

	/**
	 * "Minify" an HTML page
	 *
	 * @param string $html
	 *
	 * @param array $options
	 *
	 * 'cssMinifier' : (optional) callback function to process content of STYLE
	 * elements.
	 *
	 * 'jsMinifier' : (optional) callback function to process content of SCRIPT
	 * elements. Note: the type attribute is ignored.
	 *
	 * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
	 * unset, minify will sniff for an XHTML doctype.
	 *
	 * @return string
	 */
	public static function minify($html, $options = array()) {
		$min = new self($html, $options);
		return $min->process();
	}


	/**
	 * Create a minifier object
	 *
	 * @param string $html
	 *
	 * @param array $options
	 *
	 * 'cssMinifier' : (optional) callback function to process content of STYLE
	 * elements.
	 *
	 * 'jsMinifier' : (optional) callback function to process content of SCRIPT
	 * elements. Note: the type attribute is ignored.
	 *
	 * 'jsCleanComments' : (optional) whether to remove HTML comments beginning and end of script block
	 *
	 * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
	 * unset, minify will sniff for an XHTML doctype.
	 *
	 * @return null
	 */
	public function __construct($html, $options = array())
	{
		$this->_html = str_replace("\r\n", "\n", trim($html));
		if (isset($options['xhtml'])) {
			$this->_isXhtml = (bool)$options['xhtml'];
		}
		if (isset($options['cssMinifier'])) {
			$this->_cssMinifier = $options['cssMinifier'];
		}
		if (isset($options['jsMinifier'])) {
			$this->_jsMinifier = $options['jsMinifier'];
		}

		$this->_stripCrlf = (isset($options['stripCrlf']) ? (boolean) $options['stripCrlf'] : false) ;
		$this->_ignoredComments = (isset($options['ignoredComments']) ? (array) $options['ignoredComments'] : array());
	}


	/**
	 * Minify the markeup given in the constructor
	 *
	 * @return string
	 */
	public function process()
	{
		if ($this->_isXhtml === null) {
			$this->_isXhtml = (false !== strpos($this->_html, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML'));
		}

		$this->_replacementHash = 'MINIFYHTML' . md5( isset( $_SERVER['REQUEST_TIME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME'] ) ) : '' );
		$this->_placeholders = array();

		// replace dynamic tags
		$this->_html = preg_replace_callback(
			'~(<!--\s*m(func|clude)(.*)-->\s*<!--\s*/m(func|clude)\s*-->)~is'
			,array($this, '_removeComment')
			,$this->_html);

		// replace SCRIPTs (and minify) with placeholders
		$this->_html = preg_replace_callback(
			'/(\\s*)<script(\\b[^>]*?>)([\\s\\S]*?)<\\/script>(\\s*)/i'
			,array($this, '_removeScriptCB')
			,$this->_html);

		// replace STYLEs (and minify) with placeholders
		$this->_html = preg_replace_callback(
			'/\\s*<style(\\b[^>]*>)([\\s\\S]*?)<\\/style>\\s*/i'
			,array($this, '_removeStyleCB')
			,$this->_html);

		// remove HTML comments (not containing IE conditional comments).
		$this->_html = preg_replace_callback(
			'/<!--([\\s\\S]*?)-->/'
			,array($this, '_commentCB')
			,$this->_html);

		// replace PREs with placeholders
		$this->_html = preg_replace_callback('/\\s*<pre(\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/i'
			,array($this, '_removePreCB')
			,$this->_html);

		// replace TEXTAREAs with placeholders
		$this->_html = preg_replace_callback(
			'/\\s*<textarea(\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/i'
			,array($this, '_removeTextareaCB')
			,$this->_html);

		// trim each line.
		// @todo take into account attribute values that span multiple lines.
		$this->_html = preg_replace('/^\\s+|\\s+$/m', '', $this->_html);

		// remove ws around block/undisplayed elements
		$this->_html = preg_replace('/\\s+(<\\/?(?:area|article|aside|base(?:font)?|blockquote|body'
			.'|canvas|caption|center|col(?:group)?|dd|dir|div|dl|dt|fieldset|figcaption|figure|footer|form'
			.'|frame(?:set)?|h[1-6]|head|header|hgroup|hr|html|legend|link|main|map|menu|meta|nav'
			.'|ol|opt(?:group|ion)|output|p|param|section|t(?:able|body|head|d|h||r|foot|itle)'
			.'|ul|video)\\b[^>]*>)/i', '$1', $this->_html);

		// remove whitespaces outside of all elements
		$this->_html = preg_replace(
			'/>((\\s)(?:\\s*))?([^<]+?)((\\s)(?:\\s*))?</'
			,'>$2$3$5<'
			,$this->_html);

		// remove whitespaces before end of all empty elements
		$this->_html = preg_replace(
			'/\\s*\\/>/'
			,'/>'
			,$this->_html);

		// remove trailing slash from void elements
		$_html = preg_replace(
			'~<(area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)(([^\'">]|\"[^\"]*\"|\'[^\']*\'|)*?)\\s*[/]?>~i'
			,'<$1$2>'
			,$this->_html);

		// Avoid PREG_JIT_STACKLIMIT_ERROR.  Thanks @ericek111 for https://github.com/W3EDGE/w3-total-cache/issues/190.
		if ( preg_last_error() === PREG_NO_ERROR ) {
			$this->_html = $_html;
		}
		unset( $_html );

		// use newlines before 1st attribute in open tags (to limit line lengths)
		$this->_html = preg_replace('/(<[a-z\\-]+)\\s+([^>]+>)/i', "$1\n$2", $this->_html);

		if ($this->_stripCrlf) {
			$this->_html = preg_replace("~[\r\n]+~", ' ', $this->_html);
		} else {
			$this->_html = preg_replace("~[\r\n]+~", "\n", $this->_html);
		}

		// fill placeholders
		$this->_html = str_replace(
			array_keys($this->_placeholders)
			,array_values($this->_placeholders)
			,$this->_html
		);
		// issue 229: multi-pass to catch scripts that didn't get replaced in textareas
		$this->_html = str_replace(
			array_keys($this->_placeholders)
			,array_values($this->_placeholders)
			,$this->_html
		);

		// in HTML5, type attribute is unnecessary for JavaScript resources
		// in HTML5, type attribute for style element is not needed and should be omitted
		if (false !== stripos($this->_html, '<!doctype html>')) {
			$this->_html = preg_replace(
				'/<(script|style)([^>]*)\\stype=[\'"]?(text\\/javascript|text\\/css|application\\/javascript)[\'"]?([^>]*)>/i'
				,'<$1$2$4>'
				,$this->_html);
		}

		// unquote attribute values without spaces
		$this->_html = preg_replace_callback(
			'/(<([a-z\\-]+)\\s)\\s*([^>]+>)/m'
			,array($this, '_removeAttributeQuotes')
			,$this->_html);

		return $this->_html;
	}

	protected function _commentCB($m)
	{
		return (0 === strpos($m[1], '[') || false !== strpos($m[1], '<![') || $this->_ignoredComment($m[1]))
			? $m[0]
			: '';
	}

	protected function _ignoredComment($comment)
	{
		foreach ($this->_ignoredComments as $ignoredComment) {
			if (!empty($ignoredComment) && stristr($comment, $ignoredComment) !== false) {
				return true;
			}
		}

		return false;
	}

	protected function _reservePlace($content)
	{
		$placeholder = '%' . $this->_replacementHash . count($this->_placeholders) . '%';
		$this->_placeholders[$placeholder] = $content;
		return $placeholder;
	}

	protected $_isXhtml = null;
	protected $_replacementHash = null;
	protected $_placeholders = array();
	protected $_cssMinifier = null;
	protected $_jsMinifier = null;
	protected $_stripCrlf = null;
	protected $_ignoredComments = null;

	protected function _removePreCB($m)
	{
		return $this->_reservePlace("<pre{$m[1]}");
	}

	protected function _removeTextareaCB($m)
	{
		return $this->_reservePlace("<textarea{$m[1]}");
	}

	protected function _removeStyleCB($m)
	{
		$openStyle = "<style{$m[1]}";
		$css = $m[2];
		// remove HTML comments
		$css = preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $css);

		// remove CDATA section markers
		$css = $this->_removeCdata($css);

		// minify
		$minifier = $this->_cssMinifier
			? $this->_cssMinifier
			: 'trim';
		$css = call_user_func($minifier, $css);

		return $this->_reservePlace($this->_needsCdata($css)
			? "{$openStyle}/*<![CDATA[*/{$css}/*]]>*/</style>"
			: "{$openStyle}{$css}</style>"
		);
	}

	protected function _removeScriptCB($m)
	{
		$openScript = "<script{$m[2]}";
		$js = $m[3];

		$script_tag = "<script{$m[2]}>{$js}</script>";

		$type = '';
		if (preg_match('#type="([^"]+)"#i', $m[2], $matches)) {
			$type = strtolower($matches[1]);
		}

		// whitespace surrounding? preserve at least one space
		$ws1 = ($m[1] === '') ? '' : ' ';
		$ws2 = ($m[4] === '') ? '' : ' ';

		// remove HTML comments (and ending "//" if present)
		if ($this->_jsCleanComments) {
			$js = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/', '', $js);
		}

		// minify
		$minifier = $this->_jsMinifier
			? $this->_jsMinifier
			: 'trim';

		if (in_array($type, array('text/template', 'text/x-handlebars-template'))) {
			$minifier = '';
		}

		$minifier = apply_filters('w3tc_minify_html_script_minifier', $minifier, $type, $script_tag);

		if (empty($minifier)) {
			$needsCdata = false;
		} else {
			// remove CDATA section markers
			$js_old = $js;
			$js = $this->_removeCdata($js);
			$needsCdata = ( $js_old != $js );

			$js = call_user_func($minifier, $js);
		}

		return $this->_reservePlace($needsCdata && $this->_needsCdata($js)
			? "{$ws1}{$openScript}/*<![CDATA[*/{$js}/*]]>*/</script>{$ws2}"
			: "{$ws1}{$openScript}{$js}</script>{$ws2}"
		);
	}

	protected function _removeCdata($str)
	{
		if (false !== strpos($str, '<![CDATA[')) {
			$str = str_replace('//<![CDATA[', '', $str);

			$str = preg_replace('~/\*\s*<!\[CDATA\[\s*\*/~', '', $str);
			$str = str_replace('<![CDATA[', '', $str);

			$str = str_replace('//]]>', '', $str);
			$str = preg_replace('~/\*\s*\]\]>\s*\*/~', '', $str);
			$str = str_replace(']]>', '', $str);
		}

		return $str;
	}

	protected function _removeComment($m)
	{
		return $this->_reservePlace($m[1]);
	}

	protected function _needsCdata($str)
	{
		return ($this->_isXhtml && preg_match('/(?:[<&]|\\-\\-|\\]\\]>)/', $str));
	}

	protected function _removeAttributeQuotes($m) {
		// whatsapp/fb bots dont read meta tags without quotes well
		if (strtolower($m[2]) != 'meta') {
			$m[3] = preg_replace_callback( '~([a-z0-9\\-])=(?<quote>[\'"])([^"\'\\s=]*)\k<quote>(\\s|>|/>)~i',
				array( $this, '_removeAttributeQuotesCallback'), $m[3] );
		}

		return $m[1] . $m[3];
	}



	public function _removeAttributeQuotesCallback( $m ) {
		// empty tag values like <div data-value=""> to <div data-value
		if ( $m[3] === '' ) {
			return $m[1] . $m[4];
		}

		// 1. <a href=bla/>hi</a> is sometimes (XHTML? HTML5 specs doesnt allow that)
		// parsed as <a href=bla></a>hi</a> by browsers
		// avoid that by turning it to <a href=bla/ >hi</a>

		// 2. auto-closing tags without space at the end e.g. <div data-value="aa"/>
		// should have space after value <div data-value=aa />
		// otherwise some browsers assume data-value="aa/"
		if ( /* 1 */ $m[4] == '/>' ||
			/* 2 */ ( $m[4] == '>' && substr( $m[3], -1, 1 ) == '/' ) ) {
			return $m[1] . '=' . $m[3] . ' ' . $m[4];
		}

		return $m[1] . '=' . $m[3] . $m[4];
	}
}
