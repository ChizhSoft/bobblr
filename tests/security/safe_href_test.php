<?php declare(strict_types=1);

/**
 * Run from the repository root:
 *     php tests/security/safe_href_test.php
 *
 * No database or application bootstrap is needed.
 */
require dirname(__DIR__, 2) . '/Utilities/Strings.php';

$strings = new \Utilities\Strings();
$cases = [
    ['https://example.org/post/1', true],
    ['HTTP://example.org/post/1', true],
    ['http://example.org/', true],
    ['mailto:hello@example.org', true],
    ['/profile/beaver', true],
    ['#comments', true],
    ['?sort=new', true],
    ['javascript:alert(1)', false],
    ['JaVaScRiPt:alert(1)', false],
    ['data:text/html,test', false],
    ['vbscript:alert(1)', false],
    ['file:///etc/passwd', false],
    ['//outside.example.org', false],
    ['/\\outside.example.org', false],
    ['https://', false],
    ['not-a-valid-absolute-url', false],
    ["https://example.org/\nmalformed", false],
    ['', false],
];
$failures = 0;

foreach ($cases as [$href, $expected]) {
    $actual = $strings->is_safe_href($href);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: " . var_export($href, true) .
            " (expected " . ($expected ? 'allowed' : 'blocked') . ")\n");
        ++$failures;
    }
}

if ($failures !== 0) {
    fwrite(STDERR, "$failures security URL test(s) failed.\n");
    exit(1);
}

echo count($cases) . " security URL tests passed.\n";
