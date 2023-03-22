<?php
namespace W3TC;

class UserExperience_DeferScripts_Mutator {
	private $config;
	private $modified = false;
	private $includes = [];




	public function __construct( $config ) {
		$this->config = $config;
	}



	public function run( $buffer ) {
		$r = apply_filters( 'w3tc_deferscripts_mutator_before', array(
			'buffer' => $buffer,
			'modified' => $this->modified
		) );
		$buffer = $r['buffer'];
		$this->modified = $r['modified'];

		$this->includes = $this->config->get_array(
			[ 'user-experience-defer-scripts', 'includes' ] );

		$buffer = preg_replace_callback(
			'~<script\s[^>]+>~is',
			[ $this, 'tag_script' ],
			$buffer
		);

		return $buffer;
	}



	public function content_modified() {
		return $this->modified;
	}



	public function tag_script( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_included( $content ) ) {
			$count = 0;
			$content = preg_replace( '~(\s)src=~is',
				'$1data-lazy="w3tc" data-src=', $content, -1, $count );

			if ($count > 0) {
				$this->modified = true;
			}
		}

		return $content;
	}



	private function is_content_included( $content ) {
		foreach ( $this->includes as $w ) {
			if ( !empty($w) ) {
				if ( strpos( $content, $w ) !== FALSE ) {
					return true;
				}
			}
		}

		return false;
	}



	/* Ask minify to skip tags processed by this module */
	public function w3tc_minify_js_script_tags( $script_tags ) {
		return array_filter( $script_tags, function( $i ) {
			return !preg_match( '~\sdata-lazy="w3tc"\s~', $i );
		} );
	}
}
