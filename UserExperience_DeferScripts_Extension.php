<?php
namespace W3TC;

class UserExperience_DeferScripts_Extension {
	private $config;
	private $mutator;



	public function __construct() {
		$this->config = Dispatcher::config();
	}



	public function run() {
		Util_Bus::add_ob_callback( 'lazyload', [ $this, 'ob_callback' ] );
		add_filter( 'w3tc_minify_js_script_tags',
			[ $this, 'w3tc_minify_js_script_tags' ] );

		add_action( 'w3tc_userexperience_page',
			[ $this, 'w3tc_userexperience_page' ] );
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
		$can_process = apply_filters( 'w3tc_deferscripts_can_process', $can_process );

		// set reject reason in comment
		if ( $can_process['enabled'] ) {
			$reject_reason = '';
		} else {
			$reject_reason = empty( $can_process['reason'] ) ?
				' (not specified)' : ' (' . $can_process['reason'] . ')';
		}

		$buffer = str_replace( '{w3tc_deferscripts_reject_reason}',
			$reject_reason, $buffer );

		// processing
		if ( !$can_process['enabled'] ) {
			return $buffer;
		}

		$this->mutator = new UserExperience_DeferScripts_Mutator( $this->config );
		$buffer = $this->mutator->run( $buffer );

		// embed lazyload script
		if ( $this->mutator->content_modified() ) {
			$buffer = apply_filters( 'w3tc_deferscripts_embed_script', $buffer );

			$is_embed_script = apply_filters( 'w3tc_deferscripts_is_embed_script', true );
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

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$can_process['enabled'] = false;
			$can_process['reason'] = 'feed';

			return $can_process;
		}

		return $can_process;
	}



	public function w3tc_footer_comment( $strings ) {
		$strings[] = __( 'Defer Scripts', 'w3-total-cache' ) . '{w3tc_deferscripts_reject_reason}';
		return $strings;
	}



	private function embed_script( $buffer ) {
		$config_timeout = $this->config->get_integer( [
			'user-experience-defer-scripts',
			'timeout'
		] );
		$content = file_get_contents( W3TC_DIR . '/UserExperience_DeferScripts_Script.js' );
		$content = str_replace( '{config_timeout}', $config_timeout, $content );

		$footer_script = '<script>' . $content . '</script>';
		$buffer = preg_replace( '~</body(\s+[^>]*)*>~Ui',
			$footer_script . '\\0', $buffer, 1 );

		return $buffer;
	}



	public function w3tc_minify_js_script_tags( $script_tags ) {
		if ( !is_null( $this->mutator ) ) {
			$script_tags = $this->mutator->w3tc_minify_js_script_tags( $script_tags );
		}

		return $script_tags;
	}



	public function w3tc_userexperience_page() {
		include( __DIR__ . '/UserExperience_DeferScripts_Page_View.php' );
	}
}



$o = new UserExperience_DeferScripts_Extension();
$o->run();
