<?php
namespace W3TC;



class LazyLoad_Plugin {
	private $config;
	private $modified = false;
	private $excludes;
	private $posts_by_url = array();



	public function __construct() {
		$this->config = Dispatcher::config();
	}



	public function run() {
		Util_Bus::add_ob_callback( 'lazyload', array( $this, 'ob_callback' ) );
		$this->metaslider_hooks();

		add_filter( 'wp_get_attachment_url',
			array( $this, 'wp_get_attachment_url' ), 10, 2 );
	}



	public function ob_callback( $buffer ) {
		if ( $buffer == '' || !\W3TC\Util_Content::is_html_xml( $buffer ) ) {
			return $buffer;
		}

		$can_process = array(
			'enabled' => true,
			'buffer' => $buffer,
			'reason' => null
		);
		$can_process = $this->can_process( $can_process );
		$can_process = apply_filters( 'w3tc_lazyload_can_process', $can_process );
		if ( !$can_process['enabled'] ) {
			return $buffer;
		}

		$this->excludes = apply_filters( 'w3tc_lazyload_excludes',
			$this->config->get_array( 'lazyload.exclude' ) );

		if ( $this->config->get_boolean( 'lazyload.process_img' ) ) {
			$buffer = preg_replace_callback(
				'~(<img[^>]+>)~',
				array( $this, 'tag_with_src' ), $buffer
			);
		}

		if ( $this->config->get_boolean( 'lazyload.process_background' ) ) {
			$buffer = preg_replace_callback(
				'~(<[^>]+background:\s*url[^>]+>)~',
				array( $this, 'tag_with_background' ), $buffer
			);
		}

		// embed lazyload script
		if ( $this->modified ) {
			$buffer = apply_filters( 'w3tc_lazyload_embed_script', $buffer );

			$is_embed_script = apply_filters( 'w3tc_lazyload_is_embed_script', true );
			if ( $is_embed_script ) {
				$buffer = $this->embed_script( $buffer );
			}
		}

		return $buffer;
	}



	private function can_process( $can_process ) {
		if ( defined( 'WP_ADMIN' ) ) {
			$can_process['enabled'] = false;
			$can_process['reason'] = 'WP_ADMIN';

			return $can_process;
		}

		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			$can_process['enabled'] = false;
			$can_process['reason'] = 'SHORTINIT';

			return $can_process;
		}

		return $can_process;
	}



	public function tag_with_src( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_excluded( $content ) ) {
			return $content;
		}

		// get image dimensions
		$dim = $this->tag_get_dimensions( $content );

		// do replace
		$count = 0;
		$content = preg_replace( '~(\s)src=~i',
			'$1src="' . $this->placeholder( $dim['w'], $dim['h'] ) .
			'" data-src=', $content, -1, $count );

		if ( $count > 0 ) {
			$content = preg_replace( '~(\s)(srcset|sizes)=~i',
				'$1data-$2=', $content );

			$content = $this->add_class_lazy( $content );
			$this->modified = true;
		}

		return $content;
	}



	public function tag_get_dimensions( $content ) {
		$dim = array( 'w' => 1, 'h' => 1 );
		$m = null;
		if ( preg_match( '~\swidth=[\s\'"]*([0-9]+)~i', $content, $m ) ) {
			$dim['h'] = $dim['w'] = (int)$m[1];

			if ( preg_match( '~\sheight=[\s\'"]*([0-9]+)~i', $content, $m ) ) {
				$dim['h'] = (int)$m[1];
				return $dim;
			}
		}

		// if not in attributes - try to find via url
		if ( !preg_match( '~\ssrc=(\'([^\']*)\'|"([^"]*)"|([^\'"][^\\s]*))~i',
				$content, $m ) ) {
			return $dim;
		}

		$url = ( !empty( $m[4] ) ? $m[4] : ( ( !empty( $m[3] ) ? $m[3] : $m2 ) ) );

		// full url found
		if ( isset( $this->posts_by_url[$url] ) ) {
			$post_id = $this->posts_by_url[$url];

			$image = wp_get_attachment_image_src( $post_id, 'full' );
			if ( $image ) {
				$dim['w'] = $image[1];
				$dim['h'] = $image[2];
			}

			return $dim;
		}

		// try resized url by format
		static $base_url = null;
		if ( is_null( $base_url ) ) {
			$base_url = wp_get_upload_dir()['baseurl'];
		}

		if ( substr( $url, 0, strlen( $base_url ) ) == $base_url &&
				 preg_match( '~(.+)-(\\d+)x(\\d+)(\\.[a-z0-9]+)$~i', $url, $m ) ) {
			$dim['w'] = (int)$m[2];
			$dim['h'] = (int)$m[3];
		}

		return $dim;
	}



	public function tag_with_background( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_excluded( $content ) ) {
			return $content;
		}

		$quote_match = null;
		if ( !preg_match( '~\s+style\s*=\s*([\"\'])~', $content, $quote_match ) ) {
			return $content;
		}
		$quote = $quote_match[1];

		$count = 0;
		$content = preg_replace_callback(
			'~(\s+)(style\s*=\s*[' . $quote . '])(.*?)([' . $quote . '])~',
			array( $this, 'style_offload_background' ), $content, -1, $count
		);

		if ( $count > 0 ) {
			$content = $this->add_class_lazy( $content );
			$this->modified = true;
		}

		return $content;
	}



	public function style_offload_background( $matches ) {
		list( $match, $v1, $v2, $v, $quote ) = $matches;
		$url_match = null;
		preg_match( '~background:\s*(url\([^>]+\))~', $v, $url_match );
		$v = preg_replace( '~background:\s*url\([^>]+\)[;]?\s*~', '', $v );

		return $v1 . $v2 . $v . $quote . ' data-bg=' . $quote . $url_match[1] . $quote;
	}



	private function add_class_lazy( $content ) {
		$count = 0;
		$content = preg_replace_callback(
			'~(\s+)(class=)([\"\'])(.*?)([\"\'])~',
			array( $this, 'class_process' ), $content, -1, $count
		);

		if ( $count <= 0) {
			$content = preg_replace(
				'~<(\S+)(\s+)~', '<$1$2class="lazy" ', $content
			);
		}

		return $content;
	}



	public function class_process( $matches ) {
		list( $match, $v1, $v2, $quote, $v ) = $matches;
		if ( preg_match( '~(^|\\s)lazy($|\\s)~', $v ) ) {
			return $match;
		}

		$v .= ' lazy';

		return $v1 . $v2 . $quote . $v . $quote;
	}


	private function is_content_excluded( $content ) {
		foreach ( $this->excludes as $w ) {
			if ( strpos( $content, $w ) !== FALSE ) {
				return true;
			}
		}

		return false;
	}



	private function placeholder( $w, $h ) {
		return 'data:image/svg+xml,%3Csvg%20xmlns=\'http://www.w3.org/2000/svg\'%20viewBox=\'0%200%20' . $w . '%20'. $h . '\'%3E%3C/svg%3E';
	}



	private function embed_script( $buffer ) {
		$js_url = plugins_url( 'pub/js/lazyload.min.js', W3TC_FILE );
		$method = $this->config->get_string( 'lazyload.embed_method' );

		$fireEvent = 'function(t){var e;try{e=new CustomEvent("w3tc_lazyload_loaded",{detail:{e:t}})}catch(a){(e=document.createEvent("CustomEvent")).initCustomEvent("w3tc_lazyload_loaded",!1,!1,{e:t})}window.dispatchEvent(e)}';
		$config = '{elements_selector:".lazy",callback_loaded:' . $fireEvent . '}';

		if ( $method == 'async_head' ) {
			$embed_script =
				'<script>window.w3tc_lazyload=1,window.lazyLoadOptions=' . $config . '</script>' .
				'<style>img.lazy{min-height:1px}</style>' .
				'<script async src="' . $js_url . '"></script>';

			$buffer = preg_replace( '~<head(\s+[^>]*)*>~Ui',
				'\\0' . $embed_script, $buffer, 1 );

			// add protection to footer if async script executed too early
			$footer_script =
				'<script>' .
				'document.addEventListener("DOMContentLoaded",function() {' .
				'if (typeof LazyLoad !== "undefined") {' .
				'window.w3tc_lazyload=new LazyLoad(window.lazyLoadOptions)' .
				'}})</script>';
			$buffer = preg_replace( '~</body(\s+[^>]*)*>~Ui',
				$footer_script . '\\0', $buffer, 1 );

		} elseif ( $method == 'inline_footer' ) {
			$footer_script =
				'<style>img.lazy{min-height:1px}</style>' .
				'<script>' .
				file_get_contents( W3TC_DIR . '/pub/js/lazyload.min.js' ) .
				'window.w3tc_lazyload=new LazyLoad(' . $config . ')</script>';
			$buffer = preg_replace( '~</body(\s+[^>]*)*>~Ui',
				$footer_script . '\\0', $buffer, 1 );
		} else {   // 'sync_head'
			$head_script =
				'<style>img.lazy{min-height:1px}</style>' .
				'<script src="' . $js_url . '"></script>';
			$buffer = preg_replace( '~<head(\s+[^>]*)*>~Ui',
				'\\0' . $head_script, $buffer, 1 );

			$footer_script =
				'<script>window.w3tc_lazyload=new LazyLoad(' . $config . ')</script>';
			$buffer = preg_replace( '~</body(\s+[^>]*)*>~Ui',
				$footer_script . '\\0', $buffer, 1 );
		}

		return $buffer;
	}



	public function wp_get_attachment_url( $url, $post_id ) {
		$this->posts_by_url[$url] = $post_id;
		return $url;
	}



	private function metaslider_hooks() {
		add_filter( 'metaslider_nivo_slider_get_html',
			array( $this, 'metaslider_nivo_slider_get_html' ) );
	}



	public function metaslider_nivo_slider_get_html( $content ) {
		// nivo slider use "src" attr of <img> tags to populate
		// own image via JS, i.e. cant be replaced by lazyloading
		$content = preg_replace(
			'~(\s+)(class=)([\"\'])(.*?)([\"\'])~',
			'$1$2$3$4 no-lazy$5', $content
		);

		return $content;
	}
}
