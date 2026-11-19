<?php declare(strict_types=1);

/**
 * No HTTP requests, database or upload fixtures needed.
 * Run: php tests/security/upload_policy_test.php
 */
require dirname(__DIR__, 2).'/Utilities/Files.php';

$files = new \Utilities\Files();
$urls = [
    'http://127.0.0.1/latest/meta-data/',
    'http://169.254.169.254/latest/meta-data/',
    'http://[::1]/',
    'file:///etc/passwd',
    'https://example.org/example.jpg',
    'http://example.org/redirect',
];
$failed = 0;

foreach ($urls as $url) {
    $result = $files->upload_image_by_url('test', $url, false);
    if (!isset($result['error']) || $result['error'] === '' || $result['url'] !== null) {
        fwrite(STDERR, "FAIL: remote URL import was not refused: $url\n");
        ++$failed;
    }
}

foreach ([
    ['image', fn(array $f): array => $files->upload_image('test', $f, false)],
    ['audio', fn(array $f): array => $files->upload_audio('test', $f, false)],
    ['video', fn(array $f): array => $files->upload_video('test', $f, false)],
] as [$type, $upload]) {
    $invalidUploads = [
        [],
        ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0],
        ['error' => UPLOAD_ERR_OK, 'tmp_name' => '/not/a/real/upload', 'size' => 128, 'name' => 'fake.'.$type],
        ['error' => UPLOAD_ERR_OK, 'tmp_name' => '/not/a/real/upload', 'size' => 999999999, 'name' => 'huge.'.$type],
    ];
    foreach ($invalidUploads as $file) {
        $result = $upload($file);
        if (!isset($result['error']) || $result['error'] === '' || $result['url'] !== null) {
            fwrite(STDERR, "FAIL: invalid $type upload was accepted\n");
            ++$failed;
        }
    }
}

if ($failed > 0) {
    exit(1);
}
echo (count($urls) + 12)." upload policy checks passed.\n";
