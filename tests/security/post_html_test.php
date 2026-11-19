<?php declare(strict_types=1);

/**
 * Requires ext-dom and ext-mbstring.
 * Run: php tests/security/post_html_test.php
 */
define('BASE_URL', 'http://bobblr.test/');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require dirname(__DIR__, 2).'/Utilities/Strings.php';

$strings = new \Utilities\Strings();
$errors = [];

$badLink = $strings->prepare_post_text('<p><a href="JaVaScRiPt:alert(1)">hello</a></p>');
if (stripos($badLink, 'javascript:') !== false) {
    $errors[] = 'javascript: href was retained';
}

$validLink = $strings->prepare_post_text('<p><a href="https://example.org/post">safe</a></p>');
if (!str_contains($validLink, 'href="https://example.org/post"')) {
    $errors[] = 'safe HTTPS link was removed';
}

$img = $strings->prepare_post_text('<img src="/uploads/test.png" onerror="alert(1)" alt="test">');
if (!str_contains($img, 'http://bobblr.test/uploads/test.png')
    || stripos($img, 'onerror') !== false) {
    $errors[] = 'local image was removed or event handler retained';
}

$remoteImg = $strings->prepare_post_text('<img src="https://evil.example/track.png" onload="alert(1)">');
if (str_contains($remoteImg, 'evil.example') || stripos($remoteImg, 'onload') !== false) {
    $errors[] = 'external image source/event handler retained';
}

$localVideo = $strings->prepare_post_text('<video src="/uploads/movie.mp4" autoplay></video>');
if (!str_contains($localVideo, 'http://bobblr.test/uploads/movie.mp4')
    || stripos($localVideo, 'autoplay') !== false) {
    $errors[] = 'local video was removed or disallowed attribute retained';
}

$oldPost = $strings->check_srcs_of_media('', '<p>old</p><img src="https://evil.example/spy.jpg" onerror="alert(1)">');
if (str_contains($oldPost, 'evil.example') || stripos($oldPost, 'onerror') !== false) {
    $errors[] = 'unsafe old post HTML was not cleaned on output';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "FAIL: $error\n");
    }
    exit(1);
}
echo "6 post HTML checks passed.\n";
