<?php declare(strict_types=1);

/**
 * CLI regression tests for post media URL restrictions.
 * Run: php tests/security/local_upload_path_test.php
 */
define('BASE_URL', 'http://bobblr.test/');
require dirname(__DIR__, 2).'/Utilities/Strings.php';

$strings = new \Utilities\Strings();
$cases = [
    ['/uploads/zhabbler_a.jpeg', '/uploads/zhabbler_a.jpeg'],
    ['/uploads/image.png', '/uploads/image.png'],
    ['/uploads/animated_1.gif', '/uploads/animated_1.gif'],
    ['/uploads/voice.mp3', '/uploads/voice.mp3'],
    ['/uploads/clip.mp4', '/uploads/clip.mp4'],
    ['http://bobblr.test/uploads/image.jpg', '/uploads/image.jpg'],
    ['http://bobblr.test/uploads/image.gif', '/uploads/image.gif'],
    ['/uploads/../config.neon', null],
    ['/uploads/../../bootstrap.php', null],
    ['/uploads/other/secret.jpg', null],
    ['/uploads/.hidden.jpg', null],
    ['/uploads/test.php', null],
    ['/uploads/test.svg', null],
    ['/uploads/test.jpg?preview=1', null],
    ['/uploads/test.jpg#fragment', null],
    ['/uploads/test.jpg/../../bootstrap.php', null],
    ['//evil.example/uploads/image.jpg', null],
    ['http://bobblr.test.evil.example/uploads/image.jpg', null],
    ['https://evil.example/uploads/image.jpg', null],
    ['file:///uploads/image.jpg', null],
    ['http://bobblr.test/other/file.mp4', null],
    ['/static/images/logo.png', null],
    ['', null],
];

$errors = 0;
foreach ($cases as [$input, $expected]) {
    $actual = $strings->get_local_upload_path($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: ".var_export($input, true).
            " expected ".var_export($expected, true).
            " got ".var_export($actual, true)."\n");
        ++$errors;
    }
}
if ($errors > 0) {
    exit(1);
}
echo count($cases)." local upload path checks passed.\n";
