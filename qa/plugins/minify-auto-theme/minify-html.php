<?php
/**
 * Template Name: Minify Auto - HTML test
 * Description: A Page Template for testing minify HTML
 *
 * @package W3TC
 * @subpackage W3TC QA
 */

get_header(); ?>
<div id="main-content" class="main-content">

	<div id="multiple-whitespace1" data-attr="   space-in-attr   ">    multiple whitespace around element

	</div>
	<div id="multiple-whitespace2">    multiple whitespace around element     </div>
	<unkTag id="multiple-whitespace3">    multiple whitespace around element     </unkTag>

	<div id="void-elements1"><br /><hr /></div>

	<div id="void-elements2">
		<br/>
		<hr/>
	</div>

	<div id="void-elements3">
		<br
		/>
	</div>

	<div id="void-elements4">
		<br
		  >
	</div>

	<div id="void-elements5">
		<input type="text" value="5" />
	</div>

	<div id="auto-closed6">
		<div data-value="6"/>
	</div>

	<div id="auto-closed7">
		<div data-value="7/" />
	</div>

	<div id="quoted-attribute1" class="some-class" data-test="do not remove quotes on this one">
		content
	</div>

	<div id="quoted-attribute2" data-test-quote1="" data-test-quote2='' data-test-quote3='0' data-test-quote4=' '>
		with empty content
	</div>

	<div id="quoted-attribute3" data-test-quote1="'" data-test-quote2='"'>
		with just quote
	</div>

	<div id="quoted-attribute-mixed1"
		data-test-quote1="' at start"
		data-test-quote2="at ' middle"
		data-test-quote3="at end'">
		with mixed quote1
	</div>

	<div id="quoted-attribute-mixed2"
		data-test-quote1='" at start'
		data-test-quote2='at " middle'
		data-test-quote3='at end"'>
		with mixed quote2
	</div>

	<script>
	var m = '   no spaces removal   <a title="here\'">  and here</a><br \/>';
	</script>

	<script type="application/ld+json">
	{"br_tag":"test<br /> tag", "spaces around":"   spaces  "}
	</script>

	<div id="link-ending-slash"><a href="https://www.website.com/path/">bla</a></div>
	<div id="link-ending-slash-extra-tags"><a href="https://www.website.com/path/" class="my">bla2</a></div>
</div>

<?php get_footer(); ?>
