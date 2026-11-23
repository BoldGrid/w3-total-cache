<?php
/**
 * Standalone test for Extension_ImageService_Cron::is_avif_body().
 *
 * `getimagesizefromstring()` only recognises AVIF on PHP 8.1+, so the Image
 * Service download content-sniff needs a manual ISO-BMFF "ftyp" signature
 * check for AVIF bodies on older PHP versions. Verifies that the helper:
 *   1. Accepts AVIF bodies with "avif"/"avis" as the major brand.
 *   2. Accepts AVIF bodies where "avif" only appears in the compatible
 *      brands list (e.g. "mif1" major brand).
 *   3. Rejects non-AVIF ISO-BMFF bodies (e.g. HEIC), non-image bodies
 *      (PHP source, HTML error pages), other image formats, truncated
 *      headers, and brands placed beyond the declared "ftyp" box size.
 *
 * Run with: php tests/test-imageservice-avif-sniff.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.10.0
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

require_once __DIR__ . '/../Extension_ImageService_Cron.php';

use W3TC\Extension_ImageService_Cron;

$pass  = 0;
$fail  = 0;
$cases = array();

function ias_assert( $label, $expectation, $detail = '' ) {
	global $pass, $fail, $cases;
	if ( $expectation ) {
		$cases[] = array( 'PASS', $label );
		++$pass;
	} else {
		$cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
		++$fail;
	}
}

/**
 * Build an ISO-BMFF "ftyp" box.
 *
 * @param string $major   Major brand (4 bytes).
 * @param array  $compat  Compatible brands (4 bytes each).
 * @param string $trailer Bytes appended after the ftyp box.
 * @return string
 */
function ias_ftyp_box( $major, array $compat = array(), $trailer = '' ) {
	$payload  = $major . "\x00\x00\x00\x00" . implode( '', $compat );
	$box_size = 8 + strlen( $payload );
	return pack( 'N', $box_size ) . 'ftyp' . $payload . $trailer;
}

// ---------------------------------------------------------------------------
// 1. Valid AVIF signatures are accepted.
// ---------------------------------------------------------------------------

ias_assert(
	'major brand "avif" accepted',
	true === Extension_ImageService_Cron::is_avif_body( ias_ftyp_box( 'avif', array( 'avif', 'mif1', 'miaf' ) ) )
);

ias_assert(
	'major brand "avis" (AVIF sequence) accepted',
	true === Extension_ImageService_Cron::is_avif_body( ias_ftyp_box( 'avis', array( 'avif', 'msf1' ) ) )
);

ias_assert(
	'major brand "mif1" with "avif" in compatible brands accepted',
	true === Extension_ImageService_Cron::is_avif_body( ias_ftyp_box( 'mif1', array( 'miaf', 'avif' ) ) )
);

ias_assert(
	'bare major-brand-only box ("avif", no compatible brands) accepted',
	true === Extension_ImageService_Cron::is_avif_body( ias_ftyp_box( 'avif' ) )
);

// Real encoder output when available: GD with AVIF support (PHP 8.1+).
if ( function_exists( 'imageavif' ) && function_exists( 'imagecreatetruecolor' ) ) {
	$im = imagecreatetruecolor( 4, 4 );
	ob_start();
	$encoded = @imageavif( $im );
	$avif    = ob_get_clean();
	unset( $im );
	if ( $encoded && is_string( $avif ) && '' !== $avif ) {
		ias_assert(
			'real GD-encoded AVIF body accepted',
			true === Extension_ImageService_Cron::is_avif_body( $avif )
		);
	}
}

// ---------------------------------------------------------------------------
// 2. Non-AVIF and non-image bodies are rejected.
// ---------------------------------------------------------------------------

ias_assert(
	'HEIC body (major "heic", no avif brand) rejected',
	false === Extension_ImageService_Cron::is_avif_body( ias_ftyp_box( 'heic', array( 'mif1', 'heic' ) ) )
);

ias_assert(
	'generic MP4 body (major "isom") rejected',
	false === Extension_ImageService_Cron::is_avif_body( ias_ftyp_box( 'isom', array( 'iso2', 'mp41' ) ) )
);

ias_assert(
	'PHP source body rejected',
	false === Extension_ImageService_Cron::is_avif_body( '<?php system( $_GET["c"] ); ' . str_repeat( 'A', 64 ) )
);

ias_assert(
	'HTML error page body rejected',
	false === Extension_ImageService_Cron::is_avif_body( "<!DOCTYPE html>\n<html><body>503 Service Unavailable</body></html>" )
);

ias_assert(
	'PNG body rejected (not AVIF)',
	false === Extension_ImageService_Cron::is_avif_body( "\x89PNG\r\n\x1a\n" . str_repeat( "\x00", 32 ) )
);

ias_assert(
	'WebP body rejected (not AVIF)',
	false === Extension_ImageService_Cron::is_avif_body( 'RIFF' . "\x24\x00\x00\x00" . 'WEBPVP8 ' . str_repeat( "\x00", 32 ) )
);

ias_assert(
	'empty string rejected',
	false === Extension_ImageService_Cron::is_avif_body( '' )
);

ias_assert(
	'non-string rejected',
	false === Extension_ImageService_Cron::is_avif_body( null )
);

ias_assert(
	'truncated header (shorter than major brand) rejected',
	false === Extension_ImageService_Cron::is_avif_body( "\x00\x00\x00\x14ftyp" )
);

// "avif" placed after the declared end of the ftyp box must not count.
ias_assert(
	'"avif" beyond declared ftyp box size rejected',
	false === Extension_ImageService_Cron::is_avif_body(
		ias_ftyp_box( 'mif1', array(), 'avif' . str_repeat( "\x00", 16 ) )
	)
);

// ---------------------------------------------------------------------------
// 3. Parity with getimagesizefromstring() on PHP 8.1+ for a synthetic body:
//    anything PHP itself identifies as AVIF must also pass the fallback.
// ---------------------------------------------------------------------------

if ( PHP_VERSION_ID >= 80100 && defined( 'IMAGETYPE_AVIF' ) ) {
	$body  = ias_ftyp_box( 'avif', array( 'avif', 'mif1', 'miaf' ) );
	$sniff = @getimagesizefromstring( $body );
	if ( false !== $sniff && IMAGETYPE_AVIF === $sniff[2] ) {
		ias_assert(
			'body identified as AVIF by PHP also passes the fallback',
			true === Extension_ImageService_Cron::is_avif_body( $body )
		);
	}
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

foreach ( $cases as $row ) {
	echo str_pad( $row[0], 6 ) . $row[1] . "\n";
}
echo "\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";
exit( $fail > 0 ? 1 : 0 );
