<?php
/**
 * File: class-w3tc-dbcluster-validator-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Generic_AdminActions_Config;

/**
 * Class: W3tc_Dbcluster_Validator_Test
 *
 * Coverage for the tokenizer-based content validator that gates
 * `w3tc_config_dbcluster_config_save`. The validated file is later
 * `require`'d, so regressions here re-open the arbitrary-PHP-write →
 * RCE primitive that  /  closed.
 *
 * @since 2.10.0
 */
class W3tc_Dbcluster_Validator_Test extends WP_UnitTestCase {

	/**
	 * The shipped sample config (`ini/dbcluster-config-sample.php`) is
	 * what a fresh operator will paste back in — it MUST validate.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_accepts_shipped_sample() {
		$sample = file_get_contents( dirname( __DIR__, 2 ) . '/ini/dbcluster-config-sample.php' );
		$this->assertNotEmpty( $sample );
		$this->assertNull( Generic_AdminActions_Config::validate_dbcluster_content( $sample ) );
	}

	/**
	 * A minimal accept: just `<?php $w3tc_dbcluster_config = array(...);`.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_accepts_minimal_assignment() {
		$src = '<?php $w3tc_dbcluster_config = array( "persistent" => false );';
		$this->assertNull( Generic_AdminActions_Config::validate_dbcluster_content( $src ) );
	}

	/**
	 * `global $w3tc_dbcluster_config;` declaration is allowed (the
	 * sample uses it).
	 *
	 * @since 2.10.0
	 */
	public function test_validate_accepts_global_keyword() {
		$src = "<?php\nglobal \$w3tc_dbcluster_config;\n\$w3tc_dbcluster_config = array();";
		$this->assertNull( Generic_AdminActions_Config::validate_dbcluster_content( $src ) );
	}

	/**
	 * Every shell-execution / code-execution primitive is refused.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_rejects_code_execution_primitives() {
		$payloads = array(
			'function call'  => '<?php $w3tc_dbcluster_config = array(); system("id");',
			'include attack' => '<?php include $_GET["x"]; $w3tc_dbcluster_config = [];',
			'require attack' => '<?php require $_GET["x"]; $w3tc_dbcluster_config = [];',
			'eval attack'    => '<?php eval($_GET["x"]); $w3tc_dbcluster_config = [];',
			'backtick exec'  => '<?php $w3tc_dbcluster_config = []; `id`;',
			'exit'           => '<?php exit; $w3tc_dbcluster_config = [];',
			'new gadget'     => '<?php $w3tc_dbcluster_config = []; new EvilClass;',
			'static method'  => '<?php WP_Foo::evil(); $w3tc_dbcluster_config = [];',
			'object op'      => '<?php $x->foo(); $w3tc_dbcluster_config = [];',
			'function decl'  => '<?php function evil(){}; $w3tc_dbcluster_config = [];',
			'class decl'     => '<?php class Evil {}; $w3tc_dbcluster_config = [];',
			'namespace decl' => '<?php namespace Evil; $w3tc_dbcluster_config = [];',
			'echo'           => '<?php echo "x"; $w3tc_dbcluster_config = [];',
			'print'          => '<?php print "x"; $w3tc_dbcluster_config = [];',
		);

		foreach ( $payloads as $label => $src ) {
			$err = Generic_AdminActions_Config::validate_dbcluster_content( $src );
			$this->assertIsString( $err, "Expected rejection for [$label] but validator accepted it." );
		}
	}

	/**
	 * HTML output side-effects (close-tag + trailing literal, short-echo
	 * tag) must be refused. The validated file is `require`'d, so any
	 * literal HTML in it would be printed to the response.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_rejects_html_output_side_effects() {
		$payloads = array(
			// Close PHP, then bare text — `require` would print "evil".
			'close-tag + inline html'  => '<?php $w3tc_dbcluster_config = array(); ?>evil',
			// Short-echo tag — directly prints when required.
			'open-tag-with-echo'       => '<?php $w3tc_dbcluster_config = array(); ?> hello <?= "evil" ?>',
			/**
			 * Reopens after a close-tag — even with no trailing text, the
			 * close→open round-trip emits whitespace.
			 */
			'close + reopen tag'       => '<?php $w3tc_dbcluster_config = array(); ?> <?php ',
		);

		foreach ( $payloads as $label => $src ) {
			$err = Generic_AdminActions_Config::validate_dbcluster_content( $src );
			$this->assertIsString( $err, "Expected rejection for [$label] but validator accepted it." );
		}
	}

	/**
	 * A bare reference to `$w3tc_dbcluster_config` (no `=` after it) is
	 * not an initialisation and must be refused. The previous gate was
	 * "the variable name appears anywhere"; this verifies the gate now
	 * looks for an actual assignment.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_requires_actual_assignment_to_target_variable() {
		$err = Generic_AdminActions_Config::validate_dbcluster_content( '<?php $w3tc_dbcluster_config;' );
		$this->assertIsString( $err );
		$this->assertStringContainsString( 'must assign', $err );

		/**
		 * `global $w3tc_dbcluster_config;` alone (without a follow-up
		 * assignment elsewhere in the file) is also a bare reference.
		 */
		$err = Generic_AdminActions_Config::validate_dbcluster_content( '<?php global $w3tc_dbcluster_config;' );
		$this->assertIsString( $err );

		// `$w3tc_dbcluster_config = ...` (the legitimate form) is accepted.
		$this->assertNull(
			Generic_AdminActions_Config::validate_dbcluster_content( '<?php $w3tc_dbcluster_config = array();' )
		);

		/**
		 * Subscript-style initialisation (`$w3tc_dbcluster_config['key'] = ...`)
		 * is also accepted — that's a legitimate way to populate the array.
		 */
		$this->assertNull(
			Generic_AdminActions_Config::validate_dbcluster_content(
				'<?php $w3tc_dbcluster_config = array(); $w3tc_dbcluster_config["persistent"] = false;'
			)
		);
	}

	/**
	 * Every value-as-callable invocation shape is refused.
	 *
	 * The original token-walk denylist only gated `T_STRING + (`, so
	 * call shapes whose callable was a string literal, a variable, an
	 * expression, a subscript, or an arrow-function IIFE slipped past.
	 * The tightened validator rejects any `(` whose preceding
	 * significant token is `)`, `]`, `}`, T_VARIABLE, or
	 * T_CONSTANT_ENCAPSED_STRING (plus T_FN / T_CURLY_OPEN /
	 * T_DOLLAR_OPEN_CURLY_BRACES / T_LIST in the forbidden-token
	 * list). Each payload below was demonstrated to slip past the
	 * earlier validator during round-2 review — they must reject
	 * after this fix.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_rejects_value_as_callable_invocations() {
		$payloads = array(
			'string-literal callable'        => '<?php $w3tc_dbcluster_config = ("printf")("PWN\n");',
			'inline-assign string callable'  => '<?php $w3tc_dbcluster_config = ($f = "printf")("PWN\n");',
			'subscript callable'             => '<?php $w3tc_dbcluster_config = (["printf","foo"])[0]("PWN\n");',
			'globals-subscript callable'     => '<?php $w3tc_dbcluster_config = $GLOBALS["x"]("y");',
			'arrow-function IIFE'            => '<?php $w3tc_dbcluster_config = (fn () => phpinfo())();',
			'pre-assigned variable callable' => '<?php $sneaky = "printf"; $w3tc_dbcluster_config = $sneaky("PWN\n");',
			'complex interpolation callable' => '<?php $sneaky = "printf"; $w3tc_dbcluster_config = "{$sneaky(\"PWN\\n\")}";',
		);

		foreach ( $payloads as $label => $src ) {
			$err = Generic_AdminActions_Config::validate_dbcluster_content( $src );
			$this->assertIsString(
				$err,
				"Expected rejection for [$label] but validator accepted it: $src"
			);
		}
	}

	/**
	 * Empty / no-php-tag / non-assigning files are refused.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_rejects_malformed_files() {
		$this->assertIsString( Generic_AdminActions_Config::validate_dbcluster_content( '' ) );
		$this->assertIsString( Generic_AdminActions_Config::validate_dbcluster_content( '   ' ) );
		$this->assertIsString( Generic_AdminActions_Config::validate_dbcluster_content( '$w3tc_dbcluster_config = array();' ) );
		// PHP tag present but no assignment to the target variable.
		$this->assertIsString( Generic_AdminActions_Config::validate_dbcluster_content( '<?php $other = array();' ) );
	}
}
