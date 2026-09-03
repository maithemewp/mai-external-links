<?php
/**
 * Plain-PHP harness for Links::isExternal() and Links::mark().
 *
 * No WordPress runtime. The three core functions the class touches are stubbed
 * below to the behaviour it relies on, and WP_HTML_Tag_Processor is loaded from
 * a real WordPress if one is reachable, so mark() is exercised against the
 * genuine parser rather than a stand-in.
 *
 * Run: php tests/harness/links.php [path-to-wordpress]
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/../../' );

$fail = 0;

function check( bool $cond, string $label, string $detail = '' ): void {
	global $fail;
	echo ( $cond ? "PASS " : "FAIL " ) . $label . ( $detail ? "   \u{2192} $detail" : '' ) . "\n";
	if ( ! $cond ) {
		$fail++;
	}
}

// ---- WordPress stubs -------------------------------------------------------

$GLOBALS['mel_home'] = 'https://balloon-juice.com';

function home_url( string $path = '' ): string {
	return $GLOBALS['mel_home'] . $path;
}

function wp_parse_url( string $url, int $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}

function add_filter( ...$args ): bool {
	return true;
}

function is_main_query(): bool {
	return true;
}

// WP_HTML_Tag_Processor is core's, and mark() depends on its exact behaviour, so
// use the real one when a WordPress is reachable rather than faking it.
$wpRoot = $argv[1] ?? '/Users/jivedig/Herd/balloon-juice';
$tagProcessor = $wpRoot . '/wp-includes/html-api/class-wp-html-tag-processor.php';

if ( ! is_readable( $tagProcessor ) ) {
	fwrite( STDERR, "Cannot read $tagProcessor\nPass a WordPress root as argv[1].\n" );
	exit( 2 );
}

// utf8.php needs this one helper from compat.php, which is otherwise far too
// heavy to pull in. Same answer as core's for any build with PCRE UTF-8 support,
// which every supported PHP has.
function _wp_can_use_pcre_u( $set = null ) {
	return true;
}

// Copied verbatim from core's kses.php. Requiring that file would drag in the
// whole sanitiser; the list itself is static, and the tag processor only reads
// it to decide which attributes hold a URL.
function wp_kses_uri_attributes() {
	return [
		'action', 'archive', 'background', 'cite', 'classid', 'codebase', 'data',
		'formaction', 'href', 'icon', 'longdesc', 'manifest', 'poster', 'profile',
		'src', 'usemap', 'xmlns',
	];
}

function apply_filters( $hook, $value, ...$args ) {
	return $value;
}

// Real core, not a stub: the tag processor calls into both, and mark()'s output
// is only meaningful if it came from the parser the plugin actually runs on.
require_once $wpRoot . '/wp-includes/utf8.php';
require_once $wpRoot . '/wp-includes/html-api/class-wp-html-decoder.php';
require_once $wpRoot . '/wp-includes/html-api/class-wp-html-attribute-token.php';
require_once $wpRoot . '/wp-includes/html-api/class-wp-html-span.php';
require_once $wpRoot . '/wp-includes/html-api/class-wp-html-text-replacement.php';
require_once $tagProcessor;

require_once __DIR__ . '/../../src/Links.php';

use Mai\ExternalLinks\Links;

$links = new Links();

/** Reach the private methods; they are private for callers, not for tests. */
$isExternal = function ( string $href, string $host ) use ( $links ): bool {
	$m = new ReflectionMethod( Links::class, 'isExternal' );
	$m->setAccessible( true );
	return $m->invoke( $links, $href, $host );
};
$mark = function ( string $html ) use ( $links ): string {
	$m = new ReflectionMethod( Links::class, 'mark' );
	$m->setAccessible( true );
	return $m->invoke( $links, $html );
};
$siteHost = function () use ( $links ): ?string {
	$m = new ReflectionMethod( Links::class, 'siteHost' );
	$m->setAccessible( true );
	return $m->invoke( $links );
};

// ---- siteHost --------------------------------------------------------------

check( 'balloon-juice.com' === $siteHost(), 'siteHost: plain host' );

$GLOBALS['mel_home'] = 'https://www.balloon-juice.com';
check( 'balloon-juice.com' === $siteHost(), 'siteHost: leading www. is stripped' );

$GLOBALS['mel_home'] = 'https://DEV.Balloon-Juice.COM';
check( 'dev.balloon-juice.com' === $siteHost(), 'siteHost: lowercased' );

$GLOBALS['mel_home'] = 'https://balloon-juice.com';

// ---- isExternal ------------------------------------------------------------

$host = 'balloon-juice.com';

check( true === $isExternal( 'https://example.com/post', $host ), 'external: plain https' );
check( true === $isExternal( 'http://example.com/post', $host ), 'external: plain http' );
check( false === $isExternal( 'https://balloon-juice.com/2026/01/01/x/', $host ), 'internal: own host' );
check( false === $isExternal( 'https://www.balloon-juice.com/x/', $host ), 'internal: www subdomain of own host' );
check( false === $isExternal( 'https://dev.balloon-juice.com/x/', $host ), 'internal: any subdomain of own host' );
check( false === $isExternal( '/relative/path/', $host ), 'skipped: relative path has no scheme' );
check( false === $isExternal( '#comment-123', $host ), 'skipped: fragment' );
check( false === $isExternal( 'mailto:someone@example.com', $host ), 'skipped: mailto' );
check( false === $isExternal( 'tel:+15555555555', $host ), 'skipped: tel' );
check( false === $isExternal( 'javascript:alert(1)', $host ), 'skipped: javascript scheme' );
check( true === $isExternal( 'HTTPS://EXAMPLE.COM/x', $host ), 'external: uppercase scheme and host' );

// The bug the parsed-host comparison exists for. A substring test sees the
// site's own host inside the query string and calls this internal.
check( true === $isExternal( 'https://example.com/?from=balloon-juice.com', $host ),
	'external: own host in the QUERY STRING is still external' );
check( true === $isExternal( 'https://balloon-juice.com.evil.example/x', $host ),
	'external: own host as a PREFIX of another domain is still external' );
check( true === $isExternal( 'https://notballoon-juice.com/x', $host ),
	'external: own host as a SUFFIX of another domain is still external' );

// No DNS, by construction: a host that cannot resolve is still external.
check( true === $isExternal( 'http://firedoglake.com/2009/12/24/treasury/', $host ),
	'external: a dead domain is still marked (no DNS lookup)' );
check( true === $isExternal( 'http://192.168.1.1/router', $host ),
	'external: a private address is still marked (no DNS lookup)' );

// ---- mark ------------------------------------------------------------------

$out = $mark( '<p>See <a href="https://example.com/x">this</a>.</p>' );
check( str_contains( $out, 'target="_blank"' ), 'mark: sets target on an external link' );
check( str_contains( $out, 'rel="noopener noreferrer"' ), 'mark: sets rel on an external link' );

$internal = '<p>See <a href="https://balloon-juice.com/x">this</a>.</p>';
check( $mark( $internal ) === $internal, 'mark: internal-only content is byte-identical' );

$none = '<p>No links at all here.</p>';
check( $mark( $none ) === $none, 'mark: link-free content is byte-identical' );

$mixed = $mark( '<a href="/internal/">a</a><a href="https://example.com/">b</a><a href="#x">c</a>' );
check( 1 === substr_count( $mixed, 'target="_blank"' ), 'mark: only the external link is marked', $mixed );

// An existing target is replaced rather than duplicated.
$already = $mark( '<a href="https://example.com/" target="_self">x</a>' );
check( 1 === substr_count( $already, 'target=' ), 'mark: does not duplicate an existing target attribute', $already );
check( str_contains( $already, 'target="_blank"' ), 'mark: overrides an existing target' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
